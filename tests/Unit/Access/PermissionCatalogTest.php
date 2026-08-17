<?php

use Crater\Enums\Permission;
use Crater\Enums\RoleName;

/**
 * Invariantes del catalogo de permisos y de la jerarquia de roles.
 *
 * Estos tests no tocan la base de datos: verifican que las definiciones del
 * codigo sean coherentes entre si. Corren rapido y son la primera linea de
 * defensa contra un error de configuracion de permisos, que es la clase de bug
 * que no se nota hasta que alguien ve lo que no tiene que ver.
 *
 * Si agregas un permiso o un rol y alguno de estos falla, no lo silencies:
 * probablemente el error este en la definicion nueva.
 */

it('tiene todas las constantes de permiso registradas en el catalogo', function () {
    $reflection = new ReflectionClass(Permission::class);
    $constants = array_values($reflection->getConstants());
    $catalog = Permission::all();

    $missing = array_diff($constants, $catalog);

    expect($missing)->toBeEmpty(
        'Constantes sin entrada en catalog(): '.implode(', ', $missing)
    );
});

it('no tiene entradas de catalogo sin constante declarada', function () {
    $reflection = new ReflectionClass(Permission::class);
    $constants = array_values($reflection->getConstants());

    $orphans = array_diff(Permission::all(), $constants);

    expect($orphans)->toBeEmpty(
        'Entradas de catalog() sin constante: '.implode(', ', $orphans)
    );
});

it('asigna un grupo y una etiqueta a cada permiso', function () {
    foreach (Permission::catalog() as $name => $meta) {
        expect($meta)->toHaveKeys(['group', 'label']);
        expect($meta['group'])->not->toBeEmpty("El permiso {$name} no tiene grupo");
        expect($meta['label'])->not->toBeEmpty("El permiso {$name} no tiene etiqueta");
    }
});

it('usa nombres de permiso con el formato dominio.recurso.accion', function () {
    foreach (Permission::all() as $name) {
        expect($name)->toMatch(
            '/^[a-z_]+\.[a-z_]+(\.[a-z_]+)?$/',
            "El permiso {$name} no respeta la convencion de nombres"
        );
    }
});

// --- jerarquia de roles ---------------------------------------------------

it('solo le da permisos exclusivos a la administracion total', function () {
    $exclusive = Permission::totalAdminOnly();

    foreach (RoleName::definitions() as $role => $definition) {
        if ($role === RoleName::TOTAL_ADMIN) {
            continue;
        }

        $leaked = array_intersect($definition['permissions'], $exclusive);

        expect($leaked)->toBeEmpty(
            "El rol {$role} tiene permisos reservados a administracion total: ".implode(', ', $leaked)
        );
    }
});

it('define solo roles con permisos que existen', function () {
    $catalog = Permission::all();

    foreach (RoleName::definitions() as $role => $definition) {
        $unknown = array_diff($definition['permissions'], $catalog);

        expect($unknown)->toBeEmpty(
            "El rol {$role} referencia permisos inexistentes: ".implode(', ', $unknown)
        );
    }
});

it('tiene una unica administracion total en el tope de la jerarquia', function () {
    $definitions = RoleName::definitions();
    $topLevel = array_filter($definitions, fn ($d) => $d['hierarchy_level'] === 0);

    expect($topLevel)->toHaveCount(1);
    expect(array_key_first($topLevel))->toBe(RoleName::TOTAL_ADMIN);
});

it('le da a la administracion total todos los permisos', function () {
    $definitions = RoleName::definitions();

    expect($definitions[RoleName::TOTAL_ADMIN]['permissions'])
        ->toHaveCount(count(Permission::all()));
});

it('usa un tipo de alcance valido en cada rol', function () {
    $valid = ['global', 'level', 'division', 'section'];

    foreach (RoleName::definitions() as $role => $definition) {
        expect($definition['scope_type'])->toBeIn(
            $valid,
            "El rol {$role} declara un alcance invalido"
        );
    }
});

// --- separacion de responsabilidades ---------------------------------------

it('no le da acceso academico sensible a la administracion economica', function () {
    $finance = RoleName::definitions()[RoleName::FINANCE_ADMIN]['permissions'];

    $forbidden = array_intersect($finance, [
        Permission::GRADE_VIEW_ALL,
        Permission::STUDENT_VIEW_SENSITIVE,
        Permission::STUDENT_VIEW_FILE,
        Permission::DOCUMENT_VIEW_ALL,
    ]);

    expect($forbidden)->toBeEmpty(
        'La administracion economica no debe acceder al legajo ni a las notas'
    );
});

it('limita al docente a sus propias secciones', function () {
    $teacher = RoleName::definitions()[RoleName::TEACHER]['permissions'];

    expect($teacher)->toContain(Permission::GRADE_VIEW_OWN_SECTIONS);
    expect($teacher)->not->toContain(Permission::GRADE_VIEW_ALL);
    expect($teacher)->not->toContain(Permission::ATTENDANCE_VIEW_ALL);
    expect($teacher)->not->toContain(Permission::STUDENT_VIEW_SENSITIVE);
});

it('no le da ningun permiso de gestion a familias ni estudiantes', function () {
    $dangerous = array_merge(Permission::totalAdminOnly(), [
        Permission::GRADE_RECORD,
        Permission::GRADE_VIEW_ALL,
        Permission::ATTENDANCE_VIEW_ALL,
        Permission::ATTENDANCE_RECORD,
        Permission::STUDENT_VIEW_SENSITIVE,
        Permission::STUDENT_MANAGE,
        Permission::FINANCE_VIEW,
        Permission::USER_MANAGE,
        Permission::AUDIT_VIEW,
    ]);

    foreach ([RoleName::GUARDIAN, RoleName::STUDENT] as $role) {
        $granted = RoleName::definitions()[$role]['permissions'];
        $leaked = array_intersect($granted, $dangerous);

        expect($leaked)->toBeEmpty(
            "El rol {$role} tiene permisos de gestion: ".implode(', ', $leaked)
        );
    }
});

it('reserva el acceso a informacion sensible del legajo a muy pocos roles', function () {
    $withAccess = [];

    foreach (RoleName::definitions() as $role => $definition) {
        if (in_array(Permission::STUDENT_VIEW_SENSITIVE, $definition['permissions'], true)) {
            $withAccess[] = $role;
        }
    }

    // Administracion total, direccion general, direccion de nivel y equipo de
    // orientacion. Nadie mas.
    expect($withAccess)->toEqualCanonicalizing([
        RoleName::TOTAL_ADMIN,
        RoleName::GENERAL_DIRECTOR,
        RoleName::LEVEL_DIRECTOR,
        RoleName::COUNSELOR,
    ]);
});

it('marca como auditado todo permiso que requiere aprobacion', function () {
    $audited = Permission::alwaysAudited();

    foreach (Permission::requiresApproval() as $permission) {
        expect($audited)->toContain(
            $permission,
            "El permiso {$permission} exige aprobacion pero no se audita"
        );
    }
});

it('protege los roles que nadie salvo administracion total puede otorgar', function () {
    expect(RoleName::protectedRoles())->toContain(RoleName::TOTAL_ADMIN);
    expect(RoleName::protectedRoles())->toContain(RoleName::GENERAL_DIRECTOR);
});
