FROM postgres:${POSTGRES_VERSION:-16}

COPY docker/db/docker-entrypoint-initdb.d/init-pg_trgm-extension.sh /docker-entrypoint-initdb.d/init-pg_trgm-extension.sh
