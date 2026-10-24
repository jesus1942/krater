<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila E1: los cargos que la migracion de relleno creo desde roles vigentes se
 * insertaron sin evento de auditoria. Cada uno mueve una ficha de "sin nivel"
 * al nivel de su rol, y ena:smoke exige que ese cambio este auditado. Escribe
 * el mismo evento que el modelo (staffassignment.created). Repetible: no
 * duplica eventos.
 */
class AuditBackfilledStaffCargos extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('staff_assignments', 'role_user_id')) {
            return;
        }
        $audited = 0;
        $cargos = DB::table('staff_assignments')->whereNotNull('role_user_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('audit_logs')->whereColumn('audit_logs.auditable_id', 'staff_assignments.id')
                    ->where('audit_logs.auditable_type', 'Crater\\Models\\StaffAssignment')
                    ->where('audit_logs.action', 'staffassignment.created');
            })->orderBy('id')->get();
        foreach ($cargos as $cargo) {
            DB::table('audit_logs')->insert([
                'company_id' => $cargo->company_id, 'school_level_id' => $cargo->school_level_id, 'user_id' => null,
                'action' => 'staffassignment.created', 'auditable_type' => 'Crater\\Models\\StaffAssignment',
                'auditable_id' => $cargo->id, 'old_values' => null,
                'new_values' => json_encode(['staff_member_id' => $cargo->staff_member_id, 'school_level_id' => $cargo->school_level_id,
                    'role_id' => $cargo->role_id, 'role_user_id' => $cargo->role_user_id, 'origen' => 'migracion E1 desde roles vigentes']),
                'severity' => 'medium', 'created_at' => now(),
            ]);
            $audited++;
        }
        if (app()->runningInConsole() && defined('STDOUT')) {
            fwrite(STDOUT, 'E1 cargos auditados: '.$audited.PHP_EOL);
        }
    }

    public function down()
    {
        throw new RuntimeException('La auditoria no se revierte.');
    }
}
