FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    bash \
    zip \
    unzip \
    sqlite \
    sqlite-dev \
    curl \
    git \
    icu-dev \
    libxml2-dev \
    oniguruma-dev \
    libzip-dev \
    zlib-dev \
    mysql-client \
    build-base \
    g++ \
    make \
    autoconf \
    supervisor

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    intl \
    xml \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

COPY laravel-setup.sh /usr/local/bin/laravel-setup.sh
RUN chmod +x /usr/local/bin/laravel-setup.sh

RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www \
    && chown -R www:www . \
    && chmod -R 775 storage bootstrap/cache

USER www

ENTRYPOINT ["/usr/local/bin/laravel-setup.sh"]
