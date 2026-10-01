<?php

namespace Tests\Isolation;

use Crater\Models\Invoice;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\CreatesApplication;

class LegacyRoutesSecurityTest extends TestCase
{
    use CreatesApplication;

    private User $admin;
    private User $preceptor;
    private User $finance;
    private User $director;
    private User $secondary;
    private User $primaryCustomer;
    private User $secondaryCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite');
        TenantContext::clear();
        foreach ([
            '2014_10_11_071840_create_companies_table.php' => 'CreateCompaniesTable',
            '2014_10_11_125754_create_currencies_table.php' => 'CreateCurrenciesTable',
            '2014_10_12_000000_create_users_table.php' => 'CreateUsersTable',
            '2019_09_26_145012_create_company_settings_table.php' => 'CreateCompanySettingsTable',
            '2020_09_26_100951_create_user_settings_table.php' => 'CreateUserSettingsTable',
            '2020_09_07_103054_create_file_disks_table.php' => 'CreateFileDisksTable',
            '2019_09_14_120124_create_media_table.php' => 'CreateMediaTable',
            '2020_09_22_153617_add_columns_to_media_table.php' => 'AddColumnsToMediaTable',
            '2019_12_14_000001_create_personal_access_tokens_table.php' => 'CreatePersonalAccessTokensTable',
            '2026_08_14_000000_create_students_table.php' => 'CreateStudentsTable',
        ] as $file => $class) {
            require_once database_path('migrations/'.$file);
            (new $class())->up();
        }
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('creator_id')->nullable();
        });
        Schema::create('school_levels', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('company_id');
            $table->string('code'); $table->string('name'); $table->boolean('enabled')->default(true); $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('school_level_user', function (Blueprint $table) {
            $table->unsignedInteger('school_level_id'); $table->unsignedInteger('user_id');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedInteger('school_level_id')->nullable();
        });
        foreach ([
            '2026_09_01_000100_create_academic_core_tables.php' => 'CreateAcademicCoreTables',
            '2026_09_01_000300_create_rbac_tables.php' => 'CreateRbacTables',
            '2026_09_01_000600_create_student_family_tables.php' => 'CreateStudentFamilyTables',
        ] as $file => $class) {
            require_once database_path('migrations/'.$file);
            (new $class())->up();
        }
        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('company_id'); $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable(); $table->string('unique_hash')->unique();
            $table->bigInteger('due_amount')->default(0); $table->timestamps();
        });
        DB::table('currencies')->insert(['name' => 'Peso', 'code' => 'ARS', 'symbol' => '$', 'precision' => 2,
            'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => false]);
        foreach ([1, 2] as $id) {
            DB::table('companies')->insert(['id' => $id, 'name' => 'Institucion ficticia '.$id]);
            DB::table('company_settings')->insert(['company_id' => $id, 'option' => 'carbon_date_format', 'value' => 'd/m/Y']);
        }
        DB::table('school_levels')->insert([
            ['id' => 1, 'company_id' => 1, 'code' => 'primary', 'name' => 'Primario'],
            ['id' => 2, 'company_id' => 1, 'code' => 'secondary', 'name' => 'Secundario'],
            ['id' => 3, 'company_id' => 2, 'code' => 'primary', 'name' => 'Otra escuela'],
        ]);
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->admin = $this->account('total', 'staff');
        $this->assign($this->admin, 'total_admin', null);
        $this->preceptor = $this->account('preceptor', 'admin');
        $this->assign($this->preceptor, 'preceptor', 1);
        $this->finance = $this->account('finance'); $this->assign($this->finance, 'finance_admin', 1);
        $this->director = $this->account('director'); $this->assign($this->director, 'level_director', 1);
        $this->secondary = $this->account('secondary'); $this->assign($this->secondary, 'preceptor', 2);
        $this->primaryCustomer = $this->account('family-primary', 'customer');
        $this->secondaryCustomer = $this->account('family-secondary', 'customer');
        DB::table('students')->insert([
            ['id' => 1, 'company_id' => 1, 'school_level_id' => 1, 'guardian_id' => $this->primaryCustomer->id,
                'first_name' => 'Alumno', 'last_name' => 'Primario', 'school_year' => 2026],
            ['id' => 2, 'company_id' => 1, 'school_level_id' => 2, 'guardian_id' => $this->secondaryCustomer->id,
                'first_name' => 'Alumno', 'last_name' => 'Secundario', 'school_year' => 2026],
            ['id' => 3, 'company_id' => 1, 'school_level_id' => 1, 'guardian_id' => null,
                'first_name' => 'Otra', 'last_name' => 'Division', 'school_year' => 2026],
        ]);
        foreach ([1, 2] as $level) {
            DB::table('academic_years')->insert(['id' => $level, 'company_id' => 1, 'school_level_id' => $level,
                'year' => 2026, 'name' => 'Ciclo ficticio', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31']);
            DB::table('grade_levels')->insert(['id' => $level, 'company_id' => 1, 'school_level_id' => $level,
                'name' => 'Curso ficticio', 'position' => 1]);
        }
        foreach ([1 => 1, 2 => 2, 3 => 1] as $division => $level) {
            DB::table('divisions')->insert(['id' => $division, 'company_id' => 1, 'school_level_id' => $level,
                'academic_year_id' => $level, 'grade_level_id' => $level, 'name' => 'Division '.$division]);
            DB::table('enrollments')->insert(['student_id' => $division, 'company_id' => 1, 'school_level_id' => $level,
                'academic_year_id' => $level, 'division_id' => $division, 'enrolled_on' => '2026-03-01']);
        }
        DB::table('user_scopes')->insert(['user_id' => $this->preceptor->id, 'company_id' => 1,
            'scope_type' => 'division', 'scope_id' => 1]);
        $this->withHeaders(['company' => '1', 'school-level' => '1']);
    }

    private function account(string $name, string $role = 'staff', int $company = 1): User
    {
        return User::create(['name' => $name, 'email' => $name.'@example.invalid', 'password' => 'clave-ficticia-1234',
            'company_id' => $company, 'role' => $role, 'currency_id' => 1])->fresh();
    }

    private function loginAs(User $user): void
    {
        auth()->forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function assign(User $user, string $role, ?int $level): void
    {
        DB::table('role_user')->insert(['user_id' => $user->id, 'company_id' => $user->company_id,
            'role_id' => DB::table('roles')->where('company_id', $user->company_id)->where('name', $role)->value('id'),
            'school_level_id' => $level]);
    }

    public function test_preceptor_cannot_cross_levels_or_access_legacy_administration_and_finance(): void
    {
        $this->loginAs($this->preceptor);
        $before = DB::table('users')->where('id', $this->admin->id)->first();
        foreach (['/users', '/company/settings', '/download-backup', '/backups', '/mail/config', '/disks',
            '/customers', '/search?search=family-secondary', '/invoices', '/estimates', '/payments', '/expenses', '/items', '/dashboard'] as $path) {
            $this->getJson('/api/v1'.$path)->assertStatus(403);
        }
        $this->putJson('/api/v1/users/'.$this->admin->id, ['email' => 'tomada@example.invalid', 'password' => 'otra-clave-ficticia'])->assertStatus(403);
        $this->getJson('/api/v1/students', ['school-level' => '2'])->assertStatus(403);
        $this->getJson('/api/v1/students', ['company' => '2', 'school-level' => '3'])->assertStatus(403);
        $this->getJson('/api/v1/students')->assertStatus(200)->assertJsonCount(1, 'students.data')->assertJsonPath('students.data.0.id', 1);
        $this->getJson('/api/v1/students/3')->assertStatus(403);
        $this->getJson('/api/v1/school-levels')->assertStatus(200)->assertJsonCount(1, 'levels')->assertJsonPath('levels.0.id', 1);
        $after = DB::table('users')->where('id', $this->admin->id)->first();
        $this->assertSame($before->email, $after->email); $this->assertSame($before->password, $after->password);
    }

    public function test_finance_reads_only_customers_and_search_results_from_the_active_level(): void
    {
        $this->loginAs($this->finance);
        $foreign = $this->account('foreign-family', 'customer', 2);
        // Vinculo canonico sin guardian_id legacy, para cubrir ambos caminos.
        DB::table('family_members')->insert(['id' => 1, 'company_id' => 1, 'user_id' => $this->primaryCustomer->id, 'name' => 'Familia canonica']);
        DB::table('student_family_members')->insert(['company_id' => 1, 'student_id' => 1, 'family_member_id' => 1]);
        DB::table('students')->where('id', 1)->update(['guardian_id' => null]);
        $this->getJson('/api/v1/search?search=family')->assertStatus(200)
            ->assertJsonCount(1, 'customers.data')->assertJsonPath('customers.data.0.id', $this->primaryCustomer->id)->assertJsonPath('users', []);
        $this->getJson('/api/v1/customers')->assertStatus(200)->assertJsonCount(1, 'customers.data');
        foreach ([$foreign->id, $this->secondaryCustomer->id, $this->admin->id] as $id) {
            $this->getJson('/api/v1/customers/'.$id)->assertStatus($id === $foreign->id ? 404 : 403);
            $this->putJson('/api/v1/customers/'.$id, ['name' => 'Intento de cambio'])->assertStatus($id === $foreign->id ? 404 : 403);
        }
        $this->postJson('/api/v1/customers/delete', ['ids' => [$this->primaryCustomer->id, $this->secondaryCustomer->id]])->assertStatus(403);
        $this->assertTrue(DB::table('users')->where('id', $this->primaryCustomer->id)->exists());
        $this->getJson('/api/v1/invoices/templates')->assertStatus(200);
    }

    public function test_read_permission_does_not_allow_financial_writes(): void
    {
        $reader = $this->account('reader'); $this->assign($reader, 'staff', 1);
        $roleId = DB::table('roles')->where('company_id', 1)->where('name', 'staff')->value('id');
        DB::table('permission_role')->insert(['role_id' => $roleId,
            'permission_id' => DB::table('permissions')->where('name', 'finance.view')->value('id')]);
        $this->loginAs($reader);
        $this->getJson('/api/v1/invoices/templates')->assertStatus(200);
        foreach (['invoices', 'estimates', 'payments', 'expenses', 'items'] as $resource) {
            $this->postJson('/api/v1/'.$resource, [])->assertStatus(403);
            $this->postJson('/api/v1/'.$resource.'/delete', ['ids' => [1]])->assertStatus(403);
        }
    }

    public function test_user_list_is_scoped_and_account_creation_never_grants_admin(): void
    {
        $this->loginAs($this->director);
        $this->getJson('/api/v1/users')->assertStatus(200)->assertJsonCount(3, 'users.data');
        $this->getJson('/api/v1/users/'.$this->secondary->id)->assertStatus(403);
        $this->getJson('/api/v1/users/'.$this->admin->id)->assertStatus(403);
        $this->loginAs($this->admin);
        $this->putJson('/api/v1/users/'.$this->admin->id, [])->assertStatus(403);
        $this->postJson('/api/v1/users', ['name' => 'Nuevo', 'email' => 'nuevo@example.invalid', 'password' => 'clave-ficticia-1234',
            'role' => 'super admin', 'company_id' => 2, 'is_active' => false])->assertStatus(200)->assertJsonPath('user.role', 'staff');
        $new = User::where('email', 'nuevo@example.invalid')->first();
        $this->assertSame(1, (int) $new->company_id); $this->assertTrue($new->is_active);
        $this->assertSame(0, DB::table('role_user')->where('user_id', $new->id)->count());
        $this->loginAs($new); $this->getJson('/api/v1/users')->assertStatus(403);
    }

    public function test_user_deactivation_preserves_the_account_and_revokes_tokens_atomically(): void
    {
        $token = $this->secondary->createToken('prueba')->plainTextToken;
        $this->loginAs($this->admin);
        $this->postJson('/api/v1/users/delete', ['users' => [$this->secondary->id, $this->admin->id]])->assertStatus(403);
        $this->assertTrue($this->secondary->fresh()->is_active);
        $this->assertSame(1, $this->secondary->tokens()->count());
        $this->postJson('/api/v1/users/delete', ['users' => [$this->secondary->id]])->assertStatus(200);
        $this->assertNotNull($this->secondary->fresh()); $this->assertFalse($this->secondary->fresh()->is_active);
        $this->assertSame(0, $this->secondary->tokens()->count());
        auth()->guard('web')->logout();
        auth()->forgetGuards();
        $this->getJson('/api/v1/school-levels', ['Authorization' => 'Bearer '.$token])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['username' => $this->secondary->email, 'password' => 'clave-ficticia-1234', 'device_name' => 'prueba'])->assertStatus(422);
        $this->loginAs($this->secondary->fresh()); $this->getJson('/api/v1/bootstrap')->assertStatus(403);
    }

    public function test_user_manage_permission_still_respects_hierarchy_company_and_all_target_levels(): void
    {
        $roleId = DB::table('roles')->where('company_id', 1)->where('name', 'level_director')->value('id');
        DB::table('permission_role')->insert(['role_id' => $roleId,
            'permission_id' => DB::table('permissions')->where('name', 'system.user.manage')->value('id')]);
        $foreign = $this->account('foreign-staff', 'staff', 2);
        $this->assign($this->preceptor, 'teacher', 2);
        $this->loginAs($this->director);
        foreach ([$this->admin, $this->director, $this->secondary, $foreign, $this->preceptor] as $target) {
            $this->putJson('/api/v1/users/'.$target->id, ['name' => 'Cambio prohibido',
                'email' => $target->email, 'password' => 'clave-ficticia-cambiada'])->assertStatus($target->id === $foreign->id ? 404 : 403);
        }
        $this->loginAs($this->admin);
        $this->putJson('/api/v1/users/'.$this->secondary->id, ['name' => 'Cambio autorizado',
            'email' => $this->secondary->email])->assertStatus(200)->assertJsonPath('user.name', 'Cambio autorizado');
        $this->getJson('/api/v1/users', ['company' => '2', 'school-level' => '3'])->assertStatus(200)->assertJsonCount(1, 'users.data');
        $this->getJson('/api/v1/users', ['company' => '999'])->assertStatus(403);
    }

    public function test_financial_writes_cannot_indirectly_reference_customers_from_another_level(): void
    {
        $this->loginAs($this->finance);
        foreach (['invoices', 'estimates', 'payments', 'expenses'] as $resource) {
            $this->postJson('/api/v1/'.$resource, ['user_id' => $this->secondaryCustomer->id])->assertStatus(403);
        }
        $this->getJson('/api/v1/search', ['company' => '1invalid'])->assertStatus(403);
        $this->getJson('/api/v1/search', ['school-level' => '1invalid'])->assertStatus(403);
        $this->assign($this->primaryCustomer, 'total_admin', null);
        $this->putJson('/api/v1/customers/'.$this->primaryCustomer->id,
            ['name' => 'Familia con rol', 'email' => 'tomada@example.invalid', 'password' => 'clave-ficticia-cambiada'])->assertStatus(403);
        $this->assertSame($this->primaryCustomer->email, $this->primaryCustomer->fresh()->email);
    }

    public function test_real_staging_fixture_matrix_is_idempotent_and_cannot_run_in_production(): void
    {
        $fixture = app(\Crater\Services\Access\StagingAccessFixtures::class);
        $this->app['env'] = 'production';
        DB::enableQueryLog(); DB::flushQueryLog();
        try {
            $fixture->prepare(1);
            $this->fail('No debe preparar cuentas en produccion');
        } catch (\RuntimeException $e) {
            $this->assertSame([], DB::getQueryLog());
        }
        $this->app['env'] = 'staging';
        DB::connection()->setDatabaseName('krater_staging');
        DB::table('companies')->where('id', 1)->update(['unique_hash' => 'suiteena-staging-fixture']);
        config(['staging.admin_password' => 'clave-ficticia-exclusiva-del-test-0123456789']);
        $this->assertSame(0, Artisan::call('ena:preparar-staging', ['--matriz' => true]));
        $count = DB::table('users')->count(); $studentCount = DB::table('students')->count();
        $preceptor = DB::table('users')->where('email', 'preceptor.primary.staging@example.invalid')->first();
        $this->assertNotNull($preceptor);
        $this->assertTrue(Hash::check(hash_hmac('sha256', $preceptor->email, config('staging.admin_password')), $preceptor->password));
        DB::table('role_user')->where('user_id', $preceptor->id)->update(['ends_on' => '2026-01-01']);
        DB::table('users')->where('id', $preceptor->id)->update(['is_active' => false]);
        $this->assertSame(0, Artisan::call('ena:preparar-staging', ['--matriz' => true]));
        $this->assertSame($count, DB::table('users')->count());
        $this->assertSame($studentCount, DB::table('students')->count());
        $this->assertSame($preceptor->password, DB::table('users')->where('id', $preceptor->id)->value('password'));
        $this->assertSame('2026-01-01', DB::table('role_user')->where('user_id', $preceptor->id)->value('ends_on'));
        $this->assertFalse((bool) DB::table('users')->where('id', $preceptor->id)->value('is_active'));
    }

    public function test_legacy_role_membership_and_expired_assignments_never_authorize(): void
    {
        $legacy = $this->account('legacy', 'super admin');
        DB::table('school_level_user')->insert(['school_level_id' => 1, 'user_id' => $legacy->id]);
        $this->loginAs($legacy); $this->getJson('/api/v1/users')->assertStatus(403);
        $access = app(AccessManager::class);
        $this->assertFalse($access->isTotalAdmin($legacy));
        $this->assertTrue($access->allows($this->finance, 'finance.view', 1));
        DB::table('role_user')->where('user_id', $this->finance->id)->update(['ends_on' => now()->subDay()->toDateString()]);
        $this->assertFalse($access->allows($this->finance, 'finance.view', 1));
        $this->loginAs($this->finance); $this->getJson('/api/v1/invoices/templates')->assertStatus(403);
        $this->loginAs($this->preceptor);
        DB::table('school_level_user')->insert(['school_level_id' => 2, 'user_id' => $this->preceptor->id]);
        $this->getJson('/api/v1/students', ['school-level' => '2'])->assertStatus(403);
    }

    public function test_pdf_urls_require_signature_and_expiry_bound_to_the_exact_document(): void
    {
        DB::table('invoices')->insert(['id' => 1, 'company_id' => 1, 'school_level_id' => 1, 'unique_hash' => 'documento-ficticio']);
        $this->app->instance(\Crater\Http\Controllers\V1\Invoice\InvoicePdfController::class,
            new class extends \Crater\Http\Controllers\V1\Invoice\InvoicePdfController {
                public function __invoke(Invoice $invoice) { return response('Documento '.$invoice->id); }
            });
        $url = (new Invoice(['unique_hash' => 'documento-ficticio']))->invoicePdfUrl;
        $this->get('/invoices/pdf/documento-ficticio')->assertStatus(403);
        $this->get($url)->assertStatus(200)->assertSee('Documento 1');
        $this->get(str_replace('documento-ficticio', 'otro-documento', $url))->assertStatus(403);
        $expired = URL::temporarySignedRoute('documents.invoice', now()->subSecond(), ['invoice' => 'documento-ficticio']);
        $this->get($expired)->assertStatus(403);
        $this->get('/customer/invoices/pdf/documento-ficticio')->assertStatus(403);
        $this->get('/payments/pdf/documento-ficticio')->assertStatus(403);
        $this->get('/estimates/pdf/documento-ficticio')->assertStatus(403);
        $this->get('/expenses/1/receipt')->assertStatus(403);
    }

    public function test_financial_payload_cannot_move_data_to_another_tenant_or_reference_its_students(): void
    {
        $this->loginAs($this->finance);
        foreach (['invoices', 'estimates', 'payments', 'expenses', 'items'] as $resource) {
            $this->postJson('/api/v1/'.$resource, ['company_id' => 2])->assertStatus(403);
            $this->postJson('/api/v1/'.$resource, ['school_level_id' => 2])->assertStatus(403);
            $this->postJson('/api/v1/'.$resource, ['school_level_id' => null])->assertStatus(403);
        }
        $this->postJson('/api/v1/invoices', ['student_id' => 2])->assertStatus(403);
        $this->postJson('/api/v1/payments', ['enrollment_id' => 2])->assertStatus(403);
    }

    public function test_saved_pdf_uses_private_storage_even_when_the_default_disk_is_public(): void
    {
        DB::table('company_settings')->insert([
            ['company_id' => 1, 'option' => 'language', 'value' => 'es'],
            ['company_id' => 1, 'option' => 'save_pdf_to_disk', 'value' => 'YES'],
        ]);
        config(['filesystems.default' => 'public']);
        \Illuminate\Support\Facades\Storage::fake('finance_private');
        $invoice = new PdfStorageFixtureInvoice(['company_id' => 1, 'unique_hash' => 'pdf-privado']);
        $invoice->saveQuietly();
        $this->assertTrue($invoice->generatePDF('invoice', 'prueba-r1'));
        $media = $invoice->getFirstMedia('invoice');
        $this->assertSame('finance_private', $media->disk);
        $this->assertStringNotContainsString(public_path(), $media->getPath());
        $this->assertSame('%PDF-1.4\nDocumento ficticio', file_get_contents($invoice->getGeneratedPDF('invoice')['path']));
        $this->assertSame(200, $invoice->getGeneratedPDFOrStream('invoice')->getStatusCode());
    }

    public function test_pdf_rendering_returns_pdf_bytes_without_wrapping_another_http_response(): void
    {
        DB::table('company_settings')->insert(['company_id' => 1, 'option' => 'language', 'value' => 'es']);
        $invoice = new PdfStorageFixtureInvoice(['company_id' => 1, 'unique_hash' => 'pdf-render']);
        $response = $invoice->getGeneratedPDFOrStream('invoice');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('%PDF-1.4\nDocumento ficticio', $response->getContent());
    }

    public function test_migration_converts_only_legacy_super_admin_and_seeder_does_not_restore_revocations(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN is_active');
        $legacy = $this->account('historico', 'super admin');
        $ordinary = $this->account('admin-legacy', 'admin');
        require_once database_path('migrations/2026_10_01_180000_harden_user_access.php');
        (new \HardenUserAccess())->up();
        $access = app(AccessManager::class);
        $this->assertTrue($access->isTotalAdmin($legacy->fresh()));
        $this->assertFalse($access->hasActiveRole($ordinary->fresh()));
        DB::table('role_user')->where('user_id', $legacy->id)->update(['ends_on' => now()->subDay()->toDateString()]);
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->assertFalse($access->isTotalAdmin($legacy->fresh()));
        $this->assertSame(0, DB::table('role_user')->where('user_id', $ordinary->id)->count());
    }

    public function test_production_migration_blocks_a_release_without_an_active_total_admin(): void
    {
        DB::table('role_user')->where('user_id', $this->admin->id)->delete();
        $legacy = $this->account('historico-revocado', 'super admin');
        $this->assign($legacy, 'total_admin', null);
        DB::table('role_user')->where('user_id', $legacy->id)->update(['ends_on' => '2026-01-01']);
        $this->app['env'] = 'production';
        require_once database_path('migrations/2026_10_01_180000_harden_user_access.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('falta un total_admin vigente');
        (new \HardenUserAccess())->up();
    }
}

class PdfStorageFixtureInvoice extends Invoice
{
    protected $table = 'invoices';

    public function getPDFData()
    {
        return new class {
            public function output() { return '%PDF-1.4\nDocumento ficticio'; }
        };
    }
}
