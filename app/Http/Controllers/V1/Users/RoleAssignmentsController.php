<?php

namespace Crater\Http\Controllers\V1\Users;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Services\Access\RoleAssignments;
use Crater\Services\Access\TenantUsers;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleAssignmentsController extends Controller
{
    /** Catalogo y opciones de alcance filtrados por la autoridad del actor. */
    public function roles(Request $request, AccessManager $access)
    {
        $actor = $request->user();
        app(\Crater\Services\Audit\Auditor::class)->recordPermissionUse(Permission::USER_VIEW, $actor, $request->path(), 'GET');
        $levels = DB::table('school_levels')->where('company_id', TenantContext::companyId())->where('enabled', true);
        if (! $access->isTotalAdmin($actor)) {
            $levels->whereIn('id', $access->levelIds($actor));
        }
        if (TenantContext::schoolLevelId()) {
            $levels->where('id', TenantContext::schoolLevelId());
        }
        $levels = $levels->get(['id', 'name']);
        $roles = DB::table('roles')->where('company_id', TenantContext::companyId())->orderBy('hierarchy_level')->get()
            ->map(function ($role) use ($actor, $access, $levels) {
                $role->grantable_level_ids = $levels->filter(fn ($level) => $access->canGrantRole($actor, $role->name, $role->hierarchy_level, $level->id))
                    ->pluck('id')->values();
                $role->grantable = $role->scope_type === 'global'
                    ? $access->canGrantRole($actor, $role->name, $role->hierarchy_level, null)
                    : $role->grantable_level_ids->isNotEmpty();
                return $role;
            });
        $divisions = DB::table('divisions')->leftJoin('academic_years', 'academic_years.id', '=', 'divisions.academic_year_id')->where('divisions.company_id', TenantContext::companyId())->whereIn('divisions.school_level_id', $levels->pluck('id'))
            ->get(['divisions.id', 'divisions.name', 'divisions.school_level_id', 'divisions.academic_year_id', 'academic_years.year'])->filter(fn ($d) => $access->allows($actor, Permission::ROLE_ASSIGN, $d->school_level_id, ['type' => 'division', 'id' => $d->id]))->values();
        $sections = DB::table('course_sections')->leftJoin('subjects', 'subjects.id', '=', 'course_sections.subject_id')->leftJoin('divisions', 'divisions.id', '=', 'course_sections.division_id')->where('course_sections.company_id', TenantContext::companyId())->whereIn('course_sections.school_level_id', $levels->pluck('id'))
            ->get(['course_sections.id', 'course_sections.division_id', 'course_sections.subject_id', 'course_sections.school_level_id', 'subjects.name as subject_name', 'divisions.name as division_name'])->filter(fn ($s) => $access->allows($actor, Permission::ROLE_ASSIGN, $s->school_level_id, ['type' => 'course_section', 'id' => $s->id]))->values();
        return response()->json(['roles' => $roles, 'levels' => $levels, 'divisions' => $divisions, 'sections' => $sections]);
    }

    /** Consulta historial sin revelar asignaciones de otros niveles. */
    public function index(Request $request, User $user, AccessManager $access, TenantUsers $users)
    {
        abort_unless($users->canViewStaff($request->user(), $user), 403);
        app(\Crater\Services\Audit\Auditor::class)->recordPermissionUse(Permission::USER_VIEW, $request->user(), $request->path(), 'GET');
        $query = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('school_levels', 'school_levels.id', '=', 'role_user.school_level_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'role_user.division_id')
            ->where('role_user.user_id', $user->id)->where('role_user.company_id', TenantContext::companyId());
        if (! $access->isTotalAdmin($request->user())) {
            $query->where('role_user.school_level_id', TenantContext::schoolLevelId());
        }
        return response()->json(['assignments' => $query->orderByDesc('role_user.id')->get(['role_user.*', 'roles.label', 'roles.name', 'roles.scope_type', 'school_levels.name as level_name', 'divisions.name as division_name']),
            'can_manage' => $access->canManageUser($request->user(), $user)]);
    }

    /** Da de alta una asignacion validada por el servicio de autorizacion. */
    public function store(Request $request, User $user, RoleAssignments $assignments)
    {
        $data = $request->validate(['role_id' => ['required', 'integer'], 'school_level_id' => ['nullable', 'integer'],
            'division_id' => ['nullable', 'integer'], 'course_section_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'], 'ends_at' => ['nullable', 'date_format:Y-m-d']]);
        return response()->json(['id' => $assignments->grant($request->user(), $user, $data)], 201);
    }

    /** Conserva la asignacion y registra actor y momento de revocacion. */
    public function revoke(Request $request, int $id, RoleAssignments $assignments)
    {
        $assignments->revoke($request->user(), $id);
        return response()->json(['success' => true]);
    }

    /** Explica permisos del nivel consultado y sus asignaciones de origen. */
    public function effective(Request $request, User $user, AccessManager $access, TenantUsers $users)
    {
        abort_unless($users->canViewStaff($request->user(), $user), 403);
        app(\Crater\Services\Audit\Auditor::class)->recordPermissionUse(Permission::USER_VIEW, $request->user(), $request->path(), 'GET');
        $level = TenantContext::schoolLevelId();
        $permissions = [];
        foreach ($access->effectivePermissions($user, $level) as $permission) {
            $sources = $access->assignmentsWithPermission($user, $permission, $level)->get();
            $permissions[] = ['name' => $permission, 'label' => Permission::catalog()[$permission]['label'] ?? $permission,
                'sources' => $sources, 'total_admin' => $access->isTotalAdmin($user)];
        }
        return response()->json(['school_level_id' => $level, 'permissions' => $permissions]);
    }
}
