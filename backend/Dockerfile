FROM php:8.2-apache
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apt-get update && apt-get install -y libpq-dev curl unzip git \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && curl -sS https://getcomposer.org/installer \
       | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*
RUN echo "upload_max_filesize = 50M\npost_max_size = 50M" \
    > /usr/local/etc/php/conf.d/uploads.ini
RUN a2enmod rewrite
COPY apache-site.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]