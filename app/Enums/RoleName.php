<?php

namespace Crater\Enums;

/**
 * Jerarquia de roles de la Suite ENA.
 *
 * `hierarchy_level`: numero MAS BAJO = MAS autoridad. La regla que sostiene
 * todo el modelo es: nadie puede asignar, editar ni suspender a alguien de
 * nivel jerarquico igual o superior al propio. Sin esa regla, cualquier
 * secretario podria darse a si mismo el rol de administracion total.
 *
 * ALCANCE (`scope_type`):
 *   global  -> toda la institucion, los tres niveles
 *   level   -> un nivel (Primario, Secundario o Terciario)
 *   division-> una o varias divisiones concretas
 *   section -> una o varias secciones de materia
 *
 * La administracion total (nivel 0) es el unico rol que puede tocar
 * credenciales, roles y configuracion sensible. Deberia haber DOS personas con
 * ese rol, no una: si hay una sola y pierde el acceso, la escuela queda sin
 * poder administrar el sistema. Y no mas de tres.
 */
final class RoleName
{
    /** Administracion total. Unico rol con permisos restringidos. */
    const TOTAL_ADMIN = 'total_admin';

    /** Direccion general de la institucion, los tres niveles. */
    const GENERAL_DIRECTOR = 'general_director';

    /** Direccion de un nivel. */
    const LEVEL_DIRECTOR = 'level_director';

    /** Vicedireccion de un nivel. */
    const VICE_DIRECTOR = 'vice_director';

    /** Secretaria academica: matriculas, actas, documentacion. */
    const ACADEMIC_SECRETARY = 'academic_secretary';

    /** Administracion economica: facturacion, cobros, gastos. */
    const FINANCE_ADMIN = 'finance_admin';

    /** Preceptor o tutor de division: asistencia y seguimiento. */
    const PRECEPTOR = 'preceptor';

    /** Docente a cargo de secciones de materia. */
    const TEACHER = 'teacher';

    /** Equipo de orientacion escolar. Unico rol no directivo con acceso sensible. */
    const COUNSELOR = 'counselor';

    /** Bibliotecario, administrativo y demas personal de apoyo. */
    const STAFF = 'staff';

    /** Familia o responsable legal. */
    const GUARDIAN = 'guardian';

    /** Estudiante. En Terciario accede a sus propios datos sin tutor. */
    const STUDENT = 'student';

    /**
     * Definicion completa de los roles del sistema.
     *
     * @return array<string, array{label: string, hierarchy_level: int, scope_type: string, permissions: array}>
     */
    public static function definitions(): array
    {
        $P = Permission::class;

        return [
            self::TOTAL_ADMIN => [
                'label' => 'Administracion total',
                'description' => 'Control completo del sistema, incluidas credenciales y roles. Reservado a dos o tres personas.',
                'hierarchy_level' => 0,
                'scope_type' => 'global',
                // Todos, sin excepcion.
                'permissions' => Permission::all(),
            ],

            self::GENERAL_DIRECTOR => [
                'label' => 'Direccion general',
                'description' => 'Conduccion de la institucion en los tres niveles. No administra credenciales.',
                'hierarchy_level' => 10,
                'scope_type' => 'global',
                'permissions' => array_values(array_diff(Permission::all(), [
                    // La direccion general conduce, pero no toca la infraestructura.
                    $P::SECRETS_MANAGE,
                    $P::SECRETS_VIEW_MASKED,
                    $P::ROLE_MANAGE,
                    $P::BACKUP_MANAGE,
                    $P::SETTINGS_MANAGE,
                    $P::SCHOOL_LEVEL_MANAGE,
                    $P::PROMOTION_REVERT,
                ])),
            ],

            self::LEVEL_DIRECTOR => [
                'label' => 'Direccion de nivel',
                'description' => 'Conduccion de un nivel. Aprueba cierres de periodo y firma documentos.',
                'hierarchy_level' => 20,
                'scope_type' => 'level',
                'permissions' => [
                    $P::ACADEMIC_YEAR_VIEW, $P::ACADEMIC_YEAR_MANAGE,
                    $P::STUDY_PLAN_VIEW, $P::STUDY_PLAN_MANAGE,
                    $P::DIVISION_VIEW, $P::DIVISION_MANAGE,
                    $P::SECTION_VIEW, $P::SECTION_MANAGE, $P::SECTION_ASSIGN_TEACHER,
                    $P::ENROLLMENT_VIEW, $P::ENROLLMENT_MANAGE, $P::ENROLLMENT_TRANSFER,
                    $P::GRADE_VIEW_ALL, $P::GRADE_PUBLISH, $P::GRADE_AMEND_CLOSED,
                    $P::GRADE_OVERRIDE_COMPUTED, $P::TERM_CLOSE, $P::SCALE_MANAGE,
                    $P::ATTENDANCE_VIEW_ALL, $P::ATTENDANCE_JUSTIFY, $P::ATTENDANCE_AMEND_PAST,
                    $P::PROMOTION_RULES_VIEW, $P::PROMOTION_SIMULATE, $P::PROMOTION_OVERRIDE_RESULT,
                    $P::DOCUMENT_VIEW_ALL, $P::DOCUMENT_ISSUE_INTERNAL,
                    $P::DOCUMENT_UPLOAD_OFFICIAL, $P::DOCUMENT_SIGN,
                    $P::STUDENT_VIEW_BASIC, $P::STUDENT_VIEW_FILE, $P::STUDENT_VIEW_SENSITIVE,
                    $P::STUDENT_MANAGE, $P::GUARDIAN_MANAGE,
                    $P::CLASS_VIEW, $P::CLASS_SCHEDULE, $P::RECORDING_VIEW, $P::RECORDING_MANAGE,
                    $P::USER_VIEW, $P::ROLE_ASSIGN, $P::SETTINGS_VIEW, $P::AUDIT_VIEW,
                ],
            ],

            self::VICE_DIRECTOR => [
                'label' => 'Vicedireccion',
                'description' => 'Acompania a la direccion de nivel. No firma documentos ni cierra periodos.',
                'hierarchy_level' => 25,
                'scope_type' => 'level',
                'permissions' => [
                    $P::ACADEMIC_YEAR_VIEW, $P::STUDY_PLAN_VIEW,
                    $P::DIVISION_VIEW, $P::DIVISION_MANAGE,
                    $P::SECTION_VIEW, $P::SECTION_MANAGE,
                    $P::ENROLLMENT_VIEW, $P::ENROLLMENT_MANAGE,
                    $P::GRADE_VIEW_ALL, $P::GRADE_PUBLISH,
                    $P::ATTENDANCE_VIEW_ALL, $P::ATTENDANCE_JUSTIFY,
                    $P::PROMOTION_RULES_VIEW, $P::PROMOTION_SIMULATE,
                    $P::DOCUMENT_VIEW_ALL, $P::DOCUMENT_ISSUE_INTERNAL,
                    $P::STUDENT_VIEW_BASIC, $P::STUDENT_VIEW_FILE, $P::STUDENT_MANAGE,
                    $P::CLASS_VIEW, $P::CLASS_SCHEDULE, $P::RECORDING_VIEW,
                    $P::USER_VIEW,
                ],
            ],

            self::ACADEMIC_SECRETARY => [
                'label' => 'Secretaria academica',
                'description' => 'Matriculas, actas, legajos y emision de documentacion.',
                'hierarchy_level' => 30,
                'scope_type' => 'level',
                'permissions' => [
                    $P::ACADEMIC_YEAR_VIEW, $P::STUDY_PLAN_VIEW,
                    $P::DIVISION_VIEW, $P::SECTION_VIEW,
                    $P::ENROLLMENT_VIEW, $P::ENROLLMENT_MANAGE, $P::ENROLLMENT_TRANSFER,
                    $P::GRADE_VIEW_ALL,
                    $P::ATTENDANCE_VIEW_ALL, $P::ATTENDANCE_JUSTIFY,
                    $P::DOCUMENT_VIEW_ALL, $P::DOCUMENT_ISSUE_INTERNAL, $P::DOCUMENT_UPLOAD_OFFICIAL,
                    $P::STUDENT_VIEW_BASIC, $P::STUDENT_VIEW_FILE, $P::STUDENT_MANAGE,
                    $P::GUARDIAN_MANAGE, $P::USER_VIEW,
                ],
            ],

            self::FINANCE_ADMIN => [
                'label' => 'Administracion economica',
                'description' => 'Facturacion, cobros y gastos. No accede a lo academico ni a legajos.',
                'hierarchy_level' => 30,
                'scope_type' => 'level',
                'permissions' => [
                    $P::FINANCE_VIEW, $P::FINANCE_INVOICE_MANAGE, $P::FINANCE_PAYMENT_MANAGE,
                    $P::FINANCE_EXPENSE_MANAGE, $P::FINANCE_REPORT_VIEW,
                    // Solo lo minimo para poder facturar a la familia correcta.
                    $P::STUDENT_VIEW_BASIC, $P::ENROLLMENT_VIEW,
                ],
            ],

            self::PRECEPTOR => [
                'label' => 'Preceptor',
                'description' => 'Asistencia y seguimiento de sus divisiones. Alcance limitado a las divisiones asignadas.',
                'hierarchy_level' => 40,
                'scope_type' => 'division',
                'permissions' => [
                    $P::DIVISION_VIEW, $P::SECTION_VIEW, $P::ENROLLMENT_VIEW,
                    $P::ATTENDANCE_VIEW_ALL, $P::ATTENDANCE_RECORD, $P::ATTENDANCE_JUSTIFY,
                    $P::GRADE_VIEW_ALL,
                    $P::STUDENT_VIEW_BASIC, $P::STUDENT_VIEW_FILE,
                    $P::DOCUMENT_VIEW_SECTION,
                    $P::CLASS_VIEW,
                ],
            ],

            self::TEACHER => [
                'label' => 'Docente',
                'description' => 'Carga notas y asistencia de las secciones a su cargo, y de ninguna otra.',
                'hierarchy_level' => 50,
                'scope_type' => 'section',
                'permissions' => [
                    $P::SECTION_VIEW, $P::ENROLLMENT_VIEW,
                    $P::GRADE_VIEW_OWN_SECTIONS, $P::GRADE_RECORD, $P::GRADE_PUBLISH,
                    $P::ATTENDANCE_VIEW_OWN_SECTIONS, $P::ATTENDANCE_RECORD,
                    $P::STUDENT_VIEW_BASIC,
                    $P::DOCUMENT_VIEW_SECTION,
                    $P::CLASS_VIEW, $P::CLASS_SCHEDULE, $P::CLASS_MODERATE, $P::RECORDING_VIEW,
                ],
            ],

            self::COUNSELOR => [
                'label' => 'Equipo de orientacion',
                'description' => 'Acceso al legajo completo, incluida la informacion sensible. Todo acceso queda auditado.',
                'hierarchy_level' => 40,
                'scope_type' => 'level',
                'permissions' => [
                    $P::DIVISION_VIEW, $P::SECTION_VIEW, $P::ENROLLMENT_VIEW,
                    $P::GRADE_VIEW_ALL, $P::ATTENDANCE_VIEW_ALL,
                    $P::STUDENT_VIEW_BASIC, $P::STUDENT_VIEW_FILE, $P::STUDENT_VIEW_SENSITIVE,
                    $P::DOCUMENT_VIEW_ALL,
                ],
            ],

            self::STAFF => [
                'label' => 'Personal de apoyo',
                'description' => 'Acceso minimo de consulta.',
                'hierarchy_level' => 60,
                'scope_type' => 'level',
                'permissions' => [
                    $P::DIVISION_VIEW, $P::SECTION_VIEW, $P::STUDENT_VIEW_BASIC,
                ],
            ],

            self::GUARDIAN => [
                'label' => 'Familia',
                'description' => 'Ve unicamente a sus hijos vinculados, y solo lo publicado.',
                'hierarchy_level' => 90,
                'scope_type' => 'global',
                'permissions' => [
                    $P::DOCUMENT_VIEW_OWN_CHILDREN,
                ],
            ],

            self::STUDENT => [
                'label' => 'Estudiante',
                'description' => 'Ve sus propias notas, asistencia y clases. En Terciario, sin intermediacion de tutor.',
                'hierarchy_level' => 95,
                'scope_type' => 'global',
                'permissions' => [
                    $P::CLASS_VIEW, $P::RECORDING_VIEW,
                ],
            ],
        ];
    }

    /**
     * Roles que NO pueden ser asignados por nadie que no sea administracion
     * total, aunque tenga el permiso `system.role.assign`.
     */
    public static function protectedRoles(): array
    {
        return [self::TOTAL_ADMIN, self::GENERAL_DIRECTOR];
    }

    public static function all(): array
    {
        return array_keys(self::definitions());
    }
}
