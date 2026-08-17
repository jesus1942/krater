#!/bin/sh
envsubst '$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default
php-fpm -D
nginx -g 'daemon off;'
