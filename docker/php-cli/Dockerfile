#
#  This file is part of the bitrix24-php-sdk package.
#
#  © Maksim Mesilov <mesilov.maxim@gmail.com>
#
#  For the full copyright and license information, please view the LICENSE
#  file that was distributed with this source code.
#

# Separate Composer stage to install PHP dependencies efficiently
# https://hub.docker.com/_/composer
FROM composer:2.8 AS composer

# Use the official PHP CLI base; only bcmath and intl are missing for our lock.
FROM php:8.4-cli-alpine3.23 AS dev-php

# Copy Composer binary from the official Composer image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Link to the source repository for this image (OCI standard label)
LABEL "org.opencontainers.image.source"="https://github.com/mesilov/bitrix24-cli"

# unzip handles Composer dist archives; ICU is required by intl at runtime.
# Build from the base's PHP sources without a PECL or external installer download.
RUN set -eux; \
    apk add --no-cache icu-libs unzip; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev; \
    docker-php-ext-install -j"$(nproc)" bcmath intl pcntl; \
    apk del --no-network .build-deps

WORKDIR /var/www/html

ENV COMPOSER_CACHE_DIR=/tmp/composer/cache

# ── cheap UID/GID layers last (only these rebuild on UID change) ──────────────
ARG UID=10001
ARG GID=10001

RUN addgroup -g "${GID}" cli \
    && adduser -D -u "${UID}" -G cli cli \
    && chown cli:cli /var/www/html \
    && chmod 755 /var/www/html

USER cli
