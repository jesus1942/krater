<?php

namespace Crater\Console\Commands;

use Crater\Services\Data\GoogleDriveBackupDestination;
use Crater\Services\Data\MySqlBackupService;
use Illuminate\Console\Command;
use Throwable;

class CopyBackupExternal extends Command
{
    protected $signature = 'ena:backup:externo';
    protected $description = 'Copia el ultimo backup cifrado a Google Drive y verifica SHA-256';

    /** Cron independiente: un fallo de Drive no altera backups ni la disponibilidad web. */
    public function handle(MySqlBackupService $service, GoogleDriveBackupDestination $destination)
    {
        try {
            $this->line('SuiteEna copia externa: '.json_encode($service->externalCopy($destination), JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('No se pudo verificar la copia externa ('.class_basename($e).').');
            return self::FAILURE;
        }
    }
}
