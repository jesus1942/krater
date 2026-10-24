<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nucleo academico de la Suite ENA.
 *
 * Estructura: ciclo lectivo -> plan de estudios -> curso (anio) -> division
 * -> seccion de materia -> matricula del estudiante.
 *
 * Todo cuelga de `school_levels`, la tabla que ya existe y separa Primario,
 * Secundario y Terciario. Cada tabla lleva `company_id` ademas de
 * `school_level_id` porque el aislamiento por empresa es el tenant externo y
 * el nivel es el tenant interno; sin las dos columnas no se puede indexar
 * bien ni aplicar los scopes globales existentes.
 *
 * Convenciones:
 * - Identificadores: `unsignedInteger` para alinear con las tablas heredadas
 *   de Crater, que usan `increments()` y no `bigIncrements()`. Mezclar
 *   `bigInteger` con `integer` rompe las claves foraneas en MySQL.
 * - Borrado: `restrictOnDelete` en todo lo que sea historia academica. Una
 *   calificacion nunca se borra en cascada por accidente.
 * - Fechas: `date` para lo que es calendario escolar, `timestamp` para
 *   auditoria.
 */
class CreateAcademicCoreTables extends Migration
{
    public function up()
    {
        // --- ciclo lectivo ---------------------------------------------------
        // El anio escolar. Es la unidad de corte de TODA la vida academica: sin
        // ciclo no hay matricula, ni nota, ni promocion.

        Schema::create('academic_years', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');

            $table->smallInteger('year');                  // 2026
            $table->string('name');                        // "Ciclo lectivo 2026"
            $table->date('starts_on');
            $table->date('ends_on');

            // draft: se esta armando | active: en curso | closing: cierre en
            // proceso | closed: cerrado, historico e inmutable
            $table->string('status', 20)->default('draft');

            // Momento en que se ejecuto el cierre y quien lo aprobo. Nulo
            // mientras el ciclo no se cerro.
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('closed_by')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();

            // Un solo ciclo por anio y por nivel.
            $table->unique(['school_level_id', 'year'], 'academic_years_level_year_unique');
            $table->index(['company_id', 'status']);
        });

        // --- periodos del ciclo ----------------------------------------------
        // Trimestres, cuatrimestres o bimestres. Se define por nivel porque
        // Primario y Secundario no siempre usan el mismo corte que Terciario.

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('academic_year_id');

            $table->string('name');                        // "1.er trimestre"
            $table->smallInteger('position');              // 1, 2, 3
            $table->string('kind', 20)->default('term');   // term | semester | bimester | final
            $table->date('starts_on');
            $table->date('ends_on');

            // Ventana en que los docentes pueden cargar notas. Fuera de esta
            // ventana la carga requiere autorizacion de direccion.
            $table->timestamp('grading_opens_at')->nullable();
            $table->timestamp('grading_closes_at')->nullable();
            $table->boolean('is_closed')->default(false);

            $table->timestamps();

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->unique(['academic_year_id', 'position'], 'academic_terms_year_position_unique');
        });

        // --- plan de estudios -------------------------------------------------
        // Version del disenio curricular. Se versiona porque un alumno que
        // ingreso en 2024 termina con el plan 2024 aunque en 2026 haya otro.

        Schema::create('study_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');

            $table->string('name');                        // "Tecnicatura Superior en Tecnologias Aplicadas"
            $table->string('code', 50)->nullable();
            $table->string('resolution_number')->nullable(); // Res. ME N.o 452/26
            $table->smallInteger('effective_from_year');
            $table->smallInteger('effective_to_year')->nullable();
            $table->smallInteger('duration_years');
            $table->string('degree_awarded')->nullable();  // "Tecnico/a Superior en..."
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->index(['school_level_id', 'enabled']);
        });

        // --- espacios curriculares (materias) ---------------------------------
        // La materia como definicion del plan, no como dictado concreto.

        Schema::create('subjects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('study_plan_id')->nullable();

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->smallInteger('year_of_plan')->nullable();   // en que anio del plan se dicta
            $table->smallInteger('weekly_hours')->nullable();
            $table->smallInteger('total_hours')->nullable();

            // annual | first_semester | second_semester | modular
            $table->string('duration', 20)->default('annual');

            // Si false, no computa para promocion (por ejemplo un taller
            // optativo). Se evalua igual pero no bloquea el pase de curso.
            $table->boolean('counts_for_promotion')->default(true);

            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('study_plan_id')->references('id')->on('study_plans')->nullOnDelete();
            $table->index(['study_plan_id', 'year_of_plan']);
        });

        // --- correlatividades --------------------------------------------------
        // Sobre todo para Terciario. `requirement` distingue si para cursar X
        // hace falta tener regularizada o aprobada la materia Y.

        Schema::create('subject_prerequisites', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('subject_id');
            $table->unsignedInteger('required_subject_id');

            // to_enroll: para inscribirse a cursar | to_sit_exam: para rendir final
            $table->string('scope', 20)->default('to_enroll');
            // regular: alcanza con estar regular | passed: hay que tenerla aprobada
            $table->string('requirement', 20)->default('regular');

            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('required_subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->unique(['subject_id', 'required_subject_id', 'scope'], 'subject_prereq_unique');
        });

        // --- curso (anio de estudio) ------------------------------------------
        // "1.er grado", "3.er anio", "2.o anio de la tecnicatura".

        Schema::create('grade_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');

            $table->string('name');                        // "3.er anio"
            $table->smallInteger('position');              // 3 — orden de progresion

            // El curso al que se promociona. Nulo en el ultimo anio: ahi el
            // alumno egresa en vez de promocionar. Esta columna es lo que hace
            // posible la promocion automatica.
            $table->unsignedInteger('promotes_to_id')->nullable();

            // Unidad pedagogica: 1.o y 2.o grado de Primaria se evaluan como
            // bloque y no hay repitencia entre ambos.
            $table->string('pedagogical_unit', 50)->nullable();

            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('promotes_to_id')->references('id')->on('grade_levels')->nullOnDelete();
            $table->unique(['school_level_id', 'position'], 'grade_levels_level_position_unique');
        });

        // --- division ----------------------------------------------------------
        // "3.er anio A". Es la instancia concreta de un curso en un ciclo.

        Schema::create('divisions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('academic_year_id');
            $table->unsignedInteger('grade_level_id');

            $table->string('name');                        // "A"
            $table->string('shift', 20)->nullable();       // morning | afternoon | evening
            $table->smallInteger('capacity')->nullable();

            // Preceptor o tutor a cargo del grupo.
            $table->unsignedInteger('head_teacher_id')->nullable();

            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('grade_level_id')->references('id')->on('grade_levels')->restrictOnDelete();
            $table->foreign('head_teacher_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['academic_year_id', 'grade_level_id', 'name'], 'divisions_year_grade_name_unique');
            $table->index(['company_id', 'school_level_id', 'academic_year_id'], 'divisions_tenant_index');
        });

        // --- seccion de materia -------------------------------------------------
        // El dictado concreto: esta materia, en esta division, este ciclo, con
        // estos docentes. Es el objeto que se espeja como curso en Moodle y el
        // que agrupa las clases en vivo.

        Schema::create('course_sections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('academic_year_id');
            $table->unsignedInteger('division_id');
            $table->unsignedInteger('subject_id');

            $table->string('name')->nullable();            // opcional, para desambiguar
            $table->string('status', 20)->default('active'); // active | archived

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();

            $table->unique(['division_id', 'subject_id'], 'course_sections_division_subject_unique');
            $table->index(['company_id', 'academic_year_id'], 'course_sections_tenant_index');
        });

        // --- docentes de la seccion ----------------------------------------------

        Schema::create('course_section_teacher', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('course_section_id');
            $table->unsignedInteger('user_id');

            // titular | suplente | ayudante | pareja_pedagogica
            $table->string('role', 30)->default('titular');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->timestamps();

            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['course_section_id', 'user_id', 'role'], 'cst_section_user_role_unique');
            $table->index('user_id');
        });

        // --- matricula del estudiante en la division ------------------------------
        // Una fila por alumno por ciclo. Es el registro que se promociona.

        Schema::create('enrollments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('academic_year_id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('division_id');

            // active: cursando | transferred_out: se fue a otra escuela
            // withdrawn: baja | completed: termino el ciclo
            $table->string('status', 20)->default('active');

            // Resultado del ciclo. Nulo hasta que se cierra.
            // promoted: promociona | promoted_with_pending: pasa con previas
            // retained: repite | graduated: egresa | pending_decision: requiere
            // decision humana
            $table->string('outcome', 30)->nullable();

            $table->date('enrolled_on');
            $table->date('left_on')->nullable();

            // Condicion de cursada, sobre todo Terciario: regular | libre | oyente
            $table->string('attendance_condition', 20)->default('regular');

            // Adecuaciones curriculares. El detalle pedagogico NO va aca (es
            // dato sensible): aca solo la marca operativa, el detalle va al
            // legajo con control de acceso propio.
            $table->boolean('has_curricular_adaptation')->default(false);

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->restrictOnDelete();

            // Un alumno no puede estar dos veces en el mismo ciclo y nivel.
            $table->unique(['academic_year_id', 'student_id', 'school_level_id'], 'enrollments_year_student_unique');
            $table->index(['division_id', 'status']);
            $table->index(['company_id', 'academic_year_id', 'outcome'], 'enrollments_outcome_index');
        });

        // --- inscripcion a la materia -----------------------------------------------
        // Normalmente se deriva de la matricula, pero se materializa porque en
        // Terciario un alumno cursa materias sueltas de distintos anios, y
        // porque un recursante cursa solo las que debe.

        Schema::create('section_enrollments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('enrollment_id');
            $table->unsignedInteger('course_section_id');

            // active | withdrawn
            $table->string('status', 20)->default('active');

            // Resultado en la materia: passed | failed | pending_exam |
            // regular | free | equivalence
            $table->string('result', 20)->nullable();

            // Si la materia se dio por equivalencia, el acto administrativo.
            $table->string('equivalence_note')->nullable();

            $table->timestamps();

            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
            $table->foreign('course_section_id')->references('id')->on('course_sections')->restrictOnDelete();
            $table->unique(['enrollment_id', 'course_section_id'], 'section_enrollments_unique');
            $table->index(['course_section_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('section_enrollments');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_section_teacher');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('subject_prerequisites');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_years');
    }
}
