#!/bin/bash

set -e

php artisan config:clear

if [ "${RAILPACK_SKIP_MIGRATIONS:-}" != "true" ]; then
  php artisan migrate --force
fi

php artisan storage:link
php artisan optimize

echo "Starting Laravel server ..."

docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
