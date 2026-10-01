#!/bin/sh
set -eu

: "${PORT:=8080}"
export PORT

php artisan ena:auditar-niveles --json > /tmp/ena-level-audit.json

envsubst '$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default

php-fpm -D
exec nginx -g 'daemon off;'
