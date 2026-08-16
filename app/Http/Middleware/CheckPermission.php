<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Services\Access\AccessManager;
use Illuminate\Http\Request;

/**
 * Exige un permiso para acceder a la ruta.
 *
 *   Route::post('/academic-years', ...)
 *       ->middleware('permission:'.Permission::ACADEMIC_YEAR_MANAGE);
 *
 * Reemplaza al middleware `admin`, que comparaba `users.role` contra dos
 * strings y daba acceso a toda la API o a ninguna.
 *
 * El nivel institucional sale del header `school-level`. Si la ruta no es de un
 * nivel concreto, se pasa null y el resolutor evalua los roles globales.
 *
 * NOTA sobre el cuerpo del 403: no dice que permiso falto. Decirlo le mapea la
 * estructura de autorizacion a quien este probando el sistema.
 */
class CheckPermission
{
    protected $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        // Puente de compatibilidad durante la migracion a RBAC. El middleware
        // admin heredado ya considera este valor administracion total, por lo
        // que no debe perder acceso a los modulos nuevos si role_user aun no
        // fue poblado correctamente.
        if ($user->role === 'super admin') {
            return $next($request);
        }

        $schoolLevelId = $request->header('school-level');
        $schoolLevelId = $schoolLevelId !== null ? (int) $schoolLevelId : null;

        // Varios permisos en el mismo middleware se evaluan como O: alcanza con
        // tener uno. Para exigir todos, encadenar dos middleware.
        foreach ($permissions as $permission) {
            if ($this->access->allows($user, $permission, $schoolLevelId)) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'forbidden'], 403);
    }
}
