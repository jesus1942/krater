<?php

namespace Crater\Console\Commands;

use Crater\Models\CompanySetting;
use Crater\Models\Country;
use Crater\Models\Currency;
use Crater\Models\PaymentMethod;
use Crater\Models\Unit;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\CurrenciesTableSeeder;
use Database\Seeders\DefaultSettingsSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Console\Command;

class SetupData extends Command
{
    protected $signature = 'crater:setup-data';

    protected $description = 'Carga monedas, paises, settings y datos base si faltan (idempotente)';

    public function handle()
    {
        if (Currency::count() === 0) {
            $this->call('db:seed', ['--class' => CurrenciesTableSeeder::class, '--force' => true]);
            $this->info('Monedas cargadas.');
        }

        if (Country::count() === 0) {
            $this->call('db:seed', ['--class' => CountriesTableSeeder::class, '--force' => true]);
            $this->info('Paises cargados.');
        }

        if (PaymentMethod::count() === 0) {
            $this->call('db:seed', ['--class' => PaymentMethodSeeder::class, '--force' => true]);
            $this->info('Metodos de pago cargados.');
        }

        if (Unit::count() === 0) {
            $this->call('db:seed', ['--class' => UnitSeeder::class, '--force' => true]);
            $this->info('Unidades cargadas.');
        }

        if (CompanySetting::count() === 0) {
            $this->call('db:seed', ['--class' => DefaultSettingsSeeder::class, '--force' => true]);
            $this->info('Configuracion de empresa cargada.');
        }

        $this->info('Datos base verificados.');
    }
}
