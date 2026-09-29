FROM composer:2 AS composer

FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip rabbitmq-c libpng libjpeg-turbo freetype oniguruma && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev rabbitmq-c-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev libxml2-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd intl mbstring pdo_pgsql xml zip && \
    pecl install amqp && \
    docker-php-ext-enable amqp && \
    apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

FROM base AS development
ENV APP_ENV=dev
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
RUN mkdir -p storage/cache storage/logs && chown -R www-data:www-data storage

FROM base AS production
ENV APP_ENV=prod
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN mkdir -p storage/cache storage/logs && chown -R www-data:www-data storage
