ARG PHP_VERSION=8.3

FROM shopware/docker-base:${PHP_VERSION}-nginx-otel AS base-image
FROM shopware/shopware-cli:latest-php-${PHP_VERSION} AS shopware-cli

FROM shopware-cli AS build

ARG SHOPWARE_COMPOSER_VERSION_REF

SHELL ["/usr/bin/env", "bash", "-c"]

RUN --mount=type=secret,id=composer_auth,dst=/src/auth.json \
    --mount=type=cache,target=/root/.composer \
    --mount=type=cache,target=/root/.npm <<EOF
set -euo pipefail

shopware-cli project create /src ${SHOPWARE_COMPOSER_VERSION_REF} --verbose

composer -d /src require --ignore-platform-reqs --no-interaction "shopware/deployment-helper"

shopware-cli project ci /src
EOF

FROM base-image

COPY --from=build --chown=82:82 --link /src /var/www/html
ADD --chown=82:82 https://github.com/shopware/web-recovery/releases/latest/download/shopware-installer.phar.php /var/www/html/public/shopware-installer.phar.php
