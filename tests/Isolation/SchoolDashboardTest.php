<?php

namespace Tests\Isolation;

use Crater\Models\User;
use Crater\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** Inicio escolar: matricula, ocupacion, personal y cobranza por nivel, sin datos de facturero. */
class SchoolDashboardTest extends TestCase
{
    use CreatesApplication;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'queue.default' => 'sync', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite'); TenantContext::clear();
        Artisan::call('migrate', ['--force' => true]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        DB::table('companies')->insert(['id' => 1, 'name' => 'Institucion ficticia', 'unique_hash' => 'dashboard-fixture']);
        foreach (['carbon_date_format' => 'd/m/Y', 'moment_date_format' => 'DD/MM/YYYY', 'language' => 'es', 'currency' => 1,
            'time_zone' => 'UTC', 'fiscal_year' => '1-12'] as $key => $value) {
            DB::table('company_settings')->insert(['id' => DB::table('company_settings')->max('id') + 1, 'company_id' => 1, 'option' => $key, 'value' => $value]);
        }
        Artisan::call('db:seed', ['--class' => 'RbacSeeder', '--force' => true]);

        // Primario: 1.er año A (3 de 4) y B (sin capacidad, 1). Secundario: 1.er año A (2 de 2).
        foreach ([1 => ['primary', 'Primario', ['A' => [4, 3], 'B' => [null, 1]]], 2 => ['secondary', 'Secundario', ['A' => [2, 2]]]] as $level => [$code, $name, $divisions]) {
            DB::table('school_levels')->insert(['id' => $level, 'company_id' => 1, 'code' => $code, 'name' => $name, 'enabled' => true]);
            $year = DB::table('academic_years')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'year' => 2026,
                'name' => 'Ciclo 2026', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31', 'status' => 'active']);
            $old = DB::table('academic_years')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'year' => 2025,
                'name' => 'Ciclo 2025', 'starts_on' => '2025-03-01', 'ends_on' => '2025-12-31', 'status' => 'closed']);
            $grade = DB::table('grade_levels')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'name' => '1.er año', 'position' => 1]);
            DB::table('divisions')->insert(['company_id' => 1, 'school_level_id' => $level, 'academic_year_id' => $old,
                'grade_level_id' => $grade, 'name' => 'Z', 'enabled' => true, 'capacity' => 30]);
            foreach ($divisions as $letter => [$capacity, $students]) {
                $division = DB::table('divisions')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'academic_year_id' => $year,
                    'grade_level_id' => $grade, 'name' => $letter, 'enabled' => true, 'capacity' => $capacity, 'shift' => 'morning']);
                foreach (range(1, $students) as $n) {
                    $student = DB::table('students')->insertGetId(['company_id' => 1, 'school_level_id' => $level, 'first_name' => 'Alumno',
                        'last_name' => $level.$letter.$n, 'school_year' => 2026, 'status' => 'active', 'dni' => 'DNI-'.$level.$letter.$n]);
                    DB::table('enrollments')->insert(['company_id' => 1, 'school_level_id' => $level, 'academic_year_id' => $year,
                        'student_id' => $student, 'division_id' => $division, 'enrolled_on' => '2026-03-01', 'status' => 'active']);
                }
            }
            // Un pase dentro del nivel no cuenta como matricula vigente.
            DB::table('enrollments')->insert(['company_id' => 1, 'school_level_id' => $level, 'academic_year_id' => $year,
                'student_id' => $student, 'division_id' => $division, 'enrolled_on' => '2026-03-01', 'status' => 'transferred_out']);
        }

        $this->admin = $this->account('total', 'total_admin', null);
        $family1 = $this->family('Familia Primario');
        $family2 = $this->family('Familia Secundario');
        $this->invoice(1, $family1, 50000, 50000, now()->subDays(40)->toDateString());
        $this->invoice(1, $family1, 50000, 20000, now()->subDays(10)->toDateString());
        $this->invoice(1, $family1, 50000, 50000, now()->addDays(10)->toDateString());
        $this->invoice(2, $family2, 80000, 80000, now()->subDays(5)->toDateString());
        $this->invoice(2, $family2, 99999, 99999, now()->subDays(5)->toDateString(), 'DRAFT');
        DB::table('payments')->insert(['company_id' => 1, 'school_level_id' => 1, 'user_id' => $family1, 'payment_number' => 'PAY-1',
            'payment_date' => now()->toDateString(), 'amount' => 30000, 'unique_hash' => 'pay-1']);

        foreach ([[1, 'active', 1], [2, 'active', 2], [3, 'active', null], [4, 'inactive', 1]] as [$n, $status, $level]) {
            $member = DB::table('staff_members')->insertGetId(['company_id' => 1, 'first_name' => 'Persona', 'last_name' => (string) $n,
                'staff_category' => 'teacher', 'employment_status' => $status]);
            DB::table('staff_assignments')->insert(['company_id' => 1, 'staff_member_id' => $member, 'school_level_id' => $level,
                'position_title' => 'Cargo', 'function_category' => 'teaching', 'active' => true]);
        }
    }

    private function account(string $name, string $role, ?int $level): User
    {
        $user = User::create(['company_id' => 1, 'name' => $name.' Apellido', 'email' => $name.'@example.invalid',
            'password' => 'ficticia-local-1234', 'role' => 'staff', 'currency_id' => 1]);
        DB::table('role_user')->insert(['company_id' => 1, 'user_id' => $user->id, 'school_level_id' => $level,
            'role_id' => DB::table('roles')->where('company_id', 1)->where('name', $role)->value('id')]);

        return $user;
    }

    private function family(string $name): int
    {
        return DB::table('users')->insertGetId(['company_id' => 1, 'name' => $name, 'email' => str_replace(' ', '', $name).'@example.invalid',
            'role' => 'customer', 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function invoice(int $level, int $family, int $total, int $due, string $dueDate, string $status = 'SENT'): void
    {
        static $n = 0;
        $n++;
        DB::table('invoices')->insert(['company_id' => 1, 'school_level_id' => $level, 'user_id' => $family,
            'invoice_date' => now()->toDateString(), 'due_date' => $dueDate, 'invoice_number' => 'CUO-'.$n, 'status' => $status,
            'paid_status' => $due === $total ? 'UNPAID' : 'PARTIALLY_PAID', 'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'sub_total' => $total, 'total' => $total, 'tax' => 0, 'due_amount' => $due, 'unique_hash' => 'cuo-'.$n]);
    }

    private function as(User $user, ?int $level)
    {
        auth()->forgetGuards();
        TenantContext::clear();
        $this->actingAs($user->fresh(), 'web');

        return $this->withHeaders(['company' => '1', 'school-level' => $level === null ? '' : (string) $level]);
    }

    public function test_whole_institution_sees_every_level_and_only_the_current_cycle(): void
    {
        $data = $this->as($this->admin, null)->getJson('/api/v1/dashboard/school')->assertOk()->json();

        $this->assertNull($data['school_level']);
        $this->assertSame([6, 3, 6, 5], [$data['academic']['students'], $data['academic']['divisions_count'],
            $data['academic']['capacity'], $data['academic']['occupied_with_capacity']]);
        $this->assertSame(['Primario', 'Primario', 'Secundario'], array_column($data['academic']['divisions'], 'level'));
        $this->assertSame(3, $data['staff']['active']);
        // Vencidas: 50000 + 20000 (primario) + 80000 (secundario); la futura y el borrador no cuentan.
        $this->assertSame([150000, 200000, 2], [$data['finance']['overdue_total'], $data['finance']['due_total'], $data['finance']['families_overdue']]);
        $this->assertSame(['Familia Secundario', 'Familia Primario'], array_column($data['finance']['top_overdue'], 'name'));
        $this->assertSame(2, $data['finance']['top_overdue'][1]['fees']);
        $this->assertSame(['billed' => 230000, 'collected' => 30000, 'rate' => 13], $data['finance']['month']);
        $this->assertArrayNotHasKey('estimates', $data);
    }

    public function test_level_header_limits_every_block_to_that_level(): void
    {
        // Mismo publico que el tablero anterior (finance.view); con el nivel del encabezado.
        $data = $this->as($this->admin, 1)->getJson('/api/v1/dashboard/school')->assertOk()->json();

        $this->assertSame('Primario', $data['school_level']);
        $this->assertSame([4, 2], [$data['academic']['students'], $data['academic']['divisions_count']]);
        $this->assertSame([null, null], array_column($data['academic']['divisions'], 'level'));
        $this->assertSame([3, 4, 1], [$data['academic']['divisions'][0]['enrolled'], $data['academic']['divisions'][0]['capacity'], $data['academic']['divisions'][1]['enrolled']]);
        $this->assertSame(2, $data['staff']['active']);
        $this->assertSame([70000, 1], [$data['finance']['overdue_total'], $data['finance']['families_overdue']]);
    }

    public function test_blocks_follow_permissions_and_roles_without_finance_are_rejected(): void
    {
        $finance = $this->account('tesoreria', 'finance_admin', 1);
        $data = $this->as($finance, 1)->getJson('/api/v1/dashboard/school')->assertOk()->json();
        // Tesoreria ve la matricula agregada (tiene academic.enrollment.view), no el personal.
        $this->assertSame(4, $data['academic']['students']);
        $this->assertNull($data['staff']);
        $this->assertSame(70000, $data['finance']['overdue_total']);

        $this->as($this->account('preceptor', 'preceptor', 1), 1)->getJson('/api/v1/dashboard/school')->assertStatus(403);
        $this->as($this->account('docente', 'teacher', 1), 1)->getJson('/api/v1/dashboard/school')->assertStatus(403);
    }

    public function test_level_direction_sees_enrollment_and_staff_but_nothing_of_finance(): void
    {
        $director = $this->account('director', 'level_director', 1);
        $data = $this->as($director, 1)->getJson('/api/v1/dashboard/school')->assertOk()->json();
        $this->assertSame([4, 2], [$data['academic']['students'], $data['staff']['active']]);
        $this->assertNull($data['finance']);
        $this->assertStringNotContainsString('Familia', json_encode($data));
        $this->as($director, 2)->getJson('/api/v1/dashboard/school')->assertStatus(403);

        // El bootstrap le indica al frontend en que niveles hay inicio.
        $access = app(\Crater\Services\Access\AccessManager::class);
        $this->assertSame(['all' => false, 'levels' => [1 => true]], $access->frontendAccess($director->fresh())['school_dashboard']);
        $preceptor = $this->account('preceptor2', 'preceptor', 1);
        $this->assertSame(['all' => false, 'levels' => [1 => false]], $access->frontendAccess($preceptor->fresh())['school_dashboard']);
        $this->assertTrue($access->frontendAccess($this->admin->fresh())['school_dashboard']['all']);
    }

    public function test_home_view_has_no_invoicing_leftovers(): void
    {
        $view = file_get_contents(resource_path('assets/js/views/dashboard/Dashboard.vue'));
        $this->assertStringContainsString("'/api/v1/dashboard/school'", $view);
        foreach (['Presupuestos', 'Ventas', 'This year', 'DashboardChart', 'estimates'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $view);
        }
        $this->assertFileDoesNotExist(resource_path('assets/js/views/dashboard/DashboardChart.vue'));
    }
}
