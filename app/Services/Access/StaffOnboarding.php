<?php

namespace Crater\Services\Access;

use Crater\Enums\Permission;
use Crater\Enums\RoleName;
use Crater\Models\AuditLog;
use Crater\Models\CourseSection;
use Crater\Models\StaffAssignment;
use Crater\Models\StaffMember;
use Crater\Models\TeachingAssignment;
use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fila E1: alta unificada de personal.
 *
 * La ficha (StaffMember) es la persona. Sus cargos son roles con alcance
 * (role_user via RoleAssignments) y quedan tambien como cargo de RR.HH.; sus
 * asignaciones docentes otorgan el rol docente sobre cada seccion. La cuenta
 * de acceso es opcional y se crea, vincula o desactiva desde la ficha.
 *
 * Los permisos salen solo de los roles: esta clase nunca toca permission_role.
 * Solo la administracion total y la direccion de nivel operan aca, y la
 * direccion unicamente en su nivel.
 */
class StaffOnboarding
{
    /** Roles que no se dan como cargo: la docencia sale de las asignaciones. */
    const NOT_A_CARGO = [RoleName::TEACHER, RoleName::GUARDIAN, RoleName::STUDENT];

    const FUNCTION_BY_ROLE = [
        RoleName::TOTAL_ADMIN => 'management', RoleName::GENERAL_DIRECTOR => 'management',
        RoleName::LEVEL_DIRECTOR => 'management', RoleName::VICE_DIRECTOR => 'management',
        RoleName::ACADEMIC_SECRETARY => 'administrative', RoleName::FINANCE_ADMIN => 'administrative',
        RoleName::HR_ADMIN => 'administrative', RoleName::PRECEPTOR => 'support',
        RoleName::PRECEPTOR_REGISTRAR => 'support', RoleName::COUNSELOR => 'support', RoleName::STAFF => 'support',
    ];

    protected AccessManager $access;
    protected RoleAssignments $roles;
    /** Ficha que se esta creando: todavia no tiene nivel y la direccion puede cargarla. */
    protected ?int $creating = null;

    public function __construct(AccessManager $access, RoleAssignments $roles)
    {
        $this->access = $access;
        $this->roles = $roles;
    }

    /** Ficha, cargos, docencia y acceso en una sola transaccion. */
    public function create(User $actor, array $data): StaffMember
    {
        $levels = array_merge(array_column($data['cargos'] ?? [], 'school_level_id'),
            array_map(fn ($t) => $this->sectionLevel((int) $t['course_section_id']), $data['teaching'] ?? []));
        if (! $this->access->isTotalAdmin($actor) && ! $levels) {
            throw ValidationException::withMessages(['cargos' => 'Indicá al menos un cargo o una asignación docente de tu nivel.']);
        }
        foreach ($levels as $level) {
            $this->authorizeLevel($actor, $level === null ? null : (int) $level);
        }

        return DB::transaction(function () use ($actor, $data) {
            $this->roles->lockInstitution(TenantContext::companyId());
            $member = StaffMember::create([
                'company_id' => TenantContext::companyId(),
                'document_type' => $data['document_type'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'document_number_normalized' => $this->normalizeDocument($data['document_number'] ?? null),
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
                'staff_category' => 'other', 'employment_status' => 'active',
                'hire_date' => $data['hire_date'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $this->audit('staff.onboarded', $actor, $member, null, ['cargos' => count($data['cargos'] ?? []),
                'teaching' => count($data['teaching'] ?? []), 'access' => (bool) ($data['access']['enabled'] ?? false)]);

            $this->creating = $member->id;
            try {
                if (! empty($data['access']['enabled'])) {
                    $this->attachAccess($actor, $member, $data['access']);
                }
                foreach ($data['cargos'] ?? [] as $cargo) {
                    $this->addCargo($actor, $member, $cargo);
                }
                foreach ($data['teaching'] ?? [] as $teaching) {
                    $this->addTeaching($actor, $member, $teaching);
                }
            } finally {
                $this->creating = null;
            }

            return $member->fresh();
        });
    }

    public function addCargo(User $actor, StaffMember $member, array $cargo): StaffAssignment
    {
        $role = DB::table('roles')->where('company_id', TenantContext::companyId())->where('id', $cargo['role_id'])->first();
        if (! $role || in_array($role->name, self::NOT_A_CARGO, true)) {
            throw ValidationException::withMessages(['role_id' => 'Ese rol no es un cargo: la docencia se carga como asignación docente.']);
        }
        $level = isset($cargo['school_level_id']) ? (int) $cargo['school_level_id'] : null;
        $division = isset($cargo['division_id']) ? (int) $cargo['division_id'] : null;
        $this->authorizeLevel($actor, $level);
        $this->authorizeMember($actor, $member);

        return DB::transaction(function () use ($actor, $member, $role, $level, $division, $cargo) {
            $assignment = $member->assignments()->create([
                'company_id' => TenantContext::companyId(), 'school_level_id' => $level,
                'position_title' => $role->label, 'function_category' => self::FUNCTION_BY_ROLE[$role->name] ?? 'other',
                'start_date' => $cargo['starts_on'] ?? now()->toDateString(), 'end_date' => $cargo['ends_on'] ?? null,
                'active' => true, 'weekly_hours' => $cargo['weekly_hours'] ?? null,
                'role_id' => $role->id, 'division_id' => $division,
            ]);
            if ($member->user_id) {
                $this->grantCargo($actor, $member, $assignment);
            } else {
                // Sin cuenta igual se valida alcance y jerarquia del cargo.
                $this->roles->authorizeScope($actor, $role, $level, $division, null);
            }
            if (! $member->staff_category || $member->staff_category === 'other') {
                $member->update(['staff_category' => $assignment->function_category]);
            }

            return $assignment->fresh();
        });
    }

    public function endCargo(User $actor, StaffMember $member, StaffAssignment $assignment): void
    {
        abort_unless((int) $assignment->staff_member_id === (int) $member->id && $assignment->role_id, 404);
        $this->authorizeLevel($actor, $assignment->school_level_id === null ? null : (int) $assignment->school_level_id);
        $this->authorizeMember($actor, $member);
        DB::transaction(function () use ($actor, $assignment) {
            if ($assignment->role_user_id) {
                $this->roles->revoke($actor, (int) $assignment->role_user_id);
            }
            $assignment->update(['active' => false, 'end_date' => now()->toDateString()]);
        });
    }

    public function addTeaching(User $actor, StaffMember $member, array $teaching): TeachingAssignment
    {
        $section = CourseSection::where('company_id', TenantContext::companyId())->where('status', 'active')
            ->find($teaching['course_section_id']);
        if (! $section) {
            throw ValidationException::withMessages(['course_section_id' => 'La materia del curso no existe o está archivada.']);
        }
        $this->authorizeLevel($actor, (int) $section->school_level_id);
        $this->authorizeMember($actor, $member);
        if (! empty($teaching['ends_on']) && ! empty($teaching['starts_on']) && $teaching['ends_on'] < $teaching['starts_on']) {
            throw ValidationException::withMessages(['ends_on' => 'El fin debe ser igual o posterior al inicio.']);
        }

        return DB::transaction(function () use ($actor, $member, $section, $teaching) {
            abort_if(TeachingAssignment::active()->where('staff_member_id', $member->id)
                ->where('course_section_id', $section->id)->lockForUpdate()->exists(), 409, 'Ya tiene asignada esa materia en ese curso.');
            $assignment = TeachingAssignment::create([
                'company_id' => TenantContext::companyId(), 'school_level_id' => $section->school_level_id,
                'staff_member_id' => $member->id, 'course_section_id' => $section->id,
                'weekly_hours' => $teaching['weekly_hours'] ?? null,
                'starts_on' => $teaching['starts_on'] ?? now()->toDateString(), 'ends_on' => $teaching['ends_on'] ?? null,
                'created_by' => $actor->id,
            ]);
            if ($member->user_id) {
                $this->grantTeaching($actor, $member, $assignment);
            }

            return $assignment->fresh();
        });
    }

    /** Revocar la asignacion revoca el rol docente de esa seccion en el acto. */
    public function revokeTeaching(User $actor, StaffMember $member, TeachingAssignment $assignment): void
    {
        abort_unless((int) $assignment->staff_member_id === (int) $member->id, 404);
        $this->authorizeLevel($actor, (int) $assignment->school_level_id);
        $this->authorizeMember($actor, $member);
        DB::transaction(function () use ($actor, $assignment) {
            $locked = TeachingAssignment::whereKey($assignment->id)->lockForUpdate()->first();
            if ($locked->revoked_at) {
                return;
            }
            if ($locked->role_user_id) {
                $this->roles->revoke($actor, (int) $locked->role_user_id);
            }
            $locked->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);
        });
    }

    /**
     * Crea o vincula la cuenta y otorga los roles de cargos y docencia vigentes.
     * Cambia la cuenta en todos los niveles: la direccion solo puede si la
     * persona trabaja unicamente en su nivel.
     */
    public function enableAccess(User $actor, StaffMember $member, array $access): User
    {
        $this->authorizeWholeMember($actor, $member);

        return DB::transaction(function () use ($actor, $member, $access) {
            $this->roles->lockInstitution(TenantContext::companyId());
            $member = StaffMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if ($member->user_id) {
                $user = User::findOrFail($member->user_id);
                if (! $user->is_active) {
                    $this->roles->authorizeTarget($actor, $user);
                    $user->forceFill(['is_active' => true]);
                    if (! empty($access['password'])) {
                        $user->password = $access['password'];
                        $user->forceFill(['must_change_password' => true]);
                    }
                    $user->save();
                    $this->audit('staff.access_enabled', $actor, $member, null, ['user_id' => $user->id]);
                }
            } else {
                $user = $this->attachAccess($actor, $member, $access);
            }
            foreach ($member->assignments()->where('active', true)->whereNotNull('role_id')->whereNull('role_user_id')->get() as $cargo) {
                $this->grantCargo($actor, $member, $cargo);
            }
            foreach ($member->teachingAssignments()->active()->whereNull('role_user_id')->get() as $teaching) {
                $this->grantTeaching($actor, $member, $teaching);
            }

            return $user->fresh();
        });
    }

    /** Desactiva la cuenta y cierra sus sesiones; conserva ficha, cargos e historial. */
    public function disableAccess(User $actor, StaffMember $member): void
    {
        abort_unless($member->user_id, 404);
        $this->authorizeWholeMember($actor, $member);
        DB::transaction(function () use ($actor, $member) {
            $this->roles->lockInstitution(TenantContext::companyId());
            $target = User::whereKey($member->user_id)->lockForUpdate()->firstOrFail();
            $this->roles->authorizeTarget($actor, $target);
            if ($this->access->isTotalAdmin($target)) {
                $this->roles->ensureAdministratorRemains(TenantContext::companyId(), null, [$target->id]);
            }
            $target->forceFill(['is_active' => false, 'remember_token' => null])->save();
            $target->tokens()->delete();
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }
            $this->access->forget($target);
            $this->audit('staff.access_disabled', $actor, $member, null, ['user_id' => $target->id]);
        });
    }

    /** Lo que muestra la ficha: datos, cargos, docencia y acceso. */
    public function present(StaffMember $member): array
    {
        $member->load(['assignments.schoolLevel:id,name', 'assignments.division:id,name',
            'teachingAssignments.courseSection.subject:id,name', 'teachingAssignments.courseSection.division:id,name,grade_level_id',
            'teachingAssignments.courseSection.division.gradeLevel:id,name']);
        $roles = DB::table('roles')->where('company_id', TenantContext::companyId())->pluck('label', 'id');
        $user = $member->user_id ? User::withTrashed()->find($member->user_id) : null;

        return [
            'member' => collect($member->toArray())->except(['assignments', 'teaching_assignments'])->all(),
            'cargos' => $member->assignments->map(fn ($a) => [
                'id' => $a->id, 'role_id' => $a->role_id, 'role' => $a->role_id ? ($roles[$a->role_id] ?? null) : null,
                'position_title' => $a->position_title, 'school_level' => optional($a->schoolLevel)->name,
                'school_level_id' => $a->school_level_id, 'division' => optional($a->division)->name,
                'start_date' => optional($a->start_date)->toDateString(), 'end_date' => optional($a->end_date)->toDateString(),
                'active' => $a->active, 'granted' => (bool) $a->role_user_id, 'weekly_hours' => $a->weekly_hours,
            ])->values(),
            'teaching' => $member->teachingAssignments->sortByDesc('id')->map(fn ($t) => [
                'id' => $t->id, 'course_section_id' => $t->course_section_id,
                'subject' => optional(optional($t->courseSection)->subject)->name,
                'course' => trim(optional(optional(optional($t->courseSection)->division)->gradeLevel)->name.' '.optional(optional($t->courseSection)->division)->name),
                'school_level_id' => $t->school_level_id, 'weekly_hours' => $t->weekly_hours,
                'starts_on' => optional($t->starts_on)->toDateString(), 'ends_on' => optional($t->ends_on)->toDateString(),
                'revoked_at' => optional($t->revoked_at)->toDateTimeString(), 'granted' => (bool) $t->role_user_id,
            ])->values(),
            'access' => $user ? ['user_id' => $user->id, 'email' => $user->email, 'is_active' => (bool) $user->is_active,
                'must_change_password' => (bool) $user->must_change_password] : null,
        ];
    }

    /** Alta permitida en el nivel: total admin, o direccion de ese nivel parada en el. */
    public function authorizeLevel(User $actor, ?int $level): void
    {
        if ($this->access->isTotalAdmin($actor)) {
            return;
        }
        abort_unless($level !== null && $level === TenantContext::schoolLevelId()
            && $this->access->allows($actor, Permission::HR_STAFF_ONBOARD, $level), 403);
    }

    /** La direccion opera sobre personas con cargo o docencia vigente en su nivel. */
    public function authorizeMember(User $actor, StaffMember $member): void
    {
        abort_unless((int) $member->company_id === TenantContext::companyId(), 404);
        if ($this->access->isTotalAdmin($actor)) {
            return;
        }
        if ($this->creating === (int) $member->id) {
            return;
        }
        abort_unless(in_array(TenantContext::schoolLevelId(), $this->memberLevels($member), true), 403);
    }

    public function authorizeWholeMember(User $actor, StaffMember $member): void
    {
        abort_unless((int) $member->company_id === TenantContext::companyId(), 404);
        if ($this->access->isTotalAdmin($actor)) {
            return;
        }
        $levels = $this->memberLevels($member);
        abort_unless($levels !== [] && array_diff($levels, [TenantContext::schoolLevelId()]) === [], 403);
        $this->authorizeLevel($actor, TenantContext::schoolLevelId());
    }

    /** Niveles de los cargos y la docencia vigentes; un cargo institucional cuenta como nulo. */
    public function memberLevels(StaffMember $member): array
    {
        $cargos = $member->assignments()->where('active', true)->pluck('school_level_id');
        $teaching = $member->teachingAssignments()->active()->pluck('school_level_id');

        return $cargos->merge($teaching)->map(fn ($id) => $id === null ? null : (int) $id)->unique()->values()->all();
    }

    protected function attachAccess(User $actor, StaffMember $member, array $access): User
    {
        if (! empty($access['user_id'])) {
            $user = User::where('company_id', TenantContext::companyId())->where('role', '<>', 'customer')
                ->lockForUpdate()->find($access['user_id']);
            if (! $user || StaffMember::where('user_id', $user->id)->where('id', '<>', $member->id)->exists()) {
                throw ValidationException::withMessages(['access.user_id' => 'La cuenta no existe o ya pertenece a otra ficha.']);
            }
            $this->roles->authorizeTarget($actor, $user);
            $member->update(['user_id' => $user->id]);
            $this->audit('staff.access_linked', $actor, $member, null, ['user_id' => $user->id]);

            return $user;
        }

        $email = strtolower(trim($access['email'] ?? $member->email ?? ''));
        if ($email === '' || User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['access.email' => 'Indicá un correo que no esté en uso por otra cuenta.']);
        }
        $user = new User(['name' => trim($member->first_name.' '.$member->last_name), 'email' => $email,
            'phone' => $member->phone, 'password' => $access['password'], 'role' => 'staff',
            'company_id' => TenantContext::companyId(), 'creator_id' => $actor->id]);
        abort_unless($this->access->canManageUser($actor, $user), 403);
        $user->forceFill(['is_active' => true, 'must_change_password' => true])->save();
        $user->setSettings(['language' => config('app.locale')]);
        $member->update(['user_id' => $user->id, 'email' => $member->email ?: $email]);
        $this->audit('staff.access_created', $actor, $member, null, ['user_id' => $user->id]);

        return $user;
    }

    protected function grantCargo(User $actor, StaffMember $member, StaffAssignment $cargo): void
    {
        $id = $this->roles->grant($actor, User::findOrFail($member->user_id), ['role_id' => $cargo->role_id,
            'school_level_id' => $cargo->school_level_id, 'division_id' => $cargo->division_id,
            'starts_at' => optional($cargo->start_date)->toDateString(), 'ends_at' => optional($cargo->end_date)->toDateString()]);
        $cargo->update(['role_user_id' => $id]);
    }

    protected function grantTeaching(User $actor, StaffMember $member, TeachingAssignment $teaching): void
    {
        $role = DB::table('roles')->where('company_id', TenantContext::companyId())->where('name', RoleName::TEACHER)->value('id');
        abort_unless($role, 409, 'Falta el rol docente en el catálogo.');
        $id = $this->roles->grant($actor, User::findOrFail($member->user_id), ['role_id' => $role,
            'school_level_id' => $teaching->school_level_id, 'course_section_id' => $teaching->course_section_id,
            'starts_at' => optional($teaching->starts_on)->toDateString(), 'ends_at' => optional($teaching->ends_on)->toDateString()]);
        $teaching->update(['role_user_id' => $id]);
    }

    protected function sectionLevel(int $section): ?int
    {
        $level = DB::table('course_sections')->where('id', $section)->where('company_id', TenantContext::companyId())->value('school_level_id');
        if ($level === null) {
            throw ValidationException::withMessages(['teaching' => 'La materia del curso no existe.']);
        }

        return (int) $level;
    }

    protected function audit(string $action, User $actor, StaffMember $member, ?int $level, array $values): void
    {
        AuditLog::create(['company_id' => TenantContext::companyId(), 'school_level_id' => $level ?? TenantContext::schoolLevelId(),
            'user_id' => $actor->id, 'action' => $action, 'auditable_type' => StaffMember::class, 'auditable_id' => $member->id,
            'new_values' => $values, 'severity' => AuditLog::SEVERITY_HIGH, 'created_at' => now()]);
    }

    protected function normalizeDocument(?string $document): ?string
    {
        if ($document === null || trim($document) === '') {
            return null;
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $document));
    }
}
