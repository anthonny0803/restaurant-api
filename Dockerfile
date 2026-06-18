# syntax=docker/dockerfile:1

# Production image for both Cloud Run services:
#   - web:    default command (nginx serves HTTP on $PORT)
#   - worker: command overridden to "php artisan queue:work"
# Both keep nginx listening on $PORT so Cloud Run health checks pass.
FROM serversideup/php:8.3-fpm-nginx

# PHP extensions required by the app:
#   - pdo_pgsql: PostgreSQL driver (Neon)
#   - pcntl:     signal handling for graceful queue worker shutdown (--max-time)
#   - intl, bcmath: required by Laravel/Filament
USER root
RUN install-php-extensions pdo_pgsql pcntl intl bcmath
USER www-data

# Production runtime behavior (serversideup AUTORUN):
#   - Regenerate Laravel caches at startup; they depend on env/secrets that
#     Cloud Run injects at runtime, so they cannot be baked at build time.
#   - Migrations are disabled here and run via a dedicated Cloud Run Job to
#     avoid races between concurrent instances.
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    SSL_MODE=off

WORKDIR /var/www/html

# Install PHP dependencies first for better layer caching. Skip scripts to keep
# artisan from booting during build (no env/secrets are available yet).
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy the application and build the optimized, authoritative autoloader.
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# Publish Filament admin panel assets into public/ for production serving.
RUN php artisan filament:assets

# Register the supervised queue worker as an s6 service. It runs alongside
# nginx (so the worker service still answers $PORT for Cloud Run health checks)
# and only processes the queue when WORKER_ENABLED=true.
USER root
COPY docker/s6-rc.d/laravel-worker /etc/s6-overlay/s6-rc.d/laravel-worker
RUN chmod +x /etc/s6-overlay/s6-rc.d/laravel-worker/run \
    && touch /etc/s6-overlay/s6-rc.d/user/contents.d/laravel-worker
USER www-data
