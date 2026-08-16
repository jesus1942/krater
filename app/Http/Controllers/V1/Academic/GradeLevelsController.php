<?php

namespace Crater\Http\Controllers\V1\Academic;

use Crater\Http\Controllers\Controller;
use Crater\Models\Division;
use Crater\Models\GradeLevel;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cursos: "1.er grado", "3.er anio".
 *
 * `promotes_to_id` encadena un curso con el siguiente y es lo que hace posible
 * la promocion automatica. El ultimo curso lo deja nulo: quien lo aprueba
 * egresa en vez de promocionar.
 */
class GradeLevelsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAnyDivision', Division::class);

        $levels = GradeLevel::with('promotesTo:id,name')
            ->where('company_id', TenantContext::companyId())
            ->ordered()
            ->get();

        return response()->json(['data' => $levels]);
    }

    public function store(Request $request)
    {
        $this->authorize('manageDivision', Division::class);

        $datos = $this->validar($request);

        $level = GradeLevel::create(array_merge($datos, [
            'company_id' => TenantContext::companyId(),
            'school_level_id' => TenantContext::schoolLevelId(),
        ]));

        return response()->json(['data' => $level], 201);
    }

    public function update(Request $request, GradeLevel $gradeLevel)
    {
        $this->authorize('manageDivision', Division::class);

        $datos = $this->validar($request, $gradeLevel);

        // Un curso no puede promocionar a si mismo: seria un ciclo infinito en
        // el motor de promocion.
        if (isset($datos['promotes_to_id']) && (int) $datos['promotes_to_id'] === $gradeLevel->id) {
            return response()->json([
                'message' => 'Un curso no puede promocionar a si mismo.',
                'errors' => ['promotes_to_id' => ['Elegi otro curso de destino.']],
            ], 422);
        }

        $gradeLevel->update($datos);

        return response()->json(['data' => $gradeLevel->fresh('promotesTo')]);
    }

    protected function validar(Request $request, ?GradeLevel $actual = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'position' => [
                'required',
                'integer',
                'min:1',
                // La posicion ordena la progresion: dos cursos con la misma
                // dejarian ambigua la secuencia de promocion.
                Rule::unique('grade_levels')
                    ->where(fn ($q) => $q
                        ->where('company_id', TenantContext::companyId())
                        ->where('school_level_id', TenantContext::schoolLevelId()))
                    ->ignore(optional($actual)->id),
            ],
            'promotes_to_id' => [
                'nullable',
                'integer',
                Rule::exists('grade_levels', 'id')->where(fn ($q) => $q
                    ->where('company_id', TenantContext::companyId())
                    ->where('school_level_id', TenantContext::schoolLevelId())),
            ],
            'pedagogical_unit' => ['nullable', 'string', 'max:50'],
            'enabled' => ['sometimes', 'boolean'],
        ], [
            'position.unique' => 'Ya existe un curso en esa posicion para este nivel.',
        ]);
    }
}
