FROM dunglas/frankenphp:1.13.5-php8.5.12-trixie

RUN install-php-extensions @composer intl zip

ENV SERVER_NAME=:80

WORKDIR /app

COPY . /app

RUN composer install --no-interaction --prefer-dist
