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

    /**
     * Espeja Permission::catalog() en la tabla.
     *
     * Los permisos que ya no existen en el codigo NO se borran automaticamente:
     * borrarlos arrastraria en cascada las asignaciones de rol, y si alguien
     * renombro una constante por error se perderia la configuracion. Se
     * reportan para revision manual.
     */
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

            // Se reemplaza el conjunto completo de permisos del rol: la
            // definicion del codigo manda. Si alguien agrego un permiso a mano
            // en la base, se pierde a proposito — para eso estan los roles
            // personalizados, que no son is_system.
            DB::table('permission_role')->where('role_id', $rolId)->delete();

            $filas = [];

            foreach ($definicion['permissions'] as $permiso) {
                if (! isset($permisosPorNombre[$permiso])) {
                    continue;
                }

                // Cinturon y tirantes: aunque la definicion del rol se
                // equivoque, ningun rol que no sea administracion total recibe
                // un permiso exclusivo.
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
     * Solo asigna un rol nuevo cuando la persona todavia no tiene ninguna
     * entrada RBAC. De ese modo una ejecucion posterior del seeder nunca pisa
     * decisiones hechas desde la administracion de la escuela.
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
            if (DB::table('role_user')->where('user_id', $user->id)->exists()) {
                continue;
            }

            $roleName = $user->role === 'super admin'
                ? RoleName::TOTAL_ADMIN
                : RoleName::GENERAL_DIRECTOR;

            if (! isset($roleIds[$roleName])) {
                continue;
            }

            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $roleIds[$roleName],
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
