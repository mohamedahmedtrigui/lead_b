# Production image for Render (PHP-FPM + Nginx, listens on 8080).
# https://serversideup.net/open-source/docker-php/
FROM serversideup/php:8.4-fpm-nginx AS base

USER root
# intl: French formatting · gd: mPDF (PDF lead files) · pdo_pgsql/mbstring are built in.
RUN install-php-extensions intl gd
USER www-data

WORKDIR /var/www/html

# Dependencies first (cached layer while composer.lock is unchanged).
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/app/mpdf storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true \
    LOG_CHANNEL=stderr

EXPOSE 8080
