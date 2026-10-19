<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia del personal: una fila por persona y dia (presente, ausente,
 * tarde o licencia) y las licencias como rangos de fechas con su tipo.
 *
 * La asistencia de alumnos ya tenia su tabla (attendance_records); esta
 * migracion solo agrega lo del personal. No toca datos existentes.
 */
class CreateStaffAttendance extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('staff_leaves')) {
            Schema::create('staff_leaves', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->unsignedInteger('staff_member_id');
                // illness | family | training | study | union | personal | other
                $table->string('type', 20);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedInteger('cancelled_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('staff_member_id')->references('id')->on('staff_members')->cascadeOnDelete();
                $table->index(['company_id', 'starts_on', 'ends_on']);
                $table->index(['staff_member_id', 'starts_on']);
            });
        }

        if (! Schema::hasTable('staff_attendance')) {
            Schema::create('staff_attendance', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id');
                $table->unsignedInteger('staff_member_id');
                $table->date('date');
                // present | absent | late | leave
                $table->string('status', 20);
                $table->unsignedInteger('staff_leave_id')->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('staff_member_id')->references('id')->on('staff_members')->cascadeOnDelete();
                $table->foreign('staff_leave_id')->references('id')->on('staff_leaves')->nullOnDelete();
                $table->unique(['staff_member_id', 'date'], 'staff_attendance_member_date_unique');
                $table->index(['company_id', 'date']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('staff_attendance');
        Schema::dropIfExists('staff_leaves');
    }
}
