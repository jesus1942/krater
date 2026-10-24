<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila E1: cada ficha vinculada a un usuario toma como cargos los roles
 * vigentes de ese usuario, con el nivel de cada rol. El cargo institucional
 * (sin nivel) queda solo para la administracion total; otro rol global sin
 * nivel se informa y no se convierte. Un rol docente por seccion pasa a
 * asignacion docente. No cambia role_user ni contraseñas: solo vincula.
 */
class BackfillStaffCargos extends Migration
{
    const FUNCTION_BY_ROLE = [
        'total_admin' => 'management', 'general_director' => 'management', 'level_director' => 'management',
        'vice_director' => 'management', 'academic_secretary' => 'administrative', 'finance_admin' => 'administrative',
        'hr_admin' => 'administrative', 'preceptor' => 'support', 'preceptor_registrar' => 'support',
        'counselor' => 'support', 'staff' => 'support',
    ];

    public function up()
    {
        if (! Schema::hasTable('teaching_assignments')) {
            return;
        }
        $counts = ['cargos' => 0, 'institutional_cargos' => 0, 'teaching' => 0, 'skipped_global_without_level' => 0];
        $today = now()->toDateString();

        DB::transaction(function () use (&$counts, $today) {
            $linked = DB::table('staff_assignments')->whereNotNull('role_user_id')->pluck('role_user_id')
                ->merge(DB::table('teaching_assignments')->whereNotNull('role_user_id')->pluck('role_user_id'))
                ->map(fn ($id) => (int) $id)->all();
            $rows = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->join('staff_members', function ($join) {
                    $join->on('staff_members.user_id', '=', 'role_user.user_id')->on('staff_members.company_id', '=', 'role_user.company_id');
                })
                ->whereNull('role_user.revoked_at')->whereColumn('roles.company_id', 'role_user.company_id')
                ->where(fn ($q) => $q->whereNull('role_user.ends_on')->orWhere('role_user.ends_on', '>=', $today))
                ->whereNotIn('roles.name', ['guardian', 'student'])->whereNotIn('role_user.id', $linked)
                ->orderBy('role_user.id')
                ->select('role_user.*', 'roles.name as role_name', 'roles.label as role_label', 'staff_members.id as member_id')->get();

            foreach ($rows as $row) {
                $section = property_exists($row, 'course_section_id') ? $row->course_section_id : null;
                if ($row->role_name === 'teacher' && $section) {
                    $level = DB::table('course_sections')->where('id', $section)->value('school_level_id');
                    if ($level === null) {
                        continue;
                    }
                    DB::table('teaching_assignments')->insert(['company_id' => $row->company_id, 'school_level_id' => $level,
                        'staff_member_id' => $row->member_id, 'course_section_id' => $section, 'starts_on' => $row->starts_on,
                        'ends_on' => $row->ends_on, 'role_user_id' => $row->id, 'created_at' => now(), 'updated_at' => now()]);
                    $counts['teaching']++;
                    continue;
                }
                if ($row->school_level_id === null && $row->role_name !== 'total_admin') {
                    $counts['skipped_global_without_level']++;
                    continue;
                }
                DB::table('staff_assignments')->insert(['company_id' => $row->company_id, 'staff_member_id' => $row->member_id,
                    'school_level_id' => $row->school_level_id, 'position_title' => $row->role_label,
                    'function_category' => self::FUNCTION_BY_ROLE[$row->role_name] ?? 'other',
                    'start_date' => $row->starts_on, 'end_date' => $row->ends_on, 'active' => true,
                    'role_id' => $row->role_id, 'division_id' => property_exists($row, 'division_id') ? $row->division_id : null,
                    'role_user_id' => $row->id, 'created_at' => now(), 'updated_at' => now()]);
                $counts['cargos']++;
                if ($row->school_level_id === null) {
                    $counts['institutional_cargos']++;
                }
                DB::table('staff_members')->where('id', $row->member_id)->where('staff_category', 'other')
                    ->update(['staff_category' => self::FUNCTION_BY_ROLE[$row->role_name] ?? 'other']);
            }
        });

        if (app()->runningInConsole() && defined('STDOUT')) {
            fwrite(STDOUT, 'E1 cargos desde roles vigentes: '.json_encode($counts).PHP_EOL);
        }
    }

    public function down()
    {
        throw new RuntimeException('E1 no se revierte por migracion.');
    }
}
