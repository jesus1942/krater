FROM node:24-bookworm-slim AS frontend
RUN apt-get update && apt-get install -y --no-install-recommends git ca-certificates \
    && git config --global url."https://github.com/".insteadOf ssh://git@github.com/ \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps --no-audit --no-fund
COPY resources ./resources
COPY webpack.mix.js tailwind.config.js ./
RUN mkdir -p public/assets/js public/assets/css \
    && NODE_OPTIONS=--openssl-legacy-provider npm run production

FROM php:7.4-fpm

ARG user=www
ARG uid=1000

# Dependencias del sistema + nginx
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    mariadb-client \
    nginx \
    gettext-base \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Extensiones PHP
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crear usuario del sistema
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

WORKDIR /var/www

# Copiar codigo fuente
COPY . .

# Incorporar el frontend Vue compilado para que los cambios lleguen a produccion
COPY --from=frontend /app/public/assets /var/www/public/assets
COPY --from=frontend /app/public/mix-manifest.json /var/www/public/mix-manifest.json

# Instalar dependencias PHP
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Directorios de Laravel + permisos + marcar como instalado en la imagen
RUN mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        storage/app/public \
    && echo "1" > storage/app/database_created \
    && ln -sfn /var/www/storage/app/public /var/www/public/storage \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache

# Descargar fuentes Poppins localmente (evita requests externos en runtime)
RUN mkdir -p /var/www/public/assets/fonts/poppins \
    && curl -fsSL "https://cdn.jsdelivr.net/npm/@fontsource/poppins@5/files/poppins-latin-300-normal.woff2" \
            -o /var/www/public/assets/fonts/poppins/poppins-300.woff2 \
    && curl -fsSL "https://cdn.jsdelivr.net/npm/@fontsource/poppins@5/files/poppins-latin-400-normal.woff2" \
            -o /var/www/public/assets/fonts/poppins/poppins-400.woff2 \
    && curl -fsSL "https://cdn.jsdelivr.net/npm/@fontsource/poppins@5/files/poppins-latin-500-normal.woff2" \
            -o /var/www/public/assets/fonts/poppins/poppins-500.woff2 \
    && curl -fsSL "https://cdn.jsdelivr.net/npm/@fontsource/poppins@5/files/poppins-latin-600-normal.woff2" \
            -o /var/www/public/assets/fonts/poppins/poppins-600.woff2

# Configuracion de nginx
RUN cp /var/www/nginx.conf /etc/nginx/sites-available/default.template \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && chmod +x /var/www/start.sh
