# syntax=docker/dockerfile:1
###############################################################################
# ColdChain EMS — PHP 8.4 FPM image (API, queue workers, scheduler, MQTT sub)
###############################################################################
FROM php:8.4-fpm-alpine AS base

# System deps + build toolchain for PECL extensions
RUN apk add --no-cache \
        bash git curl unzip icu-dev oniguruma-dev libzip-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath pcntl intl zip gd opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

# Composer (copied from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sensible production-ish PHP + opcache settings
COPY infra/php/php.ini /usr/local/etc/php/conf.d/zz-coldchain.ini

WORKDIR /var/www/html

# Install dependencies first (better layer caching)
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-dev --no-autoloader || true

# App source
COPY backend/ ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY infra/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
