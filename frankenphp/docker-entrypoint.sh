#!/bin/sh
# Container entrypoint (ADR-005): waits for PostgreSQL, applies migrations and
# imports the Markdown content, then hands over to FrankenPHP.
#
# Migrations + import run when APP_ENV=prod or RUN_MIGRATIONS=1. Any other
# command (composer, bin/console, vendor/bin/phpunit, sh) is executed directly.
set -e

if [ "$1" = 'frankenphp' ]; then
	# Railway injects PORT; Caddy reads SERVER_NAME.
	if [ -n "${PORT:-}" ]; then
		export SERVER_NAME=":${PORT}"
	fi

	# Dev container without vendor/: install dependencies on first start.
	if [ "${APP_ENV:-dev}" != 'prod' ] && [ -f composer.json ] && [ ! -d vendor ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	if [ -n "${DATABASE_URL:-}" ] || grep -qs '^DATABASE_URL=' .env .env.local 2>/dev/null; then
		echo 'Warte auf PostgreSQL ...'
		attempts=0
		until php bin/console dbal:run-sql -q 'SELECT 1' >/dev/null 2>&1; do
			attempts=$((attempts + 1))
			if [ "$attempts" -ge 60 ]; then
				echo 'PostgreSQL ist nach 60 Versuchen nicht erreichbar.' >&2
				exit 1
			fi
			sleep 1
		done
		echo 'PostgreSQL ist erreichbar.'

		if [ "${APP_ENV:-dev}" = 'prod' ] || [ "${RUN_MIGRATIONS:-0}" = '1' ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
			php bin/console docs:import
		fi
	fi

	mkdir -p var/cache var/log
fi

exec docker-php-entrypoint "$@"
