<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Landing en el dominio principal, Suite en su subdominio.
 *
 * En un host de la landing solo se sirve el sitio publico (portada y paginas
 * de nivel); el resto de las rutas
 * web (login, admin, PDFs firmados, etc.) redirige con 301 a la misma ruta en
 * APP_URL. En el host de la Suite, la raiz lleva al ingreso. Sin
 * LANDING_HOSTS configurado no cambia nada.
 */
class SplitPublicHosts
{
    /** Rutas del sitio publico que se sirven en los hosts de la landing. */
    const PUBLIC_PATHS = ['/', 'primario', 'secundario', 'terciario'];

    public function handle(Request $request, Closure $next)
    {
        $landing = config('ena-hosts.landing_hosts', []);
        if (! $landing) {
            return $next($request);
        }
        $host = strtolower($request->getHost());
        $app = rtrim((string) config('app.url'), '/');
        $appHost = strtolower((string) parse_url($app, PHP_URL_HOST));

        if (in_array($host, $landing, true)) {
            if (in_array($request->path(), self::PUBLIC_PATHS, true) || $appHost === '' || $appHost === $host) {
                return $next($request);
            }

            return redirect()->away($app.'/'.ltrim($request->getRequestUri(), '/'), 301);
        }
        if ($request->path() === '/' && $host === $appHost) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
