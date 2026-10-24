<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plataforma: configuracion segura, integraciones, clases en vivo,
 * documentos y motor de promocion.
 *
 * CONFIGURACION Y SECRETOS
 * ------------------------
 * Hoy la app guarda credenciales en texto plano: `file_disks.credentials` es
 * un JSON sin cifrar, y `GET /api/v1/mail/config` devuelve la contrasena SMTP
 * en la respuesta. Ademas escribe el `.env` desde la web, cosa que en Railway
 * no sobrevive un redeploy porque el filesystem es efimero.
 *
 * `secure_settings` reemplaza eso: valor cifrado en reposo con la APP_KEY,
 * nunca devuelto por la API (solo un enmascarado), y con marca de que roles
 * pueden tocarlo.
 */
class CreatePlatformTables extends Migration
{
    public function up()
    {
        // --- configuracion sensible ---------------------------------------------

        Schema::create('secure_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');

            $table->string('key', 100);                 // "moodle.ws_token"
            $table->string('group', 50);                // "moodle"

            // Cifrado con el cast `encrypted` de Laravel. Nunca se lee desde
            // un endpoint: solo lo consume el servicio que lo necesita.
            $table->text('value')->nullable();

            // Lo unico que ve la UI: "sk_live_****3f9a". Se calcula al guardar.
            $table->string('masked_preview', 60)->nullable();

            // Si true, solo la administracion total puede leerlo o cambiarlo,
            // y el cambio pasa por `approval_requests`.
            $table->boolean('requires_total_admin')->default(true);

            // Rotacion: cuando se cambio por ultima vez y cuando vence.
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['company_id', 'key'], 'secure_settings_unique');
            $table->index('group');
        });

        // --- mapeo con sistemas externos --------------------------------------------
        // Tabla generica: sirve para Moodle, para BigBlueButton y para lo que
        // venga. Evita ensuciar las tablas academicas con columnas `moodle_id`,
        // `bbb_id`, etc.

        Schema::create('external_mappings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');

            $table->string('system', 30);               // moodle | bigbluebutton | sinide
            $table->string('local_type', 100);          // "Crater\Models\CourseSection"
            $table->unsignedInteger('local_id');
            $table->string('external_id', 190);
            $table->json('metadata')->nullable();

            $table->timestamp('synced_at')->nullable();
            // ok | stale | error
            $table->string('sync_status', 20)->default('ok');
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->unique(['system', 'local_type', 'local_id'], 'external_mappings_local_unique');
            $table->unique(['system', 'local_type', 'external_id'], 'external_mappings_external_unique');
            $table->index(['company_id', 'system', 'sync_status'], 'external_mappings_status_index');
        });

        // --- cola de sincronizacion --------------------------------------------------
        // Registro de intentos. Sirve para reintentar y para explicar por que
        // algo no aparecio en Moodle.

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');

            $table->string('system', 30);
            $table->string('operation', 100);           // "course.create"
            $table->string('local_type', 100)->nullable();
            $table->unsignedInteger('local_id')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            // pending | running | succeeded | failed | abandoned
            $table->string('status', 20)->default('pending');
            $table->smallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['status', 'scheduled_for']);
            $table->index(['system', 'status']);
        });

        // --- clases en vivo -----------------------------------------------------------

        Schema::create('live_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('course_section_id');

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();

            // scheduled | live | ended | cancelled
            $table->string('status', 20)->default('scheduled');

            // Identificador de la sala en el proveedor. No es la URL: las URL
            // se firman al vuelo y expiran.
            $table->string('provider', 30)->default('bigbluebutton');
            $table->string('external_meeting_id', 190)->nullable();

            // Se guardan cifradas: quien tenga la de moderador entra como
            // docente. Nunca se exponen por la API.
            $table->text('attendee_secret')->nullable();
            $table->text('moderator_secret')->nullable();

            $table->boolean('is_recorded')->default(false);
            $table->boolean('recording_available')->default(false);
            $table->string('recording_external_id', 190)->nullable();
            $table->timestamp('recording_expires_at')->nullable();

            // Para clases de Primario: la grabacion requiere consentimiento.
            $table->boolean('consent_required')->default(false);

            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['course_section_id', 'scheduled_start'], 'live_classes_section_time_index');
            $table->index(['company_id', 'status']);
        });

        // --- participacion en la clase en vivo -------------------------------------------
        // Alimentada por los webhooks del proveedor. De aca sale la asistencia
        // automatica de las clases remotas.

        Schema::create('live_class_participants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('live_class_id');
            $table->unsignedInteger('user_id')->nullable();

            $table->string('external_user_id', 190)->nullable();
            $table->string('display_name')->nullable();
            $table->string('role', 20)->nullable();     // moderator | viewer

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->unsignedInteger('total_seconds')->default(0);

            // Cantidad de veces que entro. Muchas reconexiones suele indicar
            // mala conexion, no ausencia: importa para no penalizar mal.
            $table->smallInteger('join_count')->default(1);

            $table->timestamps();

            $table->foreign('live_class_id')->references('id')->on('live_classes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['live_class_id', 'user_id']);
        });

        // --- reglas de promocion -----------------------------------------------------------
        // Versionadas por nivel y por ciclo. La normativa cambia y los ciclos
        // viejos tienen que poder recalcularse con la regla que los rigio.
        //
        // El motor NO tiene reglas en el codigo: lee de aca. Ver
        // docs/08-calificaciones-promocion-boletines.md para el formato JSON.

        Schema::create('promotion_rule_sets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');

            $table->string('name');
            $table->smallInteger('version')->default(1);

            $table->smallInteger('effective_from_year');
            $table->smallInteger('effective_to_year')->nullable();

            // Referencia normativa que respalda la regla.
            $table->string('legal_reference')->nullable();

            $table->json('rules');

            // draft | active | superseded
            $table->string('status', 20)->default('draft');

            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['school_level_id', 'name', 'version'], 'promotion_rule_sets_unique');
            $table->index(['company_id', 'status']);
        });

        // --- corrida de cierre de ciclo -------------------------------------------------------
        // El cierre es la operacion mas peligrosa del sistema. Se hace en dos
        // pasos: simulacion (dry_run) y ejecucion. La simulacion no toca nada y
        // deja el resultado aca para que direccion lo revise.

        Schema::create('promotion_runs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('academic_year_id');
            $table->unsignedInteger('promotion_rule_set_id');

            // dry_run | execution
            $table->string('mode', 20)->default('dry_run');
            // pending | running | completed | failed | reverted
            $table->string('status', 20)->default('pending');

            $table->integer('students_evaluated')->default(0);
            $table->integer('promoted_count')->default(0);
            $table->integer('promoted_with_pending_count')->default(0);
            $table->integer('retained_count')->default(0);
            $table->integer('graduated_count')->default(0);
            $table->integer('manual_review_count')->default(0);

            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();

            // La ejecucion real exige una aprobacion previa.
            $table->unsignedInteger('approval_request_id')->nullable();

            $table->unsignedInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Ventana de reversion. Pasada esa fecha, el cierre es definitivo.
            $table->timestamp('revertible_until')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedInteger('reverted_by')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('promotion_rule_set_id')->references('id')->on('promotion_rule_sets')->restrictOnDelete();
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->nullOnDelete();
            $table->foreign('started_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reverted_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['academic_year_id', 'mode', 'status'], 'promotion_runs_year_index');
        });

        // --- resultado por alumno de la corrida --------------------------------------------------
        // Una fila por alumno. Guarda POR QUE se decidio lo que se decidio: sin
        // esto, "el sistema dice que repite" es indefendible ante una familia.

        Schema::create('promotion_results', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('promotion_run_id');
            $table->unsignedInteger('enrollment_id');

            // promoted | promoted_with_pending | retained | graduated |
            // pending_decision
            $table->string('outcome', 30);

            // Traza legible del calculo: que regla se aplico, que materias
            // pesaron, que porcentaje de asistencia dio.
            $table->json('evaluation_trace')->nullable();

            $table->smallInteger('subjects_passed')->default(0);
            $table->smallInteger('subjects_failed')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            // Si una persona cambio el resultado automatico.
            $table->boolean('was_overridden')->default(false);
            $table->string('original_outcome', 30)->nullable();
            $table->text('override_reason')->nullable();
            $table->unsignedInteger('overridden_by')->nullable();

            // Matricula creada en el ciclo siguiente, si se ejecuto.
            $table->unsignedInteger('resulting_enrollment_id')->nullable();

            $table->timestamps();

            $table->foreign('promotion_run_id')->references('id')->on('promotion_runs')->cascadeOnDelete();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->restrictOnDelete();
            $table->foreign('overridden_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resulting_enrollment_id')->references('id')->on('enrollments')->nullOnDelete();

            $table->unique(['promotion_run_id', 'enrollment_id'], 'promotion_results_unique');
            $table->index('outcome');
        });

        // --- documentos academicos -------------------------------------------------------------
        // Libreta interna, boletin oficial del ministerio, constancias,
        // certificados. Todo lo que se emite o se recibe y hay que conservar.

        Schema::create('academic_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('school_level_id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('academic_year_id')->nullable();
            $table->unsignedInteger('academic_term_id')->nullable();

            // internal_report: libreta que emite la app
            // official_report: boletin del ministerio, se adjunta
            // certificate | transcript | enrollment_proof
            $table->string('kind', 40);

            $table->string('title');
            $table->string('document_number')->nullable();

            // Hash del archivo. Permite probar que el PDF no fue alterado.
            $table->string('file_hash', 128)->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();

            // Los documentos se versionan: si se corrige una nota despues de
            // emitido, se emite una version nueva y la anterior queda.
            $table->smallInteger('version')->default(1);
            $table->unsignedInteger('supersedes_id')->nullable();

            // draft | issued | superseded | annulled
            $table->string('status', 20)->default('draft');

            $table->timestamp('issued_at')->nullable();
            $table->unsignedInteger('issued_by')->nullable();

            // Firma electronica o digital.
            $table->string('signature_kind', 30)->nullable();
            $table->text('signature_data')->nullable();
            $table->timestamp('signed_at')->nullable();

            // Distribucion a la familia.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->unsignedInteger('viewed_by')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->nullOnDelete();
            $table->foreign('supersedes_id')->references('id')->on('academic_documents')->nullOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('viewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['student_id', 'kind', 'status'], 'academic_documents_student_index');
            $table->index(['company_id', 'academic_year_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('academic_documents');
        Schema::dropIfExists('promotion_results');
        Schema::dropIfExists('promotion_runs');
        Schema::dropIfExists('promotion_rule_sets');
        Schema::dropIfExists('live_class_participants');
        Schema::dropIfExists('live_classes');
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('external_mappings');
        Schema::dropIfExists('secure_settings');
    }
}
