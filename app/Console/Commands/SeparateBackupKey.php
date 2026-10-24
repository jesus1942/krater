<?php

namespace Crater\Console\Commands;

use Crater\Services\Data\MySqlBackupService;
use Illuminate\Console\Command;
use Throwable;

class SeparateBackupKey extends Command
{
    protected $signature = 'ena:backup:separar-clave';
    protected $description = 'Preserva una vez la clave legacy cifrada con la clave independiente de backups';

    /** Ejecutar una vez antes de crear nuevos ZIP o rotar APP_KEY; nunca imprime claves. */
    public function handle(MySqlBackupService $service)
    {
        try {
            $this->line(json_encode($service->separateLegacyKey(), JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('No se pudo preservar la clave antigua ('.class_basename($e).').');
            return self::FAILURE;
        }
    }
}
