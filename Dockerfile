#syntax=docker/dockerfile:1.4

# Adapted from https://github.com/dunglas/symfony-docker


# Versions
FROM dunglas/frankenphp:1-php8.3 AS frankenphp_upstream


# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target


# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install --no-install-recommends -y \
    acl \
    file \
    gettext \
    git \
    bash \
    pdftk \
    supervisor \
    cron \
    ghostscript \
    lsb-release \
    gnupg2 \
    wget \
    nano \
	&& rm -rf /var/lib/apt/lists/*

# Install PostGreSQL 16 client
RUN sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'; \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg; \
    apt-get update && apt-get install --no-install-recommends -y postgresql-client-16 && rm -rf /var/lib/apt/lists/*

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN set -eux; \
    install-php-extensions \
        @composer \
		    intl \
		    opcache \
		    zip \
        apcu \
        bcmath \
        gd \
        pdo_pgsql \
        uploadprogress \
    ;

COPY --link docker/frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --link docker/frankenphp/conf.d/php-cli.ini $PHP_INI_DIR/php-cli.ini
COPY --link --chmod=755 docker/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link docker/frankenphp/caddy/prod/Caddyfile /etc/caddy/Caddyfile

# Setup cron
COPY --link docker/cron/crontab.prod /etc/cronjob
RUN chmod 0644 /etc/cronjob; \
    crontab /etc/cronjob

# Add some bash aliases
RUN echo "alias drush='/app/vendor/bin/drush'" >> ~/.bashrc; \
    echo "alias ll='ls -al'" >> ~/.bashrc

# Setup supervizor
COPY --link docker/supervisor/supervisor.conf /app/docker/supervisor/supervisor.conf

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
		xdebug \
	;

COPY --link docker/frankenphp/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]
