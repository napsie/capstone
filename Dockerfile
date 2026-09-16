FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip \
    && a2enmod headers expires deflate rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
COPY docker/production.ini /usr/local/etc/php/conf.d/production.ini
COPY scripts/railway-start.sh /usr/local/bin/railway-start
RUN chmod +x /usr/local/bin/railway-start \
    && chown -R www-data:www-data /var/www/html/images/system_logos /var/www/html/uploads

CMD ["railway-start"]
