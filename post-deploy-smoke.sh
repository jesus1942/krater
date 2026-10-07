#!/bin/sh
# Revision fila 6: ensayo de backup con clave propia en staging aislado.
# Ejecutar desde un worker del mismo commit despues de que Railway marque SUCCESS.
set -eu
php artisan ena:smoke --http
