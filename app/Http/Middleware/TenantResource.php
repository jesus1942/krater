<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Completa los scopes de modelos legacy que solo tienen company_id. */
class TenantResource
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->route()->parameters() as $resource) {
            if ($resource instanceof Model && array_key_exists('company_id', $resource->getAttributes())) {
                abort_unless((int) $resource->company_id === TenantContext::companyId(), 404);
            }
        }

        return $next($request);
    }
}
