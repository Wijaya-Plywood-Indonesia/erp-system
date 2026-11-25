#!/bin/sh
set -e
# Hanya container php-fpm yang migrasi + cache. Worker/scheduler langsung jalan.
if [ "$1" = "php-fpm" ]; then
  php artisan migrate --force
  php artisan shield:generate --all --panel=admin --no-interaction || true
  php artisan optimize
  php artisan filament:optimize || true
fi
exec "$@"
