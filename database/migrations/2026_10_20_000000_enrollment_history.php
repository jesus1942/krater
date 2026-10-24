<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila 7: historial de matriculas.
 *
 * Un pase cierra la matricula vigente (transferred_out) y abre otra; dentro del
 * mismo ciclo y nivel eso exige dos filas (incluso volver a una division
 * anterior), que la unicidad anterior impedia. Queda un indice comun y "una
 * sola matricula activa por alumno" la garantiza StudentPlacement bloqueando
 * la fila del alumno.
 *
 * Ademas crea la matricula de los alumnos que solo tenian ubicacion en los
 * campos legacy (altas de direccion anteriores a esta fila), cuando el ciclo,
 * curso y division coinciden exactamente y sin ambiguedad. No borra ni cambia
 * alumnos; informa conteos sin datos personales.
 */
class EnrollmentHistory extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('enrollments', 'previous_enrollment_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->unsignedInteger('previous_enrollment_id')->nullable();
                $table->index('previous_enrollment_id');
            });
        }
        if (! $this->hasIndex('enrollments_year_student_index')) {
            Schema::table('enrollments', function (Blueprint $table) {
                // Se crea antes de quitar la anterior: en MySQL sostiene la FK de academic_year_id.
                $table->index(['academic_year_id', 'student_id'], 'enrollments_year_student_index');
            });
        }
        if ($this->hasIndex('enrollments_year_student_unique')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropUnique('enrollments_year_student_unique');
            });
        }

        $counts = ['created' => 0, 'no_match' => 0, 'ambiguous' => 0];
        DB::transaction(function () use (&$counts) {
            $students = DB::table('students')->whereNotNull('school_level_id')
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')->from('enrollments')->whereColumn('enrollments.student_id', 'students.id')
                        ->where('enrollments.status', 'active');
                })->orderBy('id')->get(['id', 'company_id', 'school_level_id', 'school_year', 'grade', 'division', 'created_at']);
            foreach ($students as $student) {
                $divisions = DB::table('divisions')
                    ->join('academic_years', 'academic_years.id', '=', 'divisions.academic_year_id')
                    ->join('grade_levels', 'grade_levels.id', '=', 'divisions.grade_level_id')
                    ->where('divisions.company_id', $student->company_id)->where('divisions.school_level_id', $student->school_level_id)
                    ->where('academic_years.school_level_id', $student->school_level_id)->where('academic_years.year', $student->school_year)
                    ->where('grade_levels.school_level_id', $student->school_level_id)->where('grade_levels.name', $student->grade)
                    ->where('divisions.name', $student->division)
                    ->get(['divisions.id', 'divisions.academic_year_id']);
                if ($divisions->count() !== 1) {
                    $counts[$divisions->isEmpty() ? 'no_match' : 'ambiguous']++;
                    continue;
                }
                DB::table('enrollments')->insert(['company_id' => $student->company_id, 'school_level_id' => $student->school_level_id,
                    'academic_year_id' => $divisions[0]->academic_year_id, 'student_id' => $student->id,
                    'division_id' => $divisions[0]->id, 'status' => 'active',
                    'enrolled_on' => substr((string) ($student->created_at ?? now()), 0, 10),
                    'attendance_condition' => 'regular', 'has_curricular_adaptation' => false,
                    'created_at' => now(), 'updated_at' => now()]);
                $counts['created']++;
            }
        });

        if (app()->runningInConsole() && defined('STDOUT')) {
            fwrite(STDOUT, 'Fila 7 matriculas desde campos legacy: '.json_encode($counts).PHP_EOL);
        }
    }

    public function down()
    {
        // Volver a la unicidad anterior borraria pases dentro del mismo ciclo.
        throw new RuntimeException('El historial de matriculas no se revierte por migracion.');
    }

    protected function hasIndex(string $name): bool
    {
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('enrollments');

        return array_key_exists(strtolower($name), array_change_key_case($indexes, CASE_LOWER));
    }
}
