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
        require_once database_path('migrations/2026_10_07_000000_extend_deploy_snapshots_audit.php');
        (new \ExtendDeploySnapshotsAudit())->up();
        require_once database_path('migrations/2026_09_01_000500_create_audit_logs_table.php');
        (new \CreateAuditLogsTable())->up();
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

    /** Emite evidencia ficticia con el mismo formato de la auditoria real. */
    private function auditChange(string $action, string $model, int $id, array $old = [], array $new = [], int $company = 1): int
    {
        return DB::table('audit_logs')->insertGetId(['company_id' => $company, 'school_level_id' => $new['school_level_id'] ?? $old['school_level_id'] ?? null,
            'action' => $action, 'auditable_type' => 'Crater\\Models\\'.$model, 'auditable_id' => $id,
            'old_values' => json_encode($old), 'new_values' => json_encode($new), 'created_at' => now()]);
    }

    public function test_audited_deletion_passes_and_is_reported_once(): void
    {
        Artisan::call('ena:smoke');
        $this->auditChange('invoice.deleted', 'Invoice', 1, ['school_level_id' => 1]);
        DB::table('invoices')->where('id', 1)->delete();
        $report = app(DeploySnapshotService::class)->check();
        $this->assertTrue($report['passed']);
        $this->assertSame(1, $report['explained_losses']['invoices:all:all']['audited']);
        DB::table('invoices')->where('id', 2)->delete();
        $this->assertFalse(app(DeploySnapshotService::class)->check()['passed']);
    }

    public function test_reassignments_and_student_relocations_follow_multiple_hops(): void
    {
        DB::table('students')->insert(['id' => 1, 'company_id' => 1, 'school_level_id' => 1]);
        Artisan::call('ena:smoke');
        $this->auditChange('level_reassigned', 'Invoice', 1, ['school_level_id' => 1], ['school_level_id' => 2]);
        DB::table('invoices')->where('id', 1)->update(['school_level_id' => 2]);
        foreach ([[1, 2], [2, 3]] as [$from, $to]) {
            $this->auditChange('student_relocated', 'Student', 1, ['school_level_id' => $from], ['school_level_id' => $to]);
        }
        DB::table('students')->where('id', 1)->update(['school_level_id' => 3]);
        $report = app(DeploySnapshotService::class)->check();
        $this->assertTrue($report['passed']);
        $this->assertCount(2, $report['explained_losses']['students:1:1']['evidence'][0]['audit_ids']);
        $this->assertArrayHasKey('invoices:1:1', $report['explained_losses']);
    }

    public function test_old_wrong_record_wrong_company_and_duplicate_events_cannot_cover_loss(): void
    {
        $this->auditChange('invoice.deleted', 'Invoice', 1, ['school_level_id' => 1]);
        Artisan::call('ena:smoke'); // consume el evento anterior
        $this->auditChange('invoice.deleted', 'Invoice', 2, ['school_level_id' => 1]); // otra identidad
        $this->auditChange('invoice.deleted', 'Invoice', 1, ['school_level_id' => 1], [], 2); // otra empresa
        DB::table('invoices')->where('id', 1)->delete();
        $this->assertFalse(app(DeploySnapshotService::class)->check()['passed']);
        $this->auditChange('invoice.deleted', 'Invoice', 1, ['school_level_id' => 1]);
        $this->auditChange('invoice.deleted', 'Invoice', 1, ['school_level_id' => 1]);
        DB::table('invoices')->where('id', 3)->delete();
        $report = app(DeploySnapshotService::class)->check();
        $this->assertFalse($report['passed']);
        $this->assertSame(1, $report['losses']['invoices:all:all']['unexplained']);
    }

    public function test_revocation_is_informational_and_never_allows_unrelated_data_loss(): void
    {
        Artisan::call('ena:smoke');
        $this->auditChange('role_revoked', 'User', 1);
        $report = app(DeploySnapshotService::class)->check();
        $this->assertTrue($report['passed']);
        $this->assertSame('role_revoked', $report['audit_events'][0]['action']);
        DB::table('invoices')->where('id', 1)->delete();
        $this->assertFalse(app(DeploySnapshotService::class)->check()['passed']);
    }

    /** Esquema minimo con autoridad real de AccessManager, sin falsificar el resolutor. */
    private function actor(): \Crater\Models\User
    {
        Schema::create('currencies', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->integer('company_id'); $t->integer('currency_id')->nullable();
            $t->string('email'); $t->string('password'); $t->boolean('is_active')->default(true);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->increments('id'); $t->integer('company_id'); $t->string('name'); $t->string('scope_type');
        });
        Schema::create('role_user', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('role_id'); $t->integer('company_id');
            $t->integer('school_level_id')->nullable(); $t->timestamp('revoked_at')->nullable();
            $t->date('starts_on')->nullable(); $t->date('ends_on')->nullable();
        });
        DB::table('users')->insert(['id' => 1, 'company_id' => 1, 'email' => 'admin@example.invalid',
            'password' => bcrypt('clave-ficticia-1234')]);
        DB::table('roles')->insert(['id' => 1, 'company_id' => 1, 'name' => 'total_admin', 'scope_type' => 'global']);
        DB::table('role_user')->insert(['user_id' => 1, 'role_id' => 1, 'company_id' => 1]);
        return \Crater\Models\User::find(1);
    }

    public function test_acceptance_requires_current_total_admin_and_audits_a_new_baseline(): void
    {
        $actor = $this->actor();
        $service = app(DeploySnapshotService::class);
        $service->check();
        DB::table('invoices')->where('id', 1)->delete();
        $failed = $service->check();
        DB::table('role_user')->update(['revoked_at' => now()]);
        try { $service->accept($failed['snapshot_id'], $actor, 'Revision manual'); $this->fail('No debe aceptar un rol revocado.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('administracion total', $e->getMessage()); }
        DB::table('role_user')->update(['revoked_at' => null]);
        $accepted = $service->accept($failed['snapshot_id'], $actor, 'Baja revisada por administracion');
        $this->assertGreaterThan($failed['snapshot_id'], $accepted['snapshot_id']);
        $this->assertFalse((bool) DB::table('deploy_snapshots')->where('id', $failed['snapshot_id'])->value('passed'));
        $audit = DB::table('audit_logs')->where('action', 'deploy_snapshot_accepted')->first();
        $this->assertSame(1, (int) $audit->user_id);
        $this->assertSame('Baja revisada por administracion', json_decode($audit->new_values, true)['reason']);
        $this->assertTrue($service->check()['passed']);
    }

    public function test_acceptance_rejects_stale_foreign_and_missing_reason(): void
    {
        $actor = $this->actor(); $service = app(DeploySnapshotService::class); $service->check();
        DB::table('invoices')->where('id', 4)->delete();
        $failed = $service->check();
        foreach (['', 'Revision manual'] as $reason) {
            try { $service->accept($failed['snapshot_id'], $actor, $reason); $this->fail('No debe aceptar.'); }
            catch (\RuntimeException $e) { $this->assertNotSame('', $e->getMessage()); }
        }
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'deploy_snapshot_accepted')->count());
        DB::table('invoices')->where('id', 1)->delete();
        try { $service->accept($failed['snapshot_id'], $actor, 'Revision manual'); $this->fail('Foto desactualizada.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('cambiaron', $e->getMessage()); }
        $this->assertSame(1, Artisan::call('ena:smoke:aceptar', ['snapshot' => $failed['snapshot_id'], '--motivo' => 'Revision', '--usuario' => 'admin@example.invalid', '--no-interaction' => true]));
    }

    public function test_interactive_accept_command_prompts_for_identity_and_audits_the_baseline(): void
    {
        $this->actor();
        $service = app(DeploySnapshotService::class); $service->check();
        DB::table('invoices')->where('id', 1)->delete(); $failed = $service->check();
        $command = new \Crater\Console\Commands\AcceptDeploySnapshot();
        $command->setLaravel($this->app);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $tester->setInputs(['admin@example.invalid', 'clave-ficticia-1234']);
        $this->assertSame(0, $tester->execute(['snapshot' => $failed['snapshot_id'], '--motivo' => 'Revision interactiva'], ['interactive' => true]));
        $this->assertTrue($service->check()['passed']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'deploy_snapshot_accepted')->count());
        $this->assertStringNotContainsString('clave-ficticia-1234', $tester->getDisplay());
    }

    public function test_audit_failure_rolls_back_baseline_acceptance(): void
    {
        $actor = $this->actor(); $service = app(DeploySnapshotService::class); $service->check();
        DB::table('invoices')->where('id', 1)->delete(); $failed = $service->check();
        \Crater\Models\AuditLog::creating(function ($log) { if ($log->action === 'deploy_snapshot_accepted') { throw new \RuntimeException('auditoria caida'); } });
        try { $service->accept($failed['snapshot_id'], $actor, 'Revision'); $this->fail('Debe revertir.'); }
        catch (\RuntimeException $e) { $this->assertSame('auditoria caida', $e->getMessage()); }
        finally { \Crater\Models\AuditLog::flushEventListeners(); }
        $this->assertSame(2, DB::table('deploy_snapshots')->count());
        $this->assertFalse($service->check()['passed']);
    }

    public function test_rotating_app_key_does_not_change_backup_or_historical_decryption(): void
    {
        $service = $this->archiveService();
        $report = $service->backup();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $record = json_decode(Storage::disk('ena_backups')->get($report['key'].'.json'), true);
        $zip = new \ZipArchive(); $zip->open(Storage::disk('ena_backups')->path($report['key']));
        $zip->setPassword($service->restoreEncryptionKey($record));
        $this->assertSame(str_repeat('SQL FICTICIO; ', 20), $zip->getFromName('database.sql')); $zip->close();
        config(['ena-operations.previous_encryption_keys' => ['legacy' => str_repeat('antigua-', 8)]]);
        $this->assertSame(str_repeat('antigua-', 8), $service->restoreEncryptionKey([]));
    }

    public function test_backup_refuses_missing_or_reused_app_key(): void
    {
        $service = $this->archiveService();
        foreach (['', str_repeat('a', 32), 'base64:'.base64_encode(str_repeat('a', 32))] as $key) {
            config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'ena-operations.encryption_key' => $key]);
            try { $service->encryptionKey(); $this->fail('No debe reutilizar APP_KEY.'); }
            catch (BackupOperationException $e) { $this->assertStringContainsString('independiente', $e->getMessage()); }
        }
    }

    public function test_legacy_key_is_preserved_once_and_survives_app_key_rotation(): void
    {
        $service = $this->archiveService(); $old = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $old]);
        $this->assertTrue($service->separateLegacyKey()['passed']);
        $wrapped = Storage::disk('ena_backups')->get('suiteena/testing/keyring/legacy.key.enc');
        $this->assertStringNotContainsString($old, $wrapped);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertSame($old, $service->restoreEncryptionKey([]));
        $this->assertTrue($service->separateLegacyKey()['already_preserved']);
        $this->assertSame($wrapped, Storage::disk('ena_backups')->get('suiteena/testing/keyring/legacy.key.enc'));
    }

    /** Simula Drive pero conserva las requests reales y la descarga en disco. */
    private function drive(bool $corrupt = false, int $failure = 0): array
    {
        config(['ena-operations.external_drive' => ['folder_id' => 'escuela_fixture', 'client_id' => 'fixture',
            'client_secret' => 'secreto-ficticio', 'refresh_token' => 'token-ficticio']]);
        $requests = new \ArrayObject(); $archive = '';
        $handler = function ($request, array $options) use ($requests, &$archive, $corrupt, $failure) {
            $requests[] = ['method' => $request->getMethod(), 'url' => (string) $request->getUri()];
            $n = count($requests);
            if ($n === $failure) { return \GuzzleHttp\Promise\promise_for(new \GuzzleHttp\Psr7\Response(403, [], 'secret-provider-error')); }
            if ($n === 1) { $response = new \GuzzleHttp\Psr7\Response(200, [], '{"access_token":"fixture"}'); }
            elseif ($n === 2) { $response = new \GuzzleHttp\Psr7\Response(200, [], '{"mimeType":"application/vnd.google-apps.folder","capabilities":{"canAddChildren":true}}'); }
            elseif ($n === 3 || $n === 6) { $response = new \GuzzleHttp\Psr7\Response(200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=fixture']); }
            elseif ($n === 4) { $archive = (string) $request->getBody(); $response = new \GuzzleHttp\Psr7\Response(200, [], '{"id":"zip_fixture"}'); }
            elseif ($n === 5) {
                file_put_contents($options['sink'], $corrupt ? 'corrupto' : $archive);
                $response = new \GuzzleHttp\Psr7\Response(200, [], $corrupt ? 'corrupto' : $archive);
            } else { $response = new \GuzzleHttp\Psr7\Response(200, [], '{"id":"manifest_fixture"}'); }
            return \GuzzleHttp\Promise\promise_for($response);
        };
        return [new \Crater\Services\Data\GoogleDriveBackupDestination(new \GuzzleHttp\Client(['handler' => $handler, 'http_errors' => false])), $requests];
    }

    public function test_external_copy_transfers_latest_ciphertext_and_verifies_download_sha256(): void
    {
        $service = $this->archiveService();
        \Carbon\Carbon::setTestNow('2026-10-07 06:00:00');
        try {
            $first = $service->backup();
            \Carbon\Carbon::setTestNow('2026-10-08 06:00:00');
            $last = $service->backup(); [$drive, $requests] = $this->drive();
            $report = $service->externalCopy($drive);
            $this->assertTrue($report['passed']); $this->assertSame($last['key'], $report['key']);
            $this->assertSame($last['archive_sha256'], $report['archive_sha256']);
            $this->assertSame('manifest_fixture', $report['manifest_file_id']);
            $this->assertCount(7, $requests);
            $this->assertTrue(Storage::disk('ena_backups')->exists($first['key']));
            $this->assertCount(1, Storage::disk('ena_backups')->files('suiteena/testing/external-copies'));
        } finally { \Carbon\Carbon::setTestNow(); }
    }

    public function test_corrupted_external_copy_and_drive_denial_never_publish_success(): void
    {
        foreach ([[true, 0], [false, 2], [false, 7]] as [$corrupt, $failure]) {
            $service = $this->archiveService(); $backup = $service->backup(); [$drive, $requests] = $this->drive($corrupt, $failure);
            try { $service->externalCopy($drive); $this->fail('No debe publicar evidencia aprobada.'); }
            catch (BackupOperationException $e) { $this->assertStringNotContainsString('secret-provider-error', $e->getMessage()); }
            $this->assertSame([], Storage::disk('ena_backups')->files('suiteena/testing/external-copies'));
            $this->assertTrue(Storage::disk('ena_backups')->exists($backup['key']));
            if ($corrupt) { $this->assertCount(5, $requests); }
        }
    }

    public function test_external_copy_requires_drive_configuration_and_valid_source_checksum(): void
    {
        $service = $this->archiveService(); $backup = $service->backup(); [$drive, $requests] = $this->drive();
        Storage::disk('ena_backups')->put($backup['key'], 'corrupto');
        try { $service->externalCopy($drive); $this->fail('No debe subir una fuente corrupta.'); }
        catch (BackupOperationException $e) { $this->assertStringContainsString('checksum', $e->getMessage()); }
        $this->assertCount(0, $requests);
        config(['ena-operations.external_drive.folder_id' => null]);
        $this->expectException(BackupOperationException::class);
        $service->externalCopy($drive);
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
