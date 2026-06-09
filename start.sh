#!/bin/sh
php-fpm -D
caddy run --config /var/www/Caddyfile --adapter caddyfile
