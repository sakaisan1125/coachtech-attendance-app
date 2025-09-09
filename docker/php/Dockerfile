FROM php:8.3-fpm
RUN apt-get update \
 && apt-get install -y git unzip libicu-dev libonig-dev libzip-dev libpng-dev \
 && docker-php-ext-install pdo_mysql intl zip
# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
