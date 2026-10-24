<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->index();
            $table->string('code');
            $table->string('name');
            $table->integer('phonecode');
        });

        // Cargar despues de crear la tabla, no desde la migracion de 2014.
        // Este seeder se ejecuta sobre una tabla recien creada y vacia.
        (new \Database\Seeders\CountriesTableSeeder())->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
}
