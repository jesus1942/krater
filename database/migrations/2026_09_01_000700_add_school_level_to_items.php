<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSchoolLevelToItems extends Migration
{
    public function up()
    {
        /*
         * `school_level_id` ya fue agregado a items por
         * 2026_08_14_010000_create_school_levels_and_scopes. El problema era
         * que el módulo de artículos no inicializaba TenantContext y los
         * registros nuevos podían quedar con nivel nulo.
         *
         * Los artículos históricos nulos no pueden reasignarse de manera
         * genérica porque no conservan el nivel que estaba activo al crearlos.
         * El concepto "Cuota nivel" fue identificado y confirmado como
         * perteneciente a Primaria, por lo que solo corregimos ese dato.
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
        // No revertimos el dato: antes de esta migración el nivel era NULL y
        // perder nuevamente la procedencia confirmada sería degradar datos.
    }
}
