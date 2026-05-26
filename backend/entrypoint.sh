#!/bin/sh
set -e

if [ ! -d /var/www/html/vendor ]; then
    echo "[entrypoint] Running composer install..."
    composer install --no-interaction --no-dev --optimize-autoloader --working-dir=/var/www/html
fi

mkdir -p /var/www/html/public/uploads/documents \
         /var/www/html/public/uploads/campings \
         /var/www/html/public/uploads/reviews
chown -R www-data:www-data /var/www/html/public/uploads
chmod -R 755 /var/www/html/public/uploads

exec apache2-foreground
