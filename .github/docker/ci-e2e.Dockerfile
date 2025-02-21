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

if [[ "${SHOPWARE_COMPOSER_VERSION_REF}" =~ ^dev-.* ]]; then
    export COMPOSER_ROOT_VERSION="6.6.9999999-dev"

    git clone --branch "${SHOPWARE_COMPOSER_VERSION_REF#dev-}" --depth 1 https://github.com/shopware/shopware.git /src
else
    shopware-cli project create /src ${SHOPWARE_COMPOSER_VERSION_REF#v} --verbose
fi

composer -d /src require --ignore-platform-reqs --no-interaction "shopware/deployment-helper"

shopware-cli project ci --with-dev-dependencies /src
EOF

FROM base-image

COPY --from=build --chown=82:82 --link /src /var/www/html
ADD --chown=82:82 https://github.com/shopware/web-recovery/releases/latest/download/shopware-installer.phar.php /var/www/html/public/shopware-installer.phar.php
