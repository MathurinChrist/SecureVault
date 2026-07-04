#!/bin/sh
set -e

cd /app

# ── 1. Composer dependencies ─────────────────────────────────────────────────
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# ── 2. JWT RSA keys ──────────────────────────────────────────────────────────
if [ ! -f config/jwt/private.pem ]; then
    echo "[entrypoint] Generating JWT keys..."
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA \
        -out config/jwt/private.pem \
        -pkeyopt rsa_keygen_bits:4096 \
        -pass pass:"${JWT_PASSPHRASE:-securevault}"
    openssl rsa -pubout \
        -in config/jwt/private.pem \
        -out config/jwt/public.pem \
        -passin pass:"${JWT_PASSPHRASE:-securevault}"
    echo "[entrypoint] JWT keys generated."
fi

# ── 3. Runtime directories ────────────────────────────────────────────────────
mkdir -p var/cache var/log var/share
chmod -R 777 var 2>/dev/null || chmod 777 var/cache var/log 2>/dev/null || true

# ── 4. Database migrations (retry until DB is ready) ────────────────────────
echo "[entrypoint] Running migrations..."
MAX_RETRIES=30
i=0
until php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge "$MAX_RETRIES" ]; then
        echo "[entrypoint] Database unavailable after ${MAX_RETRIES} attempts — starting anyway."
        break
    fi
    echo "[entrypoint] Attempt ${i}/${MAX_RETRIES} — retrying in 2s..."
    sleep 2
done

# ── 5. Cache warm-up ──────────────────────────────────────────────────────────
php bin/console cache:clear --no-warmup 2>/dev/null || true
php bin/console cache:warmup 2>/dev/null || true

echo "[entrypoint] Ready — starting server."
exec "$@"
