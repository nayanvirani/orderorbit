#!/bin/sh
# Starts a container the way Railpack did: the worker runs its own command (RAILPACK_START_CMD,
# e.g. "php artisan schedule:work"); the web service migrates, caches and starts FrankenPHP.
set -e
cd /app

if [ -n "$RAILPACK_START_CMD" ]; then
  exec sh -c "$RAILPACK_START_CMD"
fi

php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan optimize

echo "Starting Laravel server ..."
exec docker-php-entrypoint frankenphp run --config /Caddyfile --adapter caddyfile
