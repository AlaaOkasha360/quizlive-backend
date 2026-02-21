# ============================================================
# Stage 1: Install Composer dependencies
# ============================================================
FROM composer:2 AS composer-build

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ============================================================
# Stage 2: Production image (PHP-FPM + Nginx)
# ============================================================
FROM php:8.2-fpm AS production

# ----- System packages & PHP extensions -----
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        zip \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        opcache \
        gd \
        zip \
        xml \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ----- Copy custom configs -----
COPY docker/php.ini        /usr/local/etc/php/conf.d/99-app.ini
COPY docker/nginx.conf     /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

# ----- Application code -----
WORKDIR /var/www/html
COPY --from=composer-build /app /var/www/html

# ----- Storage & cache directories -----
RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# ----- Entrypoint -----
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Render injects the PORT env var (default 10000)
ENV PORT=10000
EXPOSE ${PORT}

ENTRYPOINT ["/entrypoint.sh"]
