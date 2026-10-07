<?php

namespace Crater\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * `login` estaba exceptuada. Se saco: la landing publica ahora expone el
     * formulario de ingreso a cualquiera que entre al sitio, asi que la ruta
     * necesita la proteccion como cualquier otra. El SPA ya manda el token
     * XSRF, porque `$addHttpCookie` esta en true.
     *
     * @var array
     */
    protected $except = [
        //
    ];
}
