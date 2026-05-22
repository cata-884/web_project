FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql
RUN a2enmod rewrite

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
      '<Directory /var/www/html/public>' \
      '  AllowOverride All' \
      '  Require all granted' \
      '</Directory>' \
      > /etc/apache2/conf-available/cat-public.conf \
    && a2enconf cat-public

WORKDIR /var/www/html

EXPOSE 80