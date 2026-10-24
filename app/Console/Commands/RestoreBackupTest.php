<?php

namespace Crater\Console\Commands;

use Crater\Services\Data\MySqlBackupService;
use Crater\Services\Data\BackupOperationException;
use Illuminate\Console\Command;
use Throwable;

class RestoreBackupTest extends Command
{
    protected $signature = 'ena:restore-test';
    protected $description = 'Restaura el ultimo backup remoto en MySQL aislado y audita niveles (solo staging)';

    public function handle(MySqlBackupService $service)
    {
        try {
            $this->line('SuiteEna restore test: '.json_encode($service->restoreTest(), JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Fallo la restauracion de prueba ('.class_basename($e).').');

            if ($e instanceof BackupOperationException) { $this->error($e->getMessage()); }

            return self::FAILURE;
        }
    }
}
