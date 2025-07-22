FROM php:8.3.6-fpm-alpine3.18

RUN apk add --no-cache \
    bash \
    zip unzip \
    sqlite sqlite-dev \
    curl git \
    icu-dev libxml2-dev \
    oniguruma-dev libzip-dev zlib-dev \
    mysql-client build-base g++ make autoconf \
    supervisor \
    gd libpng-dev libjpeg-turbo-dev freetype-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
    pdo pdo_mysql mysqli pdo_sqlite \
    intl xml zip gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY laravel-setup.sh /usr/local/bin/laravel-setup.sh
RUN chmod +x /usr/local/bin/laravel-setup.sh

RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www \
    && chown -R www:www . \
    && chmod -R 775 storage bootstrap/cache

USER www

ENTRYPOINT ["/usr/local/bin/laravel-setup.sh"]
