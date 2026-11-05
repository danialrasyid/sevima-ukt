#!/bin/sh
set -e

cd /var/www/html

# .env tidak ikut ke image (.dockerignore); variabel dari docker compose (env_file) tetap berlaku.
[ -f .env ] || cp .env.example .env

# APP_KEY: pakai dari environment bila ada; jika tidak, buat otomatis.
if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64' .env; then
    php artisan key:generate --force
fi

until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-sevima}" >/dev/null 2>&1; do
    echo "Menunggu PostgreSQL..."
    sleep 1
done

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
fi

exec "$@"
