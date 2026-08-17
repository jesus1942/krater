<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de acceso basado en roles, con alcance por nivel.
 *
 * POR QUE NO ALCANZA `users.role`
 * -------------------------------
 * Hoy la app tiene una columna `users.role` de texto libre con cuatro valores
 * posibles, y un unico middleware `admin` que compara strings. Consecuencia:
 * o sos admin y ves todo (facturacion, gastos, backups, credenciales de mail),
 * o no ves nada. En una escuela eso no sirve: un docente de 3.er anio B tiene
 * que cargar notas de SU division y de nadie mas, y no tiene por que ver la
 * facturacion.
 *
 * DISENIO
 * -------
 * Tres piezas:
 *
 * 1. `roles` + `permissions` + `permission_role`: el catalogo. Los permisos son
 *    constantes del codigo (ver app/Enums/Permission.php), la tabla los
 *    materializa para poder asignarlos.
 *
 * 2. `role_user`: la asignacion, SIEMPRE con alcance. Un usuario no es
 *    "docente" a secas: es docente DEL NIVEL SECUNDARIO. La misma persona
 *    puede ser docente en Secundario y directivo en Terciario.
 *
 * 3. `user_scopes`: el alcance fino. Un preceptor no ve todo el nivel: ve sus
 *    divisiones. Un docente ve sus secciones.
 *
 * No se usa spatie/laravel-permission a proposito: el paquete no modela
 * alcance por tenant anidado (empresa -> nivel -> division) y terminariamos
 * peleandole. El costo es escribir el resolutor de permisos; el beneficio es
 * que el alcance queda explicito en el esquema.
 *
 * OJO: `config/permission.php` existe en el repo pero es un archivo huerfano
 * de un paquete que nunca se instalo. Esta migracion NO lo usa. Conviene
 * borrar ese archivo para no confundir.
 */
class CreateRbacTables extends Migration
{
    public function up()
    {
        // --- catalogo de permisos ---------------------------------------------
        // Se siembran desde app/Enums/Permission.php. La tabla es el espejo
        // persistido del enum, no la fuente de verdad.

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->unique();      // "academic.grade.record"
            $table->string('group', 50);                // "academic"
            $table->string('label');                    // "Cargar calificaciones"
            $table->text('description')->nullable();

            // Si true, solo la administracion total puede otorgar este permiso
            // y solo ella puede ejercerlo. Ver app/Enums/Permission.php.
            $table->boolean('is_restricted')->default(false);

            $table->timestamps();

            $table->index('group');
        });

        // --- roles --------------------------------------------------------------

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();

            $table->string('name', 60);                 // "teacher"
            $table->string('label');                    // "Docente"
            $table->text('description')->nullable();

            // Jerarquia. Numero mas bajo = mas autoridad. Se usa para impedir
            // que alguien edite o asigne un rol de nivel igual o superior al
            // suyo (escalada de privilegios).
            $table->smallInteger('hierarchy_level');

            // Alcance en que tiene sentido este rol:
            // global (toda la institucion) | level (un nivel) | division | section
            $table->string('scope_type', 20)->default('level');

            // Los roles del sistema no se pueden borrar ni renombrar. Los
            // personalizados que cree la escuela, si.
            $table->boolean('is_system')->default(false);

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'name'], 'roles_company_name_unique');
            $table->index('hierarchy_level');
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');

            $table->primary(['permission_id', 'role_id']);
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        // --- asignacion de rol con alcance ----------------------------------------

        Schema::create('role_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('company_id');

            // Nulo solo para roles de alcance global.
            $table->unsignedInteger('school_level_id')->nullable();

            // Vigencia. Un suplente tiene el rol mientras dura el reemplazo.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            // Auditoria de la asignacion: quien le dio este poder a quien.
            $table->unsignedInteger('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['user_id', 'role_id', 'school_level_id'], 'role_user_unique');
            $table->index(['company_id', 'school_level_id'], 'role_user_tenant_index');
        });

        // --- alcance fino ------------------------------------------------------------
        // Limita a que divisiones o secciones llega un rol. Si un usuario tiene
        // un rol de alcance `division` y NO tiene filas aca, no ve ninguna: el
        // default es negar, no permitir.

        Schema::create('user_scopes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');

            // division | course_section | grade_level
            $table->string('scope_type', 30);
            $table->unsignedInteger('scope_id');

            // Ciclo al que aplica. Un preceptor de 3.o A en 2026 no sigue
            // teniendo acceso a 3.o A en 2027 salvo que se renueve.
            $table->unsignedInteger('academic_year_id')->nullable();

            $table->unsignedInteger('granted_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['user_id', 'scope_type', 'scope_id', 'academic_year_id'], 'user_scopes_unique');
            $table->index(['user_id', 'scope_type']);
        });

        // --- vinculo familia / estudiante ----------------------------------------------
        // Quien puede ver el legajo y las notas de un alumno, y quien puede
        // firmar. No es lo mismo: un familiar autorizado a retirar no
        // necesariamente puede autorizar una salida educativa.

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('guardian_user_id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('company_id');

            // El vinculo se guarda de forma generica a proposito: no hace falta
            // registrar el detalle de la composicion familiar para operar.
            // responsible: responsable legal | authorized: autorizado a retirar
            // billing: responsable de pago
            $table->string('relationship_kind', 30)->default('responsible');

            $table->boolean('can_view_grades')->default(true);
            $table->boolean('can_view_attendance')->default(true);
            $table->boolean('can_authorize')->default(false);
            $table->boolean('receives_billing')->default(false);

            // Si una resolucion judicial restringe el acceso, se marca aca y se
            // documenta por fuera. La app respeta la marca sin guardar el motivo.
            $table->boolean('access_restricted')->default(false);

            $table->timestamps();

            $table->foreign('guardian_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->unique(['guardian_user_id', 'student_id'], 'guardian_student_unique');
            $table->index('student_id');
        });

        // --- bitacora de auditoria -------------------------------------------------------
        // Append-only. Es la tabla que responde "quien hizo que y cuando".
        // Se escribe desde un observer, no desde los controladores: si depende
        // de que alguien se acuerde de loguear, no se loguea.

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('school_level_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();

            $table->string('action', 100);              // "grade.updated"
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedInteger('auditable_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // low | medium | high | critical — para poder alertar sobre lo
            // grave sin ahogarse en ruido.
            $table->string('severity', 20)->default('low');

            $table->timestamp('created_at')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('school_level_id')->references('id')->on('school_levels')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_index');
            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('severity');
        });

        // --- aprobaciones de doble control -----------------------------------------------
        // Las acciones criticas (cerrar un ciclo, cambiar credenciales, rotar
        // claves) no las ejecuta una sola persona: se solicitan y alguien de la
        // administracion total las aprueba.

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');

            $table->string('action', 100);              // "academic_year.close"
            $table->json('payload')->nullable();
            $table->text('justification')->nullable();

            // pending | approved | rejected | expired | executed
            $table->string('status', 20)->default('pending');

            $table->unsignedInteger('requested_by');
            $table->timestamp('requested_at')->nullable();

            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['company_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('user_scopes');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
}
