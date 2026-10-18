<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    /**
     * Fila E1: con contraseña inicial pendiente de cambio solo se permite lo
     * necesario para cambiarla (sesion, perfil propio y catalogos constantes).
     */
    const WHILE_PASSWORD_CHANGE = [
        'GET api/v1/bootstrap', 'GET api/v1/auth/check', 'GET api/v1/me', 'GET api/v1/me/settings',
        'GET api/v1/languages', 'GET api/v1/currencies', 'GET api/v1/timezones', 'GET api/v1/date/formats',
        'GET api/v1/fiscal/years', 'GET api/v1/school-levels', 'PUT api/v1/me/password', 'POST api/v1/auth/logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Sin sesion es 401 (volver a ingresar); cuenta desactivada es 403.
        if (! $request->user()) {
            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['error' => 'unauthenticated', 'message' => 'Tu sesión terminó.'], 401)
                : redirect('/login?aviso=sesion');
        }
        abort_unless($request->user()->is_active, 403);

        if ($request->user()->must_change_password && $request->is('api/*')
            && ! in_array($request->method().' '.$request->path(), self::WHILE_PASSWORD_CHANGE, true)) {
            return response()->json(['error' => 'password_change_required',
                'message' => 'Antes de continuar tenés que cambiar la contraseña inicial.'], 403);
        }

        return $next($request);
    }
}
