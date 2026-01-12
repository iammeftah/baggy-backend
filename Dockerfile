# =========================
# Base image
# =========================
FROM php:8.2-fpm-alpine

# =========================
# System dependencies
# =========================
RUN apk add --no-cache \
    nginx \
    bash \
    curl \
    git \
    unzip \
    libpng \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libzip-dev

# =========================
# PHP extensions
# =========================
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
 && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    opcache

# =========================
# Composer
# =========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================
# Working directory
# =========================
WORKDIR /var/www

# =========================
# Copy project files
# =========================
COPY . .

# =========================
# Install PHP dependencies
# =========================
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# =========================
# Permissions
# =========================
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# =========================
# Nginx config
# =========================
COPY docker/nginx.conf /etc/nginx/nginx.conf

# =========================
# Expose port
# =========================
EXPOSE 80

# =========================
# Start services
# =========================
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
