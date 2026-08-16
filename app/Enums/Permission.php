<?php

namespace Crater\Enums;

/**
 * Catalogo de permisos de la Suite ENA.
 *
 * Se implementa con constantes de clase y no con un enum nativo de PHP porque
 * composer.json declara `"php": "^7.4 || ^8.0"` y los enums existen recien
 * desde 8.1. Si en algun momento se sube el piso a 8.1, esto se convierte en
 * un enum sin cambiar los valores.
 *
 * CONVENCION DE NOMBRES: `dominio.recurso.accion`.
 *
 * REGLA DE ORO: el codigo nunca pregunta por un ROL, pregunta por un PERMISO.
 * `if ($user->role === 'admin')` es exactamente el problema que tiene la app
 * hoy y que este catalogo viene a resolver. Un rol es un paquete de permisos
 * que la escuela puede reconfigurar; un permiso es un punto de control que el
 * codigo conoce.
 */
final class Permission
{
    // ---------------------------------------------------------------- academico

    const ACADEMIC_YEAR_VIEW = 'academic.year.view';
    const ACADEMIC_YEAR_MANAGE = 'academic.year.manage';
    const ACADEMIC_YEAR_CLOSE = 'academic.year.close';

    const STUDY_PLAN_VIEW = 'academic.study_plan.view';
    const STUDY_PLAN_MANAGE = 'academic.study_plan.manage';

    const DIVISION_VIEW = 'academic.division.view';
    const DIVISION_MANAGE = 'academic.division.manage';

    const SECTION_VIEW = 'academic.section.view';
    const SECTION_MANAGE = 'academic.section.manage';
    const SECTION_ASSIGN_TEACHER = 'academic.section.assign_teacher';

    const ENROLLMENT_VIEW = 'academic.enrollment.view';
    const ENROLLMENT_MANAGE = 'academic.enrollment.manage';
    const ENROLLMENT_TRANSFER = 'academic.enrollment.transfer';

    // ---------------------------------------------------------------- evaluacion

    const GRADE_VIEW_OWN_SECTIONS = 'grading.grade.view_own_sections';
    const GRADE_VIEW_ALL = 'grading.grade.view_all';
    const GRADE_RECORD = 'grading.grade.record';
    const GRADE_PUBLISH = 'grading.grade.publish';

    /** Modificar una nota despues de cerrado el periodo. Deja rastro obligatorio. */
    const GRADE_AMEND_CLOSED = 'grading.grade.amend_closed';

    /** Pisar el promedio calculado con criterio pedagogico. Exige justificacion. */
    const GRADE_OVERRIDE_COMPUTED = 'grading.grade.override_computed';

    const TERM_CLOSE = 'grading.term.close';
    const TERM_REOPEN = 'grading.term.reopen';

    const SCALE_MANAGE = 'grading.scale.manage';

    // ---------------------------------------------------------------- asistencia

    const ATTENDANCE_VIEW_OWN_SECTIONS = 'attendance.view_own_sections';
    const ATTENDANCE_VIEW_ALL = 'attendance.view_all';
    const ATTENDANCE_RECORD = 'attendance.record';
    const ATTENDANCE_JUSTIFY = 'attendance.justify';
    const ATTENDANCE_AMEND_PAST = 'attendance.amend_past';

    // ---------------------------------------------------------------- promocion

    const PROMOTION_RULES_VIEW = 'promotion.rules.view';
    const PROMOTION_RULES_MANAGE = 'promotion.rules.manage';
    const PROMOTION_SIMULATE = 'promotion.simulate';
    const PROMOTION_EXECUTE = 'promotion.execute';
    const PROMOTION_OVERRIDE_RESULT = 'promotion.override_result';
    const PROMOTION_REVERT = 'promotion.revert';

    // ---------------------------------------------------------------- documentos

    const DOCUMENT_VIEW_OWN_CHILDREN = 'documents.view_own_children';
    const DOCUMENT_VIEW_SECTION = 'documents.view_section';
    const DOCUMENT_VIEW_ALL = 'documents.view_all';
    const DOCUMENT_ISSUE_INTERNAL = 'documents.issue_internal';
    const DOCUMENT_UPLOAD_OFFICIAL = 'documents.upload_official';
    const DOCUMENT_SIGN = 'documents.sign';
    const DOCUMENT_ANNUL = 'documents.annul';

    // ---------------------------------------------------------------- legajo

    const STUDENT_VIEW_BASIC = 'students.view_basic';
    const STUDENT_VIEW_FILE = 'students.view_file';

    /**
     * Datos de salud, informes psicopedagogicos y adecuaciones. Es la categoria
     * mas sensible del sistema: el acceso se audita SIEMPRE, aunque sea lectura.
     */
    const STUDENT_VIEW_SENSITIVE = 'students.view_sensitive';

    const STUDENT_MANAGE = 'students.manage';
    const GUARDIAN_MANAGE = 'students.guardian.manage';

    // ---------------------------------------------------------------- campus

    const CLASS_VIEW = 'campus.class.view';
    const CLASS_SCHEDULE = 'campus.class.schedule';
    const CLASS_MODERATE = 'campus.class.moderate';
    const RECORDING_VIEW = 'campus.recording.view';
    const RECORDING_MANAGE = 'campus.recording.manage';
    const MOODLE_SYNC_RUN = 'campus.moodle.sync';

    // ---------------------------------------------------------------- economico
    // Lo que ya existe hoy en la app, ahora detras de permisos.

    const FINANCE_VIEW = 'finance.view';
    const FINANCE_INVOICE_MANAGE = 'finance.invoice.manage';
    const FINANCE_PAYMENT_MANAGE = 'finance.payment.manage';
    const FINANCE_EXPENSE_MANAGE = 'finance.expense.manage';
    const FINANCE_REPORT_VIEW = 'finance.report.view';

    // ---------------------------------------------------------------- sistema

    const USER_VIEW = 'system.user.view';
    const USER_MANAGE = 'system.user.manage';
    const ROLE_ASSIGN = 'system.role.assign';
    const ROLE_MANAGE = 'system.role.manage';

    const SETTINGS_VIEW = 'system.settings.view';
    const SETTINGS_MANAGE = 'system.settings.manage';

    /** Credenciales, tokens, claves de API. Reservado. */
    const SECRETS_VIEW_MASKED = 'system.secrets.view_masked';
    const SECRETS_MANAGE = 'system.secrets.manage';

    const AUDIT_VIEW = 'system.audit.view';
    const BACKUP_MANAGE = 'system.backup.manage';
    const APPROVAL_REVIEW = 'system.approval.review';
    const SCHOOL_LEVEL_MANAGE = 'system.school_level.manage';

    /**
     * Permisos que SOLO puede tener la administracion total.
     *
     * Esta es la respuesta literal a "para ciertos cambios si o si lo debe
     * hacer la administracion total de la app". Son los que tocan la
     * infraestructura: credenciales, roles, configuracion del sistema, copias
     * de seguridad y la definicion de los niveles institucionales.
     *
     * El resolutor de permisos la aplica como corte duro: si el permiso esta
     * en esta lista y el usuario no tiene el rol de administracion total, se
     * niega aunque algun rol se lo haya otorgado por error o por manipulacion
     * de la base.
     *
     * NO incluye acciones academicas graves como cerrar un ciclo: esas las
     * puede ejercer la direccion, pero pasan por doble control. Ver
     * requiresApproval().
     */
    public static function totalAdminOnly(): array
    {
        return [
            self::SECRETS_MANAGE,
            self::SECRETS_VIEW_MASKED,
            self::ROLE_MANAGE,
            self::SETTINGS_MANAGE,
            self::BACKUP_MANAGE,
            self::SCHOOL_LEVEL_MANAGE,
            self::PROMOTION_REVERT,
        ];
    }

    /**
     * Permisos cuyo ejercicio exige doble control: quien lo dispara crea una
     * solicitud en `approval_requests` y otra persona la aprueba. El aprobador
     * no puede ser el solicitante.
     *
     * Son operaciones graves o irreversibles. Cerrar un ciclo lectivo mueve a
     * cientos de alumnos de curso; anular un boletin emitido invalida un
     * documento que la familia ya recibio.
     */
    public static function requiresApproval(): array
    {
        return array_merge(self::totalAdminOnly(), [
            self::ACADEMIC_YEAR_CLOSE,
            self::PROMOTION_EXECUTE,
            self::PROMOTION_RULES_MANAGE,
            self::TERM_REOPEN,
            self::DOCUMENT_ANNUL,
        ]);
    }

    /**
     * Permisos cuyo EJERCICIO se audita siempre, incluso cuando es solo
     * lectura. Acceder al legajo sensible de un alumno deja rastro aunque no
     * se modifique nada.
     */
    public static function alwaysAudited(): array
    {
        return array_values(array_unique(array_merge(self::requiresApproval(), [
            self::STUDENT_VIEW_SENSITIVE,
            self::GRADE_AMEND_CLOSED,
            self::GRADE_OVERRIDE_COMPUTED,
            self::PROMOTION_OVERRIDE_RESULT,
            self::ATTENDANCE_AMEND_PAST,
            self::DOCUMENT_SIGN,
            self::ENROLLMENT_TRANSFER,
            self::APPROVAL_REVIEW,
            self::AUDIT_VIEW,
            self::ROLE_ASSIGN,
        ])));
    }

    /**
     * Catalogo completo con su metadata, para sembrar la tabla `permissions`.
     *
     * @return array<string, array{group: string, label: string}>
     */
    public static function catalog(): array
    {
        return [
            self::ACADEMIC_YEAR_VIEW => ['group' => 'academic', 'label' => 'Ver ciclos lectivos'],
            self::ACADEMIC_YEAR_MANAGE => ['group' => 'academic', 'label' => 'Administrar ciclos lectivos'],
            self::ACADEMIC_YEAR_CLOSE => ['group' => 'academic', 'label' => 'Cerrar un ciclo lectivo'],
            self::STUDY_PLAN_VIEW => ['group' => 'academic', 'label' => 'Ver planes de estudio'],
            self::STUDY_PLAN_MANAGE => ['group' => 'academic', 'label' => 'Administrar planes de estudio'],
            self::DIVISION_VIEW => ['group' => 'academic', 'label' => 'Ver divisiones'],
            self::DIVISION_MANAGE => ['group' => 'academic', 'label' => 'Administrar divisiones'],
            self::SECTION_VIEW => ['group' => 'academic', 'label' => 'Ver secciones de materia'],
            self::SECTION_MANAGE => ['group' => 'academic', 'label' => 'Administrar secciones de materia'],
            self::SECTION_ASSIGN_TEACHER => ['group' => 'academic', 'label' => 'Asignar docentes a secciones'],
            self::ENROLLMENT_VIEW => ['group' => 'academic', 'label' => 'Ver matriculas'],
            self::ENROLLMENT_MANAGE => ['group' => 'academic', 'label' => 'Administrar matriculas'],
            self::ENROLLMENT_TRANSFER => ['group' => 'academic', 'label' => 'Registrar pases de escuela'],

            self::GRADE_VIEW_OWN_SECTIONS => ['group' => 'grading', 'label' => 'Ver notas de sus secciones'],
            self::GRADE_VIEW_ALL => ['group' => 'grading', 'label' => 'Ver todas las notas del nivel'],
            self::GRADE_RECORD => ['group' => 'grading', 'label' => 'Cargar calificaciones'],
            self::GRADE_PUBLISH => ['group' => 'grading', 'label' => 'Publicar calificaciones a las familias'],
            self::GRADE_AMEND_CLOSED => ['group' => 'grading', 'label' => 'Modificar notas de un periodo cerrado'],
            self::GRADE_OVERRIDE_COMPUTED => ['group' => 'grading', 'label' => 'Sobreescribir la nota calculada'],
            self::TERM_CLOSE => ['group' => 'grading', 'label' => 'Cerrar un periodo'],
            self::TERM_REOPEN => ['group' => 'grading', 'label' => 'Reabrir un periodo cerrado'],
            self::SCALE_MANAGE => ['group' => 'grading', 'label' => 'Administrar escalas de calificacion'],

            self::ATTENDANCE_VIEW_OWN_SECTIONS => ['group' => 'attendance', 'label' => 'Ver asistencia de sus secciones'],
            self::ATTENDANCE_VIEW_ALL => ['group' => 'attendance', 'label' => 'Ver toda la asistencia del nivel'],
            self::ATTENDANCE_RECORD => ['group' => 'attendance', 'label' => 'Tomar asistencia'],
            self::ATTENDANCE_JUSTIFY => ['group' => 'attendance', 'label' => 'Justificar inasistencias'],
            self::ATTENDANCE_AMEND_PAST => ['group' => 'attendance', 'label' => 'Corregir asistencia de dias pasados'],

            self::PROMOTION_RULES_VIEW => ['group' => 'promotion', 'label' => 'Ver reglas de promocion'],
            self::PROMOTION_RULES_MANAGE => ['group' => 'promotion', 'label' => 'Administrar reglas de promocion'],
            self::PROMOTION_SIMULATE => ['group' => 'promotion', 'label' => 'Simular el cierre de ciclo'],
            self::PROMOTION_EXECUTE => ['group' => 'promotion', 'label' => 'Ejecutar la promocion'],
            self::PROMOTION_OVERRIDE_RESULT => ['group' => 'promotion', 'label' => 'Cambiar el resultado de un alumno'],
            self::PROMOTION_REVERT => ['group' => 'promotion', 'label' => 'Revertir una promocion ejecutada'],

            self::DOCUMENT_VIEW_OWN_CHILDREN => ['group' => 'documents', 'label' => 'Ver documentos de sus hijos'],
            self::DOCUMENT_VIEW_SECTION => ['group' => 'documents', 'label' => 'Ver documentos de sus secciones'],
            self::DOCUMENT_VIEW_ALL => ['group' => 'documents', 'label' => 'Ver todos los documentos del nivel'],
            self::DOCUMENT_ISSUE_INTERNAL => ['group' => 'documents', 'label' => 'Emitir la libreta interna'],
            self::DOCUMENT_UPLOAD_OFFICIAL => ['group' => 'documents', 'label' => 'Cargar boletines oficiales'],
            self::DOCUMENT_SIGN => ['group' => 'documents', 'label' => 'Firmar documentos'],
            self::DOCUMENT_ANNUL => ['group' => 'documents', 'label' => 'Anular un documento emitido'],

            self::STUDENT_VIEW_BASIC => ['group' => 'students', 'label' => 'Ver datos basicos de estudiantes'],
            self::STUDENT_VIEW_FILE => ['group' => 'students', 'label' => 'Ver el legajo'],
            self::STUDENT_VIEW_SENSITIVE => ['group' => 'students', 'label' => 'Ver informacion sensible del legajo'],
            self::STUDENT_MANAGE => ['group' => 'students', 'label' => 'Administrar estudiantes'],
            self::GUARDIAN_MANAGE => ['group' => 'students', 'label' => 'Administrar responsables'],

            self::CLASS_VIEW => ['group' => 'campus', 'label' => 'Ver clases en vivo'],
            self::CLASS_SCHEDULE => ['group' => 'campus', 'label' => 'Programar clases en vivo'],
            self::CLASS_MODERATE => ['group' => 'campus', 'label' => 'Moderar una clase en vivo'],
            self::RECORDING_VIEW => ['group' => 'campus', 'label' => 'Ver grabaciones'],
            self::RECORDING_MANAGE => ['group' => 'campus', 'label' => 'Administrar y borrar grabaciones'],
            self::MOODLE_SYNC_RUN => ['group' => 'campus', 'label' => 'Ejecutar la sincronizacion con Moodle'],

            self::FINANCE_VIEW => ['group' => 'finance', 'label' => 'Ver el modulo economico'],
            self::FINANCE_INVOICE_MANAGE => ['group' => 'finance', 'label' => 'Administrar facturas'],
            self::FINANCE_PAYMENT_MANAGE => ['group' => 'finance', 'label' => 'Administrar cobros'],
            self::FINANCE_EXPENSE_MANAGE => ['group' => 'finance', 'label' => 'Administrar gastos'],
            self::FINANCE_REPORT_VIEW => ['group' => 'finance', 'label' => 'Ver reportes economicos'],

            self::USER_VIEW => ['group' => 'system', 'label' => 'Ver usuarios'],
            self::USER_MANAGE => ['group' => 'system', 'label' => 'Administrar usuarios'],
            self::ROLE_ASSIGN => ['group' => 'system', 'label' => 'Asignar roles'],
            self::ROLE_MANAGE => ['group' => 'system', 'label' => 'Crear y modificar roles'],
            self::SETTINGS_VIEW => ['group' => 'system', 'label' => 'Ver la configuracion'],
            self::SETTINGS_MANAGE => ['group' => 'system', 'label' => 'Modificar la configuracion'],
            self::SECRETS_VIEW_MASKED => ['group' => 'system', 'label' => 'Ver credenciales enmascaradas'],
            self::SECRETS_MANAGE => ['group' => 'system', 'label' => 'Administrar credenciales y tokens'],
            self::AUDIT_VIEW => ['group' => 'system', 'label' => 'Ver la bitacora de auditoria'],
            self::BACKUP_MANAGE => ['group' => 'system', 'label' => 'Administrar copias de seguridad'],
            self::APPROVAL_REVIEW => ['group' => 'system', 'label' => 'Aprobar solicitudes criticas'],
            self::SCHOOL_LEVEL_MANAGE => ['group' => 'system', 'label' => 'Administrar los niveles institucionales'],
        ];
    }

    public static function all(): array
    {
        return array_keys(self::catalog());
    }

    public static function isTotalAdminOnly(string $permission): bool
    {
        return in_array($permission, self::totalAdminOnly(), true);
    }

    public static function needsApproval(string $permission): bool
    {
        return in_array($permission, self::requiresApproval(), true);
    }

    public static function isAudited(string $permission): bool
    {
        return in_array($permission, self::alwaysAudited(), true);
    }
}
