#!/bin/sh
# Container start-up: migrate, warm caches, then hand off to Apache.
set -e

cd /var/www/html

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    # Prefer Neon's direct (non-pooled) connection for DDL when provided.
    # Runs before config:cache so the override is not baked into the cache.
    # db:migrate-locked serializes concurrent boots with a session-level
    # advisory lock, which needs this direct connection.
    DB_URL="${DB_MIGRATION_URL:-$DB_URL}" php artisan db:migrate-locked --no-interaction
fi

# Caches config, events, routes and views from the runtime environment.
php artisan optimize --no-interaction

chown -R www-data:www-data storage bootstrap/cache

if [ "${SEED_IF_EMPTY:-false}" = "true" ]; then
    # Seed in the background so Apache binds $PORT immediately and Render's
    # health check (/up, which never touches the database) passes at once.
    # DatabaseSeeder is a single transaction guarded by an advisory lock: the
    # API sees either no catalog or all of it, a second instance skips, an
    # already-seeded database is left alone, and a failed run rolls back so
    # the next boot retries.
    echo "SEED_IF_EMPTY=true: seeding the demo catalog in the background if the database is empty."
    php artisan db:seed --force --no-interaction &
fi

exec "$@"
