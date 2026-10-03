<?php

namespace Crater\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && $request->user()->is_active, 403);

        return $next($request);
    }
}
