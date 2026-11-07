# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: build the PHP dependencies and the frontend.
#
# Both PHP and Node are needed here because the Vite Wayfinder plugin runs
# `php artisan wayfinder:generate` during `npm run build`, and that command needs
# the Composer dependencies. Nothing from this stage except the build output is
# copied into the final image.
# ---------------------------------------------------------------------------
FROM php:8.5-cli-bookworm AS frontend

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    NPM_CONFIG_UPDATE_NOTIFIER=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node comes from the official image. vite-plus requires Node ^22.18 or ^24.11.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version && npm --version && php --version && composer --version

WORKDIR /app

# Composer dependencies first, so this layer is cached until composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Node dependencies (.npmrc sets ignore-scripts=true).
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .

# The framework needs these directories to boot; they are gitignored, so create them.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize

# Public name only; not a secret.
ARG VITE_APP_NAME=RequestFlow
ENV VITE_APP_NAME=${VITE_APP_NAME}

# Generates the Wayfinder bindings (via php artisan) and builds public/build.
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: the runtime image (Apache + mod_php), with no build tools or Node.
# ---------------------------------------------------------------------------
FROM php:8.5-apache-bookworm

# pdo_pgsql talks to Neon. Keep the runtime library, drop the headers.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq5 libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get purge -y --auto-remove libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'expose_php=Off'; \
        echo 'memory_limit=128M'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=64'; \
        echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/zz-app.ini"

# Apache: listen on Render's PORT and serve public/. ports.conf only declares
# "Listen 80" in the stock image, so it is emptied; the Listen directive is in
# the site file.
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN echo '# Listen is declared in sites-available/000-default.conf (uses $PORT)' \
    > /etc/apache2/ports.conf

WORKDIR /var/www/html

# Application code only: no tests, no node_modules, no .env.
COPY --from=frontend /app/vendor ./vendor
COPY --from=frontend /app/app ./app
COPY --from=frontend /app/bootstrap ./bootstrap
COPY --from=frontend /app/config ./config
COPY --from=frontend /app/database ./database
COPY --from=frontend /app/public ./public
COPY --from=frontend /app/resources/views ./resources/views
COPY --from=frontend /app/routes ./routes
COPY --from=frontend /app/artisan /app/composer.json ./

# Directories Laravel writes to at runtime. The filesystem is ephemeral on
# Render Free: nothing here is meant to survive a restart.
RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Safe defaults only; real values (APP_KEY, DB_URL, mail, ...) come from the
# environment at runtime and are never baked into the image.
ENV PORT=10000 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
