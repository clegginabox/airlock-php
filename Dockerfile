FROM dunglas/frankenphp:php8.5.1

WORKDIR /app

# Install PHP extensions
RUN install-php-extensions @composer redis memcached

COPY . /app
