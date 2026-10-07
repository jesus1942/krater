<?php

namespace Crater\Console\Commands;

use Crater\Models\User;
use Crater\Services\Data\DeploySnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AcceptDeploySnapshot extends Command
{
    protected $signature = 'ena:smoke:aceptar {snapshot} {--motivo=} {--usuario= : Email de administracion total}';
    protected $description = 'Acepta una foto revisada con autenticacion y auditoria de administracion total';

    /** La clave se pide oculta; nunca se admite como argumento ni variable del cron. */
    public function handle(DeploySnapshotService $service)
    {
        try {
            if (! ctype_digit((string) $this->argument('snapshot')) || trim((string) $this->option('motivo')) === '') {
                throw new \RuntimeException('Se requieren snapshot numerico y --motivo.');
            }
            if (! $this->input->isInteractive()) {
                throw new \RuntimeException('Requiere consola interactiva.');
            }
            $email = $this->option('usuario') ?: $this->ask('Email de administracion total');
            $actor = User::where('email', $email)->first();
            $password = $this->secret('Contrasena de administracion total');
            if (! $actor || ! is_string($password) || ! Hash::check($password, $actor->password)) {
                throw new \RuntimeException('Credenciales invalidas.');
            }
            $report = $service->accept((int) $this->argument('snapshot'), $actor, (string) $this->option('motivo'));
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('No se acepto la foto: '.($e instanceof \Illuminate\Database\QueryException ? 'Fallo la base o la auditoria.' : $e->getMessage()));
            return self::FAILURE;
        }
    }
}
