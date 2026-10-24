<?php

namespace Crater\Http\Controllers\V1\Student;

use Crater\Http\Controllers\Controller;
use Crater\Models\AcademicYear;
use Crater\Models\Division;
use Crater\Models\GradeLevel;
use Crater\Models\SchoolLevel;
use Crater\Models\Student;
use Crater\Services\Data\StudentPlacement;
use Illuminate\Http\Request;

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

    /**
     * Fila 7: dos operaciones distintas. Correccion de carga corrige la
     * matricula vigente sin historial; Pase la cierra como transferred_out y
     * abre otra. Ver StudentPlacement.
     */
    public function relocate(Request $request, Student $student, StudentPlacement $placement)
    {
        $this->ensureCompany($request, $student);
        $data = $request->validate([
            'mode' => ['required', 'in:'.StudentPlacement::MODE_CORRECTION.','.StudentPlacement::MODE_TRANSFER],
            'school_level_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'grade_level_id' => ['required', 'integer'],
            'division_id' => ['required', 'integer'],
            'effective_on' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        [$student, $enrollment] = $placement->relocate($request->user(), $student, (int) $request->header('company'), $data);

        return response()->json(['student' => $student, 'enrollment' => $enrollment, 'success' => true]);
    }

    /** Historial de matriculas en todos los niveles; lo ve quien puede ver al alumno. */
    public function history(Request $request, Student $student, StudentPlacement $placement)
    {
        $this->ensureCompany($request, $student);
        abort_unless(Student::whereKey($student->id)->accessibleTo($request->user())->exists(), 403);

        return response()->json(['data' => $placement->history($student)]);
    }

    private function ensureCompany(Request $request, Student $student)
    {
        abort_unless((int) $student->company_id === (int) $request->header('company'), 404);
    }
}
