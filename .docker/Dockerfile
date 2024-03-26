# syntax=docker/dockerfile:1

FROM composer:lts as prod-deps
WORKDIR /app
RUN --mount=type=bind,source=./composer.json,target=composer.json \
    --mount=type=bind,source=./composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer validate && \
    composer install --no-dev --no-interaction

FROM composer:lts as dev-deps
WORKDIR /app
RUN --mount=type=bind,source=./composer.json,target=composer.json \
    --mount=type=bind,source=./composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer validate && \
    composer install --no-interaction

FROM php:8.3-cli as base

LABEL org.opencontainers.image.authors="jdenoc"
LABEL org.opencontainers.image.description="Consumer for Scans made by a Doxie Q scanner"
LABEL org.opencontainers.image.licenses="MIT"
LABEL org.opencontainers.image.source="https://github.com/jdenoc/doxie-consumer"
LABEL org.opencontainers.image.title="doxie-consumer"

RUN apt update \
    && apt install -y curl

RUN cp -p "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV USER=doxie
RUN adduser --system -uid 1000 --group --no-create-home $USER

WORKDIR /opt/doxie-consumer
COPY ../src /opt/doxie-consumer/src

FROM base as development
COPY ../tests /opt/doxie-consumer/tests
RUN cp -p --force "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY ../.php-cs-fixer.dist.php /opt/doxie-consumer/.php-cs-fixer.dist.php
COPY --from=dev-deps /app/vendor /opt/doxie-consumer/vendor

FROM development as test
CMD ["./vendor/bin/pest", "tests", "--stop-on-failure"]

FROM base as compile-phar
COPY --from=prod-deps /app/vendor /opt/doxie-consumer/vendor
COPY ../box.json /opt/doxie-consumer/box.json

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions phar

RUN vendor/bin/box validate \
    && vendor/bin/box compile

FROM base as final

ENV SCANNER_HOST=doxie.scanner

WORKDIR /opt/doxie
COPY --from=compile-phar /opt/doxie-consumer/consumer.phar /opt/doxie/consumer.phar
RUN chown --recursive $USER /opt/doxie; \
    rm -rf /opt/doxie-consumer
USER $USER

ENV DOWNLOAD_DIR=/opt/doxie/scans
RUN mkdir --parents $DOWNLOAD_DIR \
    && chown --recursive $USER $DOWNLOAD_DIR

ARG APP_VERSION=dev
ENV APP_VERSION=$APP_VERSION
LABEL org.opencontainers.image.version="$APP_VERSION"

ARG BUILD_DATETIME
LABEL org.opencontainers.image.created="$BUILD_DATETIME"

CMD php consumer.phar -vvv -- $SCANNER_HOST $DOWNLOAD_DIR
