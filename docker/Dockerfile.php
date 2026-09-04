# Obraz PHP dla backendu Solidusa.
#
# Poza standardowym php-fpm potrzebujemy:
#  - pdo_mysql  -> polaczenie z baza,
#  - intl       -> poprawne sortowanie i formatowanie polskich tekstow,
#  - composer   -> instalacja zaleznosci wewnatrz kontenera.
#
# Rozszerzenie ext-redis jest celowo NIEinstalowane - kolejka zadan jest
# wylaczona, bo srodowisko docelowe nie ma Redisa. Instrukcja przywrocenia:
# docs/ARCHITECTURE.md, sekcja "Kolejka".
FROM php:8.5-fpm-alpine

# OPcache jest w obrazie php:8.5 wkompilowany na stałe - próba jego instalacji
# przez docker-php-ext-install kończy się błędem "can't stat 'modules/*'",
# bo nie powstaje żaden moduł do skopiowania. Instalujemy tylko to, czego
# w obrazie faktycznie nie ma.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache git unzip icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql intl \
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
