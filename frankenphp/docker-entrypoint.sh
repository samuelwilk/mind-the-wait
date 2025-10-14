#!/bin/sh
set -e

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- frankenphp run "$@"
fi

# If running frankenphp, warm up Symfony cache
if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Install the project the first time PHP is started
	# After the installation, the following block can be deleted
	if [ ! -f composer.json ]; then
		rm -Rf tmp/
		composer create-project "symfony/skeleton $SYMFONY_VERSION" tmp --stability="$STABILITY" --prefer-dist --no-progress --no-interaction --no-install

		cd tmp
		cp -Rp . ..
		cd -

		rm -Rf tmp/
	fi

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Symfony warmup
	if grep -q "^DATABASE_URL=" .env 2>/dev/null || [ -n "${DATABASE_URL:-}" ]; then
		echo "Waiting for database to be ready..."
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || php bin/console dbal:run-sql -q "SELECT 1" > /dev/null 2>&1; do
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable:"
			php bin/console dbal:run-sql "SELECT 1"
			exit 1
		else
			echo "The database is now ready and reachable"
		fi

		# Wait for Redis to be ready
		if grep -q "^REDIS_URL=" .env 2>/dev/null || [ -n "${REDIS_URL:-}" ]; then
			echo "Waiting for Redis to be ready..."
			ATTEMPTS_LEFT_TO_REACH_REDIS=60
			# Extract Redis host and port from REDIS_URL (format: redis://host:port)
			REDIS_HOST=$(echo "$REDIS_URL" | sed -E 's|redis://([^:]+):.*|\1|')
			REDIS_PORT=$(echo "$REDIS_URL" | sed -E 's|redis://[^:]+:([0-9]+).*|\1|')

			until [ $ATTEMPTS_LEFT_TO_REACH_REDIS -eq 0 ] || nc -z "$REDIS_HOST" "$REDIS_PORT" > /dev/null 2>&1; do
				sleep 1
				ATTEMPTS_LEFT_TO_REACH_REDIS=$((ATTEMPTS_LEFT_TO_REACH_REDIS - 1))
				echo "Still waiting for Redis to be ready... $ATTEMPTS_LEFT_TO_REACH_REDIS attempts left."
			done

			if [ $ATTEMPTS_LEFT_TO_REACH_REDIS -eq 0 ]; then
				echo "Warning: Redis is not reachable at $REDIS_HOST:$REDIS_PORT"
				echo "Continuing anyway - some features may not work"
			else
				echo "Redis is now ready and reachable"
			fi
		fi

		# Run migrations in production
		if [ "$APP_ENV" = 'prod' ]; then
			echo "Running database migrations..."
			php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
		fi
	fi

	# Warm up cache
	echo "Warming up Symfony cache..."
	php bin/console cache:clear --no-warmup
	php bin/console cache:warmup

	# Set proper permissions for cache/logs
	if [ -d var/cache ] && [ -d var/log ]; then
		# Only change permissions if we have write access
		chmod -R 777 var/cache var/log 2>/dev/null || true
	fi
fi

exec "$@"
