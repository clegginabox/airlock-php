FROM spiralscout/roadrunner AS roadrunner
# OR
# FROM ghcr.io/roadrunner-server/roadrunner as roadrunner

FROM node:22-alpine AS node-build

WORKDIR /app/examples

COPY examples/package.json examples/package-lock.json* ./
RUN npm ci

COPY examples/resources ./resources
COPY examples/app/views ./app/views
COPY examples/vite.config.js ./
COPY resources/js /app/resources/js

RUN npm run build

FROM php:8.4-cli

WORKDIR /app

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer redis memcached pcov sockets mbstring grpc
RUN apt-get update && apt-get install -y --no-install-recommends supervisor && rm -rf /var/lib/apt/lists/*

COPY . /app

WORKDIR /app/examples

RUN composer install --no-interaction --prefer-dist --ignore-platform-reqs

COPY --from=node-build /app/examples/public/build ./public/build
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

CMD ["rr", "serve"]
