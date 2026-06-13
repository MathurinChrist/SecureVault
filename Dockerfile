FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

# ── System packages ───────────────────────────────────────────────────────────
RUN apk add --no-cache \
    git \
    unzip \
    openssl \
    libpq-dev \
    libzip-dev \
    icu-dev \
    zlib-dev \
    chromium \
    chromium-chromedriver

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo_pgsql \
    zip \
    intl \
    opcache \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# ── Runtime directories ───────────────────────────────────────────────────────
RUN mkdir -p var/cache var/log var/share config/jwt && \
    chmod -R 777 var

# ── FrankenPHP server config ──────────────────────────────────────────────────
ENV SERVER_NAME=:80

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
