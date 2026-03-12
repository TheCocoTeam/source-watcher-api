# API server: PHP 8.4 Apache (aligned with board and dev PHP 8.4).
# Build from parent directory so Core is available (see docker-compose build context).
# Rebuild after changes: docker compose build api
FROM php:8.4-apache

RUN apt-get update -y && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends libzip-dev libpq-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mysqli mbstring zip pdo_pgsql pgsql \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Context must be parent dir (source-watcher-dev-env) so both api and core exist
COPY source-watcher-api /var/www/html
COPY source-watcher-core /var/www/html/source-watcher-core

RUN mkdir -p /var/www/html/.source-watcher/transformations \
    && chown -R www-data:www-data /var/www/html/.source-watcher

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
