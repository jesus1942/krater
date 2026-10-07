<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluacion, calificaciones y asistencia.
 *
 * Principio rector: LA CALIFICACION NO SE PISA, SE VERSIONA.
 * Una nota cargada y luego corregida deja rastro. `grade_entries` guarda el
 * valor vigente y `grade_entry_revisions` la historia completa. Esto no es
 * paranoia: en un conflicto con una familia, poder mostrar quien cargo que y
 * cuando es la diferencia entre resolverlo en cinco minutos o no poder
 * defenderse.
 *
 * Segundo principio: la escala de calificacion es un dato, no un tipo de
 * columna. Primario califica conceptualmente, Secundario con numeros del 1 al
 * 10, Terciario con otra escala y ademas con finales. Se guarda el valor crudo
 * y su interpretacion normalizada.
 */
class CreateAssessmentTables extends Migration
{
    public function up()
    {
        // --- escalas de calificacion -------------------------------------------
        // Configurable por nivel y por ciclo, porque la normativa cambia y los
        // ciclos viejos deben seguir interpretandose con la escala que tenian.

        Schema::create('grading_scales', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');

            $table->string('name');                        // "Numerica 1-10"
            $table->string('kind', 20);                    // numeric | conceptual | pass_fail

            // Para escalas numericas.
            $table->decimal('min_value', 6, 2)->nullable();
            $table->decimal('max_value', 6, 2)->nullable();
            $table->decimal('passing_value', 6, 2)->nullable();
            $table->smallInteger('decimals')->default(0);

            // Para escalas conceptuales: [{code, label, order, passing}]
            $table->json('levels')->nullable();

            $table->boolean('is_default')->default(false);
            $table->smallInteger('effective_from_year')->nullable();
            $table->smallInteger('effective_to_year')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->index(['school_level_id', 'is_default']);
        });

        // --- instancias de evaluacion -------------------------------------------
        // Una prueba, un trabajo practico, un informe de proceso. Cuelga de la
        // seccion de materia y de un periodo.

        Schema::create('assessments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('course_section_id');
            $table->unsignedInteger('academic_term_id')->nullable();
            $table->unsignedInteger('grading_scale_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            // exam | practical | project | oral | process | recovery | final
            $table->string('kind', 30)->default('exam');

            $table->date('due_on')->nullable();

            // Peso relativo dentro del periodo. Si todas pesan igual, queda en 1.
            $table->decimal('weight', 6, 3)->default(1);

            // Si false, es formativa: se registra pero no promedia.
            $table->boolean('counts_for_average')->default(true);

            // Origen del dato. manual: la carga el docente en la Suite.
            // moodle: viene de una actividad de Moodle sincronizada.
            $table->string('source', 20)->default('manual');
            $table->unsignedInteger('moodle_grade_item_id')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();
            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->nullOnDelete();
            $table->foreign('grading_scale_id')->references('id')->on('grading_scales')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['course_section_id', 'academic_term_id'], 'assessments_section_term_index');
        });

        // --- calificaciones (valor vigente) --------------------------------------

        Schema::create('grade_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('assessment_id');
            $table->unsignedInteger('section_enrollment_id');

            // Valor tal como lo cargo el docente: "8", "Muy bueno", "A".
            $table->string('raw_value')->nullable();

            // Interpretacion normalizada, para poder promediar y comparar
            // entre escalas distintas sin reinterpretar strings.
            $table->decimal('numeric_value', 6, 2)->nullable();
            $table->boolean('is_passing')->nullable();

            // absent | excused | not_submitted | pending
            $table->string('special_status', 20)->nullable();

            $table->text('teacher_comment')->nullable();

            // Si false, la familia todavia no la ve. Las notas se publican
            // cuando el docente cierra la carga, no a medida que las escribe.
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->unsignedInteger('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
            $table->foreign('section_enrollment_id')->references('id')->on('section_enrollments')->cascadeOnDelete();
            $table->foreign('graded_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['assessment_id', 'section_enrollment_id'], 'grade_entries_unique');
            $table->index(['company_id', 'is_published']);
        });

        // --- historia de cambios de nota -------------------------------------------
        // Append-only. Nunca se actualiza ni se borra una fila de esta tabla.

        Schema::create('grade_entry_revisions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grade_entry_id');

            $table->string('previous_raw_value')->nullable();
            $table->string('new_raw_value')->nullable();
            $table->decimal('previous_numeric_value', 6, 2)->nullable();
            $table->decimal('new_numeric_value', 6, 2)->nullable();

            // Obligatorio si el periodo ya estaba cerrado.
            $table->text('reason')->nullable();

            $table->unsignedInteger('changed_by');
            $table->string('changed_by_role', 40)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Si el cambio se hizo fuera de la ventana de carga, quien lo autorizo.
            $table->unsignedInteger('authorized_by')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->foreign('grade_entry_id')->references('id')->on('grade_entries')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('authorized_by')->references('id')->on('users')->nullOnDelete();

            $table->index('grade_entry_id');
        });

        // --- nota de cierre por materia y periodo ------------------------------------
        // El promedio o la nota conceptual que va al boletin. Se calcula, pero
        // el docente puede sobreescribirla con justificacion: el criterio
        // pedagogico manda sobre la aritmetica.

        Schema::create('term_grades', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('section_enrollment_id');
            $table->unsignedInteger('academic_term_id');

            $table->decimal('computed_value', 6, 2)->nullable();   // lo que dio el calculo
            $table->string('raw_value')->nullable();               // lo que va al boletin
            $table->decimal('numeric_value', 6, 2)->nullable();
            $table->boolean('is_passing')->nullable();

            $table->boolean('was_overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->unsignedInteger('overridden_by')->nullable();

            $table->smallInteger('absences_count')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('closed_by')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('section_enrollment_id')->references('id')->on('section_enrollments')->cascadeOnDelete();
            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->restrictOnDelete();
            $table->foreign('overridden_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['section_enrollment_id', 'academic_term_id'], 'term_grades_unique');
        });

        // --- nota final de la materia -------------------------------------------------

        Schema::create('final_grades', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('section_enrollment_id');

            $table->string('raw_value')->nullable();
            $table->decimal('numeric_value', 6, 2)->nullable();
            $table->boolean('is_passing')->nullable();

            // regular_promotion: aprobo cursando | exam: aprobo por final
            // recovery: aprobo en recuperatorio | equivalence: por equivalencia
            // failed: desaprobo | pending: adeuda
            $table->string('resolution', 30)->nullable();

            $table->date('resolved_on')->nullable();

            // Libro de actas: numero de acta y folio del examen.
            $table->string('exam_record_number')->nullable();
            $table->string('exam_book_folio')->nullable();

            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('closed_by')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('section_enrollment_id')->references('id')->on('section_enrollments')->cascadeOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();

            $table->unique('section_enrollment_id', 'final_grades_unique');
        });

        // --- materias pendientes (previas) ------------------------------------------
        // Sobrevive al cambio de ciclo: es lo que arrastra el alumno que paso
        // de anio debiendo materias.

        Schema::create('pending_subjects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('subject_id');

            // Ciclo en que quedo pendiente.
            $table->unsignedInteger('origin_academic_year_id');

            // pending | passed | expired
            $table->string('status', 20)->default('pending');

            $table->smallInteger('attempts')->default(0);
            $table->date('resolved_on')->nullable();
            $table->unsignedInteger('resolved_final_grade_id')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('origin_academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('resolved_final_grade_id')->references('id')->on('final_grades')->nullOnDelete();

            $table->unique(['student_id', 'subject_id', 'origin_academic_year_id'], 'pending_subjects_unique');
            $table->index(['company_id', 'status']);
        });

        // --- asistencia ------------------------------------------------------------
        // Se toma por division (jornada) o por seccion de materia (por hora),
        // segun el nivel. Por eso las dos FK son nulables y hay un check logico
        // en el modelo: una de las dos tiene que estar.

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('academic_year_id');
            $table->unsignedInteger('enrollment_id');

            $table->unsignedInteger('division_id')->nullable();
            $table->unsignedInteger('course_section_id')->nullable();

            $table->date('date');

            // present | absent | late | excused | remote
            $table->string('status', 20);

            // Media falta: 0.5. Falta entera: 1. Presente: 0.
            $table->decimal('absence_weight', 4, 2)->default(0);

            $table->string('justification')->nullable();
            $table->boolean('is_justified')->default(false);

            // manual | bigbluebutton — la asistencia de clase en vivo se deriva
            // de los eventos de la sala.
            $table->string('source', 20)->default('manual');

            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
            $table->foreign('course_section_id')->references('id')->on('course_sections')->nullOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['enrollment_id', 'date', 'course_section_id'], 'attendance_unique');
            $table->index(['company_id', 'date']);
            $table->index(['division_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('pending_subjects');
        Schema::dropIfExists('final_grades');
        Schema::dropIfExists('term_grades');
        Schema::dropIfExists('grade_entry_revisions');
        Schema::dropIfExists('grade_entries');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('grading_scales');
    }
}
