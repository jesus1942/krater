<?php

use Crater\Models\Country;
use Database\Seeders\CountriesTableSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class SeedCountries extends Migration
{
    public function up()
    {
        // La tabla todavia no existe en un migrate desde cero. Su propia
        // migracion carga el catalogo una vez creada.
        if (Schema::hasTable('countries') && Country::count() === 0) {
            (new CountriesTableSeeder())->run();
        }
    }

    public function down()
    {
        // no revertir datos de seed
    }
}
