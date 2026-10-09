#!/usr/bin/env bash
set -e
cd /app
mkdir -p storage/framework/{cache,sessions,views} storage/logs
chown -R application:application storage
su application -s /bin/bash -c "php artisan migrate --force --no-interaction"
su application -s /bin/bash -c "php artisan optimize"
su application -s /bin/bash -c "php artisan filament:optimize" || true
