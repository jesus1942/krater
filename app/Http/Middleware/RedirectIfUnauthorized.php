<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfUnauthorized
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return $next($request);
        }

        // Habia una cookie de sesion pero la sesion ya no existe: avisar en el login.
        return redirect($request->hasCookie(config('session.cookie')) ? '/login?aviso=sesion' : '/login');
    }
}
