<?php

namespace Crater\Http\Controllers\V1\Academic;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\Academic\SubjectRequest;
use Crater\Models\StudyPlan;
use Crater\Models\Subject;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Espacios curriculares.
 *
 * La materia como definicion del plan, no como dictado concreto: el dictado es
 * la seccion de materia, que ademas tiene division, ciclo y docentes.
 *
 * Se autoriza con los permisos de plan de estudios y no con los de division,
 * porque cambiar una materia es cambiar el disenio curricular: lo hace la
 * conduccion, no quien arma los cursos del anio.
 */
class SubjectsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewStudyPlan', Subject::class);

        $query = Subject::with('studyPlan:id,name')
            ->where('company_id', TenantContext::companyId())
            ->where('school_level_id', TenantContext::schoolLevelId());

        if ($request->filled('study_plan_id')) {
            $query->where('study_plan_id', $request->study_plan_id);
        }

        if ($request->boolean('solo_habilitadas')) {
            $query->enabled();
        }

        $subjects = $query
            ->orderByRaw('year_of_plan is null, year_of_plan')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $subjects]);
    }

    public function store(SubjectRequest $request)
    {
        $this->authorize('manageStudyPlan', Subject::class);

        $subject = Subject::create(array_merge(
            $request->validated(),
            [
                'company_id' => TenantContext::companyId(),
                'school_level_id' => TenantContext::schoolLevelId(),
            ]
        ));

        return response()->json(['data' => $subject->load('studyPlan:id,name')], 201);
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $this->authorize('manageStudyPlan', Subject::class);

        $subject->update($request->validated());

        return response()->json(['data' => $subject->fresh('studyPlan:id,name')]);
    }

    /**
     * Deshabilitar en lugar de borrar.
     *
     * Una materia borrada se lleva puestas las calificaciones historicas que
     * cuelgan de ella. Deshabilitada deja de ofrecerse para secciones nuevas,
     * pero el historial sigue siendo legible.
     */
    public function destroy(Subject $subject)
    {
        $this->authorize('manageStudyPlan', Subject::class);

        $subject->update(['enabled' => false]);

        return response()->json([
            'success' => true,
            'message' => 'La materia quedó deshabilitada. No se borra para no afectar el historial de calificaciones.',
        ]);
    }

    /**
     * Planes de estudio, para poder elegir uno al crear una materia.
     */
    public function studyPlans()
    {
        $this->authorize('viewStudyPlan', Subject::class);

        $plans = StudyPlan::where('company_id', TenantContext::companyId())
            ->where('school_level_id', TenantContext::schoolLevelId())
            ->orderByDesc('effective_from_year')
            ->get(['id', 'name', 'code', 'duration_years', 'effective_from_year', 'effective_to_year', 'enabled']);

        return response()->json(['data' => $plans]);
    }
}
