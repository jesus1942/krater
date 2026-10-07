#!/bin/sh
# Revision fila 6: conteos auditados y artefactos del mismo commit de staging.
# Ejecutar desde un worker del mismo commit despues de que Railway marque SUCCESS.
set -eu
php artisan ena:smoke --http
