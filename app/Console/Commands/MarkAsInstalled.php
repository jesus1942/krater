<?php

namespace Crater\Console\Commands;

use Crater\Models\Setting;
use Illuminate\Console\Command;

class MarkAsInstalled extends Command
{
    protected $signature = 'crater:mark-installed';

    protected $description = 'Marca la aplicacion como instalada (crea database_created y profile_complete)';

    public function handle()
    {
        \Storage::disk('local')->put('database_created', '1');
        Setting::setSetting('profile_complete', 'COMPLETED');
        $this->info('Aplicacion marcada como instalada.');
    }
}
