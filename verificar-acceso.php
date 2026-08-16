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
