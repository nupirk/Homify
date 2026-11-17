#!/usr/bin/env bash
#
# Deploy / update the API on the VPS. Run from the app directory:
#
#   sudo -u www-data bash deploy/deploy.sh
#
# Environment overrides:
#   APP_DIR=/var/www/homify-api
#   BRANCH=main
#   PHP_BIN=php8.3
#   SEED=1          # also run ProductionSeeder (idempotent; first install only)

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/homify-api}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_DIR"

echo "==> Pulling origin/$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Installing composer dependencies"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "==> Preparing database"
# SQLite needs the file to exist before migrate: the driver refuses to create it.
if grep -qE '^DB_CONNECTION=["]?sqlite' .env 2>/dev/null; then
    DB_FILE="$(grep -E '^DB_DATABASE=' .env | head -n1 | cut -d= -f2- | tr -d '"')"
    DB_FILE="${DB_FILE:-database/database.sqlite}"
    case "$DB_FILE" in
        /*) ;;
        *) DB_FILE="$APP_DIR/$DB_FILE" ;;
    esac

    if [ ! -f "$DB_FILE" ]; then
        echo "    creating $DB_FILE"
        touch "$DB_FILE"
    fi

    chown www-data:www-data "$DB_FILE" 2>/dev/null || true
    chmod 660 "$DB_FILE" 2>/dev/null || true
fi

echo "==> Running migrations"
"$PHP_BIN" artisan migrate --force

if [ "${SEED:-0}" = "1" ]; then
    echo "==> Seeding reference data"
    "$PHP_BIN" artisan db:seed --class=ProductionSeeder --force
fi

echo "==> Storage link"
if [ ! -e public/storage ]; then
    "$PHP_BIN" artisan storage:link
fi

echo "==> Rebuilding caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

echo "==> Restarting services"
sudo systemctl restart homify-queue
sudo systemctl reload php8.3-fpm

echo "==> Health check"
curl --fail --silent --max-time 10 http://127.0.0.1/up > /dev/null && echo "OK: /up responded"

echo "Deployed."
