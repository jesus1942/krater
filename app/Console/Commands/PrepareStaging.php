<?php

namespace Crater\Console\Commands;

use Crater\Enums\RoleName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Datos ficticios: nunca copia ni modifica la institucion de produccion. */
class PrepareStaging extends Command
{
    protected $signature = 'ena:preparar-staging {--matriz : Agrega preceptor, finanzas y familias ficticias para probar R1}';

    protected $description = 'Crea una institucion y un total admin ficticios exclusivamente en krater_staging';

    public function handle(): int
    {
        // Doble corte antes de cualquier consulta o escritura. No hay --force.
        if (! app()->environment('staging') || DB::connection()->getDatabaseName() !== 'krater_staging') {
            $this->error('Este comando exige APP_ENV=staging y la base krater_staging.');

            return 1;
        }

        $email = 'total-admin.staging@example.invalid';
        $companyHash = 'suiteena-staging-fixture';
        $existing = DB::table('users')->where('email', $email)->first();
        $company = DB::table('companies')->where('unique_hash', $companyHash)->first();

        if ($existing) {
            if (! $company || (int) $existing->company_id !== (int) $company->id) {
                $this->error('La cuenta de prueba existe fuera de la institucion ficticia. No se modifica.');

                return 1;
            }

            // No restablecer claves, roles ni fechas al repetir un deploy.
            if ($this->option('matriz')) {
                app(\Crater\Services\Access\StagingAccessFixtures::class)->prepare((int) $company->id);
            }
            $this->info('Institucion ficticia ya preparada; se conservan sus credenciales y asignaciones.');

            return 0;
        }

        $password = config('staging.admin_password');
        if (! is_string($password) || strlen($password) < 24) {
            $this->error('Configurar STAGING_ADMIN_PASSWORD con al menos 24 caracteres. No se muestra ni se genera una clave por defecto.');

            return 1;
        }

        DB::transaction(function () use ($email, $companyHash, $password, $company) {
            $now = now();
            $companyId = $company ? $company->id : DB::table('companies')->insertGetId([
                'name' => 'SuiteEna - institucion ficticia de staging',
                'unique_hash' => $companyHash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $currencyId = DB::table('currencies')->where('code', 'ARS')->value('id');
            if (! $currencyId) {
                throw new \RuntimeException('Falta el catalogo de moneda ARS; no se crea una cuenta incompleta.');
            }

            $userId = DB::table('users')->insertGetId([
                'name' => 'Total admin staging (prueba)',
                'email' => $email,
                'company_id' => $companyId,
                'currency_id' => $currencyId,
                // Etiqueta para la UI heredada hasta R2. La autoridad se asigna abajo.
                'role' => 'super admin',
                'password' => Hash::make($password),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['primary' => 'Primario (prueba)', 'secondary' => 'Secundario (prueba)'] as $code => $name) {
                DB::table('school_levels')->insertOrIgnore([
                    'company_id' => $companyId, 'code' => $code, 'name' => $name,
                    'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ([
                'currency' => $currencyId, 'time_zone' => 'America/Argentina/Buenos_Aires',
                'language' => 'es', 'fiscal_year' => '1-12', 'carbon_date_format' => 'd/m/Y',
                'moment_date_format' => 'DD/MM/YYYY', 'save_pdf_to_disk' => 'NO',
                'invoice_prefix' => 'FAC', 'estimate_prefix' => 'PRE', 'payment_prefix' => 'COB',
                'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
                'invoice_auto_generate' => 'YES', 'estimate_auto_generate' => 'YES', 'payment_auto_generate' => 'YES',
                'invoice_number_length' => 6, 'estimate_number_length' => 6, 'payment_number_length' => 6,
            ] as $option => $value) {
                DB::table('company_settings')->insertOrIgnore([
                    'company_id' => $companyId, 'option' => $option, 'value' => $value,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('user_settings')->insert([
                'user_id' => $userId, 'key' => 'language', 'value' => 'es',
                'created_at' => $now, 'updated_at' => $now,
            ]);

            if ($this->call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]) !== 0) {
                throw new \RuntimeException('Fallo la siembra RBAC; se revierte la institucion ficticia.');
            }
            $roleId = DB::table('roles')->where('company_id', $companyId)->where('name', RoleName::TOTAL_ADMIN)->value('id');
            if (! $roleId) {
                throw new \RuntimeException('No se pudo preparar el rol total_admin.');
            }
            if (! DB::table('role_user')->where('user_id', $userId)->where('role_id', $roleId)->whereNull('school_level_id')->exists()) {
                DB::table('role_user')->insert([
                    'user_id' => $userId, 'company_id' => $companyId, 'role_id' => $roleId,
                    'school_level_id' => null, 'starts_on' => null, 'ends_on' => null,
                    'granted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });

        if ($this->option('matriz')) {
            $companyId = DB::table('companies')->where('unique_hash', $companyHash)->value('id');
            app(\Crater\Services\Access\StagingAccessFixtures::class)->prepare((int) $companyId);
        }
        $this->info('Institucion ficticia y total_admin preparados en staging. No se imprimen credenciales.');

        return 0;
    }
}
