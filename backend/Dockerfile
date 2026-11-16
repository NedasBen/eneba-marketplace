# Production image for the Laravel API (deployed on Render).
# Official PHP + Apache image: one process, no supervisor, easy to reason about.
FROM php:8.2-apache

# --- System deps & PHP extensions -------------------------------------------
# libpq: PostgreSQL client library for pdo_pgsql. unzip: composer dist installs.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# --- Apache: serve /public, enable .htaccess rewrites, listen on $PORT -------
# Render injects PORT at runtime; Apache expands ${PORT} from the environment.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=8080
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!Listen 80!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite headers

# --- PHP dependencies (cached layer: only re-runs when composer files change) -
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# --- Application code --------------------------------------------------------
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
