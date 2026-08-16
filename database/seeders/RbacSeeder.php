<?php

namespace Database\Seeders;

use Crater\Enums\Permission as PermissionEnum;
use Crater\Enums\RoleName;
use Crater\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el catalogo de permisos y los roles del sistema.
 *
 * IDEMPOTENTE: se puede correr todas las veces que haga falta. Usa
 * updateOrInsert por clave natural, asi que no duplica ni pierde asignaciones
 * hechas a mano.
 *
 * Los permisos son globales (el catalogo lo define el codigo). Los roles son
 * por empresa, porque cada institucion puede tener los suyos propios ademas de
 * los del sistema.
 *
 *   php artisan db:seed --class=Database\\Seeders\\RbacSeeder
 */
class RbacSeeder extends Seeder
{
    public function run()
    {
        $this->sembrarPermisos();

        foreach (Company::all() as $company) {
            $this->sembrarRoles($company->id);
            $this->migrarRolesHeredados($company->id);
        }
    }

    protected function sembrarPermisos()
    {
        $ahora = now();
        $catalogo = PermissionEnum::catalog();

        foreach ($catalogo as $nombre => $meta) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $nombre],
                [
                    'group' => $meta['group'],
                    'label' => $meta['label'],
                    'is_restricted' => PermissionEnum::isTotalAdminOnly($nombre),
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ]
            );
        }

        $huerfanos = DB::table('permissions')
            ->whereNotIn('name', array_keys($catalogo))
            ->pluck('name');

        if ($huerfanos->isNotEmpty()) {
            $this->command->warn(
                'Hay permisos en la base que ya no existen en el codigo. '.
                'No se borran automaticamente porque arrastrarian las asignaciones de rol. '.
                'Revisar: '.$huerfanos->implode(', ')
            );
        }

        $this->command->info('Permisos sembrados: '.count($catalogo));
    }

    protected function sembrarRoles($companyId)
    {
        $ahora = now();
        $permisosPorNombre = DB::table('permissions')->pluck('id', 'name');

        foreach (RoleName::definitions() as $nombre => $definicion) {
            DB::table('roles')->updateOrInsert(
                ['company_id' => $companyId, 'name' => $nombre],
                [
                    'label' => $definicion['label'],
                    'description' => $definicion['description'] ?? null,
                    'hierarchy_level' => $definicion['hierarchy_level'],
                    'scope_type' => $definicion['scope_type'],
                    'is_system' => true,
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ]
            );

            $rolId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('name', $nombre)
                ->value('id');

            DB::table('permission_role')->where('role_id', $rolId)->delete();

            $filas = [];

            foreach ($definicion['permissions'] as $permiso) {
                if (! isset($permisosPorNombre[$permiso])) {
                    continue;
                }

                if ($nombre !== RoleName::TOTAL_ADMIN && PermissionEnum::isTotalAdminOnly($permiso)) {
                    $this->command->warn(
                        "El rol {$nombre} declara el permiso exclusivo {$permiso}. Se omite."
                    );

                    continue;
                }

                $filas[] = [
                    'role_id' => $rolId,
                    'permission_id' => $permisosPorNombre[$permiso],
                ];
            }

            if ($filas) {
                DB::table('permission_role')->insert($filas);
            }
        }

        $this->command->info(
            'Roles sembrados para la empresa '.$companyId.': '.count(RoleName::definitions())
        );
    }

    /**
     * Puente de compatibilidad para instalaciones existentes.
     *
     * Un `super admin` heredado SIEMPRE debe conservar administracion total al
     * migrar al RBAC nuevo, aunque ya tenga alguna otra fila en `role_user`.
     * Antes se omitia a cualquier usuario que tuviera un rol previo y eso
     * podia dejar al administrador historico sin acceso a los modulos nuevos.
     *
     * Para los `admin` heredados seguimos siendo conservadores: solo se les
     * asigna Direccion general cuando aun no tienen ninguna asignacion RBAC,
     * para no pisar decisiones tomadas manualmente.
     */
    protected function migrarRolesHeredados($companyId)
    {
        $roleIds = DB::table('roles')
            ->where('company_id', $companyId)
            ->whereIn('name', [RoleName::TOTAL_ADMIN, RoleName::GENERAL_DIRECTOR])
            ->pluck('id', 'name');

        $users = DB::table('users')
            ->where('company_id', $companyId)
            ->whereIn('role', ['super admin', 'admin'])
            ->get(['id', 'role']);

        foreach ($users as $user) {
            if ($user->role === 'super admin') {
                if (! isset($roleIds[RoleName::TOTAL_ADMIN])) {
                    continue;
                }

                $alreadyTotalAdmin = DB::table('role_user')
                    ->where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->where('role_id', $roleIds[RoleName::TOTAL_ADMIN])
                    ->whereNull('school_level_id')
                    ->exists();

                if ($alreadyTotalAdmin) {
                    continue;
                }

                DB::table('role_user')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleIds[RoleName::TOTAL_ADMIN],
                    'company_id' => $companyId,
                    'school_level_id' => null,
                    'starts_on' => null,
                    'ends_on' => null,
                    'granted_by' => null,
                    'granted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if (DB::table('role_user')->where('user_id', $user->id)->exists()) {
                continue;
            }

            if (! isset($roleIds[RoleName::GENERAL_DIRECTOR])) {
                continue;
            }

            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $roleIds[RoleName::GENERAL_DIRECTOR],
                'company_id' => $companyId,
                'school_level_id' => null,
                'starts_on' => null,
                'ends_on' => null,
                'granted_by' => null,
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
