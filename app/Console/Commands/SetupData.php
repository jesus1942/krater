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

    protected $description = 'Carga monedas, paises y settings base si faltan (idempotente)';

    public function handle()
    {
        if (Currency::count() === 0) {
            (new CurrenciesTableSeeder())->run();
            $this->info('Monedas cargadas.');
        } else {
            $this->info('Monedas: ya existen, omitiendo.');
        }

        if (Country::count() === 0) {
            (new CountriesTableSeeder())->run();
            $this->info('Paises cargados.');
        } else {
            $this->info('Paises: ya existen, omitiendo.');
        }

        if (PaymentMethod::count() === 0) {
            (new PaymentMethodSeeder())->run();
            $this->info('Metodos de pago cargados.');
        } else {
            $this->info('Metodos de pago: ya existen, omitiendo.');
        }

        if (Unit::count() === 0) {
            (new UnitSeeder())->run();
            $this->info('Unidades cargadas.');
        } else {
            $this->info('Unidades: ya existen, omitiendo.');
        }

        if (CompanySetting::count() === 0) {
            (new DefaultSettingsSeeder())->run();
            $this->info('Configuracion de empresa cargada.');
        } else {
            $this->info('Company settings: ya existen, omitiendo.');
        }

        $this->info('Datos base OK.');
    }
}
