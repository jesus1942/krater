<?php

namespace Crater\Http\Controllers\V1\Student;

use Crater\Http\Controllers\Controller;
use Crater\Models\AcademicYear;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\GradeLevel;
use Crater\Models\SchoolLevel;
use Crater\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentRelocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    public function options(Request $request, Student $student)
    {
        $this->ensureCompany($request, $student);
        $companyId = (int) $request->header('company');

        return response()->json([
            'levels' => SchoolLevel::where('company_id', $companyId)
                ->where('enabled', true)
                ->orderBy('id')
                ->get(['id', 'name', 'code']),
            'academic_years' => AcademicYear::acrossLevels()
                ->where('company_id', $companyId)
                ->orderByDesc('year')
                ->get(['id', 'school_level_id', 'year', 'name', 'status']),
            'grade_levels' => GradeLevel::acrossLevels()
                ->where('company_id', $companyId)
                ->where('enabled', true)
                ->orderBy('school_level_id')
                ->orderBy('position')
                ->get(['id', 'school_level_id', 'name', 'position']),
            'divisions' => Division::acrossLevels()
                ->where('company_id', $companyId)
                ->where('enabled', true)
                ->orderBy('school_level_id')
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get(['id', 'school_level_id', 'academic_year_id', 'grade_level_id', 'name', 'shift', 'capacity']),
        ]);
    }

    public function relocate(Request $request, Student $student)
    {
        $this->ensureCompany($request, $student);
        $companyId = (int) $request->header('company');

        $data = $request->validate([
            'school_level_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'grade_level_id' => ['required', 'integer'],
            'division_id' => ['required', 'integer'],
        ]);

        $result = DB::transaction(function () use ($data, $student, $companyId) {
            $schoolLevel = SchoolLevel::where('company_id', $companyId)
                ->where('enabled', true)
                ->findOrFail($data['school_level_id']);

            $academicYear = AcademicYear::acrossLevels()
                ->where('company_id', $companyId)
                ->where('school_level_id', $schoolLevel->id)
                ->find($data['academic_year_id']);

            $gradeLevel = GradeLevel::acrossLevels()
                ->where('company_id', $companyId)
                ->where('school_level_id', $schoolLevel->id)
                ->where('enabled', true)
                ->find($data['grade_level_id']);

            $division = Division::acrossLevels()
                ->where('company_id', $companyId)
                ->where('school_level_id', $schoolLevel->id)
                ->where('academic_year_id', optional($academicYear)->id)
                ->where('grade_level_id', optional($gradeLevel)->id)
                ->where('enabled', true)
                ->whereKey($data['division_id'])
                ->lockForUpdate()
                ->first();

            if (! $academicYear || ! $gradeLevel || ! $division) {
                throw ValidationException::withMessages([
                    'division_id' => ['El nivel, ciclo, curso y división elegidos no forman una ubicación académica válida.'],
                ]);
            }

            $sameYearIds = AcademicYear::acrossLevels()
                ->where('company_id', $companyId)
                ->where('year', $academicYear->year)
                ->pluck('id');

            $existing = Enrollment::acrossLevels()
                ->where('company_id', $companyId)
                ->where('student_id', $student->id)
                ->whereIn('academic_year_id', $sameYearIds)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->division_id !== $division->id && $existing->sectionEnrollments()->exists()) {
                throw ValidationException::withMessages([
                    'division_id' => ['Esta matrícula ya tiene actividad académica asociada. Para moverla debe usarse una transferencia que preserve ese historial.'],
                ]);
            }

            $occupied = Enrollment::acrossLevels()
                ->where('company_id', $companyId)
                ->where('division_id', $division->id)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->when($existing, function ($query) use ($existing) {
                    $query->where('id', '<>', $existing->id);
                })
                ->count();

            if ($division->capacity && $occupied >= $division->capacity) {
                throw ValidationException::withMessages([
                    'division_id' => ['No quedan lugares en la división elegida.'],
                ]);
            }

            $levelLabels = [
                'primary' => 'Primario',
                'secondary' => 'Secundario',
                'tertiary' => 'Terciario',
            ];

            $student->update([
                'school_level_id' => $schoolLevel->id,
                'level' => $levelLabels[$schoolLevel->code] ?? $schoolLevel->name,
                'grade' => $gradeLevel->name,
                'division' => $division->name,
                'school_year' => $academicYear->year,
            ]);

            $enrollmentData = [
                'company_id' => $companyId,
                'school_level_id' => $schoolLevel->id,
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
                'division_id' => $division->id,
                'status' => Enrollment::STATUS_ACTIVE,
                'left_on' => null,
            ];

            if ($existing) {
                $existing->update($enrollmentData);
                $enrollment = $existing->fresh();
            } else {
                $enrollmentData['enrolled_on'] = now()->toDateString();
                $enrollmentData['attendance_condition'] = 'regular';
                $enrollmentData['has_curricular_adaptation'] = false;
                $enrollment = Enrollment::create($enrollmentData);
            }

            return [$student->fresh(), $enrollment];
        });

        return response()->json([
            'student' => $result[0],
            'enrollment' => $result[1],
            'success' => true,
        ]);
    }

    private function ensureCompany(Request $request, Student $student)
    {
        abort_unless((int) $student->company_id === (int) $request->header('company'), 404);
    }
}
