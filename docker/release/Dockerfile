# syntax=docker/dockerfile:1

# Stage 1: PHP dependencies
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# Stage 2: Frontend assets
FROM node:22-bookworm-slim AS frontend

WORKDIR /app/frontend

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# Stage 3: Assemble release tree
FROM alpine:3.20 AS release

RUN apk add --no-cache zip bash

WORKDIR /release

COPY --from=vendor /app /release/app
COPY --from=frontend /app/public/build /release/app/public/build

RUN cd /release/app \
    && rm -rf \
        node_modules \
        frontend/node_modules \
        tests \
        .git \
        .github \
        docker \
        scripts \
        compose.yaml \
        compose.ci.yaml \
        Makefile \
        phpunit.xml \
        .code-review-graph \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && find storage bootstrap/cache -type d -exec chmod 775 {} \;

RUN cd /release && zip -r /taskconnect-release.zip app \
    && sha256sum /taskconnect-release.zip > /taskconnect-release.zip.sha256

FROM scratch AS export

COPY --from=release /taskconnect-release.zip /taskconnect-release.zip
COPY --from=release /taskconnect-release.zip.sha256 /taskconnect-release.zip.sha256
