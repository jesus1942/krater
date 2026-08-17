<?php
/*
 * verificar-acceso.php — banco de pruebas del control de acceso.
 *
 * Levanta una base SQLite en memoria, corre las migraciones, siembra permisos y
 * roles, crea usuarios de cada rol y ejercita AccessManager de verdad. No es un
 * lint: comprueba decisiones de autorizacion reales.
 *
 * Existe por lo mismo que verificar-esquema.php: el proyecto corre sobre PHP
 * 7.4 con Laravel 8, y en un entorno con PHP moderno PHPUnit no arranca. Esto
 * corre en cualquier lado.
 *
 *   php verificar-acceso.php
 *
 * Sale con codigo 1 si alguna comprobacion falla.
 */

require __DIR__.'/vendor/autoload.php';

use Crater\Enums\Permission as P;
use Crater\Enums\RoleName as R;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;

// --- arranque -------------------------------------------------------------

$contenedor = new Container();
Container::setInstance($contenedor);

$capsule = new Capsule($contenedor);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => false,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$contenedor->instance('db', $capsule->getDatabaseManager());
$contenedor->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
$contenedor->instance('cache', new Repository(new ArrayStore()));
$contenedor->instance('config', new Illuminate\Config\Repository([
    'database' => ['default' => 'default'],
    'app' => ['timezone' => 'UTC'],
]));

Facade::setFacadeApplication($contenedor);

foreach (['Schema' => Illuminate\Support\Facades\Schema::class, 'DB' => Illuminate\Support\Facades\DB::class] as $a => $c) {
    if (! class_exists($a, false)) {
        class_alias($c, $a);
    }
}

// --- migraciones ------------------------------------------------------------

$saltear = ['update_crater_version', 'seed_base_data', 'seed_countries'];
$archivos = glob(__DIR__.'/database/migrations/*.php');
sort($archivos);

foreach ($archivos as $archivo) {
    $nombre = basename($archivo, '.php');

    foreach ($saltear as $patron) {
        if (strpos($nombre, $patron) !== false) {
            continue 2;
        }
    }

    $antes = get_declared_classes();
    require_once $archivo;
    $clase = null;

    foreach (array_diff(get_declared_classes(), $antes) as $c) {
        if (is_subclass_of($c, Illuminate\Database\Migrations\Migration::class)) {
            $clase = $c;
        }
    }

    if ($clase) {
        (new $clase())->up();
    }
}

// --- datos de prueba ---------------------------------------------------------

$ahora = date('Y-m-d H:i:s');

$empresaId = Capsule::table('companies')->insertGetId([
    'name' => 'Escuela Nueva Austral', 'unique_hash' => 'ena', 'created_at' => $ahora, 'updated_at' => $ahora,
]);

$niveles = [];
foreach (['primary' => 'Nivel Primario', 'secondary' => 'Nivel Secundario', 'tertiary' => 'Nivel Terciario'] as $code => $name) {
    $niveles[$code] = Capsule::table('school_levels')->insertGetId([
        'company_id' => $empresaId, 'code' => $code, 'name' => $name,
        'enabled' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
}

// Permisos y roles, con la misma logica que RbacSeeder.
foreach (P::catalog() as $nombre => $meta) {
    Capsule::table('permissions')->insert([
        'name' => $nombre, 'group' => $meta['group'], 'label' => $meta['label'],
        'is_restricted' => P::isTotalAdminOnly($nombre) ? 1 : 0,
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
}

$permisoId = Capsule::table('permissions')->pluck('id', 'name');
$rolId = [];

foreach (R::definitions() as $nombre => $def) {
    $rolId[$nombre] = Capsule::table('roles')->insertGetId([
        'company_id' => $empresaId, 'name' => $nombre, 'label' => $def['label'],
        'description' => $def['description'] ?? null,
        'hierarchy_level' => $def['hierarchy_level'], 'scope_type' => $def['scope_type'],
        'is_system' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    foreach ($def['permissions'] as $permiso) {
        if ($nombre !== R::TOTAL_ADMIN && P::isTotalAdminOnly($permiso)) {
            continue;
        }
        Capsule::table('permission_role')->insert([
            'role_id' => $rolId[$nombre], 'permission_id' => $permisoId[$permiso],
        ]);
    }
}

$contadorUsuarios = 0;

function crearUsuario(string $rol, ?int $nivelId, int $empresaId, array $rolId, string $ahora): Crater\Models\User
{
    global $contadorUsuarios;
    $contadorUsuarios++;

    $id = Capsule::table('users')->insertGetId([
        'name' => $rol, 'email' => $rol.$contadorUsuarios.'@ena.test',
        'password' => 'x', 'role' => 'user', 'company_id' => $empresaId,
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    Capsule::table('role_user')->insert([
        'user_id' => $id, 'role_id' => $rolId[$rol], 'company_id' => $empresaId,
        'school_level_id' => $nivelId, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);

    $u = new Crater\Models\User();
    $u->id = $id;
    // company_id es obligatorio: el resolutor filtra todas las consultas de
    // roles y alcances por empresa, asi que un usuario sin empresa no alcanza
    // nada. Olvidarlo aca hacia fallar todas las comprobaciones.
    $u->company_id = $empresaId;
    $u->exists = true;

    return $u;
}

// --- comprobaciones ------------------------------------------------------------

$acceso = new Crater\Services\Access\AccessManager();
$fallos = [];

function comprobar(string $titulo, bool $real, bool $esperado)
{
    global $fallos;
    $ok = $real === $esperado;

    if (! $ok) {
        $fallos[] = $titulo;
    }

    printf("  %-4s %s\n", $ok ? 'ok' : 'MAL', $titulo);
}

$admin = crearUsuario(R::TOTAL_ADMIN, null, $empresaId, $rolId, $ahora);
$directorNivel = crearUsuario(R::LEVEL_DIRECTOR, $niveles['secondary'], $empresaId, $rolId, $ahora);
$docente = crearUsuario(R::TEACHER, $niveles['secondary'], $empresaId, $rolId, $ahora);
$contable = crearUsuario(R::FINANCE_ADMIN, $niveles['secondary'], $empresaId, $rolId, $ahora);
$familia = crearUsuario(R::GUARDIAN, null, $empresaId, $rolId, $ahora);
$preceptor = crearUsuario(R::PRECEPTOR, $niveles['secondary'], $empresaId, $rolId, $ahora);

echo "\n== administracion total ==\n";
comprobar('puede administrar credenciales', $acceso->allows($admin, P::SECRETS_MANAGE), true);
comprobar('es reconocida como administracion total', $acceso->isTotalAdmin($admin), true);
comprobar('esta en la cuspide de la jerarquia', $acceso->hierarchyLevel($admin) === 0, true);

echo "\n== corte duro de permisos exclusivos ==\n";
comprobar('la direccion de nivel NO administra credenciales', $acceso->allows($directorNivel, P::SECRETS_MANAGE, $niveles['secondary']), false);
comprobar('el docente NO administra credenciales', $acceso->allows($docente, P::SECRETS_MANAGE, $niveles['secondary']), false);

// Se otorga a mano el permiso exclusivo al rol docente, simulando manipulacion
// de la base o un error de configuracion. El resolutor debe negar igual.
Capsule::table('permission_role')->insert([
    'role_id' => $rolId[R::TEACHER], 'permission_id' => $permisoId[P::SECRETS_MANAGE],
]);
$contenedor->instance('cache', new Repository(new ArrayStore()));
comprobar('niega aunque la base otorgue el permiso exclusivo', $acceso->allows($docente, P::SECRETS_MANAGE, $niveles['secondary']), false);
Capsule::table('permission_role')->where('role_id', $rolId[R::TEACHER])->where('permission_id', $permisoId[P::SECRETS_MANAGE])->delete();
$contenedor->instance('cache', new Repository(new ArrayStore()));

echo "\n== alcance por nivel ==\n";
comprobar('el docente de Secundario carga notas en Secundario', $acceso->allows($docente, P::GRADE_RECORD, $niveles['secondary']), true);
comprobar('el docente de Secundario NO carga notas en Terciario', $acceso->allows($docente, P::GRADE_RECORD, $niveles['tertiary']), false);
comprobar('la direccion de Secundario NO cierra periodos en Terciario', $acceso->allows($directorNivel, P::TERM_CLOSE, $niveles['tertiary']), false);

echo "\n== separacion de responsabilidades ==\n";
comprobar('la administracion economica ve facturacion', $acceso->allows($contable, P::FINANCE_VIEW, $niveles['secondary']), true);
comprobar('la administracion economica NO ve el legajo', $acceso->allows($contable, P::STUDENT_VIEW_FILE, $niveles['secondary']), false);
comprobar('la administracion economica NO ve notas', $acceso->allows($contable, P::GRADE_VIEW_ALL, $niveles['secondary']), false);
comprobar('el docente NO ve notas de todo el nivel', $acceso->allows($docente, P::GRADE_VIEW_ALL, $niveles['secondary']), false);
comprobar('el docente NO ve informacion sensible', $acceso->allows($docente, P::STUDENT_VIEW_SENSITIVE, $niveles['secondary']), false);

echo "\n== familia ==\n";
comprobar('la familia ve documentos de sus hijos', $acceso->allows($familia, P::DOCUMENT_VIEW_OWN_CHILDREN), true);
comprobar('la familia NO ve notas de todo el nivel', $acceso->allows($familia, P::GRADE_VIEW_ALL, $niveles['secondary']), false);
comprobar('la familia NO administra usuarios', $acceso->allows($familia, P::USER_MANAGE), false);

echo "\n== alcance fino ==\n";
$seccionPropia = 101;
$seccionAjena = 202;
Capsule::table('user_scopes')->insert([
    'user_id' => $docente->id, 'company_id' => $empresaId,
    'scope_type' => 'course_section', 'scope_id' => $seccionPropia,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);
comprobar('el docente carga notas en su seccion', $acceso->allows($docente, P::GRADE_RECORD, $niveles['secondary'], ['type' => 'course_section', 'id' => $seccionPropia]), true);
comprobar('el docente NO carga notas en una seccion ajena', $acceso->allows($docente, P::GRADE_RECORD, $niveles['secondary'], ['type' => 'course_section', 'id' => $seccionAjena]), false);
comprobar('el preceptor sin divisiones asignadas no alcanza ninguna', $acceso->allows($preceptor, P::ATTENDANCE_RECORD, $niveles['secondary'], ['type' => 'division', 'id' => 55]), false);

echo "\n== escalada de privilegios ==\n";
comprobar('nadie edita sus propios roles', $acceso->canManageUser($directorNivel, $directorNivel), false);
comprobar('la direccion de nivel puede gestionar a un docente', $acceso->canManageUser($directorNivel, $docente), true);
comprobar('el docente NO puede gestionar a la direccion', $acceso->canManageUser($docente, $directorNivel), false);
comprobar('la direccion de nivel NO otorga administracion total', $acceso->canGrantRole($directorNivel, R::TOTAL_ADMIN, 0, $niveles['secondary']), false);
comprobar('la direccion de nivel NO otorga un rol de su misma jerarquia', $acceso->canGrantRole($directorNivel, R::LEVEL_DIRECTOR, 20, $niveles['secondary']), false);
comprobar('la direccion de nivel otorga el rol docente en su nivel', $acceso->canGrantRole($directorNivel, R::TEACHER, 50, $niveles['secondary']), true);
comprobar('la direccion de nivel NO otorga roles en otro nivel', $acceso->canGrantRole($directorNivel, R::TEACHER, 50, $niveles['tertiary']), false);
comprobar('la administracion total otorga administracion total', $acceso->canGrantRole($admin, R::TOTAL_ADMIN, 0), true);
comprobar('el docente NO otorga ningun rol', $acceso->canGrantRole($docente, R::STAFF, 60, $niveles['secondary']), false);

echo "\n== vigencia por fechas ==\n";
$suplente = crearUsuario(R::TEACHER, $niveles['secondary'], $empresaId, $rolId, $ahora);
Capsule::table('role_user')->where('user_id', $suplente->id)->update([
    'starts_on' => date('Y-m-d', strtotime('-30 days')),
    'ends_on' => date('Y-m-d', strtotime('-1 day')),
]);
$contenedor->instance('cache', new Repository(new ArrayStore()));
comprobar('un rol vencido ya no otorga permisos', $acceso->allows($suplente, P::GRADE_RECORD, $niveles['secondary']), false);


echo "\n== filtrado de listados por alcance ==\n";

// La direccion ve todo el nivel; el preceptor y el docente, solo lo suyo.
comprobar('la direccion de nivel tiene alcance de nivel completo', $acceso->hasLevelWideScope($directorNivel, $niveles['secondary']), true);
comprobar('el preceptor NO tiene alcance de nivel completo', $acceso->hasLevelWideScope($preceptor, $niveles['secondary']), false);
comprobar('el docente NO tiene alcance de nivel completo', $acceso->hasLevelWideScope($docente, $niveles['secondary']), false);
comprobar('la administracion total tiene alcance completo', $acceso->hasLevelWideScope($admin, $niveles['secondary']), true);

// Se arma estructura real para probar el filtrado de divisiones.
$cicloId = Capsule::table('academic_years')->insertGetId([
    'company_id' => $empresaId, 'school_level_id' => $niveles['secondary'],
    'year' => 2027, 'name' => 'Ciclo 2027', 'starts_on' => '2027-03-01', 'ends_on' => '2027-12-15',
    'status' => 'active', 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$cursoId = Capsule::table('grade_levels')->insertGetId([
    'company_id' => $empresaId, 'school_level_id' => $niveles['secondary'],
    'name' => '3.er anio', 'position' => 3, 'enabled' => 1,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);
$divA = Capsule::table('divisions')->insertGetId([
    'company_id' => $empresaId, 'school_level_id' => $niveles['secondary'],
    'academic_year_id' => $cicloId, 'grade_level_id' => $cursoId, 'name' => 'A',
    'enabled' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$divB = Capsule::table('divisions')->insertGetId([
    'company_id' => $empresaId, 'school_level_id' => $niveles['secondary'],
    'academic_year_id' => $cicloId, 'grade_level_id' => $cursoId, 'name' => 'B',
    'enabled' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$materiaId = Capsule::table('subjects')->insertGetId([
    'company_id' => $empresaId, 'school_level_id' => $niveles['secondary'],
    'name' => 'Matematica', 'duration' => 'annual', 'counts_for_promotion' => 1, 'enabled' => 1,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);
$seccionEnA = Capsule::table('course_sections')->insertGetId([
    'company_id' => $empresaId, 'school_level_id' => $niveles['secondary'],
    'academic_year_id' => $cicloId, 'division_id' => $divA, 'subject_id' => $materiaId,
    'status' => 'active', 'created_at' => $ahora, 'updated_at' => $ahora,
]);

// El preceptor tiene asignada solo la division B.
Capsule::table('user_scopes')->insert([
    'user_id' => $preceptor->id, 'company_id' => $empresaId,
    'scope_type' => 'division', 'scope_id' => $divB,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);

$alcancePreceptor = $acceso->scopedDivisionIds($preceptor);
comprobar('el preceptor alcanza solo su division', $alcancePreceptor === [$divB], true);

// El docente no tiene division asignada, pero dicta una seccion en la A: la
// alcanza por relacion, sin necesidad de duplicar el alcance.
Capsule::table('user_scopes')->insert([
    'user_id' => $docente->id, 'company_id' => $empresaId,
    'scope_type' => 'course_section', 'scope_id' => $seccionEnA,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);

$alcanceDocente = $acceso->scopedDivisionIds($docente);
comprobar('el docente alcanza la division por su seccion', in_array($divA, $alcanceDocente, true), true);
comprobar('el docente NO alcanza la division ajena', in_array($divB, $alcanceDocente, true), false);

// Alguien sin ningun alcance no alcanza nada. Que devuelva lista vacia importa:
// el controlador la usa en un whereIn, y una lista vacia devuelve cero filas.
$sinAlcance = crearUsuario(R::STAFF, $niveles['secondary'], $empresaId, $rolId, $ahora);
comprobar('sin alcance asignado no se alcanza ninguna division', $acceso->scopedDivisionIds($sinAlcance) === [], true);


echo "\n== aislamiento entre instituciones ==\n";

// Segunda empresa con su propio juego de roles, para probar que nada cruza.
$otraEmpresaId = Capsule::table('companies')->insertGetId([
    'name' => 'Otra escuela', 'unique_hash' => 'otra', 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$otroNivelId = Capsule::table('school_levels')->insertGetId([
    'company_id' => $otraEmpresaId, 'code' => 'secondary', 'name' => 'Secundario ajeno',
    'enabled' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$rolAjenoId = Capsule::table('roles')->insertGetId([
    'company_id' => $otraEmpresaId, 'name' => R::TOTAL_ADMIN, 'label' => 'Administracion total',
    'hierarchy_level' => 0, 'scope_type' => 'global', 'is_system' => 1,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);
foreach (R::definitions()[R::TOTAL_ADMIN]['permissions'] as $permiso) {
    Capsule::table('permission_role')->insert([
        'role_id' => $rolAjenoId, 'permission_id' => $permisoId[$permiso],
    ]);
}

// Un usuario de NUESTRA empresa al que se le cuelga el rol de administracion
// total de la OTRA empresa. Es el escenario de una fila mal insertada o de un
// intento de escalada cruzando instituciones.
$intruso = crearUsuario(R::TEACHER, $niveles['secondary'], $empresaId, $rolId, $ahora);
Capsule::table('role_user')->insert([
    'user_id' => $intruso->id, 'role_id' => $rolAjenoId, 'company_id' => $otraEmpresaId,
    'school_level_id' => null, 'created_at' => $ahora, 'updated_at' => $ahora,
]);
$contenedor->instance('cache', new Repository(new ArrayStore()));

comprobar('un rol de otra empresa NO convierte en administracion total', $acceso->isTotalAdmin($intruso), false);
comprobar('un rol de otra empresa NO aporta permisos', $acceso->allows($intruso, P::USER_MANAGE, $niveles['secondary']), false);
comprobar('un rol de otra empresa NO mejora la jerarquia', $acceso->hierarchyLevel($intruso) === 50, true);

// Un alcance cargado bajo otra empresa tampoco alcanza.
Capsule::table('user_scopes')->insert([
    'user_id' => $docente->id, 'company_id' => $otraEmpresaId,
    'scope_type' => 'division', 'scope_id' => 9999,
    'created_at' => $ahora, 'updated_at' => $ahora,
]);
comprobar('un alcance de otra empresa no suma divisiones', in_array(9999, $acceso->scopedDivisionIds($docente), true), false);

// --- informe -------------------------------------------------------------------

echo "\n".str_repeat('=', 72)."\n";

if ($fallos) {
    echo 'FALLARON '.count($fallos).' comprobaciones:'."\n";
    foreach ($fallos as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "Todas las comprobaciones de control de acceso pasaron.\n";
exit(0);
