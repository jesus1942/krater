<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Services\Access\AccessManager;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        abort_unless($request->user(), 401);
        abort_unless(app(AccessManager::class)->hasActiveRole($request->user()), 403);

        return $next($request);
    }
}
