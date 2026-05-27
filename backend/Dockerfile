FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev curl unzip git \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && curl -sS https://getcomposer.org/installer \
       | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "upload_max_filesize = 50M\npost_max_size = 50M" \
    > /usr/local/etc/php/conf.d/uploads.ini
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

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]