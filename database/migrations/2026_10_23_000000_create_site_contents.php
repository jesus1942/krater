<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenido editable del sitio publico: textos clave-valor y fotos. Las fotos
 * se guardan en la base porque el contenedor no tiene disco persistente; asi
 * sobreviven a los deploys y entran en los backups.
 */
class CreateSiteContents extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_contents')) {
            Schema::create('site_contents', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->string('key', 120);
                $table->text('value')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->unique(['company_id', 'key']);
            });
        }
        if (! Schema::hasTable('site_images')) {
            Schema::create('site_images', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->string('key', 60);
                $table->string('mime', 40);
                $table->unsignedInteger('size');
                $table->longText('data');
                $table->string('alt', 255)->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->unique(['company_id', 'key']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('site_images');
        Schema::dropIfExists('site_contents');
    }
}
