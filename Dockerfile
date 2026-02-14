FROM spiralscout/roadrunner as roadrunner
# OR
# FROM ghcr.io/roadrunner-server/roadrunner as roadrunner

FROM php:8.5-cli

WORKDIR /app

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer redis memcached pcov sockets mbstring

COPY . /app

RUN composer install --no-interaction --prefer-dist --ignore-platform-reqs

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

CMD ["rr", "serve"]
