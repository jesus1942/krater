FROM php:7.4-fpm

# Argumentos definidos en docker-compose.yml (valores por defecto para Railway)
ARG user=www
ARG uid=1000

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libmagickwand-dev \
    mariadb-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Extensiones PHP
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crear usuario del sistema
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

WORKDIR /var/www

# Copiar codigo fuente e instalar dependencias de PHP
COPY --chown=$user:$user . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

USER $user
