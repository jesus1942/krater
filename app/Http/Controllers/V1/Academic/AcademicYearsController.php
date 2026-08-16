<?php

namespace Crater\Http\Controllers\V1\Academic;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\Academic\AcademicYearRequest;
use Crater\Models\AcademicYear;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Ciclos lectivos.
 *
 * Las rutas ya exigen el permiso via middleware `permission:`. La policy se
 * llama igual en cada accion, porque el middleware valida el permiso en el
 * nivel del header y la policy lo valida sobre el objeto concreto — que puede
 * ser de otro nivel si alguien manda un id ajeno.
 */
class AcademicYearsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAnyAcademicYear', AcademicYear::class);

        $years = AcademicYear::whereCompany(TenantContext::companyId())
            ->with('terms')
            ->orderByDesc('year')
            ->get();

        return response()->json(['data' => $years]);
    }

    public function store(AcademicYearRequest $request)
    {
        $this->authorize('manageAcademicYear', AcademicYear::class);

        $year = AcademicYear::create(array_merge(
            $request->validated(),
            [
                'company_id' => TenantContext::companyId(),
                'school_level_id' => TenantContext::schoolLevelId(),
                'status' => AcademicYear::STATUS_DRAFT,
            ]
        ));

        return response()->json(['data' => $year->fresh('terms')], 201);
    }

    public function show(AcademicYear $academicYear)
    {
        $this->authorize('viewAcademicYear', $academicYear);

        return response()->json(['data' => $academicYear->load('terms')]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear)
    {
        $this->authorize('manageAcademicYear', $academicYear);

        $academicYear->update($request->validated());

        return response()->json(['data' => $academicYear->fresh('terms')]);
    }

    /**
     * No hay destroy.
     *
     * Un ciclo lectivo con matriculas es historia academica y no se borra: se
     * cierra. Si hace falta descartar un ciclo mal creado, se borra desde la
     * base con la matricula vacia, y eso lo hace la administracion total.
     */
}
