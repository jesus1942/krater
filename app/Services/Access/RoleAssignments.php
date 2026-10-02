<?php

namespace Crater\Services\Access;

use Crater\Enums\Permission;
use Crater\Models\AuditLog;
use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Otorgamientos append-only, revocacion inmediata y auditoria transaccional. */
class RoleAssignments
{
    /** Serializa los cambios de autoridad de una institucion antes de bloquear cuentas. */
    public function lockInstitution(int $company): void
    {
        DB::table('companies')->where('id', $company)->lockForUpdate()->first();
    }

    /** Conserva al menos un administrador activo sin vencimiento programado. */
    public function ensureAdministratorRemains(int $company, ?int $excludeAssignment = null, array $excludeUsers = []): void
    {
        $query = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->where('role_user.company_id', $company)->where('roles.company_id', $company)->where('users.company_id', $company)
            ->where('roles.name', 'total_admin')->where('roles.scope_type', 'global')
            ->whereNull('role_user.school_level_id')->whereNull('role_user.revoked_at')->whereNull('role_user.ends_on')
            ->where('users.is_active', true)->whereNotIn('users.id', $excludeUsers)
            ->where(fn ($q) => $q->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', now()->toDateString()));
        if ($excludeAssignment !== null) {
            $query->where('role_user.id', '<>', $excludeAssignment);
        }
        abort_unless($query->exists(), 409, 'Debe quedar un administrador total activo sin vencimiento.');
    }

    /** Autoriza el destino y todas sus asignaciones presentes o futuras. */
    public function authorizeTarget(User $actor, User $target): void
    {
        $access = app(AccessManager::class);
        abort_unless((int) $target->company_id === TenantContext::companyId()
            && app(TenantUsers::class)->canViewStaff($actor, $target)
            && $access->canManageUser($actor, $target), 403);
        if ($access->isTotalAdmin($actor)) {
            return;
        }
        $future = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $target->id)->whereNull('role_user.revoked_at')
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))
            ->select('role_user.school_level_id', 'roles.hierarchy_level')->get();
        foreach ($future as $assignment) {
            abort_unless($access->hierarchyLevel($actor) < $assignment->hierarchy_level
                && ($access->hasInstitutionWideScope($actor) || in_array((int) $assignment->school_level_id, $access->levelIds($actor), true)), 403);
        }
    }

    /** Valida que rol, nivel y alcance fino pertenezcan al mismo tenant. */
    public function authorizeScope(User $actor, object $role, ?int $level, ?int $division, ?int $section): void
    {
        $access = app(AccessManager::class);
        abort_unless((int) $role->company_id === TenantContext::companyId()
            && $access->canGrantRole($actor, $role->name, (int) $role->hierarchy_level, $level), 403);
        if ($role->scope_type === 'global') {
            abort_unless($level === null && $division === null && $section === null
                && ($access->isTotalAdmin($actor) || $access->hasInstitutionWideScope($actor)), 403);
            return;
        }
        abort_unless($level !== null && DB::table('school_levels')->where('id', $level)
            ->where('company_id', TenantContext::companyId())->where('enabled', true)->exists(), 403);
        abort_unless($access->isTotalAdmin($actor) || in_array($level, $access->levelIds($actor), true), 403);
        if (TenantContext::schoolLevelId() !== null) {
            abort_unless($level === TenantContext::schoolLevelId(), 403);
        }
        if ($role->scope_type === 'division') {
            abort_unless($division && ! $section && DB::table('divisions')->where('id', $division)
                ->where('company_id', $role->company_id)->where('school_level_id', $level)->exists(), 403);
            abort_unless($access->allows($actor, Permission::ROLE_ASSIGN, $level, ['type' => 'division', 'id' => $division]), 403);
        } elseif ($role->scope_type === 'section') {
            abort_unless(! $division && $section && DB::table('course_sections')->where('id', $section)
                ->where('company_id', $role->company_id)->where('school_level_id', $level)->exists(), 403);
            abort_unless($access->allows($actor, Permission::ROLE_ASSIGN, $level, ['type' => 'course_section', 'id' => $section]), 403);
        } else {
            abort_unless($role->scope_type === 'level' && ! $division && ! $section && $access->hasLevelWideScope($actor, $level, Permission::ROLE_ASSIGN), 403);
        }
    }

    /** Guarda una nueva asignacion sin sobreescribir otorgamientos anteriores. */
    public function grant(User $actor, User $target, array $data): int
    {
        return DB::transaction(function () use ($actor, $target, $data) {
            $this->lockInstitution(TenantContext::companyId());
            $actor = User::findOrFail($actor->id);
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTarget($actor, $target);
            abort_unless($target->is_active, 409, 'La cuenta esta desactivada.');
            $role = DB::table('roles')->where('id', $data['role_id'])->first();
            abort_unless($role, 403);
            $level = isset($data['school_level_id']) ? (int) $data['school_level_id'] : null;
            $division = isset($data['division_id']) ? (int) $data['division_id'] : null;
            $section = isset($data['course_section_id']) ? (int) $data['course_section_id'] : null;
            $this->authorizeScope($actor, $role, $level, $division, $section);
            $start = $data['starts_at'] ?? now()->toDateString();
            $end = $data['ends_at'] ?? null;
            if ($end && $end < $start) {
                throw ValidationException::withMessages(['ends_at' => 'El fin debe ser igual o posterior al inicio.']);
            }
            if ($role->name === 'total_admin' && ($end || $start > now()->toDateString())) {
                throw ValidationException::withMessages(['ends_at' => 'El administrador total debe quedar vigente y sin vencimiento.']);
            }
            $values = ['user_id' => $target->id, 'company_id' => $target->company_id, 'role_id' => $role->id,
                'school_level_id' => $level, 'division_id' => $division, 'course_section_id' => $section];
            $duplicate = DB::table('role_user')->where($values)->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $start));
            if ($end) {
                $duplicate->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $end));
            }
            abort_if($duplicate->exists(), 409, 'Ya existe una asignacion superpuesta con ese alcance.');
            $values += ['starts_on' => $start, 'ends_on' => $end, 'managed_scope' => true,
                'granted_by' => $actor->id, 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            $id = DB::table('role_user')->insertGetId($values);
            $this->audit('role_granted', $actor, $id, $level, $values);
            app(AccessManager::class)->forget($target);
            return $id;
        });
    }

    /** Revoca ahora; conserva las fechas originales y el historial completo. */
    public function revoke(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $this->lockInstitution(TenantContext::companyId());
            $assignment = DB::table('role_user')->where('id', $id)->where('company_id', TenantContext::companyId())->lockForUpdate()->first();
            abort_unless($assignment, 404);
            $actor = User::findOrFail($actor->id);
            $target = User::whereKey($assignment->user_id)->lockForUpdate()->firstOrFail();
            $this->authorizeTarget($actor, $target);
            $role = DB::table('roles')->where('id', $assignment->role_id)->first();
            abort_unless(app(AccessManager::class)->canGrantRole($actor, $role->name, $role->hierarchy_level, $assignment->school_level_id), 403);
            if ($assignment->managed_scope) {
                $this->authorizeScope($actor, $role, $assignment->school_level_id, $assignment->division_id, $assignment->course_section_id);
            } else {
                abort_unless(app(AccessManager::class)->isTotalAdmin($actor)
                    || (app(AccessManager::class)->hasLevelWideScope($actor, $assignment->school_level_id, Permission::ROLE_ASSIGN)
                        && $assignment->school_level_id === TenantContext::schoolLevelId()), 403);
            }
            if ($assignment->revoked_at) {
                return;
            }
            if ($role->name === 'total_admin') {
                $this->ensureAdministratorRemains($target->company_id, $id);
            }
            DB::table('role_user')->where('id', $id)->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now()]);
            $this->audit('role_revoked', $actor, $id, $assignment->school_level_id, ['target_user_id' => $target->id, 'role_id' => $role->id]);
            app(AccessManager::class)->forget($target);
        });
    }

    /** Si falla la auditoria, la transaccion revierte tambien la autoridad. */
    private function audit(string $action, User $actor, int $id, ?int $level, array $values): void
    {
        AuditLog::create(['company_id' => TenantContext::companyId(), 'school_level_id' => $level,
            'user_id' => $actor->id, 'action' => $action, 'auditable_type' => 'role_user', 'auditable_id' => $id,
            'new_values' => $values, 'severity' => AuditLog::SEVERITY_HIGH, 'created_at' => now()]);
    }
}
