<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
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

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

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
