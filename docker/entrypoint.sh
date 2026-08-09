#!/bin/sh
set -eu

APP_DIR="/var/www/html"
FIRST_RUN_MARKER="$APP_DIR/storage/app/.docker-initialized"

# Where the SQLite file lives. Follows DB_DATABASE so the data volume can be
# mounted outside the app directory; mounting it over $APP_DIR/database would
# also cover database/migrations, which a named volume only ever copies from
# the image once, silently freezing migrations at the version that created it.
SQLITE_PATH="${DB_DATABASE:-/var/lib/weathernode/database.sqlite}"
SQLITE_DIR="$(dirname "$SQLITE_PATH")"

cd "$APP_DIR"

ensure_writable_paths() {
    mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/app" "$APP_DIR/bootstrap/cache"

    # Only for SQLite: a MySQL/Postgres install has no file to create, and
    # DB_DATABASE holds a schema name rather than a path.
    if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
        mkdir -p "$SQLITE_DIR"

        # Refuse to start on a new image with a pre-2026.08 compose file.
        #
        # That combination puts the live database in the volume still mounted at
        # $APP_DIR/database, while DB_DATABASE points somewhere with no volume
        # behind it. Starting anyway would create a blank database, migrate it,
        # and look healthy while the real data sat untouched in the old volume
        # until the next `docker compose down` removed it. Moving the file is not
        # the answer either: it would relocate live data out of the volume and
        # onto the container filesystem.
        if [ ! -f "$SQLITE_PATH" ] && [ -f "$APP_DIR/database/database.sqlite" ]; then
            echo "ERROR: found a database at $APP_DIR/database/database.sqlite but DB_DATABASE points to $SQLITE_PATH."
            echo ""
            echo "The data volume moved out of the application directory. Update docker-compose.yml:"
            echo "  - db_data:/var/www/html/database    ->    - db_data:/var/lib/weathernode"
            echo "and set DB_DATABASE: \"/var/lib/weathernode/database.sqlite\""
            echo ""
            echo "Your database is untouched. The same volume simply mounts at the new path."
            exit 1
        fi

        touch "$SQLITE_PATH"
    fi

    # Mounted volumes can come in with restrictive host-side ownership/permissions.
    # Normalize write access at startup so Laravel can write logs/cache/sqlite.
    if [ "$(id -u)" -eq 0 ]; then
        chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
        [ -d "$SQLITE_DIR" ] && chown -R www-data:www-data "$SQLITE_DIR" || true
    fi

    chmod 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
    [ -d "$SQLITE_DIR" ] && chmod 775 "$SQLITE_DIR" || true
    find "$APP_DIR/storage" -type d -exec chmod 775 {} \; || true
    find "$APP_DIR/storage" -type f -exec chmod 664 {} \; || true
    find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; || true
    [ -f "$SQLITE_PATH" ] && chmod 664 "$SQLITE_PATH" || true
}

ensure_writable_paths

case "${APP_KEY:-}" in
    ""|"base64:REPLACE_WITH_YOUR_GENERATED_KEY"|"REPLACE_WITH_YOUR_APP_KEY")
    echo "ERROR: APP_KEY is not set."
    echo "Set APP_KEY in docker-compose.yml before starting Docker."
    echo "Tip: run 'php artisan key:generate --show' and paste the value."
    exit 1
    ;;
esac

if [ "${DOCKER_AUTO_MIGRATE:-true}" = "true" ]; then
    echo "[entrypoint] Running database migrations..."
    php artisan migrate --force --no-interaction
fi

if [ ! -f "$FIRST_RUN_MARKER" ]; then
    echo "[entrypoint] First container startup detected, running one-time bootstrap..."

    php artisan storage:link || true

    if [ "${DOCKER_AUTO_SEED:-true}" = "true" ]; then
        php artisan db:seed --force --no-interaction
    fi

    if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
        php artisan admin:create \
            --email="${ADMIN_EMAIL}" \
            --password="${ADMIN_PASSWORD}" \
            --name="${ADMIN_NAME:-Administrator}" \
            --no-interaction || true
    fi

    touch "$FIRST_RUN_MARKER"
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
