# Obraz PHP dla backendu Solidusa.
#
# Poza standardowym php-fpm potrzebujemy trzech rzeczy:
#  - pdo_mysql  -> polaczenie z baza,
#  - redis      -> kolejka zadan (yiisoft/queue-redis wymaga ext-redis),
#  - composer   -> instalacja zaleznosci wewnatrz kontenera.
FROM php:8.2-fpm-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && apk add --no-cache git unzip icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sensowne ustawienia dla developmentu; na produkcji podmien na wlasne php.ini.
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'date.timezone=Europe/Warsaw'; \
    } > /usr/local/etc/php/conf.d/solidus.ini

WORKDIR /app

EXPOSE 9000
