<?php

// Prueba real del guardia sobre staging, dentro de una transaccion con rollback.
// No requiere PHPUnit en la imagen productiva ni deja bajas o fotos de ensayo.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Crater\Models\AuditLog;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Services\Data\DeploySnapshotService;
use Crater\Services\Data\MySqlBackupService;
use Illuminate\Support\Facades\DB;

if (! app()->environment('staging') || config('database.connections.'.config('database.default').'.database') !== 'krater_staging') {
    fwrite(STDERR, "Solo staging aislado.\n"); exit(1);
}
$checks = [];
$assert = function ($condition, $name) use (&$checks) {
    if (! $condition) { throw new RuntimeException('Fallo: '.$name); }
    $checks[] = $name;
};
$before = app(DeploySnapshotService::class)->capture();
$depth = DB::transactionLevel();
DB::beginTransaction();
try {
    $service = app(DeploySnapshotService::class);
    $baseline = $service->check();
    $assert($baseline['passed'], 'baseline_actual_aprobada');
    $row = DB::table('invoices')->whereNotNull('school_level_id')->first();
    $assert((bool) $row, 'fixture_factura_existente');
    DB::table('invoices')->where('id', $row->id)->update(['school_level_id' => null]);
    $failed = $service->check();
    $assert(! $failed['passed'], 'reubicacion_sin_auditoria_bloquea');
    AuditLog::create(['company_id' => $row->company_id, 'school_level_id' => null,
        'action' => 'level_reassigned', 'auditable_type' => 'Crater\\Models\\Invoice', 'auditable_id' => $row->id,
        'old_values' => ['school_level_id' => $row->school_level_id], 'new_values' => ['school_level_id' => null],
        'severity' => 'high', 'created_at' => now()]);
    $explained = $service->check();
    $assert($explained['passed'] && ! empty($explained['explained_losses']), 'reubicacion_auditada_aceptada_y_reportada');
    DB::table('invoices')->where('id', $row->id)->update(['school_level_id' => $row->school_level_id]);
    AuditLog::create(['company_id' => $row->company_id, 'school_level_id' => $row->school_level_id,
        'action' => 'level_reassigned', 'auditable_type' => 'Crater\\Models\\Invoice', 'auditable_id' => $row->id,
        'old_values' => ['school_level_id' => null], 'new_values' => ['school_level_id' => $row->school_level_id],
        'severity' => 'high', 'created_at' => now()]);
    $assert($service->check()['passed'], 'recuperacion_de_conteos');
    DB::table('invoices')->where('id', $row->id)->update(['school_level_id' => null]);
    $failed = $service->check();
    $assert(! $failed['passed'], 'evento_anterior_no_reutilizable');
    $actor = User::all()->first(function ($user) use ($row) {
        return (int) $user->company_id === (int) $row->company_id && app(AccessManager::class)->isTotalAdmin($user);
    });
    $assert((bool) $actor, 'administracion_total_vigente');
    $accepted = $service->accept($failed['snapshot_id'], $actor, 'Ensayo staging fila 6: transaccion siempre revertida');
    $assert($accepted['passed'] && AuditLog::where('action', 'deploy_snapshot_accepted')->where('auditable_id', $accepted['snapshot_id'])->exists(), 'aceptacion_nueva_base_auditada');
    $assert($service->check()['passed'], 'destrabado_sin_otro_deploy');
    $assert(! (bool) DB::table('deploy_snapshots')->where('id', $failed['snapshot_id'])->value('passed'), 'foto_fallida_preservada');
    $ordinary = User::all()->first(function ($user) { return ! app(AccessManager::class)->isTotalAdmin($user); });
    $assert((bool) $ordinary, 'cuenta_sin_administracion_total');
    try { $service->accept($failed['snapshot_id'], $ordinary, 'No autorizado'); throw new LogicException('Aceptacion indebida'); }
    catch (RuntimeException $e) { $assert(strpos($e->getMessage(), 'administracion total') !== false, 'cuenta_ordinaria_rechazada'); }
} catch (Throwable $e) {
    fwrite(STDERR, 'No se aprobo el ensayo staging ('.get_class($e).')'.
        (strpos($e->getMessage(), 'Fallo:') === 0 ? ': '.$e->getMessage() : '').".\n");
    $exit = 1;
} finally {
    while (DB::transactionLevel() > $depth) { DB::rollBack(); }
}
if (! empty($exit)) { exit($exit); }
$assert($before === app(DeploySnapshotService::class)->capture(), 'rollback_preserva_conteos_operativos');
// El sobre legacy se conserva de verdad; APP_KEY se modifica solo en memoria.
$backups = app(MySqlBackupService::class);
$old = config('app.key');
$legacy = $backups->restoreEncryptionKey([]);
try {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    $assert(hash_equals($legacy, $backups->restoreEncryptionKey([])), 'clave_legacy_independiente_de_rotacion_app_key');
    $assert($backups->encryptionKey() !== $old, 'clave_backup_propia');
} finally { config(['app.key' => $old]); unset($legacy); }
echo 'SuiteEna revision fila 6: '.json_encode(['passed' => true, 'checks' => $checks, 'rollback' => true,
    'drive_live_test' => 'pending_school_folder_and_oauth', 'revision' => config('ena-operations.revision')], JSON_UNESCAPED_SLASHES)."\n";
