#!/bin/sh
set -e

php artisan migrate --force --no-interaction
php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force --no-interaction

exec "$@"