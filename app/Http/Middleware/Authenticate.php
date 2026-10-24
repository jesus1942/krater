<?php

namespace Crater\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // Se apunta a /login de forma explicita: la ruta con nombre "login"
            // es el catch-all del SPA y su parametro opcional resuelve a "/",
            // que ahora sirve la landing publica.
            return url('/login');
        }
    }
}
