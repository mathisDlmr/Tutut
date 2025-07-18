#!/bin/sh

set -eu

php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan event:clear || true

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction
php artisan optimize --no-interaction

chown -R nobody:nobody /var/www/html/storage/app/public/documents /var/www/html/storage/logs

exec php-fpm
