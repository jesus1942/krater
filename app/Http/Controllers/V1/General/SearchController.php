<?php

namespace Crater\Http\Controllers\V1\General;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Services\Access\AccessManager;
use Crater\Services\Access\TenantUsers;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, AccessManager $access, TenantUsers $users)
    {
        $levelId = TenantContext::schoolLevelId();
        $actor = $request->user();
        $customers = $access->allows($actor, Permission::FINANCE_VIEW, $levelId)
            ? $users->customers()->applyFilters($request->only('search'))->latest()->paginate(10) : [];
        $staff = $access->allows($actor, Permission::USER_VIEW, $levelId)
            ? $users->staff($actor)->applyFilters($request->only('search'))->latest()->paginate(10) : [];

        return response()->json(['customers' => $customers, 'users' => $staff]);
    }
}
