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
 * institucional explicito y permiso finance.report.view. El contexto se fija
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
            abort(403);
        }

        $levelId = (int) $request->query('school_level_id', 0);
        abort_if($levelId <= 0, 403);

        $levelExists = SchoolLevel::whereKey($levelId)
            ->where('company_id', $company->id)
            ->where('enabled', true)
            ->exists();

        abort_unless($levelExists, 403);
        abort_unless(
            $this->access->allows($user, Permission::FINANCE_REPORT_VIEW, $levelId),
            403
        );

        TenantContext::set((int) $company->id, $levelId);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
