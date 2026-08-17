FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        curl \
        default-mysql-client \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip mysqli pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# WP-CLI: usado so em dev local para instalar/gerenciar o WordPress sem depender do instalador web
RUN curl -o /usr/local/bin/wp -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

WORKDIR /var/www/html
