#!/bin/bash

set -e

chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

touch /var/www/html/storage/logs/laravel.log
chown www-data:www-data /var/www/html/storage/logs/laravel.log
chmod 664 /var/www/html/storage/logs/laravel.log


echo "🚀 Setting up database..."

php /var/www/html/artisan migrate
php /var/www/html/artisan db:seed

echo "✅ Database ready!"

exec "$@"
