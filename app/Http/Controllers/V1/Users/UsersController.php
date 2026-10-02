<?php

namespace Crater\Http\Controllers\V1\Users;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\UserRequest;
use Crater\Models\CompanySetting;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Services\Access\TenantUsers;
use Crater\Services\Access\RoleAssignments;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    public function index(Request $request, TenantUsers $users)
    {
        return response()->json(['users' => $users->staff($request->user())
            ->applyFilters($request->only(['phone', 'email', 'display_name', 'orderByField', 'orderBy']))
            ->latest()->paginate(min(100, max(1, (int) $request->get('limit', 10))))]);
    }

    public function store(UserRequest $request, AccessManager $access)
    {
        $data = $request->validated();
        $data['role'] = 'staff';
        $data['company_id'] = TenantContext::companyId();
        $data['creator_id'] = $request->user()->id;
        abort_unless($access->canManageUser($request->user(), new User($data)), 403);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->setSettings(['language' => CompanySetting::getSetting('language', $user->company_id) ?: 'es']);

            return $user;
        });

        return response()->json(['user' => $user->fresh(), 'success' => true]);
    }

    public function show(Request $request, User $user, TenantUsers $users)
    {
        abort_unless($users->canViewStaff($request->user(), $user), 403);

        return response()->json(['user' => $user, 'success' => true]);
    }

    public function update(UserRequest $request, User $user, AccessManager $access)
    {
        // FormRequest autoriza antes de validar. Se repite bajo bloqueo para
        // impedir que una asignacion concurrente eleve al destino entre ambos.
        DB::transaction(function () use ($request, $user, $access) {
            app(RoleAssignments::class)->lockInstitution(TenantContext::companyId());
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            app(RoleAssignments::class)->authorizeTarget($request->user()->fresh(), $target);
            abort_unless(app(TenantUsers::class)->canViewStaff($request->user(), $target)
                && $access->canManageUser($request->user(), $target), 403);
            $data = $request->validated();
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $target->update($data);
        });

        return response()->json(['user' => $user->fresh(), 'success' => true]);
    }

    public function delete(Request $request, AccessManager $access, TenantUsers $users)
    {
        $data = $request->validate(['users' => ['required', 'array', 'min:1'], 'users.*' => ['required', 'integer', 'distinct']]);
        DB::transaction(function () use ($request, $data, $access, $users) {
            $assignments = app(RoleAssignments::class);
            $assignments->lockInstitution(TenantContext::companyId());
            $targets = User::whereIn('id', $data['users'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($targets->count() === count($data['users']), 403);
            // Se autoriza el lote completo antes de desactivar a nadie.
            foreach ($targets as $target) {
                $assignments->authorizeTarget($request->user()->fresh(), $target);
                abort_unless($users->canViewStaff($request->user(), $target)
                    && $access->canManageUser($request->user(), $target), 403);
            }
            if ($targets->contains(fn ($target) => $access->isTotalAdmin($target))) {
                $assignments->ensureAdministratorRemains(TenantContext::companyId(), null, $data['users']);
            }
            foreach ($targets as $target) {
                $target->forceFill(['is_active' => false, 'remember_token' => null])->save();
                $target->tokens()->delete();
                if (DB::getSchemaBuilder()->hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $target->id)->delete();
                }
                $access->forget($target);
            }
        });

        return response()->json(['success' => true]);
    }
}
