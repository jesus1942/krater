<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Models\SchoolLevel;
use Crater\Models\Company;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Valida los headers de tenant CONTRA EL USUARIO AUTENTICADO.
 *
 * El usuario comun queda limitado a su empresa y a los niveles que alcanza.
 * La administracion total conserva alcance transversal sobre todas las
 * empresas/niveles, que luego siguen sujetos a los scopes del recurso.
 */
class ValidateTenant
{
    private AccessManager $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        abort_unless($user->is_active, 403);
        foreach (['company', 'school-level'] as $header) {
            $value = $request->header($header);
            abort_if($value !== null && $value !== '' && (! ctype_digit($value) || (int) $value <= 0), 403);
        }

        $isTotalAdmin = $this->access->isTotalAdmin($user);

        // --- empresa ---------------------------------------------------------

        $companyHeader = $request->header('company');

        if ($companyHeader === null) {
            $companyId = (int) $user->company_id;
        } else {
            $companyId = (int) $companyHeader;

            if (! $isTotalAdmin && $companyId !== (int) $user->company_id) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        abort_unless($companyId > 0 && Company::whereKey($companyId)->exists(), 403);

        // --- nivel institucional --------------------------------------------
        // La vista sin nivel equivale a "Toda la institucion" y es exclusiva
        // de administracion total. Ocultarla en Vue no alcanza: este corte de
        // backend evita que otro usuario la fuerce por headers/API.

        $levelHeader = $request->header('school-level');
        $levelId = null;

        if ($levelHeader === null || $levelHeader === '') {
            if (! $isTotalAdmin) {
                return response()->json([
                    'error' => 'school_level_required',
                    'message' => 'Debe seleccionar un nivel institucional.',
                ], 403);
            }
        } else {
            $levelId = (int) $levelHeader;

            $existe = SchoolLevel::whereKey($levelId)
                ->where('company_id', $companyId)
                ->where('enabled', true)
                ->exists();

            if (! $existe) {
                return response()->json(['error' => 'forbidden'], 403);
            }

            if (! $isTotalAdmin
                && ! $this->access->hasInstitutionWideScope($user)
                && ! in_array($levelId, $this->access->levelIds($user), true)) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        // Los controladores legacy reciben ahora solo headers normalizados y
        // validados. El contexto sigue siendo la fuente de verdad.
        $request->headers->set('company', (string) $companyId);
        if ($levelId !== null) {
            $request->headers->set('school-level', (string) $levelId);
        }
        TenantContext::set($companyId, $levelId);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }

}
