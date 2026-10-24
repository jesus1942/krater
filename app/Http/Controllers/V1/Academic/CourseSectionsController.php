<?php

namespace Crater\Http\Controllers\V1\Academic;

use Crater\Http\Controllers\Controller;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\Subject;
use Crater\Models\TeachingAssignment;
use Crater\Services\Audit\Auditor;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fila E1: materias por curso. Cada division dicta un conjunto de materias del
 * nivel; esa combinacion (seccion de materia) es la unidad de la asignacion
 * docente. Quitar una materia la archiva, nunca la borra.
 */
class CourseSectionsController extends Controller
{
    public function index(Request $request)
    {
        $level = $this->level();
        $data = $request->validate(['division_id' => ['nullable', 'integer']]);

        $sections = CourseSection::with(['subject:id,name,code,year_of_plan,study_plan_id', 'division:id,name,grade_level_id',
            'division.gradeLevel:id,name'])
            ->where('company_id', TenantContext::companyId())->where('school_level_id', $level)
            ->where('status', 'active')
            ->when($data['division_id'] ?? null, fn ($q, $division) => $q->where('division_id', $division))
            ->orderBy('division_id')->orderBy('subject_id')->get();

        $teachers = TeachingAssignment::active()->whereIn('course_section_id', $sections->modelKeys())
            ->with('staffMember:id,first_name,last_name')->get()->groupBy('course_section_id');
        $sections->each(fn ($section) => $section->setAttribute('teaching', ($teachers[$section->id] ?? collect())->map(fn ($t) => [
            'id' => $t->id, 'staff_member_id' => $t->staff_member_id,
            'name' => optional($t->staffMember)->full_name, 'weekly_hours' => $t->weekly_hours,
        ])->values()));

        return response()->json(['data' => $sections]);
    }

    /** Fija las materias que dicta una division: agrega, reactiva o archiva. */
    public function sync(Request $request, Division $division, Auditor $auditor)
    {
        $level = $this->level();
        abort_unless((int) $division->company_id === TenantContext::companyId() && (int) $division->school_level_id === $level, 404);
        $data = $request->validate(['subject_ids' => ['present', 'array'], 'subject_ids.*' => ['integer', 'distinct']]);
        $wanted = array_map('intval', $data['subject_ids']);

        $valid = Subject::where('company_id', TenantContext::companyId())->where('school_level_id', $level)
            ->whereIn('id', $wanted)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($valid) !== count($wanted)) {
            throw ValidationException::withMessages(['subject_ids' => 'Hay materias que no son de este nivel.']);
        }

        $result = DB::transaction(function () use ($division, $wanted, $request, $auditor) {
            Division::whereKey($division->id)->lockForUpdate()->first();
            $existing = CourseSection::where('division_id', $division->id)->lockForUpdate()->get()->keyBy('subject_id');
            $added = []; $archived = [];
            foreach ($wanted as $subjectId) {
                $section = $existing[$subjectId] ?? null;
                if (! $section) {
                    $section = CourseSection::create(['company_id' => $division->company_id, 'school_level_id' => $division->school_level_id,
                        'academic_year_id' => $division->academic_year_id, 'division_id' => $division->id,
                        'subject_id' => $subjectId, 'status' => 'active']);
                    $added[] = $section->id;
                } elseif ($section->status !== 'active') {
                    $section->update(['status' => 'active']);
                    $added[] = $section->id;
                }
            }
            foreach ($existing as $subjectId => $section) {
                if ($section->status === 'active' && ! in_array((int) $subjectId, $wanted, true)) {
                    if (TeachingAssignment::active()->where('course_section_id', $section->id)->exists()) {
                        throw ValidationException::withMessages(['subject_ids' => 'Una materia con docentes asignados no se puede quitar: revocar primero sus asignaciones.']);
                    }
                    $section->update(['status' => 'archived']);
                    $archived[] = $section->id;
                }
            }
            if ($added || $archived) {
                $auditor->record('course_sections.synced', $request->user(), ['type' => Division::class, 'id' => $division->id,
                    'company_id' => $division->company_id, 'school_level_id' => $division->school_level_id],
                    [], ['activated' => $added, 'archived' => $archived]);
            }

            return ['activated' => $added, 'archived' => $archived];
        });

        return response()->json(['success' => true] + $result);
    }

    protected function level(): int
    {
        $level = TenantContext::schoolLevelId();
        abort_unless($level, 422, 'Seleccioná un nivel institucional para ver las materias por curso.');

        return $level;
    }
}
