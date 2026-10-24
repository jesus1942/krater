<?php

namespace Crater\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Informa en el predeploy los drivers efectivos, sin secretos, para verificar
 * desde los logs que las sesiones sobreviven a un deploy.
 */
class RuntimeReport extends Command
{
    protected $signature = 'ena:runtime';

    protected $description = 'Informa drivers de sesion, cache y cola (sin secretos)';

    // Guardan la sesion en el disco o la memoria del contenedor: se pierden en cada deploy.
    const EPHEMERAL = ['file', 'array'];

    public function handle()
    {
        $driver = config('session.driver');
        $report = [
            'session_driver' => $driver,
            'session_lifetime_minutes' => (int) config('session.lifetime'),
            'sessions_table' => Schema::hasTable(config('session.table', 'sessions')),
            'cache_driver' => config('cache.default'),
            'queue_connection' => config('queue.default'),
            'sessions_survive_deploy' => ! in_array($driver, self::EPHEMERAL, true)
                && ($driver !== 'database' || Schema::hasTable(config('session.table', 'sessions'))),
        ];
        $this->line('SuiteEna runtime: '.json_encode($report));
        if (! $report['sessions_survive_deploy']) {
            $this->warn('SuiteEna runtime: las sesiones se pierden en cada deploy; usar SESSION_DRIVER=database.');
        }

        return 0;
    }
}
