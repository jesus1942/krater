<?php

namespace Tests\Isolation;

use Crater\Services\Data\BackupRetention;
use Crater\Services\Data\DeploySnapshotService;
use Crater\Services\Data\MySqlBackupService;
use Crater\Services\Data\BackupOperationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

class DeployOperationsTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('companies', function (Blueprint $table) { $table->increments('id'); });
        Schema::create('school_levels', function (Blueprint $table) {
            $table->increments('id'); $table->integer('company_id');
        });
        Schema::create('migrations', function (Blueprint $table) { $table->string('migration'); });
        foreach (array_merge(DeploySnapshotService::TABLES, ['staff_assignments']) as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->increments('id'); $table->integer('company_id'); $table->integer('school_level_id')->nullable();
                if ($name === 'staff_assignments') { $table->integer('staff_member_id'); }
            });
        }
        Schema::create('staff_members', function (Blueprint $table) {
            $table->increments('id'); $table->integer('company_id');
        });
        require_once database_path('migrations/2026_10_04_000000_create_deploy_snapshots.php');
        (new \CreateDeploySnapshots())->up();
        DB::table('companies')->insert([['id' => 1], ['id' => 2]]);
        DB::table('school_levels')->insert([
            ['id' => 1, 'company_id' => 1], ['id' => 2, 'company_id' => 1],
            ['id' => 3, 'company_id' => 1], ['id' => 4, 'company_id' => 2],
        ]);
        DB::table('invoices')->insert([
            ['company_id' => 1, 'school_level_id' => 1], ['company_id' => 1, 'school_level_id' => 2],
            ['company_id' => 1, 'school_level_id' => 3], ['company_id' => 2, 'school_level_id' => 4],
            ['company_id' => 1, 'school_level_id' => null],
        ]);
    }

    public function test_baseline_covers_three_levels_other_company_and_null(): void
    {
        $this->assertSame(0, Artisan::call('ena:smoke'));
        $snapshot = DB::table('deploy_snapshots')->first();
        $counts = json_decode($snapshot->counts, true);
        $this->assertSame(5, $counts['invoices:all:all']);
        $this->assertSame(4, $counts['invoices:1:all']);
        foreach ([1, 2, 3, 'null'] as $level) { $this->assertSame(1, $counts['invoices:1:'.$level]); }
        $this->assertSame(1, $counts['invoices:2:4']);
        $this->assertSame(0, $counts['students:1:2']);
        $this->assertTrue((bool) $snapshot->passed);
    }

    public function test_missing_group_and_global_loss_abort_without_resetting_baseline(): void
    {
        $this->assertSame(0, Artisan::call('ena:smoke'));
        DB::table('invoices')->where('school_level_id', 4)->delete();
        $this->assertSame(1, Artisan::call('ena:smoke'));
        $failed = DB::table('deploy_snapshots')->orderByDesc('id')->first();
        $this->assertSame(1, (int) $failed->previous_id);
        $this->assertFalse((bool) $failed->passed);
        $this->assertArrayHasKey('invoices:2:4', json_decode($failed->losses, true));
        $this->assertSame(1, Artisan::call('ena:smoke'));
        $this->assertSame(1, (int) DB::table('deploy_snapshots')->orderByDesc('id')->value('previous_id'));
        DB::table('invoices')->insert(['company_id' => 2, 'school_level_id' => 4]);
        $this->assertSame(0, Artisan::call('ena:smoke'));
    }

    public function test_level_relocation_is_detected_even_if_global_total_stays_the_same(): void
    {
        Artisan::call('ena:smoke');
        DB::table('invoices')->where('school_level_id', 1)->update(['school_level_id' => 2]);
        $this->assertSame(1, Artisan::call('ena:smoke'));
        $losses = json_decode(DB::table('deploy_snapshots')->orderByDesc('id')->value('losses'), true);
        $this->assertArrayHasKey('invoices:1:1', $losses);
        $this->assertArrayNotHasKey('invoices:all:all', $losses);
    }

    public function test_staff_is_counted_once_per_level_and_institution(): void
    {
        DB::table('staff_members')->insert([['id' => 1, 'company_id' => 1], ['id' => 2, 'company_id' => 1]]);
        DB::table('staff_assignments')->insert([
            ['company_id' => 1, 'staff_member_id' => 1, 'school_level_id' => 1],
            ['company_id' => 1, 'staff_member_id' => 1, 'school_level_id' => 1],
            ['company_id' => 1, 'staff_member_id' => 1, 'school_level_id' => 2],
        ]);
        $counts = app(DeploySnapshotService::class)->capture();
        $this->assertSame(2, $counts['staff_members:1:all']);
        $this->assertSame(1, $counts['staff_members:1:1']);
        $this->assertSame(1, $counts['staff_members:1:2']);
        $this->assertSame(1, $counts['staff_members:1:null']);
    }

    public function test_only_a_new_executed_declaration_with_bounded_counts_allows_loss_once(): void
    {
        Artisan::call('ena:smoke');
        $migration = '2026_10_04_000000_create_deploy_snapshots';
        config(['deploy-data-changes' => [['migration' => $migration, 'reason' => 'Depuracion ficticia revisada',
            'allowances' => ['invoices:1:1' => 1, 'invoices:1:all' => 1, 'invoices:all:all' => 1]]]]);
        DB::table('invoices')->where('school_level_id', 1)->delete();
        $this->assertSame(1, Artisan::call('ena:smoke')); // No ejecutada.
        DB::table('migrations')->insert(['migration' => $migration]);
        $this->assertSame(0, Artisan::call('ena:smoke'));
        DB::table('invoices')->insert(['company_id' => 1, 'school_level_id' => 1]);
        $this->assertSame(0, Artisan::call('ena:smoke'));
        DB::table('invoices')->where('school_level_id', 1)->delete();
        $this->assertSame(1, Artisan::call('ena:smoke')); // No reutilizable.
    }

    public function test_missing_table_fails_without_accepting_an_empty_baseline(): void
    {
        Schema::drop('payments');
        $this->assertSame(1, Artisan::call('ena:smoke'));
        $this->assertSame(0, DB::table('deploy_snapshots')->count());
    }

    public function test_retention_keeps_seven_daily_four_weekly_and_six_monthly_representatives(): void
    {
        $records = [];
        for ($i = 0; $i < 240; $i++) {
            $date = \Carbon\CarbonImmutable::parse('2026-10-04T06:00:00Z')->subDays($i);
            $records[] = ['key' => 'backup-'.$i, 'created_at' => $date->toIso8601String()];
        }
        $keep = (new BackupRetention())->keep($records);
        $expected = array_map(function ($i) { return 'backup-'.$i; }, [0, 1, 2, 3, 4, 5, 6, 7, 14, 21, 34, 65, 96, 126]);
        $this->assertEqualsCanonicalizing($expected, $keep);
        foreach (range(0, 6) as $i) { $this->assertContains('backup-'.$i, $keep); }
        $this->assertNotContains('backup-239', $keep);
        $this->assertLessThanOrEqual(17, count($keep));
    }

    public function test_restore_refuses_production_before_touching_database_or_storage(): void
    {
        $this->app['env'] = 'production';
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:restore-test'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_restore_refuses_the_live_database_host(): void
    {
        $this->app['env'] = 'staging';
        config(['database.connections.sqlite.database' => 'krater_staging', 'database.connections.sqlite.host' => 'mysql-staging',
            'ena-operations.restore_connection.host' => 'mysql-staging']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:restore-test'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_backup_has_no_local_fallback_without_bucket_configuration(): void
    {
        config(['filesystems.disks.ena_backups.endpoint' => null]);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:backup'));
        $this->assertSame([], DB::getQueryLog());
    }

    protected function archiveService(bool $changed = false): MySqlBackupService
    {
        Storage::fake('ena_backups');
        config(['filesystems.disks.ena_backups' => ['driver' => 's3', 'endpoint' => 'https://bucket.example.invalid',
            'bucket' => 'test', 'region' => 'test', 'key' => 'ficticia', 'secret' => 'ficticia'],
            'ena-operations.encryption_key' => str_repeat('clave-ficticia-', 4),
            'ena-operations.backup_source_environment' => 'testing',
            'ena-operations.backup_prefix' => 'suiteena/testing']);

        return new class($changed) extends MySqlBackupService {
            private $changed;
            private $calls = 0;
            public function __construct($changed) { $this->changed = $changed; }
            public function version(): string { return '9.7.2'; }
            public function fingerprints(): array {
                return ['students' => ['count' => $this->changed && $this->calls++ ? 0 : 1, 'sha256' => hash('sha256', 'fixture')]];
            }
            protected function run(array $arguments, $input = null): void {
                foreach ($arguments as $argument) {
                    if (strpos($argument, '--result-file=') === 0) {
                        file_put_contents(substr($argument, strlen('--result-file=')), str_repeat('SQL FICTICIO; ', 20));
                    }
                }
            }
            public function verifyDownload($key, $path, $hash): void { $this->download($key, $path, $hash); }
        };
    }

    public function test_remote_archive_is_encrypted_and_download_detects_tampering(): void
    {
        $service = $this->archiveService();
        $report = $service->backup();
        $disk = Storage::disk('ena_backups');
        $this->assertTrue($report['passed']);
        $this->assertTrue($disk->exists($report['key'].'.json'));
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($disk->path($report['key'])) === true);
        $this->assertFalse($zip->getFromName('database.sql'));
        $zip->setPassword(config('ena-operations.encryption_key'));
        $this->assertSame(str_repeat('SQL FICTICIO; ', 20), $zip->getFromName('database.sql'));
        $zip->close();
        $disk->put($report['key'], $disk->get($report['key']).'alterado');
        $path = tempnam(sys_get_temp_dir(), 'ena-check-');
        try {
            $this->expectException(BackupOperationException::class);
            $this->expectExceptionMessage('checksum');
            $service->verifyDownload($report['key'], $path, $report['archive_sha256']);
        } finally { unlink($path); }
    }

    public function test_changed_data_during_dump_does_not_publish_a_backup_or_delete_old_objects(): void
    {
        $service = $this->archiveService(true);
        $disk = Storage::disk('ena_backups');
        $disk->put('otra-institucion/backup.zip', 'conservar');
        $disk->put('suiteena/testing/preexistente.zip', 'conservar');
        try {
            $service->backup();
            $this->fail('No debe aceptar un dump cuyos datos cambiaron.');
        } catch (BackupOperationException $e) {
            $this->assertStringContainsString('cambiaron', $e->getMessage());
        }
        $this->assertEqualsCanonicalizing(['otra-institucion/backup.zip', 'suiteena/testing/preexistente.zip'], $disk->allFiles());
    }

    public function test_staging_cannot_restore_production_backups_or_query_the_source(): void
    {
        $this->app['env'] = 'staging';
        config(['ena-operations.backup_source_environment' => 'production']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:restore-test'));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_restore_refuses_a_host_other_than_the_designated_restore_server(): void
    {
        $this->app['env'] = 'staging';
        config(['ena-operations.backup_source_environment' => 'staging',
            'database.connections.sqlite.database' => 'krater_staging', 'database.connections.sqlite.host' => 'mysql-staging',
            'ena-operations.restore_connection.host' => 'mysql.railway.internal',
            'ena-operations.restore_connection.username' => 'root', 'ena-operations.restore_connection.password' => 'ficticia']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(1, Artisan::call('ena:restore-test'));
        $this->assertSame([], DB::getQueryLog());
    }
}
