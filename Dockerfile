FROM dunglas/frankenphp:1-php8.5-trixie AS build

RUN install-php-extensions bcmath intl pcntl pdo_pgsql zip

WORKDIR /app

FROM build AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM node:24-trixie-slim AS node-modules

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

FROM vendor AS assets

COPY --from=node-modules /usr/local/bin/node /usr/local/bin/node
COPY --from=node-modules /app/node_modules ./node_modules
RUN node_modules/.bin/vp build

FROM dunglas/frankenphp:1-php8.5-alpine AS app

RUN install-php-extensions bcmath intl pcntl pdo_pgsql \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'opcache.validate_timestamps=0\nopcache.memory_consumption=128\n' > "$PHP_INI_DIR/conf.d/app.ini" \
    && chown -R www-data:www-data /data/caddy /config/caddy

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /app /app
COPY --from=assets --chown=www-data:www-data /app/public/build /app/public/build

USER www-data
