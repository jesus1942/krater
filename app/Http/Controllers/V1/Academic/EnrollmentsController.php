<?php

namespace Crater\Http\Controllers\V1\Academic;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\Academic\EnrollmentRequest;
use Crater\Models\AcademicYear;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\Student;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Matriculas: que alumno cursa en que division y en que ciclo.
 *
 * La matricula es el registro que despues se promociona. Por eso no se borra
 * nunca: se le cambia el estado. Un alumno que se fue a otra escuela en mayo
 * tiene que seguir apareciendo en el ciclo con su fecha de baja, porque su
 * historial academico de esos meses existio.
 */
class EnrollmentsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['division_id' => ['required', 'integer']]);

        $division = Division::findOrFail($request->division_id);
        $this->authorize('viewEnrollmentsOfDivision', $division);

        $enrollments = Enrollment::with('student:id,first_name,last_name,dni')
            ->where('division_id', $division->id)
            ->orderBy('status')
            ->get()
            ->sortBy(fn ($m) => optional($m->student)->last_name)
            ->values();

        return response()->json([
            'data' => $enrollments,
            'meta' => [
                'division' => $division->only(['id', 'name', 'capacity']),
                'activas' => $enrollments->where('status', 'active')->count(),
                'cupo_disponible' => $division->capacity
                    ? max(0, $division->capacity - $enrollments->where('status', 'active')->count())
                    : null,
            ],
        ]);
    }

    public function disponibles(Request $request)
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $year = AcademicYear::findOrFail($request->academic_year_id);
        $this->authorize('viewAnyEnrollment', Enrollment::class);

        $students = Student::where('company_id', TenantContext::companyId())
            ->whereNotIn('id', function ($sub) use ($year) {
                $sub->select('student_id')
                    ->from('enrollments')
                    ->where('company_id', TenantContext::companyId())
                    ->where('school_level_id', $year->school_level_id)
                    ->where('academic_year_id', $year->id);
            })
            ->when($request->buscar, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('first_name', 'like', "%{$buscar}%")
                        ->orWhere('last_name', 'like', "%{$buscar}%")
                        ->orWhere('dni', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('last_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'dni']);

        return response()->json(['data' => $students]);
    }

    public function store(EnrollmentRequest $request)
    {
        $division = Division::findOrFail($request->division_id);
        $this->authorize('manageEnrollment', $division);

        $enrollment = DB::transaction(function () use ($request, $division) {
            $lockedDivision = Division::whereKey($division->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDivision->hasCapacity()) {
                throw ValidationException::withMessages([
                    'division_id' => ['No quedan lugares en esta división.'],
                ]);
            }

            $existing = Enrollment::where('academic_year_id', $lockedDivision->academic_year_id)
                ->where('school_level_id', $lockedDivision->school_level_id)
                ->where('student_id', $request->student_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'student_id' => ['El estudiante ya tiene una matrícula en este ciclo.'],
                ]);
            }

            return Enrollment::create([
                'company_id' => TenantContext::companyId(),
                'school_level_id' => $lockedDivision->school_level_id,
                'academic_year_id' => $lockedDivision->academic_year_id,
                'student_id' => $request->student_id,
                'division_id' => $lockedDivision->id,
                'status' => 'active',
                'enrolled_on' => $request->enrolled_on ?: now()->toDateString(),
                'attendance_condition' => $request->attendance_condition ?: 'regular',
                'has_curricular_adaptation' => (bool) $request->has_curricular_adaptation,
            ]);
        });

        return response()->json([
            'data' => $enrollment->load('student:id,first_name,last_name,dni'),
        ], 201);
    }

    public function update(Request $request, Enrollment $enrollment)
    {
        $datos = $request->validate([
            'status' => ['required', 'in:active,transferred_out,withdrawn,completed'],
            'left_on' => ['nullable', 'date'],
            'attendance_condition' => ['nullable', 'in:regular,libre,oyente'],
            'has_curricular_adaptation' => ['sometimes', 'boolean'],
        ]);

        $division = $enrollment->division;

        if ($datos['status'] === 'transferred_out') {
            $this->authorize('transferEnrollment', $division);
        } else {
            $this->authorize('manageEnrollment', $division);
        }

        if (in_array($datos['status'], ['transferred_out', 'withdrawn'], true) && empty($datos['left_on'])) {
            $datos['left_on'] = now()->toDateString();
        }

        if ($datos['status'] === 'active') {
            $datos['left_on'] = null;
        }

        $enrollment->update($datos);

        return response()->json([
            'data' => $enrollment->fresh('student:id,first_name,last_name,dni'),
        ]);
    }
}
