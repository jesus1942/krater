#!/bin/sh
# Ejecutar desde un worker del mismo commit despues de que Railway marque SUCCESS.
set -eu
php artisan ena:smoke --http
