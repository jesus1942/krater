<?php

namespace Tests\Isolation;

use Crater\Console\Commands\ReassignSchoolLevel;
use Crater\Enums\Permission;
use Crater\Services\Data\LevelReconciliationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;
use Throwable;

class LevelReconciliationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->seedScenario();
    }

    public function testDryRunCommandDoesNotWriteAnything(): void
    {
        $writes = [];

        DB::listen(function (QueryExecuted $query) use (&$writes) {
            $sql = strtolower(ltrim($query->sql));

            if (preg_match('/^(insert|update|delete|replace|create|alter|drop|truncate)\b/', $sql)) {
                $writes[] = $query->sql;
            }
        });

        $command = new ReassignSchoolLevel(new LevelReconciliationService());
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            'modelo' => 'Estimate',
            'ids' => [1],
            '--nivel' => 2,
            '--company' => 1,
        ]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('SIMULACION - no se escribieron datos.', $tester->getDisplay());
        $this->assertNull(DB::table('estimates')->where('id', 1)->value('school_level_id'));
        $this->assertSame([], $writes);
    }

    public function testApplyChangesLevelAndCreatesStrictAuditEntry(): void
    {
        $service = new LevelReconciliationService();

        $result = $service->apply(
            'Estimate',
            [1],
            2,
            1,
            null,
            'Clasificación histórica confirmada'
        );

        $this->assertSame(2, DB::table('estimates')->where('id', 1)->value('school_level_id'));
        $this->assertSame(2, $result[0]['to_school_level_id']);

        $audit = DB::table('audit_logs')
            ->where('action', 'level_reassigned')
            ->where('auditable_type', 'Crater\\Models\\Estimate')
            ->where('auditable_id', 1)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(1, (int) $audit->company_id);
        $this->assertSame(2, (int) $audit->school_level_id);
        $this->assertSame('high', $audit->severity);

        $old = json_decode($audit->old_values, true);
        $new = json_decode($audit->new_values, true);

        $this->assertNull($old['school_level_id']);
        $this->assertSame(2, $new['school_level_id']);
        $this->assertSame('Clasificación histórica confirmada', $new['reason']);
        $this->assertSame('level_reconciliation', $new['source']);
    }

    public function testItRefusesToOverwriteValidLevelOrContradictCanonicalLinks(): void
    {
        $service = new LevelReconciliationService();

        try {
            $service->preview('Estimate', [2], 2, 1);
            $this->fail('Debió rechazar un registro que ya tiene nivel válido.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ya tiene un nivel válido', $e->getMessage());
        }

        try {
            $service->preview('Invoice', [3], 2, 1);
            $this->fail('Debió rechazar un nivel contrario al alumno vinculado.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('alumno', $e->getMessage());
        }

        try {
            $service->preview('Estimate', [1], 3, 1);
            $this->fail('Debió rechazar un nivel de otra empresa.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('no pertenece a la empresa', $e->getMessage());
        }
    }

    public function testAuditFailureRollsBackTheLevelChange(): void
    {
        $service = new LevelReconciliationService();

        Schema::drop('audit_logs');

        try {
            $service->apply(
                'Estimate',
                [4],
                2,
                1,
                null,
                'Debe revertirse si no hay bitácora'
            );
            $this->fail('La aplicación debía fallar sin la tabla de auditoría.');
        } catch (Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertNull(DB::table('estimates')->where('id', 4)->value('school_level_id'));
    }

    public function testPermissionAndUiAreRestrictedToTotalAdministration(): void
    {
        $this->assertSame('data.reconcile', Permission::DATA_RECONCILE);
        $this->assertTrue(Permission::isTotalAdminOnly(Permission::DATA_RECONCILE));
        $this->assertTrue(Permission::isAudited(Permission::DATA_RECONCILE));

        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/api.php');
        $settings = file_get_contents($root.'/resources/assets/js/views/settings/SettingsIndex.vue');
        $page = file_get_contents($root.'/resources/assets/js/views/settings/DataReconciliation.vue');
        $router = file_get_contents($root.'/resources/assets/js/router.js');

        $this->assertStringContainsString("permission:data.reconcile", $routes);
        $this->assertStringContainsString("/api/v1/data-reconciliation/preview", $page);
        $this->assertStringContainsString("/api/v1/data-reconciliation/apply", $page);
        $this->assertStringContainsString("Toda la institución", $page);
        $this->assertStringContainsString("/admin/settings/data-reconciliation", $settings);
        $this->assertStringContainsString("totalAdminOnly: true", $settings);
        $this->assertStringContainsString("path: 'data-reconciliation'", $router);
    }

    protected function createSchema(): void
    {
        Schema::create('school_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('enabled')->default(true);
        });

        Schema::create('estimates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('estimate_number')->nullable();
            $table->date('estimate_date')->nullable();
            $table->unsignedBigInteger('total')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('student_id')->nullable();
            $table->unsignedInteger('enrollment_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->unsignedBigInteger('total')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 150);
            $table->string('auditable_type', 190)->nullable();
            $table->unsignedInteger('auditable_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('severity', 20)->default('low');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function seedScenario(): void
    {
        DB::table('school_levels')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Primario', 'code' => 'primary', 'enabled' => 1],
            ['id' => 2, 'company_id' => 1, 'name' => 'Secundario', 'code' => 'secondary', 'enabled' => 1],
            ['id' => 3, 'company_id' => 2, 'name' => 'Nivel ajeno', 'code' => 'foreign', 'enabled' => 1],
        ]);

        DB::table('estimates')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'school_level_id' => null,
                'estimate_number' => 'PRE-000001',
                'estimate_date' => '2026-08-16',
                'total' => 170608185,
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'school_level_id' => 1,
                'estimate_number' => 'PRE-000002',
                'estimate_date' => '2026-09-01',
                'total' => 1000,
            ],
            [
                'id' => 4,
                'company_id' => 1,
                'school_level_id' => null,
                'estimate_number' => 'PRE-000004',
                'estimate_date' => '2026-09-02',
                'total' => 1000,
            ],
        ]);

        DB::table('students')->insert([
            ['id' => 10, 'company_id' => 1, 'school_level_id' => 1, 'first_name' => 'Alumno', 'last_name' => 'Prueba'],
        ]);

        DB::table('invoices')->insert([
            [
                'id' => 3,
                'company_id' => 1,
                'school_level_id' => null,
                'student_id' => 10,
                'invoice_number' => 'FAC-000003',
                'invoice_date' => '2026-09-01',
                'total' => 5000,
            ],
        ]);
    }
}
