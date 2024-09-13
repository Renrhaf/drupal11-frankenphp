#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'vendor/bin/drush' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	if grep -q ^DATABASE_URL= .env; then
		echo "Waiting for database to be ready..."
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php vendor/bin/drush sql:query "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Drush command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable:"
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo "The database is now ready and reachable"
		fi
	fi

  # Create potentially missing directories
  if [ ! -d "/app/data/files/private/logs" ]; then
    mkdir /app/data/files/private/logs
  fi

  # Create potentially missing directories
  if [ ! -d "/app/data/files/private/logs/supervisor" ]; then
    mkdir /app/data/files/private/logs/supervisor
  fi

  # Copy env variable for CRON
  env >> /etc/environment

  # Use supervisor to handle cron
  echo 'Starting supervisor'
  supervisord -c /app/docker/supervisor/supervisor.conf &>/dev/null &

  mkdir -p data/files/private
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX data/files/private

	mkdir -p web/sites/default/files
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX web/sites/default/files
fi

exec docker-php-entrypoint "$@"
