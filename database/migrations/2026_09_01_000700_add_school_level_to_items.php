<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSchoolLevelToItems extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('items', 'school_level_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedInteger('school_level_id')->nullable()->after('company_id');
                $table->foreign('school_level_id')
                    ->references('id')
                    ->on('school_levels')
                    ->nullOnDelete();
                $table->index(['company_id', 'school_level_id'], 'items_company_level_index');
            });
        }

        /*
         * Los artículos anteriores a esta migración no guardaban el nivel que
         * estaba activo al crearlos, por lo que ese dato no puede reconstruirse
         * genéricamente. El único concepto histórico identificado en esta
         * instalación es "Cuota nivel" y fue confirmado como perteneciente a
         * Primaria. Se corrige de forma acotada, sin inventar nivel para otros
         * artículos legacy.
         */
        $companyIds = DB::table('items')
            ->whereNull('school_level_id')
            ->where('name', 'Cuota nivel')
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $primaryId = DB::table('school_levels')
                ->where('company_id', $companyId)
                ->where('code', 'primary')
                ->value('id');

            if ($primaryId) {
                DB::table('items')
                    ->where('company_id', $companyId)
                    ->whereNull('school_level_id')
                    ->where('name', 'Cuota nivel')
                    ->update(['school_level_id' => $primaryId]);
            }
        }
    }

    public function down()
    {
        if (Schema::hasColumn('items', 'school_level_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropForeign(['school_level_id']);
                $table->dropIndex('items_company_level_index');
                $table->dropColumn('school_level_id');
            });
        }
    }
}
