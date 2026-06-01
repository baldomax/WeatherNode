#!/bin/sh
set -eu

APP_DIR="/var/www/html"
FIRST_RUN_MARKER="$APP_DIR/storage/app/.docker-initialized"

cd "$APP_DIR"

ensure_writable_paths() {
    mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/app" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
    touch "$APP_DIR/database/database.sqlite"

    # Mounted volumes can come in with restrictive host-side ownership/permissions.
    # Normalize write access at startup so Laravel can write logs/cache/sqlite.
    if [ "$(id -u)" -eq 0 ]; then
        chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database" || true
    fi

    chmod 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database" || true
    find "$APP_DIR/storage" -type d -exec chmod 775 {} \; || true
    find "$APP_DIR/storage" -type f -exec chmod 664 {} \; || true
    find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; || true
    chmod 664 "$APP_DIR/database/database.sqlite" || true
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
