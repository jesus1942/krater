#!/bin/sh
# Railway invoca un unico proceso; set -e corta el deploy si falla cualquier paso.
# Solo migraciones y catalogos productivos. Las cuentas ficticias quedan en staging.
set -eu

echo 'SuiteEna predeploy: migraciones'
php artisan migrate --force
echo 'SuiteEna predeploy: conteos institucionales'
php artisan ena:smoke
echo 'SuiteEna predeploy: catalogo de permisos y roles'
php artisan db:seed --class=RbacSeeder --force
php artisan crater:mark-installed
echo 'SuiteEna predeploy: completo'
