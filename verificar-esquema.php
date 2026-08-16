<?php
/*
 * verificar-esquema.php — banco de pruebas del esquema.
 *
 * Ejecuta TODAS las migraciones de database/migrations contra una base SQLite
 * en memoria y reporta el esquema resultante. Sirve para validar migraciones
 * nuevas sin levantar Laravel entero (util cuando la version de PHP del entorno
 * no es compatible con el framework, como PHP 8.4 con Laravel 8).
 *
 *   php verificar-esquema.php              # corre todo y lista las tablas
 *   php verificar-esquema.php --tabla=X    # ademas describe la tabla X
 *   php verificar-esquema.php --desde=2026 # solo migraciones cuyo nombre empieza asi
 *
 * Sale con codigo 1 si alguna migracion falla.
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;

$opciones = getopt('', ['tabla::', 'desde::', 'silencioso']);
$silencioso = isset($opciones['silencioso']);

// --- arranque minimo -------------------------------------------------------

$contenedor = new Container();
Container::setInstance($contenedor);

$capsule = new Capsule($contenedor);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$contenedor->instance('db', $capsule->getDatabaseManager());
$contenedor->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
$contenedor->instance('config', new Illuminate\Config\Repository([
    'database' => ['default' => 'default'],
    'app' => ['timezone' => 'UTC'],
]));

Facade::setFacadeApplication($contenedor);

// Algunas migraciones heredadas de Crater usan `Schema` y `DB` sin importarlos,
// contando con los alias globales que registra el framework.
foreach ([
    'Schema' => Illuminate\Support\Facades\Schema::class,
    'DB' => Illuminate\Support\Facades\DB::class,
] as $alias => $clase) {
    if (! class_exists($alias, false)) {
        class_alias($clase, $alias);
    }
}

// Las migraciones de version de Crater tocan storage y semillas de datos; no
// aportan esquema, asi que se saltean.
$SALTEAR = [
    'update_crater_version',
    'seed_base_data',
    'seed_countries',
];

// SQLite no soporta varias operaciones de ALTER que usan las migraciones
// heredadas de Crater. Se desactivan las FK durante la corrida para que el
// orden de creacion no importe.
Capsule::connection()->statement('PRAGMA foreign_keys = OFF');

// --- corrida ---------------------------------------------------------------

$directorio = __DIR__.'/database/migrations';
$archivos = glob($directorio.'/*.php');
sort($archivos);
$total = count($archivos);

if (! empty($opciones['desde'])) {
    $archivos = array_filter($archivos, fn ($a) => str_starts_with(basename($a), $opciones['desde']));
}

$ok = 0;
$fallos = [];

$salteadas = 0;

foreach ($archivos as $archivo) {
    $nombre = basename($archivo, '.php');

    foreach ($SALTEAR as $patron) {
        if (str_contains($nombre, $patron)) {
            $salteadas++;
            continue 2;
        }
    }

    $antes = get_declared_classes();

    try {
        require_once $archivo;
        $nuevas = array_diff(get_declared_classes(), $antes);
        $clase = null;

        foreach ($nuevas as $candidata) {
            if (is_subclass_of($candidata, Illuminate\Database\Migrations\Migration::class)) {
                $clase = $candidata;
            }
        }

        if (! $clase) {
            // Migraciones anonimas: `return new class extends Migration`
            $instancia = require $archivo;
            if (! $instancia instanceof Illuminate\Database\Migrations\Migration) {
                throw new RuntimeException('no se encontro la clase de migracion');
            }
        } else {
            $instancia = new $clase();
        }

        $instancia->up();
        $ok++;

        if (! $silencioso) {
            echo "  ok  {$nombre}\n";
        }
    } catch (Throwable $e) {
        $fallos[] = [$nombre, $e->getMessage()];
        echo "  FALLA {$nombre}\n        ".$e->getMessage()."\n";
    }
}

// --- informe ---------------------------------------------------------------

echo "\n";
echo str_repeat('=', 72)."\n";
echo "Migraciones aplicadas: {$ok} · salteadas (semillas y bumps de version): {$salteadas} · total {$total}\n";

if ($fallos) {
    echo "Fallaron ".count($fallos).":\n";
    foreach ($fallos as [$nombre, $mensaje]) {
        echo "  - {$nombre}: {$mensaje}\n";
    }
}

$tablas = Capsule::connection()
    ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

echo "\nTablas creadas: ".count($tablas)."\n";
foreach ($tablas as $t) {
    $columnas = Capsule::schema()->getColumnListing($t->name);
    echo sprintf("  %-38s %2d columnas\n", $t->name, count($columnas));
}

if (! empty($opciones['tabla'])) {
    $tabla = $opciones['tabla'];
    echo "\n".str_repeat('-', 72)."\n";
    echo "Detalle de `{$tabla}`\n\n";

    foreach (Capsule::connection()->select("PRAGMA table_info({$tabla})") as $c) {
        echo sprintf(
            "  %-28s %-14s %s%s\n",
            $c->name,
            $c->type,
            $c->notnull ? 'NOT NULL ' : '',
            $c->pk ? 'PK' : ''
        );
    }

    $fks = Capsule::connection()->select("PRAGMA foreign_key_list({$tabla})");
    if ($fks) {
        echo "\n  Claves foraneas:\n";
        foreach ($fks as $f) {
            echo "    {$f->from} -> {$f->table}.{$f->to}  (on delete {$f->on_delete})\n";
        }
    }

    $idx = Capsule::connection()->select("PRAGMA index_list({$tabla})");
    if ($idx) {
        echo "\n  Indices:\n";
        foreach ($idx as $i) {
            $cols = array_map(
                fn ($x) => $x->name,
                Capsule::connection()->select("PRAGMA index_info({$i->name})")
            );
            $tipo = $i->unique ? 'UNIQUE' : 'index';
            echo "    {$tipo} (".implode(', ', $cols).")\n";
        }
    }
}

exit($fallos ? 1 : 0);
