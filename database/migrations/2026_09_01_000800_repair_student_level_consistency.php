<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairStudentLevelConsistency extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('students') || ! Schema::hasTable('school_levels')) {
            return;
        }

        $map = [
            'Primario' => 'primary',
            'Secundario' => 'secondary',
            'Terciario' => 'tertiary',
        ];

        foreach ($map as $legacyLabel => $code) {
            $levels = DB::table('school_levels')->where('code', $code)->get(['id', 'company_id']);
            foreach ($levels as $level) {
                DB::table('students')
                    ->where('company_id', $level->company_id)
                    ->where('level', $legacyLabel)
                    ->where(function ($query) use ($level) {
                        $query->whereNull('school_level_id')->orWhere('school_level_id', '<>', $level->id);
                    })
                    ->update(['school_level_id' => $level->id]);
            }
        }
    }

    public function down()
    {
        // No se revierte a una asociación conocida como incorrecta.
    }
}
