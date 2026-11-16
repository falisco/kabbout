# The Kabbout server: Symfony behind FrankenPHP, serving the built game and /api on one port.
# Build context: the repository root.

# 1. the game's bundle, built as `npm run build:web` builds it
FROM node:22.23.5-alpine AS web
WORKDIR /src
COPY package.json package-lock.json ./
COPY engine/package.json engine/
COPY bridge/package.json bridge/
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY engine/src engine/src
COPY app/web app/web
# the sources and their tests are in the bundle or of no use to a player
RUN npm run build:web && rm -r app/web/src app/web/test app/web/tsconfig.json

# 2. the PHP dependencies of production, exactly as locked
FROM composer:2 AS vendor
WORKDIR /app
COPY server/composer.json server/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-autoloader --no-scripts
# the class map is closed: it is written once the server's own classes are there
COPY server/src src
RUN composer dump-autoload --no-dev --classmap-authoritative

# 3. what runs: FrankenPHP in classic mode (a fresh PHP for each request), as an unprivileged user
FROM dunglas/frankenphp:1.13.5-php8.5
ENV APP_ENV=prod APP_DEBUG=0
WORKDIR /app
COPY server/composer.json ./
COPY server/bin bin
COPY server/config config
COPY server/public public
COPY server/src src
COPY --from=vendor /app/vendor vendor
COPY --from=web /src/app/web public
COPY server/docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 server/docker/entrypoint.sh /usr/local/bin/kabbout-entrypoint
RUN mkdir -p var \
    && chown -R www-data:www-data var /data /config
USER www-data
# the container is built once, here, and not at the first request
RUN php bin/console cache:warmup
EXPOSE 8080
ENTRYPOINT ["kabbout-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
