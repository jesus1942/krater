<?php

namespace Tests\Isolation;

use Crater\Console\Commands\AuditSchoolLevels;
use Crater\Services\Data\LevelAuditService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class LevelAuditReadOnlyTest extends TestCase
{
    public function test_audit_is_read_only_and_reports_level_anomalies(): void
    {
        $this->useInMemoryDatabase();
        $this->createMinimalSchema();
        $this->seedAuditScenario();

        $writes = [];

        DB::listen(function (QueryExecuted $query) use (&$writes) {
            $sql = strtolower(ltrim($query->sql));

            if (preg_match('/^(insert|update|delete|replace|create|alter|drop|truncate)\b/', $sql)) {
                $writes[] = $query->sql;
            }
        });

        $command = new AuditSchoolLevels(new LevelAuditService());
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            '--company' => 1,
            '--json' => true,
        ]);

        $this->assertSame(0, $status);

        $report = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($report);
        $this->assertTrue($report['read_only']);
        $this->assertCount(1, $report['companies']);

        $invoice = $report['companies'][0]['models']['Invoice'];
        $this->assertSame(4, $invoice['total']);
        $this->assertSame(1, $invoice['valid_level']);
        $this->assertSame(1, $invoice['null_level']);
        $this->assertSame(1, $invoice['missing_level']);
        $this->assertSame(1, $invoice['foreign_company_level']);

        $suggestions = collect($report['companies'][0]['economic_suggestions']['Invoice'])->keyBy('id');

        $this->assertSame('unico', $suggestions[1]['status']);
        $this->assertSame(1, $suggestions[1]['suggested_school_level_id']);
        $this->assertSame('unico', $suggestions[2]['status']);
        $this->assertSame(1, $suggestions[2]['suggested_school_level_id']);
        $this->assertSame('unico', $suggestions[3]['status']);
        $this->assertSame(1, $suggestions[3]['suggested_school_level_id']);

        $this->assertSame([], $writes, 'ena:auditar-niveles ejecuto una query de escritura.');
    }

    protected function useInMemoryDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function createMinimalSchema(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('school_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->string('name');
        });

        foreach ([
            'study_plans',
            'course_sections',
            'grade_levels',
            'academic_years',
            'divisions',
            'items',
            'subjects',
        ] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->unsignedInteger('school_level_id')->nullable();
            });
        }

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('guardian_id')->nullable();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('student_id')->nullable();
        });

        Schema::create('estimates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('student_id')->nullable();
            $table->unsignedInteger('enrollment_id')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('student_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
        });
    }

    protected function seedAuditScenario(): void
    {
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'ENA'],
            ['id' => 2, 'name' => 'Otra empresa'],
        ]);

        DB::table('school_levels')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Primario'],
            ['id' => 2, 'company_id' => 2, 'name' => 'Nivel ajeno'],
        ]);

        DB::table('students')->insert([
            ['id' => 10, 'company_id' => 1, 'school_level_id' => 1, 'guardian_id' => 50],
        ]);

        DB::table('enrollments')->insert([
            ['id' => 20, 'company_id' => 1, 'school_level_id' => 1, 'student_id' => 10],
        ]);

        DB::table('invoices')->insert([
            ['id' => 1, 'company_id' => 1, 'school_level_id' => null, 'user_id' => 50, 'student_id' => 10, 'enrollment_id' => 20],
            ['id' => 2, 'company_id' => 1, 'school_level_id' => 999, 'user_id' => 50, 'student_id' => null, 'enrollment_id' => null],
            ['id' => 3, 'company_id' => 1, 'school_level_id' => 2, 'user_id' => 50, 'student_id' => null, 'enrollment_id' => null],
            ['id' => 4, 'company_id' => 1, 'school_level_id' => 1, 'user_id' => 50, 'student_id' => 10, 'enrollment_id' => 20],
        ]);
    }
}
