<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Enums\Permission;
use Crater\Models\Company;
use Crater\Models\SchoolLevel;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Protege los reportes PDF que se abren en iframe/ventana web.
 *
 * El hash de empresa identifica el recurso, pero NO es una credencial. Para
 * generar un reporte se exige usuario autenticado, empresa autorizada, nivel
 * institucional o consolidado exclusivo de administración total y permiso finance.report.view. El contexto se fija
 * antes de ejecutar el controlador para que los scopes por nivel funcionen en
 * Invoice, Expense y relaciones dependientes.
 */
class ReportTenant
{
    private AccessManager $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $company = Company::where('unique_hash', $request->route('hash'))->firstOrFail();
        $isTotalAdmin = $this->access->isTotalAdmin($user);

        if (! $isTotalAdmin && (int) $user->company_id !== (int) $company->id) {
            abort(403, 'No tenés acceso a los informes de esta institución.');
        }

        $rawLevel = $request->query('school_level_id');
        abort_if($rawLevel !== null && $rawLevel !== '' && (! is_scalar($rawLevel) || ! ctype_digit((string) $rawLevel) || (int) $rawLevel <= 0),
            422, 'Elegí un nivel institucional válido.');
        $levelId = $rawLevel === null || $rawLevel === '' ? null : (int) $rawLevel;
        abort_if($levelId === null && ! $isTotalAdmin, 403, 'Seleccioná un nivel para ver sus informes.');

        $levelExists = $levelId === null || SchoolLevel::whereKey($levelId)
            ->where('company_id', $company->id)
            ->where('enabled', true)
            ->exists();

        abort_unless($levelExists, 403, 'El nivel no está habilitado en esta institución.');
        abort_unless(
            $this->access->allows($user, Permission::FINANCE_REPORT_VIEW, $levelId),
            403, 'No tenés permiso para consultar estos informes.'
        );

        TenantContext::set((int) $company->id, $levelId);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
