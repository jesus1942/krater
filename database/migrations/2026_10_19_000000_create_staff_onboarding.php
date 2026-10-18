<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila E1: la ficha de personal es la entidad visible; el usuario de acceso
 * cuelga de ella. Agrega columnas y una tabla, vincula fichas y usuarios que
 * coinciden por correo y crea la ficha de los usuarios de personal que no
 * tenian una. No borra ni modifica permisos.
 */
class CreateStaffOnboarding extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('must_change_password')->default(false);
            });
        }

        if (! Schema::hasColumn('staff_assignments', 'role_id')) {
            Schema::table('staff_assignments', function (Blueprint $table) {
                // Cargo con rol: el rol y su alcance; role_user_id es el otorgamiento
                // vigente cuando la persona tiene acceso.
                $table->unsignedInteger('role_id')->nullable();
                $table->unsignedInteger('division_id')->nullable();
                $table->unsignedInteger('role_user_id')->nullable();
                $table->index('role_user_id');
            });
        }

        if (! Schema::hasTable('teaching_assignments')) {
            Schema::create('teaching_assignments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->unsignedInteger('school_level_id');
                $table->unsignedInteger('staff_member_id');
                $table->unsignedInteger('course_section_id');
                $table->decimal('weekly_hours', 6, 2)->nullable();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                // Rol docente sobre la seccion; nulo mientras la persona no tenga acceso.
                $table->unsignedInteger('role_user_id')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedInteger('revoked_by')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('school_level_id')->references('id')->on('school_levels')->onDelete('restrict');
                $table->foreign('staff_member_id')->references('id')->on('staff_members')->onDelete('restrict');
                $table->foreign('course_section_id')->references('id')->on('course_sections')->onDelete('restrict');
                $table->index(['company_id', 'school_level_id', 'revoked_at'], 'teaching_level_active_idx');
                $table->index(['staff_member_id', 'revoked_at'], 'teaching_member_active_idx');
            });
        }

        $counts = ['linked_by_email' => 0, 'files_created' => 0, 'ambiguous_emails' => 0];
        DB::transaction(function () use (&$counts) {
            $linked = fn () => DB::table('staff_members')->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();

            foreach (DB::table('staff_members')->whereNull('user_id')->whereNotNull('email')->orderBy('id')->get() as $member) {
                $users = DB::table('users')->where('company_id', $member->company_id)->where('role', '<>', 'customer')
                    ->whereNull('deleted_at')->whereRaw('LOWER(email) = ?', [strtolower(trim($member->email))])
                    ->whereNotIn('id', $linked())->pluck('id');
                if ($users->count() === 1) {
                    DB::table('staff_members')->where('id', $member->id)->update(['user_id' => $users->first(), 'updated_at' => now()]);
                    $counts['linked_by_email']++;
                } elseif ($users->count() > 1) {
                    $counts['ambiguous_emails']++;
                }
            }

            $staffUsers = DB::table('users')->whereNotNull('company_id')->where('role', '<>', 'customer')
                ->whereNull('deleted_at')->whereNotIn('id', $linked())->orderBy('id')->get();
            foreach ($staffUsers as $user) {
                $name = trim((string) $user->name);
                $cut = strrpos($name, ' ');
                DB::table('staff_members')->insert([
                    'company_id' => $user->company_id, 'user_id' => $user->id,
                    'first_name' => $cut === false ? ($name ?: 'Sin nombre') : substr($name, 0, $cut),
                    'last_name' => $cut === false ? '' : substr($name, $cut + 1),
                    'email' => $user->email, 'phone' => property_exists($user, 'phone') ? $user->phone : null,
                    'staff_category' => 'other', 'employment_status' => (! property_exists($user, 'is_active') || $user->is_active) ? 'active' : 'inactive',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $counts['files_created']++;
            }
        });

        if (app()->runningInConsole() && defined('STDOUT')) {
            fwrite(STDOUT, 'E1 fichas de personal: '.json_encode($counts).PHP_EOL);
        }
    }

    public function down()
    {
        throw new RuntimeException('E1 no se revierte por migracion: las fichas y asignaciones son historia.');
    }
}
