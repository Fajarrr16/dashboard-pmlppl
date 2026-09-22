FROM node:22-alpine AS frontend

WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite \
    && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && apache2-foreground"]
