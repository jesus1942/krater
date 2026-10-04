<?php

namespace Crater\Console\Commands;

use Crater\Services\Data\MySqlBackupService;
use Crater\Services\Data\BackupOperationException;
use Illuminate\Console\Command;
use Throwable;

class InstitutionBackup extends Command
{
    protected $signature = 'ena:backup';
    protected $description = 'Backup MySQL cifrado a S3 privado con retencion 7 diarios / 4 semanales / 6 mensuales';

    public function handle(MySqlBackupService $service)
    {
        try {
            $this->line('SuiteEna backup: '.json_encode($service->backup(), JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Fallo el backup ('.class_basename($e).'). No se confirma ni se podan backups sin verificar.');

            if ($e instanceof BackupOperationException) { $this->error($e->getMessage()); }

            return self::FAILURE;
        }
    }
}
