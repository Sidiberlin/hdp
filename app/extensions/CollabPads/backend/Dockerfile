FROM composer:latest AS builder
COPY bin /usr/src/CollabpadsBackend/bin
COPY src /usr/src/CollabpadsBackend/src
COPY composer.json /usr/src/CollabpadsBackend/
COPY config.docker.php /usr/src/CollabpadsBackend/config.php
RUN cd /usr/src/CollabpadsBackend/ && composer update --no-dev --ignore-platform-req ext-mongodb

FROM php:8.3-cli
RUN pecl install mongodb-1.20.0 && docker-php-ext-enable mongodb
RUN mkdir -p /usr/src/CollabpadsBackend
COPY --from=builder /usr/src/CollabpadsBackend/ /usr/src/CollabpadsBackend/
WORKDIR /usr/src/CollabpadsBackend
# ps is required for the init.sh script
RUN apt-get update && apt-get install -y procps
ENTRYPOINT [ "sh", "./bin/init.sh" ]
