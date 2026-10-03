<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedUsableAcademicStructure extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('school_levels') || ! Schema::hasTable('academic_years') || ! Schema::hasTable('grade_levels') || ! Schema::hasTable('divisions')) {
            return;
        }

        $year = 2026;
        $today = now();

        $levels = DB::table('school_levels')
            ->where('enabled', true)
            ->whereIn('code', ['primary', 'secondary', 'tertiary'])
            ->get();

        foreach ($levels as $level) {
            $academicYearId = DB::table('academic_years')
                ->where('company_id', $level->company_id)
                ->where('school_level_id', $level->id)
                ->where('year', $year)
                ->value('id');

            if (! $academicYearId) {
                $academicYearId = DB::table('academic_years')->insertGetId([
                    'company_id' => $level->company_id,
                    'school_level_id' => $level->id,
                    'year' => $year,
                    'name' => 'Ciclo lectivo '.$year,
                    // Fechas administrativas iniciales: editables desde Configuración.
                    'starts_on' => $year.'-02-01',
                    'ends_on' => $year.'-12-31',
                    'status' => 'active',
                    'created_at' => $today,
                    'updated_at' => $today,
                ]);
            }

            $count = 0;
            if ($level->code === 'primary' || $level->code === 'secondary') {
                $count = 6;
            } elseif ($level->code === 'tertiary' && Schema::hasTable('study_plans')) {
                $count = (int) DB::table('study_plans')
                    ->where('company_id', $level->company_id)
                    ->where('school_level_id', $level->id)
                    ->where('enabled', true)
                    ->max('duration_years');
            }

            if ($count < 1) {
                // Terciario depende del plan de estudios. No inventamos duración.
                continue;
            }

            $gradeIds = [];
            for ($position = 1; $position <= $count; $position++) {
                $name = $level->code === 'primary'
                    ? $position.'.º grado'
                    : $position.'.º año';

                $gradeId = DB::table('grade_levels')
                    ->where('company_id', $level->company_id)
                    ->where('school_level_id', $level->id)
                    ->where('position', $position)
                    ->value('id');

                if (! $gradeId) {
                    $gradeId = DB::table('grade_levels')->insertGetId([
                        'company_id' => $level->company_id,
                        'school_level_id' => $level->id,
                        'name' => $name,
                        'position' => $position,
                        'pedagogical_unit' => $level->code === 'primary' && $position <= 2 ? 'Primer ciclo' : null,
                        'enabled' => true,
                        'created_at' => $today,
                        'updated_at' => $today,
                    ]);
                }
                $gradeIds[$position] = $gradeId;
            }

            foreach ($gradeIds as $position => $gradeId) {
                $nextId = $gradeIds[$position + 1] ?? null;
                DB::table('grade_levels')->where('id', $gradeId)->update(['promotes_to_id' => $nextId]);

                $exists = DB::table('divisions')
                    ->where('company_id', $level->company_id)
                    ->where('school_level_id', $level->id)
                    ->where('academic_year_id', $academicYearId)
                    ->where('grade_level_id', $gradeId)
                    ->where('name', 'A')
                    ->exists();

                if (! $exists) {
                    DB::table('divisions')->insert([
                        'company_id' => $level->company_id,
                        'school_level_id' => $level->id,
                        'academic_year_id' => $academicYearId,
                        'grade_level_id' => $gradeId,
                        'name' => 'A',
                        'shift' => null,
                        'capacity' => null,
                        'enabled' => true,
                        'created_at' => $today,
                        'updated_at' => $today,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        // Data-only, idempotente y no destructiva. No eliminamos estructura académica
        // porque puede haber sido usada por matrículas después del deploy.
    }
}
