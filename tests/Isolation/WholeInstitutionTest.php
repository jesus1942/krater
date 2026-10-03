<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;
use Tests\Support\InstitutionScenario;

/** Prueba el mismo contrato de toda la institución y niveles elegidos. */
class WholeInstitutionTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
        // DBAL 2 reconstruye incorrectamente estas PK al alterar texto en SQLite.
        // Se corrige solo el esquema vacío del test; MySQL usa las migraciones sin cambios.
        foreach (['items', 'invoice_items', 'estimate_items', 'estimates', 'expenses', 'company_settings'] as $table) {
            $sql = DB::selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$table])->sql;
            if (strpos($sql, 'PRIMARY KEY(') !== false) {
                $this->assertSame(0, DB::table($table)->count());
                $sql = preg_replace('/, PRIMARY KEY\([^)]*\)/', '', $sql);
                $sql = preg_replace('/\bid INTEGER NOT NULL\b/', 'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $sql, 1);
                DB::statement('DROP TABLE '.$table); DB::statement($sql);
            }
        }
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Bus::fake(); // PDFs de documentos se prueban en staging; informes se renderizan aquí.
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institución ficticia', 'unique_hash' => 'institution-fixture']);
        $settingId = 1;
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'invoice_prefix' => 'INV', 'estimate_prefix' => 'EST', 'payment_prefix' => 'PAY', 'time_zone' => 'UTC', 'fiscal_year' => '1-12', 'save_pdf_to_disk' => 'NO', 'invoice_number_length' => 6, 'estimate_number_length' => 6, 'payment_number_length' => 6,
            'invoice_auto_generate' => 'YES', 'estimate_auto_generate' => 'YES', 'payment_auto_generate' => 'YES'] as $key => $value) {
            DB::table('company_settings')->insert(['id' => $settingId++, 'company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $admin = User::create(['company_id' => 1, 'name' => 'Total ficticio', 'email' => 'total@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $admin->id,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', 'total_admin')->value('id')]);
        $this->actingAs($admin->fresh(), 'web');
        $this->withHeaders(['company' => '1']);
    }

    public function test_full_http_walk_and_edits_keep_each_records_level(): void
    {
        $customer = User::create(['company_id' => 1, 'name' => 'Familia ficticia', 'email' => 'family@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'customer', 'currency_id' => 1]);
        $fixture = InstitutionScenario::fixture(1, $customer->id);
        $result = InstitutionScenario::run(function ($method, $path, $payload, $level) {
            $this->withHeaders(['school-level' => $level === null ? '' : (string) $level]);
            $response = $this->json($method, $path, $payload);
            return ['status' => $response->status(), 'json' => json_decode($response->getContent(), true), 'body' => $response->getContent()];
        }, $fixture, 1, 'institution-fixture');
        $this->assertTrue($result['passed']); $this->assertGreaterThan(250, $result['count']);
    }

    /** La cola síncrona no debe renderizar un cobro antes de su hash firmado. */
    public function test_payment_pdf_waits_for_persisted_document_hash(): void
    {
        $payment = \Crater\Models\Payment::create(['company_id' => 1, 'user_id' => User::first()->id,
            'payment_number' => 'HASH-PRUEBA', 'payment_date' => '2026-10-02', 'amount' => 1000]);
        Bus::assertNotDispatched(\Crater\Jobs\GeneratePaymentPdfJob::class);
        $payment->unique_hash = 'hash-ficticio-listo';
        $payment->save();
        Bus::assertDispatched(\Crater\Jobs\GeneratePaymentPdfJob::class, fn ($job) =>
            $job->payment->id === $payment->id && $job->payment->unique_hash === 'hash-ficticio-listo');
        $this->getJson('/api/v1/payments', ['school-level' => ''])->assertStatus(200);
    }
    /** Nivel inválido o ajeno no puede producir altas ni vínculos cruzados. */
    public function test_creation_validates_enabled_company_level_and_references(): void
    {
        DB::table('companies')->insert(['id' => 2, 'name' => 'Otra institución ficticia']);
        DB::table('school_levels')->insert([
            ['id' => 1, 'company_id' => 1, 'code' => 'primary', 'name' => 'Primario', 'enabled' => true],
            ['id' => 2, 'company_id' => 1, 'code' => 'secondary', 'name' => 'Deshabilitado', 'enabled' => false],
            ['id' => 3, 'company_id' => 2, 'code' => 'primary', 'name' => 'Ajeno', 'enabled' => true],
        ]);
        $payload = ['name' => 'Concepto ficticio', 'price' => 1000];
        foreach ([null, 0, 2, 3, 999, '1abc', [1]] as $level) {
            $this->postJson('/api/v1/items', $payload + ['school_level_id' => $level])->assertStatus(422);
        }
        $this->assertSame(0, DB::table('items')->count());
        $this->postJson('/api/v1/items', $payload, ['school-level' => '1'])->assertStatus(200);
        $this->postJson('/api/v1/items', $payload + ['school_level_id' => 2], ['school-level' => '1'])->assertStatus(403);
        $this->getJson('/api/v1/items/1', ['company' => '2', 'school-level' => ''])->assertStatus(404);
        $this->postJson('/api/v1/items', $payload + ['school_level_id' => 1, 'company_id' => 2])->assertStatus(403);
    }

    /** Los registros históricos sin nivel se editan, pero no se reclasifican. */
    public function test_legacy_null_level_is_preserved_and_student_cannot_remove_level(): void
    {
        $item = \Crater\Models\Item::create(['company_id' => 1, 'name' => 'Histórico ficticio', 'price' => 1000]);
        $this->putJson('/api/v1/items/'.$item->id, ['name' => 'Histórico editado', 'price' => 2000, 'school_level_id' => null])->assertStatus(200);
        $this->assertNull($item->fresh()->school_level_id);
        DB::table('school_levels')->insert(['id' => 1, 'company_id' => 1, 'code' => 'primary', 'name' => 'Primario']);
        $this->putJson('/api/v1/items/'.$item->id, ['name' => 'Intento', 'price' => 2000, 'school_level_id' => 1])->assertStatus(403);
        foreach ([null, 1] as $level) {
            $student = \Crater\Models\Student::create(['company_id' => 1, 'school_level_id' => $level, 'first_name' => 'Alumno', 'last_name' => 'Ficticio', 'status' => 'active', 'school_year' => 2026]);
            $basic = ['first_name' => 'Editado', 'last_name' => 'Ficticio', 'status' => 'active'];
            $this->putJson('/api/v1/students/'.$student->id, $basic)->assertStatus(200);
            $this->putJson('/api/v1/students/'.$student->id, $basic + ['school_level_id' => $level === null ? 1 : null])->assertStatus(422);
            $this->assertSame($level, $student->fresh()->school_level_id === null ? null : (int) $student->fresh()->school_level_id);
        }
    }

    /** Consolidado = suma de los tres filtros, conservando el límite de empresa. */
    public function test_report_aggregates_all_levels_and_filtered_report_has_exact_amount(): void
    {
        foreach ([1, 2, 3] as $level) {
            DB::table('school_levels')->insert(['id' => $level, 'company_id' => 1, 'code' => 'level'.$level, 'name' => 'Nivel ficticio '.$level]);
            DB::table('invoices')->insert(['company_id' => 1, 'school_level_id' => $level, 'invoice_number' => 'REP-'.$level,
                'invoice_date' => '2026-10-02', 'due_date' => '2026-10-30', 'status' => 'SENT', 'paid_status' => 'PAID',
                'tax_per_item' => 'NO', 'discount_per_item' => 'NO', 'sub_total' => $level * 10000,
                'total' => $level * 10000, 'tax' => 0, 'due_amount' => $level * 10000]);
        }
        DB::table('companies')->insert(['id' => 2, 'name' => 'Institución ajena']);
        DB::table('invoices')->insert(['company_id' => 2, 'school_level_id' => 1, 'invoice_number' => 'REP-AJENO',
            'invoice_date' => '2026-10-02', 'due_date' => '2026-10-30', 'status' => 'SENT', 'paid_status' => 'PAID',
            'tax_per_item' => 'NO', 'discount_per_item' => 'NO', 'sub_total' => 99999, 'total' => 99999, 'tax' => 0, 'due_amount' => 0]);
        foreach ([null => 60000, 1 => 10000, 2 => 20000, 3 => 30000] as $level => $expected) {
            $this->get('/reports/profit-loss/institution-fixture?from_date=2026-01-01&to_date=2026-12-31&school_level_id='.$level)->assertStatus(200);
            $this->assertSame($expected, (int) view()->getShared()['income']);
            $this->assertNull(TenantContext::companyId());
        }
        $this->get('/reports/profit-loss/institution-fixture?school_level_id=999')->assertStatus(403)->assertSee('El nivel no está habilitado', false);
        $this->get('/reports/profit-loss/institution-fixture?school_level_id=abc')->assertStatus(422)->assertSee('Elegí un nivel institucional válido', false);
    }

    /** El flag heredado all_levels nunca permite escapar del nivel del encabezado. */
    public function test_selected_level_remains_fixed_and_foreign_links_are_rejected(): void
    {
        $customer = User::create(['company_id' => 1, 'name' => 'Familia ficticia', 'email' => 'fixture-family@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'customer', 'currency_id' => 1]);
        $fixture = InstitutionScenario::fixture(1, $customer->id);
        $primary = $fixture['levels'][0]; $secondary = $fixture['levels'][1];
        $this->getJson('/api/v1/students?all_levels=1', ['school-level' => (string) $primary['school_level_id']])
            ->assertStatus(200)->assertJsonCount(1, 'students.data')
            ->assertJsonPath('students.data.0.school_level_id', $primary['school_level_id']);
        $this->getJson('/api/v1/students', ['school-level' => ''])->assertStatus(200)->assertJsonCount(3, 'students.data');
        $this->postJson('/api/v1/invoices', ['school_level_id' => $primary['school_level_id'],
            'student_id' => $secondary['student_id']])->assertStatus(403);
        $this->getJson('/api/v1/students/placement-options?school_level_id='.$secondary['school_level_id'],
            ['school-level' => (string) $primary['school_level_id']])->assertStatus(422);
    }

}
