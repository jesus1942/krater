<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conserva cada otorgamiento y vincula su alcance a su propia vigencia. */
class AddRoleAssignmentLifecycle extends Migration
{
    public function up()
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->index(['user_id', 'role_id', 'school_level_id'], 'role_user_history_index');
        });
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropUnique('role_user_unique');
            $table->unsignedInteger('division_id')->nullable();
            $table->unsignedInteger('course_section_id')->nullable();
            $table->boolean('managed_scope')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('revoked_by')->nullable();
        });
    }

    public function down()
    {
        // El historial puede contener varias altas del mismo rol. No se comprime
        // ni se borra para recuperar la antigua restriccion unica.
        throw new RuntimeException('La historia de roles requiere una migracion de reversion revisada.');
    }
}
