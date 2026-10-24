<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** Fila 5: bajas logicas, papelera de total admin y saldos reversibles. */
class SoftDeletionTest extends TestCase
{
    use CreatesApplication;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
        // Misma correccion de PK de SQLite que WholeInstitutionTest.
        foreach (['items', 'invoice_items', 'estimate_items', 'estimates', 'expenses', 'company_settings'] as $table) {
            $sql = DB::selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$table])->sql;
            if (strpos($sql, 'PRIMARY KEY(') !== false) {
                $sql = preg_replace('/, PRIMARY KEY\([^)]*\)/', '', $sql);
                $sql = preg_replace('/\bid INTEGER NOT NULL\b/', 'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $sql, 1);
                DB::statement('DROP TABLE '.$table); DB::statement($sql);
            }
        }
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Bus::fake();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'trash-fixture']);
        $settingId = 1;
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'invoice_prefix' => 'INV', 'estimate_prefix' => 'EST', 'payment_prefix' => 'PAY', 'time_zone' => 'UTC', 'fiscal_year' => '1-12',
            'invoice_number_length' => 6, 'estimate_number_length' => 6, 'payment_number_length' => 6] as $key => $value) {
            DB::table('company_settings')->insert(['id' => $settingId++, 'company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        foreach ([1, 2] as $level) {
            DB::table('school_levels')->insert(['id' => $level, 'company_id' => 1, 'code' => 'level'.$level, 'name' => 'Nivel ficticio '.$level]);
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);
        $this->admin = $this->account('total', 'total_admin', null);
        $this->customer = User::create(['company_id' => 1, 'name' => 'Familia ficticia', 'email' => 'family@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'customer', 'currency_id' => 1]);
        $this->loginAs($this->admin);
        $this->withHeaders(['company' => '1', 'school-level' => '']);
    }

    private function loginAs(User $user): void
    {
        auth()->forgetGuards();
        $this->actingAs($user->fresh(), 'web');
    }

    private function account(string $name, string $role, ?int $level): User
    {
        $user = User::create(['company_id' => 1, 'name' => $name, 'email' => $name.'@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $user->id, 'school_level_id' => $level,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id')]);

        return $user;
    }

    private function invoice(string $number, int $total, int $level = 1, ?int $userId = null): int
    {
        return DB::table('invoices')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'invoice_number' => $number,
            'user_id' => $userId ?? $this->customer->id, 'invoice_date' => '2026-10-02', 'due_date' => '2099-10-30',
            'status' => 'SENT', 'paid_status' => 'UNPAID', 'sent' => true, 'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'sub_total' => $total, 'total' => $total, 'tax' => 0, 'due_amount' => $total, 'unique_hash' => 'h-'.$number]);
    }

    private function payment(string $number, int $invoiceId, int $amount, int $level = 1): int
    {
        DB::table('invoices')->where('id', $invoiceId)->update([
            'due_amount' => DB::raw('due_amount - '.$amount), 'paid_status' => 'PARTIALLY_PAID']);

        return DB::table('payments')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'payment_number' => $number,
            'user_id' => $this->customer->id, 'invoice_id' => $invoiceId, 'payment_date' => '2026-10-03', 'amount' => $amount,
            'unique_hash' => 'h-'.$number]);
    }

    public function test_delete_is_logical_audited_and_only_total_admin_sees_and_restores_the_trash(): void
    {
        $id = $this->invoice('INV-000001', 10000);
        $this->postJson('/api/v1/invoices/delete', ['ids' => [$id]])->assertOk();

        $this->assertNotNull(DB::table('invoices')->where('id', $id)->value('deleted_at'));
        $this->getJson('/api/v1/invoices/'.$id)->assertStatus(404);
        $this->assertSame(0, (int) $this->getJson('/api/v1/invoices')->json('invoices.total'));
        $this->assertTrue(DB::table('audit_logs')->where('action', 'invoice.deleted')->where('auditable_id', $id)->exists());

        $finance = $this->account('finance', 'finance_admin', 1);
        $this->loginAs($finance);
        $this->getJson('/api/v1/trash/invoices', ['school-level' => '1'])->assertStatus(403);
        $this->postJson('/api/v1/trash/invoices/'.$id.'/restore', [], ['school-level' => '1'])->assertStatus(403);

        $this->loginAs($this->admin);
        $this->assertSame([$id], array_column($this->getJson('/api/v1/trash/invoices')->assertOk()->json('records.data'), 'id'));
        $this->getJson('/api/v1/trash/students')->assertStatus(404);
        $this->postJson('/api/v1/trash/invoices/'.$id.'/restore')->assertOk();
        $this->assertNull(DB::table('invoices')->where('id', $id)->value('deleted_at'));
        $this->assertTrue(DB::table('audit_logs')->where('action', 'invoice.restored')->where('auditable_id', $id)->exists());
        $this->postJson('/api/v1/trash/invoices/'.$id.'/restore')->assertStatus(404);
    }

    public function test_invoice_with_active_payment_needs_payment_down_first_and_balances_round_trip(): void
    {
        $invoiceId = $this->invoice('INV-000001', 10000);
        $paymentId = $this->payment('PAY-000001', $invoiceId, 4000);

        $this->postJson('/api/v1/invoices/delete', ['ids' => [$invoiceId]])->assertStatus(422);
        $this->assertNull(DB::table('invoices')->where('id', $invoiceId)->value('deleted_at'));

        $this->postJson('/api/v1/payments/delete', ['ids' => [$paymentId]])->assertOk();
        $invoice = DB::table('invoices')->find($invoiceId);
        $this->assertSame([10000, 'UNPAID'], [(int) $invoice->due_amount, $invoice->paid_status]);
        $this->assertNotNull(DB::table('payments')->where('id', $paymentId)->value('deleted_at'));

        $this->postJson('/api/v1/invoices/delete', ['ids' => [$invoiceId]])->assertOk();
        // El cobro no vuelve mientras su factura siga dada de baja.
        $this->postJson('/api/v1/trash/payments/'.$paymentId.'/restore')->assertStatus(422);
        $this->postJson('/api/v1/trash/invoices/'.$invoiceId.'/restore')->assertOk();
        $this->postJson('/api/v1/trash/payments/'.$paymentId.'/restore')->assertOk();
        $invoice = DB::table('invoices')->find($invoiceId);
        $this->assertSame([6000, 'PARTIALLY_PAID'], [(int) $invoice->due_amount, $invoice->paid_status]);

        // Un cobro restaurado nunca deja saldo negativo.
        $this->postJson('/api/v1/payments/delete', ['ids' => [$paymentId]])->assertOk();
        $this->payment('PAY-000002', $invoiceId, 8000);
        $this->postJson('/api/v1/trash/payments/'.$paymentId.'/restore')->assertStatus(422);
        $this->assertSame(2000, (int) DB::table('invoices')->where('id', $invoiceId)->value('due_amount'));
        $this->assertNotNull(DB::table('payments')->where('id', $paymentId)->value('deleted_at'));
    }

    public function test_numbers_of_deleted_documents_are_never_reused(): void
    {
        $this->invoice('INV-000001', 1000);
        $last = $this->invoice('INV-000002', 1000);
        $this->postJson('/api/v1/invoices/delete', ['ids' => [$last]])->assertOk();

        $this->getJson('/api/v1/next-number?key=invoice')->assertOk()->assertJsonPath('nextNumber', '000003');
        $rule = new \Crater\Rules\UniqueNumber(\Crater\Models\Invoice::class);
        $this->assertFalse($rule->passes('invoice_number', 'INV-000002'));
        $this->assertTrue($rule->passes('invoice_number', 'INV-000003'));
    }

    public function test_bulk_delete_outside_scope_is_rejected_whole(): void
    {
        $own = $this->invoice('INV-000001', 1000, 1);
        $other = $this->invoice('INV-000002', 1000, 2);
        $finance = $this->account('finance', 'finance_admin', 1);
        $this->loginAs($finance);

        $this->postJson('/api/v1/invoices/delete', ['ids' => [$own, $other]], ['school-level' => '1'])->assertStatus(403);
        $this->assertSame(0, DB::table('invoices')->whereNotNull('deleted_at')->count());
        $this->postJson('/api/v1/invoices/delete', ['ids' => [$own]], ['school-level' => '1'])->assertOk();
        $this->assertNotNull(DB::table('invoices')->where('id', $own)->value('deleted_at'));
    }

    public function test_family_falls_and_returns_with_its_documents_in_every_level(): void
    {
        $first = $this->invoice('INV-000001', 1000, 1);
        $second = $this->invoice('INV-000002', 3000, 2);
        $paymentId = $this->payment('PAY-000001', $second, 1000, 2);
        $earlier = $this->invoice('INV-000003', 500, 1);
        $this->postJson('/api/v1/invoices/delete', ['ids' => [$earlier]])->assertOk();
        DB::table('invoices')->where('id', $earlier)->update(['deleted_at' => '2026-01-01 00:00:00']);

        $this->postJson('/api/v1/customers/delete', ['ids' => [$this->customer->id]])->assertOk();
        $this->assertNotNull(DB::table('users')->where('id', $this->customer->id)->value('deleted_at'));
        $this->assertSame(3, DB::table('invoices')->whereNotNull('deleted_at')->count());
        $this->assertNotNull(DB::table('payments')->where('id', $paymentId)->value('deleted_at'));
        // Una factura de la familia no vuelve sola.
        $this->postJson('/api/v1/trash/invoices/'.$first.'/restore')->assertStatus(422);

        $this->postJson('/api/v1/trash/customers/'.$this->customer->id.'/restore')->assertOk();
        $this->assertNull(DB::table('users')->where('id', $this->customer->id)->value('deleted_at'));
        $this->assertSame([$earlier], DB::table('invoices')->whereNotNull('deleted_at')->pluck('id')->all());
        $this->assertNull(DB::table('payments')->where('id', $paymentId)->value('deleted_at'));
        $this->assertSame(2000, (int) DB::table('invoices')->where('id', $second)->value('due_amount'));
    }

    public function test_trash_screen_is_total_admin_only_and_never_exposes_permanent_delete(): void
    {
        $menu = file_get_contents(resource_path('assets/js/views/settings/SettingsIndex.vue'));
        $this->assertMatchesRegularExpression("#link: '/admin/settings/trash',\\s+title: 'Papelera',\\s+icon: '[a-z-]+',\\s+totalAdminOnly: true#", $menu);
        $this->assertStringContainsString("component: TrashSetting", file_get_contents(resource_path('assets/js/router.js')));
        $screen = file_get_contents(resource_path('assets/js/views/settings/TrashSetting.vue'));
        $this->assertStringContainsString('/restore', $screen);
        $this->assertStringNotContainsString('forceDelete', $screen.file_get_contents(app_path('Services/Data/TrashBin.php')));
        $this->assertStringNotContainsString('axios.delete', $screen);
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (strpos($route->uri(), 'api/v1/trash') === 0) {
                $this->assertContains('permission:system.settings.manage', $route->gatherMiddleware());
                $this->assertNotContains('DELETE', $route->methods());
            }
        }
    }
}
