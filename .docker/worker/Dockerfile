FROM spiralscout/roadrunner AS roadrunner

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

COPY docker-entrypoint.sh /usr/local/bin/
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/app/examples/supervisord.conf"]
