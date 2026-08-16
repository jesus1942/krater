<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Models\SchoolLevel;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            return $next($request);
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

        // --- nivel institucional --------------------------------------------

        $levelHeader = $request->header('school-level');
        $levelId = null;

        if ($levelHeader !== null && $levelHeader !== '') {
            $levelId = (int) $levelHeader;

            $existe = SchoolLevel::whereKey($levelId)
                ->where('company_id', $companyId)
                ->where('enabled', true)
                ->exists();

            if (! $existe) {
                return response()->json(['error' => 'forbidden'], 403);
            }

            if (! $isTotalAdmin
                && ! $this->tieneAlcanceGlobal($user->id, $companyId)
                && ! $this->perteneceAlNivel($user->id, $levelId)) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        TenantContext::set($companyId, $levelId);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }

    protected function perteneceAlNivel($userId, $levelId): bool
    {
        return DB::table('school_level_user')
            ->where('user_id', $userId)
            ->where('school_level_id', $levelId)
            ->exists();
    }

    protected function tieneAlcanceGlobal($userId, $companyId): bool
    {
        $today = now()->toDateString();

        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->where('role_user.company_id', $companyId)
            ->where('roles.company_id', $companyId)
            ->where('roles.scope_type', 'global')
            ->whereNull('role_user.school_level_id')
            ->where(function ($query) use ($today) {
                $query->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today);
            })
            ->exists();
    }
}
