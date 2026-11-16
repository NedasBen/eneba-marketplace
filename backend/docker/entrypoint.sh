#!/bin/sh
# Container start-up: migrate, warm caches, then hand off to Apache.
set -e

cd /var/www/html

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    # Prefer Neon's direct (non-pooled) connection for DDL when provided.
    # Runs before config:cache so the override is not baked into the cache.
    DB_URL="${DB_MIGRATION_URL:-$DB_URL}" php artisan migrate --force --no-interaction
fi

# Caches config, events, routes and views from the runtime environment.
php artisan optimize --no-interaction

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
