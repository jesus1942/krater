<?php

use Crater\Models\Country;
use Database\Seeders\CountriesTableSeeder;
use Illuminate\Database\Migrations\Migration;

class SeedCountries extends Migration
{
    public function up()
    {
        if (Country::count() === 0) {
            (new CountriesTableSeeder())->run();
        }
    }

    public function down()
    {
        // no revertir datos de seed
    }
}
