<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Models\SchoolLevel;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Valida los headers de tenant CONTRA EL USUARIO AUTENTICADO.
 *
 * EL PROBLEMA QUE RESUELVE
 * ------------------------
 * El aislamiento multi-tenant de la app depende del header HTTP `company`, que
 * lo elige el cliente. Hay 82 usos de ese header en los controladores y las dos
 * validaciones que existen comparan *el recurso contra el header*, no *el
 * header contra el usuario*:
 *
 *     abort_unless((int) $schoolLevel->company_id === (int) $request->header('company'), 404);
 *
 * Eso se satisface falsificando el header. Cualquier usuario autenticado podia
 * mandar `company: 2` y operar sobre los datos de otra institucion.
 *
 * Este middleware invierte la comparacion: el header tiene que coincidir con la
 * empresa del usuario, y el nivel tiene que ser uno al que el usuario pertenece.
 *
 * Ademas fija el contexto en TenantContext, que es lo que consultan los scopes
 * globales. Antes leian `request()->header()` directamente, asi que fuera del
 * ciclo HTTP —consola, jobs, tests— no filtraban nada: fallaban abiertos.
 */
class ValidateTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // --- empresa ---------------------------------------------------------

        $companyHeader = $request->header('company');

        if ($companyHeader === null) {
            // Sin header se asume la empresa del usuario. No se hereda del
            // recurso ni se deja abierto.
            $companyId = (int) $user->company_id;
        } else {
            $companyId = (int) $companyHeader;

            if ($companyId !== (int) $user->company_id) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        // --- nivel institucional ----------------------------------------------

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

            // El usuario tiene que estar vinculado al nivel, salvo que tenga un
            // rol global (administracion total, direccion general).
            if (! $this->tieneAlcanceGlobal($user->id, $companyId) && ! $this->perteneceAlNivel($user->id, $levelId)) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        TenantContext::set($companyId, $levelId);

        try {
            return $next($request);
        } finally {
            // Se limpia siempre: en workers de cola que reutilizan el proceso,
            // dejar el contexto pegado filtraria datos del request anterior.
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
