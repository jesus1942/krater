<?php

namespace Crater\Http;

use Crater\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Crater\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Crater\Http\Middleware\TrustProxies::class,
        \Crater\Http\Middleware\ConfigMiddleware::class,
        \Fruitcake\Cors\HandleCors::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \Crater\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Crater\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:180,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Crater\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \Crater\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        // `admin` es el esquema viejo: compara users.role contra dos strings y
        // da acceso a toda la API o a ninguna. Se mantiene mientras convivan
        // los dos sistemas; las rutas nuevas usan `permission`.
        'admin' => AdminMiddleware::class,

        'permission' => \Crater\Http\Middleware\CheckPermission::class,
        'tenant' => \Crater\Http\Middleware\ValidateTenant::class,
        'report-tenant' => \Crater\Http\Middleware\ReportTenant::class,

        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'install' => \Crater\Http\Middleware\InstallationMiddleware::class,
        'redirect-if-installed' => \Crater\Http\Middleware\RedirectIfInstalled::class,
        'redirect-if-unauthenticated' => \Crater\Http\Middleware\RedirectIfUnauthorized::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * ValidateTenant tiene que ejecutarse despues de autenticar y antes de
     * SubstituteBindings. De lo contrario Laravel resuelve por ID un modelo de
     * otra institucion cuando TenantContext todavia esta vacio.
     *
     * @var array
     */
    protected $middlewarePriority = [
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Crater\Http\Middleware\Authenticate::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \Crater\Http\Middleware\ValidateTenant::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
    ];
}
