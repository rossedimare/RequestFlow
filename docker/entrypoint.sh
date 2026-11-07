#!/bin/sh
# Container entrypoint for Render: validate the environment, cache the config,
# run the database migrations (bounded retries), then start Apache.
set -eu

cd /var/www/html

log() { echo "[entrypoint] $*"; }
fail() { echo "[entrypoint] ERROR: $*" >&2; exit 1; }

# Render provides PORT; Apache reads it from the environment (docker/apache.conf).
PORT="${PORT:-10000}"
export PORT

# --- Required runtime configuration (never baked into the image) -------------
[ -n "${APP_KEY:-}" ] || fail "APP_KEY is not set. Generate one with 'php artisan key:generate --show' and set it as an environment variable."

[ "${DB_CONNECTION:-}" = "pgsql" ] || fail "DB_CONNECTION must be 'pgsql' (got '${DB_CONNECTION:-unset}'). The image has no SQLite database."

if [ -z "${DB_URL:-}" ] && { [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ]; }; then
    fail "No database configured. Set DB_URL (Neon connection string) or DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD."
fi

[ "${APP_ENV:-}" = "production" ] || log "WARNING: APP_ENV is '${APP_ENV:-unset}', expected 'production'."
[ "${APP_DEBUG:-false}" = "false" ] || log "WARNING: APP_DEBUG is enabled; do not use this in production."

# Behind Render's TLS proxy these are needed for HTTPS to work correctly. They are
# warnings only (the app still starts) and never print the variables' values.
[ -n "${TRUSTED_PROXIES:-}" ] || log "WARNING: TRUSTED_PROXIES is not set. Behind Render's proxy Laravel will see plain HTTP, so asset and redirect URLs will use http:// (mixed content) and signed email links may break. Set TRUSTED_PROXIES=*."
[ "${SESSION_SECURE_COOKIE:-}" = "true" ] || log "WARNING: SESSION_SECURE_COOKIE is not set to 'true'. Session cookies will not carry the Secure flag. Set SESSION_SECURE_COOKIE=true."

# Do not let a sleeping database make connections hang indefinitely (libpq reads this).
PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT:-10}"
export PGCONNECT_TIMEOUT

# --- Cache the configuration first, so every later artisan call and the Apache
# workers use the runtime environment variables captured at this moment. -------
log "Caching configuration..."
php artisan config:cache --no-interaction || fail "config:cache failed."

# --- Migrations, with bounded retries (Neon may still be waking up) -----------
MAX_ATTEMPTS="${MIGRATE_MAX_ATTEMPTS:-5}"
RETRY_DELAY="${MIGRATE_RETRY_DELAY:-5}"
attempt=1

while :; do
    log "Running migrations (attempt ${attempt}/${MAX_ATTEMPTS})..."
    if php artisan migrate --force --no-interaction; then
        log "Migrations finished."
        break
    fi

    if [ "$attempt" -ge "$MAX_ATTEMPTS" ]; then
        fail "Migrations failed after ${attempt} attempts. Check the database settings and the log above. Not starting the web server."
    fi

    log "Migration attempt ${attempt} failed; retrying in ${RETRY_DELAY}s..."
    attempt=$((attempt + 1))
    sleep "$RETRY_DELAY"
done

# --- Make the writable directories usable by the Apache worker user ----------
chown -R www-data:www-data storage bootstrap/cache

log "Starting: $*"
exec "$@"
