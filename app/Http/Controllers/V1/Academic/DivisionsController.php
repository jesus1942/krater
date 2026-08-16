<?php

namespace Crater\Http\Controllers\V1\Academic;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\Academic\DivisionRequest;
use Crater\Models\Division;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Divisiones: "3.er anio A" de un ciclo concreto.
 *
 * El listado aplica el alcance del usuario: la direccion ve todas las del
 * nivel, un preceptor solo las que tiene asignadas, un docente las que alcanza
 * por tener una seccion en ellas. Ese filtro se hace en la consulta, nunca en
 * el frontend: filtrar en el cliente significa que los datos igual viajaron.
 */
class DivisionsController extends Controller
{
    protected $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAnyDivision', Division::class);

        $query = Division::with(['gradeLevel', 'headTeacher:id,name'])
            ->where('company_id', TenantContext::companyId());

        if ($request->filled('academic_year_id')) {
            $query->forYear($request->academic_year_id);
        }

        if (! $this->access->hasLevelWideScope($request->user(), TenantContext::schoolLevelId())) {
            // Array vacio significa "ninguna", no "todas". whereIn con lista
            // vacia devuelve cero filas, que es exactamente lo que queremos.
            $query->whereIn('id', $this->access->scopedDivisionIds($request->user()));
        }

        $divisions = $query->orderBy('grade_level_id')->orderBy('name')->get();

        return response()->json(['data' => $divisions]);
    }

    public function store(DivisionRequest $request)
    {
        $this->authorize('manageDivision', Division::class);

        $division = Division::create(array_merge(
            $request->validated(),
            [
                'company_id' => TenantContext::companyId(),
                'school_level_id' => TenantContext::schoolLevelId(),
            ]
        ));

        return response()->json(['data' => $division->load('gradeLevel')], 201);
    }

    public function show(Division $division)
    {
        $this->authorize('viewDivision', $division);

        return response()->json([
            'data' => $division->load(['gradeLevel', 'headTeacher:id,name', 'courseSections.subject']),
        ]);
    }

    public function update(DivisionRequest $request, Division $division)
    {
        $this->authorize('manageDivision', $division);

        $division->update($request->validated());

        return response()->json(['data' => $division->fresh('gradeLevel')]);
    }
}
