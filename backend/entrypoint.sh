#!/bin/sh
set -e

echo "[entrypoint] Sincronizare dependinte Composer..."
composer install --no-interaction --no-dev --optimize-autoloader --working-dir=/var/www/html

mkdir -p /var/www/html/public/uploads/documents \
         /var/www/html/public/uploads/campings \
         /var/www/html/public/uploads/reviews
chown -R www-data:www-data /var/www/html/public/uploads
chmod -R 755 /var/www/html/public/uploads

exec apache2-foreground
