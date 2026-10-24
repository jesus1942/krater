<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ScopeIntegrationSettingsBySchoolLevel extends Migration
{
    public function up()
    {
        if (Schema::hasTable('secure_settings') && ! Schema::hasColumn('secure_settings', 'school_level_id')) {
            Schema::table('secure_settings', function (Blueprint $table) {
                // Nullable solo para preservar configuraciones legacy ya existentes.
                // Todo alta operativa nueva debe exigir un nivel concreto desde servicio/UI.
                $table->unsignedInteger('school_level_id')->nullable()->after('company_id');
                $table->foreign('school_level_id')->references('id')->on('school_levels')->nullOnDelete();
                $table->index(['company_id', 'school_level_id', 'group'], 'secure_settings_level_group_idx');
            });

            Schema::table('secure_settings', function (Blueprint $table) {
                $table->dropUnique('secure_settings_unique');
                $table->unique(['company_id', 'school_level_id', 'key'], 'secure_settings_level_unique');
            });
        }

        if (Schema::hasTable('external_mappings') && ! Schema::hasColumn('external_mappings', 'school_level_id')) {
            Schema::table('external_mappings', function (Blueprint $table) {
                $table->unsignedInteger('school_level_id')->nullable()->after('company_id');
                $table->foreign('school_level_id')->references('id')->on('school_levels')->nullOnDelete();
                $table->index(['company_id', 'school_level_id', 'system'], 'external_mappings_level_system_idx');
            });
        }

        if (Schema::hasTable('sync_operations') && ! Schema::hasColumn('sync_operations', 'school_level_id')) {
            Schema::table('sync_operations', function (Blueprint $table) {
                $table->unsignedInteger('school_level_id')->nullable()->after('company_id');
                $table->foreign('school_level_id')->references('id')->on('school_levels')->nullOnDelete();
                $table->index(['company_id', 'school_level_id', 'system', 'status'], 'sync_operations_level_status_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('sync_operations') && Schema::hasColumn('sync_operations', 'school_level_id')) {
            Schema::table('sync_operations', function (Blueprint $table) {
                $table->dropForeign(['school_level_id']);
                $table->dropIndex('sync_operations_level_status_idx');
                $table->dropColumn('school_level_id');
            });
        }

        if (Schema::hasTable('external_mappings') && Schema::hasColumn('external_mappings', 'school_level_id')) {
            Schema::table('external_mappings', function (Blueprint $table) {
                $table->dropForeign(['school_level_id']);
                $table->dropIndex('external_mappings_level_system_idx');
                $table->dropColumn('school_level_id');
            });
        }

        if (Schema::hasTable('secure_settings') && Schema::hasColumn('secure_settings', 'school_level_id')) {
            Schema::table('secure_settings', function (Blueprint $table) {
                $table->dropUnique('secure_settings_level_unique');
                $table->dropForeign(['school_level_id']);
                $table->dropIndex('secure_settings_level_group_idx');
                $table->dropColumn('school_level_id');
            });

            Schema::table('secure_settings', function (Blueprint $table) {
                $table->unique(['company_id', 'key'], 'secure_settings_unique');
            });
        }
    }
}
