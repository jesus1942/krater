<?php

namespace Crater\Services\Access;

use Crater\Models\User;

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
}
