FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql opcache zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Non-root user
RUN useradd -u 1000 -m appuser
USER appuser

WORKDIR /var/www

COPY --chown=appuser:appuser . .

RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
