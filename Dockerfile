FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/hosting.ini
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY public/ /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/html/sites \
    && chown -R www-data:www-data /var/www/html/sites \
    && chmod 755 /var/www/html/sites

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
