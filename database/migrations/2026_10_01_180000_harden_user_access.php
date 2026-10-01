<?php

use Crater\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class HardenUserAccess extends Migration
{
    public function up()
    {
        $userCount = DB::table('users')->count();
        $legacyAdmins = DB::table('users')->where('role', 'super admin')->count();
        // MySQL confirma el DDL fuera de la transaccion: permitir reintentar si
        // el proceso se interrumpio despues de agregar la columna.
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index();
            });
        }

        // Puente de una sola ejecucion. El seeder de cada deploy no recrea
        // asignaciones revocadas ni promueve cuentas legacy `admin`.
        DB::transaction(function () {
            foreach (DB::table('users')->where('role', 'super admin')->whereNotNull('company_id')->get() as $user) {
                $roleId = DB::table('roles')->where('company_id', $user->company_id)
                    ->where('name', RoleName::TOTAL_ADMIN)->value('id');
                if (! $roleId) {
                    $definition = RoleName::definitions()[RoleName::TOTAL_ADMIN];
                    $roleId = DB::table('roles')->insertGetId([
                        'company_id' => $user->company_id, 'name' => RoleName::TOTAL_ADMIN,
                        'label' => $definition['label'], 'hierarchy_level' => 0,
                        'scope_type' => 'global', 'is_system' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                // Una revocacion historica existente se conserva.
                if (! DB::table('role_user')->where('user_id', $user->id)->where('company_id', $user->company_id)
                    ->where('role_id', $roleId)->whereNull('school_level_id')->exists()) {
                    DB::table('role_user')->insert([
                        'user_id' => $user->id, 'role_id' => $roleId, 'company_id' => $user->company_id,
                        'school_level_id' => null, 'starts_on' => null, 'ends_on' => null,
                        'granted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });

        $today = now()->toDateString();
        $totalAdmins = DB::table('users')->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('users.is_active', true)->where('roles.name', RoleName::TOTAL_ADMIN)->where('roles.scope_type', 'global')
            ->whereColumn('users.company_id', 'role_user.company_id')->whereColumn('roles.company_id', 'users.company_id')
            ->whereNull('role_user.school_level_id')
            ->where(fn ($q) => $q->whereNull('role_user.starts_on')->orWhere('role_user.starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today))
            ->distinct()->count('users.id');
        Log::info('R1: migracion de acceso verificada', ['users_before' => $userCount,
            'users_after' => DB::table('users')->count(), 'legacy_super_admins' => $legacyAdmins,
            'active_total_admins' => $totalAdmins]);
        if (app()->environment('production') && $legacyAdmins > 0 && $totalAdmins === 0) {
            throw new \RuntimeException('R1: falta un total_admin vigente; se bloquea el deploy para conservar el acceso anterior.');
        }
    }

    public function down()
    {
        // La asignacion migrada se preserva: un rollback no quita al administrador.
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
}
