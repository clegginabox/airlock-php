FROM dunglas/frankenphp:php8.5.1

WORKDIR /app

# Install PHP extensions
RUN install-php-extensions @composer redis memcached

COPY . /app

RUN composer install --no-interaction --prefer-dist --ignore-platform-req=ext-zookeeper --ignore-platform-req=ext-memcached
