#syntax=docker/dockerfile:1

# Multi-stage build on FrankenPHP (ADR-002, ADR-005).
#   frankenphp_base  shared: PHP extensions, Composer, Caddyfile, entrypoint
#   frankenphp_dev   local development (compose.yaml mounts the repo into /app)
#   frankenphp_prod  Railway image: vendor without dev packages, compiled assets, warm cache
# The last stage is the default target, so `docker build .` produces the production image.

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

# ---------------------------------------------------------------------------
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# No VOLUME for /app/var: an anonymous volume would shadow the bind mount of compose.yaml,
# so a warmed cache from one `docker compose run` would be invisible to the next.

# The Debian mirrors are only reachable over HTTPS from this network; plain HTTP times out.
RUN set -eux; \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources; \
    apt-get update; \
    apt-get install -y --no-install-recommends git; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
        @composer \
        apcu \
        intl \
        opcache \
        pdo_pgsql \
        zip

ENV COMPOSER_ALLOW_SUPERUSER=1
# FrankenPHP/Caddy listens here; Railway overrides it through PORT (see docker-entrypoint.sh).
ENV SERVER_NAME=:8000

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s --interval=30s --timeout=5s \
    CMD curl -fsS "http://localhost:${PORT:-8000}/health" || exit 1

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# ---------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/conf.d/

# The source code is mounted by compose.yaml; nothing to copy here.
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

# ---------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/conf.d/

# Install dependencies first so this layer is cached while the source changes.
COPY --link composer.* symfony.* ./
RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./
RUN rm -Rf frankenphp/ tests/ compose.yaml Makefile

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    php bin/console importmap:install; \
    php bin/console asset-map:compile; \
    php bin/console cache:warmup; \
    chmod +x bin/console; sync;
