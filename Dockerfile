FROM dunglas/frankenphp:latest-php8.2-alpine

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    icu-dev \
    zlib-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    zip \
    intl \
    opcache

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Set environment
ENV CADDY_GLOBAL_OPTIONS="local_certs"

# Give permissions
RUN chown -R www-data:www-data var && \
    chmod -R 777 var

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader
