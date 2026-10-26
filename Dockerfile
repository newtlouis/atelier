# FrankenPHP embarque PHP 8.4 et Caddy : un seul processus sert l'application,
# pas de PHP-FPM ni de Nginx à configurer.
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions \
        pdo_pgsql \
        intl \
        opcache \
        zip

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

WORKDIR /app
ENV SERVER_NAME=:80

# --- Image de développement : le code est monté en volume, Xdebug disponible ---
FROM base AS dev
ENV APP_ENV=dev
RUN install-php-extensions xdebug \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# --- Image de production : le code est copié, les dépendances figées ---
FROM base AS prod
ENV APP_ENV=prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php bin/console cache:warmup
