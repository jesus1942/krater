<?php

namespace Tests\Isolation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

class PrepareStagingTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        // SQLite realmente en memoria; el nombre logico permite ejercitar el doble corte.
        DB::connection()->getPdo();
        DB::connection()->setDatabaseName('krater_staging');
        $this->app['env'] = 'staging';
        config(['staging.admin_password' => 'clave-ficticia-exclusiva-del-test-0123456789']);

        foreach ([
            '2014_10_11_071840_create_companies_table.php' => 'CreateCompaniesTable',
            '2014_10_11_125754_create_currencies_table.php' => 'CreateCurrenciesTable',
            '2014_10_12_000000_create_users_table.php' => 'CreateUsersTable',
            '2019_09_26_145012_create_company_settings_table.php' => 'CreateCompanySettingsTable',
            '2020_09_26_100951_create_user_settings_table.php' => 'CreateUserSettingsTable',
            '2026_09_01_000300_create_rbac_tables.php' => 'CreateRbacTables',
        ] as $file => $class) {
            require_once database_path('migrations/'.$file);
            (new $class())->up();
        }
        Schema::create('school_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->string('code');
            $table->string('name');
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        DB::table('currencies')->insert([
            'name' => 'Peso argentino', 'code' => 'ARS', 'symbol' => '$',
            'precision' => 2, 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false,
        ]);
    }

    /** Ni siquiera consulta los datos si el entorno o la base son incorrectos. */
    public function test_production_is_rejected_before_any_query(): void
    {
        $this->app['env'] = 'production';
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:preparar-staging'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_staging_cannot_seed_a_production_database(): void
    {
        DB::connection()->setDatabaseName('krater');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:preparar-staging'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_no_default_or_missing_password_creates_accounts(): void
    {
        config(['staging.admin_password' => null]);
        $this->assertSame(1, Artisan::call('ena:preparar-staging'));
        $this->assertSame(0, DB::table('companies')->count());
        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_account_has_explicit_total_admin_and_repetition_preserves_credentials_and_roles(): void
    {
        $this->assertSame(0, Artisan::call('ena:preparar-staging'));
        $user = DB::table('users')->first();
        $this->assertTrue(Hash::check(config('staging.admin_password'), $user->password));
        $this->assertSame(2, DB::table('school_levels')->count());
        $assignment = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)->where('roles.name', 'total_admin')->first(['role_user.*']);
        $this->assertNotNull($assignment);
        $this->assertNull($assignment->school_level_id);
        DB::table('role_user')->where('id', $assignment->id)->update(['ends_on' => '2026-01-01']);
        config(['staging.admin_password' => 'otra-clave-que-no-debe-reemplazar-la-original']);

        $this->assertSame(0, Artisan::call('ena:preparar-staging'));
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('companies')->count());
        $this->assertSame($user->password, DB::table('users')->value('password'));
        $this->assertSame('2026-01-01', DB::table('role_user')->where('id', $assignment->id)->value('ends_on'));
    }

    public function test_existing_email_in_another_company_is_not_taken_over(): void
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Otra institucion ficticia']);
        DB::table('users')->insert([
            'name' => 'Cuenta existente', 'email' => 'total-admin.staging@example.invalid',
            'company_id' => $companyId, 'password' => 'hash-a-conservar',
        ]);
        $this->assertSame(1, Artisan::call('ena:preparar-staging'));
        $this->assertSame('hash-a-conservar', DB::table('users')->value('password'));
        $this->assertSame(0, DB::table('role_user')->count());
    }
}
