<?php

namespace Crater\Services\Access;

use Crater\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Capa de compatibilidad para la migracion desde el esquema legacy de Crater.
 *
 * Mantiene como administracion total al usuario historico cuyo campo
 * `users.role` es exactamente `super admin`, pero hace que esa excepcion viva
 * dentro del resolutor central en lugar de dispersarla entre middleware y Gates.
 *
 * Una vez migradas todas las cuentas a role_user, esta clase puede retirarse y
 * volver a enlazar AccessManager directamente sin tocar controladores/policies.
 */
class LegacyCompatibleAccessManager extends AccessManager
{
    public function isTotalAdmin(User $user): bool
    {
        if ($user->role === 'super admin') {
            return true;
        }

        return parent::isTotalAdmin($user);
    }

    public function hierarchyLevel(User $user): int
    {
        // Un superadmin heredado debe conservar la cima de la jerarquia aunque
        // no tenga todavia una fila equivalente en role_user.
        if ($this->isTotalAdmin($user)) {
            return 0;
        }

        return parent::hierarchyLevel($user);
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        // La administracion total conserva alcance transversal a instituciones
        // y niveles. Esta es la autoridad maxima del sistema.
        if ($this->isTotalAdmin($actor)) {
            return true;
        }

        // Ningun rol inferior puede atravesar el limite de institucion.
        if ((int) $actor->company_id !== (int) $target->company_id) {
            return false;
        }

        // Tampoco puede administrar a un total-admin, incluso si ese total-admin
        // proviene aun del campo legacy y carece de role_user.
        if ($this->isTotalAdmin($target)) {
            return false;
        }

        return $this->hierarchyLevel($actor) < $this->hierarchyLevel($target);
    }

    /**
     * El usuario ve TODA la institucion, atravesando los niveles escolares.
     *
     * Es una pregunta distinta de hasLevelWideScope(): un director de nivel
     * puede ver todo SU nivel sin poder consultar el padron de los demas.
     * Solo la administracion total y los roles globales sin nivel asignado
     * atraviesan la institucion completa.
     */
    public function hasInstitutionWideScope(User $user): bool
    {
        if ($this->isTotalAdmin($user)) {
            return true;
        }

        $today = now()->toDateString();

        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('role_user.company_id', $user->company_id)
            ->where('roles.company_id', $user->company_id)
            ->where('roles.scope_type', 'global')
            ->whereNull('role_user.school_level_id')
            ->where(function ($q) use ($today) {
                $q->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today);
            })
            ->exists();
    }
}
