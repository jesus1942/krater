<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeploySnapshots extends Migration
{
    public function up()
    {
        Schema::create('deploy_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('environment', 40);
            $table->string('deployment_id', 100);
            $table->string('revision', 64);
            $table->unsignedBigInteger('previous_id')->nullable();
            $table->boolean('passed');
            $table->longText('counts');
            $table->longText('losses');
            $table->longText('applied_migrations');
            $table->longText('declarations');
            $table->timestamp('created_at');
            $table->index(['environment', 'passed', 'id'], 'deploy_snapshots_baseline');
        });
    }

    public function down()
    {
        Schema::dropIfExists('deploy_snapshots');
    }
}
