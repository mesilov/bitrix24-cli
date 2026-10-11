# Pipeline adapted from yiipress/engine; see build/THIRD-PARTY-NOTICES.md.
FROM composer:2.8@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer
FROM php:8.5.11-cli-bookworm@sha256:819080ed413631f6ec564184dd9acd08d9425bd863b9f8b5876b5ea2147d55e7 AS base
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 autoconf automake bison build-essential ca-certificates cmake curl \
    file flex git gzip libtool musl-tools patch pkgconf re2c tar unzip xz-utils \
    libicu-dev autopoint \
    && docker-php-ext-install bcmath intl \
    && rm -rf /var/lib/apt/lists/*
ENV PATH="/usr/local/musl/bin:${PATH}"
WORKDIR /app

FROM base AS runtime-builder
COPY build/config.json build/package.py /app/build/
COPY build/patches/ /app/build/patches/
RUN python3 build/package.py linux-amd64 --runtime-only

FROM base AS builder
COPY . /app
ARG B24CLI_SOURCE_SHA
ARG B24CLI_RELEASE_VERSION
ENV B24CLI_SOURCE_SHA=${B24CLI_SOURCE_SHA} B24CLI_RELEASE_VERSION=${B24CLI_RELEASE_VERSION}

FROM builder AS phar-builder
RUN python3 build/package.py phar
FROM scratch AS phar-artifacts
COPY --from=phar-builder /app/dist/phar/ /

FROM builder AS linux-builder
COPY --from=runtime-builder /app/runtime/package-linux-amd64/ /app/runtime/package-linux-amd64/
RUN python3 build/package.py linux-amd64
FROM scratch AS linux-artifacts
COPY --from=linux-builder /app/dist/linux-amd64/ /
