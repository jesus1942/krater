<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        // Un deploy anterior puede haber alcanzado a crear la tabla antes de
        // fallar en otra etapa de preDeploy. Si ya existe, no se intenta
        // recrearla: la bitacora debe poder recuperarse sin bloquear el arranque.
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');

            // Se guardan como IDs historicos, deliberadamente SIN foreign keys.
            // Una auditoria forense no debe perder la identidad original si un
            // usuario, institucion o nivel se elimina despues.
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();

            $table->string('action', 150);
            $table->string('auditable_type', 190)->nullable();
            $table->unsignedInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('severity', 20)->default('low');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at'], 'audit_logs_company_time');
            $table->index(['school_level_id', 'created_at'], 'audit_logs_level_time');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_time');
            $table->index(['severity', 'created_at'], 'audit_logs_severity_time');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_object');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
}
