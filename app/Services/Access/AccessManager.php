<?php

namespace Crater\Services\Access;

use Crater\Enums\Permission;
use Crater\Enums\RoleName;
use Crater\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolutor central de permisos.
 *
 * TODA decision de autorizacion del sistema pasa por aca. Los Policies, los
 * middleware y los Gates delegan en esta clase; ninguno reimplementa la logica.
 * Si hay un solo lugar donde se decide, hay un solo lugar donde auditar y un
 * solo lugar donde puede haber un agujero.
 *
 * ORDEN DE EVALUACION (importa, y es deliberado):
 *
 *   1. Usuario inactivo o suspendido            -> NIEGA
 *   2. Permiso exclusivo de administracion total
 *      y el usuario no lo es                    -> NIEGA (corte duro)
 *   3. Es administracion total                  -> PERMITE
 *   4. No tiene el permiso via ningun rol vigente-> NIEGA
 *   5. El rol no alcanza al nivel pedido         -> NIEGA
 *   6. El rol es de alcance fino y el recurso no
 *      esta en su alcance                        -> NIEGA
 *   7.                                            PERMITE
 *
 * El paso 2 va ANTES del 3 a proposito: es un corte que se aplica aunque la
 * base de datos diga otra cosa. Si alguien manipula `permission_role` para
 * darle `system.secrets.manage` al rol docente, el resolutor igual niega,
 * porque la lista vive en el codigo y no en la base.
 *
 * DEFAULT DENY: cualquier camino que no llegue explicitamente a `true` niega.
 */
class AccessManager
{
    /**
     * @param  string  $permission  constante de Permission
     * @param  int|null  $schoolLevelId  nivel en el que se pide el permiso
     * @param  array{type: string, id: int}|null  $scope  recurso concreto
     */
    public function allows(
        User $user,
        string $permission,
        ?int $schoolLevelId = null,
        ?array $scope = null
    ): bool {
        // 1. Cuenta habilitada.
        if (! $this->isActive($user)) {
            return false;
        }

        $isTotalAdmin = $this->isTotalAdmin($user);

        // 2. Corte duro. Se evalua antes de cualquier atajo.
        if (Permission::isTotalAdminOnly($permission) && ! $isTotalAdmin) {
            return false;
        }

        // 3. La administracion total pasa el resto.
        if ($isTotalAdmin) {
            return true;
        }

        // 4. Permisos efectivos en el nivel pedido.
        $granted = $this->effectivePermissions($user, $schoolLevelId);

        if (! in_array($permission, $granted, true)) {
            return false;
        }

        // 5 y 6. Alcance fino.
        if ($scope !== null && ! $this->withinScope($user, $permission, $scope, $schoolLevelId)) {
            return false;
        }

        return true;
    }

    public function denies(User $user, string $permission, ?int $schoolLevelId = null, ?array $scope = null): bool
    {
        return ! $this->allows($user, $permission, $schoolLevelId, $scope);
    }

    /**
     * Permisos efectivos del usuario en un nivel, unificando todos sus roles
     * vigentes. Roles vencidos o todavia no vigentes no cuentan.
     */
    public function effectivePermissions(User $user, ?int $schoolLevelId = null): array
    {
        if (! $this->isActive($user)) {
            return [];
        }
        if ($this->isTotalAdmin($user)) {
            return Permission::all();
        }

        // Autoridad vigente en cada consulta: una revocacion o vencimiento no
        // puede seguir otorgando acceso por una cache de cinco minutos.
        $rows = $this->rolesQuery($user, $schoolLevelId)
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->pluck('permissions.name')->unique()->values()->all();

        return array_values(array_diff($rows, Permission::totalAdminOnly()));
    }

    /**
     * Verifica el alcance fino: que el recurso concreto este entre los que el
     * usuario tiene asignados.
     *
     * Los permisos `*_view_all` estan pensados para roles de alcance de nivel
     * (direccion, secretaria); si un rol de alcance division los tiene, el
     * alcance sigue mandando.
     *
     * @param  array{type: string, id: int}  $scope
     */
    public function withinScope(User $user, string $permission, array $scope, ?int $schoolLevelId = null): bool
    {
        $roles = $this->rolesFor($user, $schoolLevelId);

        // Si alguno de sus roles vigentes es de alcance de nivel o global, no
        // hace falta chequear recurso por recurso.
        foreach ($roles as $role) {
            if (in_array($role->scope_type, ['global', 'level'], true)) {
                return true;
            }
        }

        if (empty($roles)) {
            return false;
        }

        // Alcance explicito. Sin filas asignadas, no alcanza a nada: el default
        // es negar.
        $has = DB::table('user_scopes')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('scope_type', $scope['type'])
            ->where('scope_id', $scope['id'])
            ->exists();

        if ($has) {
            return true;
        }

        // Un docente pedido sobre una division alcanza si tiene alguna seccion
        // de esa division. Se resuelve la relacion en vez de exigir que el
        // alcance este duplicado.
        if ($scope['type'] === 'division') {
            return DB::table('user_scopes')
                ->join('course_sections', 'course_sections.id', '=', 'user_scopes.scope_id')
                ->where('user_scopes.user_id', $user->id)
                ->where('user_scopes.company_id', $user->company_id)
                ->where('course_sections.company_id', $user->company_id)
                ->where('user_scopes.scope_type', 'course_section')
                ->where('course_sections.division_id', $scope['id'])
                ->exists();
        }

        return false;
    }

    /**
     * El usuario tiene algun rol vigente de alcance global o de nivel.
     *
     * Es la pregunta "ve todo el nivel o solo lo suyo", y la usan los listados
     * para decidir si filtran por alcance. Vive aca y no en los controladores
     * para que la decision se tome en un solo lugar.
     */
    public function hasLevelWideScope(User $user, ?int $schoolLevelId = null): bool
    {
        if ($this->isTotalAdmin($user)) {
            return true;
        }

        foreach ($this->rolesFor($user, $schoolLevelId) as $role) {
            if (in_array($role->scope_type, ['global', 'level'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ids de division que alcanza el usuario, para filtrar listados.
     *
     * Incluye las asignadas directamente y las que alcanza por tener alguna
     * seccion de materia en ellas. Devuelve array vacio si no alcanza ninguna,
     * y el llamador debe interpretarlo como "ninguna", no como "todas".
     */
    public function scopedDivisionIds(User $user): array
    {
        // El filtro por empresa va en las dos consultas: un alcance de otra
        // institucion no debe alcanzar nada aca, aunque la fila exista.
        $directas = DB::table('user_scopes')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('scope_type', 'division')
            ->pluck('scope_id');

        $porSeccion = DB::table('user_scopes')
            ->join('course_sections', 'course_sections.id', '=', 'user_scopes.scope_id')
            ->where('user_scopes.user_id', $user->id)
            ->where('user_scopes.company_id', $user->company_id)
            ->where('course_sections.company_id', $user->company_id)
            ->where('user_scopes.scope_type', 'course_section')
            ->pluck('course_sections.division_id');

        return $directas
            ->merge($porSeccion)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * La persona es administracion total.
     *
     * Se consulta contra la tabla de roles, no contra `users.role`. La columna
     * heredada de Crater se mantiene por compatibilidad durante la migracion,
     * pero NO es fuente de verdad para autorizar.
     */
    public function isTotalAdmin(User $user): bool
    {
        return $this->isActive($user) && $this->currentAssignments($user)
            ->where('roles.name', RoleName::TOTAL_ADMIN)
            ->where('roles.scope_type', 'global')
            ->whereNull('role_user.school_level_id')->exists();
    }

    public function hasActiveRole(User $user): bool
    {
        return $this->isActive($user) && $this->currentAssignments($user)->exists();
    }

    public function hasInstitutionWideScope(User $user): bool
    {
        return $this->isActive($user) && $this->currentAssignments($user)
            ->where('roles.scope_type', 'global')->whereNull('role_user.school_level_id')->exists();
    }

    public function levelIds(User $user): array
    {
        if (! $this->isActive($user)) {
            return [];
        }
        if ($this->hasInstitutionWideScope($user)) {
            return DB::table('school_levels')->where('company_id', $user->company_id)
                ->where('enabled', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->currentAssignments($user)
            ->join('school_levels', 'school_levels.id', '=', 'role_user.school_level_id')
            ->where('school_levels.company_id', $user->company_id)->where('school_levels.enabled', true)
            ->pluck('school_levels.id')->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    protected function currentAssignments(User $user)
    {
        $today = now()->toDateString();

        return DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('role_user.company_id', $user->company_id)
            ->where('roles.company_id', $user->company_id)
            ->where(fn ($q) => $q->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today));
    }

    protected function rolesQuery(User $user, ?int $schoolLevelId = null)
    {
        return $this->currentAssignments($user)->where(function ($q) use ($schoolLevelId) {
            $q->where(fn ($global) => $global->whereNull('role_user.school_level_id')->where('roles.scope_type', 'global'));
            if ($schoolLevelId !== null) {
                $q->orWhere('role_user.school_level_id', $schoolLevelId);
            }
        });
    }

    /**
     * Nivel jerarquico mas alto que ostenta el usuario (numero mas bajo).
     * Devuelve PHP_INT_MAX si no tiene ningun rol.
     */
    public function hierarchyLevel(User $user): int
    {
        $min = $this->currentAssignments($user)->min('roles.hierarchy_level');

        return $min === null ? PHP_INT_MAX : (int) $min;
    }

    /**
     * Regla anti escalada de privilegios.
     *
     * Nadie puede asignar, editar ni suspender a alguien de jerarquia igual o
     * superior a la propia, ni otorgar un rol mas poderoso que el que tiene.
     * Sin esto, cualquiera con `system.role.assign` podria promoverse a si
     * mismo a administracion total.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if (! $this->isActive($actor) || ($target->exists && (int) $actor->id === (int) $target->id)) {
            return false;
        }
        if ($this->isTotalAdmin($actor)) {
            return true;
        }
        if ((int) $actor->company_id !== (int) $target->company_id || $this->isTotalAdmin($target)) {
            return false;
        }
        if ($this->hierarchyLevel($actor) >= $this->hierarchyLevel($target)) {
            return false;
        }
        if (! $target->exists) {
            return true; // alta staff sin asignaciones; nunca otorga un rol
        }
        if ($this->hasInstitutionWideScope($actor)) {
            return true;
        }
        $targetAssignments = $this->currentAssignments($target)->pluck('role_user.school_level_id');

        return $targetAssignments->isNotEmpty()
            && ! $targetAssignments->contains(null)
            && $targetAssignments->diff($this->levelIds($actor))->isEmpty();
    }

    /**
     * Puede otorgar este rol concreto.
     *
     * `$schoolLevelId` es el nivel en el que se otorga, y hay que pasarlo: los
     * roles de alcance de nivel (direccion, secretaria) tienen sus permisos
     * asignados a un nivel concreto, asi que consultar ROLE_ASSIGN sin nivel
     * solo mira los roles globales y da falso para todos ellos.
     */
    public function canGrantRole(
        User $actor,
        string $roleName,
        int $roleHierarchy,
        ?int $schoolLevelId = null
    ): bool {
        // Los roles protegidos solo los otorga la administracion total, tenga
        // quien tenga el permiso de asignar.
        if (in_array($roleName, RoleName::protectedRoles(), true)) {
            return $this->isTotalAdmin($actor);
        }

        if (! $this->allows($actor, Permission::ROLE_ASSIGN, $schoolLevelId)) {
            return false;
        }

        // No se puede otorgar un rol de jerarquia igual o superior a la propia.
        // El "igual" tambien importa: si no, dos personas del mismo nivel
        // podrian ampliarse los permisos mutuamente.
        return $this->hierarchyLevel($actor) < $roleHierarchy;
    }

    /**
     * Roles vigentes del usuario, opcionalmente filtrados por nivel.
     */
    protected function rolesFor(User $user, ?int $schoolLevelId = null): array
    {
        if (! $this->isActive($user)) {
            return [];
        }

        return $this->rolesQuery($user, $schoolLevelId)
            ->select('roles.name', 'roles.scope_type', 'roles.hierarchy_level')->get()->all();
    }

    protected function isActive(User $user): bool
    {
        // La columna se agrega en la migracion de endurecimiento. Si todavia no
        // existe, se asume activo para no romper durante la transicion.
        if (! array_key_exists('is_active', $user->getAttributes())) {
            return true;
        }

        return (bool) $user->is_active;
    }

    /**
     * Limpia entradas historicas por compatibilidad. R1 consulta la autoridad
     * vigente sin cache para que revocar/desactivar surta efecto inmediato.
     */
    public function forget(User $user): void
    {
        Cache::forget("acl:u{$user->id}:total_admin");
        Cache::forget("acl:u{$user->id}:lglobal");

        foreach (DB::table('school_levels')->pluck('id') as $levelId) {
            Cache::forget("acl:u{$user->id}:l{$levelId}");
        }
    }
}
