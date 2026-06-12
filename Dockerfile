#syntax=docker/dockerfile:1
FROM dunglas/frankenphp:1-php8.3 AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]
WORKDIR /app
VOLUME /app/var/

RUN <<-EOF
    apt-get update
    apt-get install -y --no-install-recommends file git
    install-php-extensions @composer apcu intl opcache zip redis pdo_pgsql
    rm -rf /var/lib/apt/lists/*
EOF

ENV COMPOSER_ALLOW_SUPERUSER=1

# Ajustement des chemins : on va chercher les fichiers dans le dossier frankenphp/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Stage de Dev
FROM frankenphp_base AS frankenphp_dev
ENV APP_ENV=dev
ENV XDEBUG_MODE=off
RUN <<-EOF
    mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
    install-php-extensions xdebug
    git config --system --add safe.directory /app
EOF
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]