# nginx + PHP-FPM on the official PHP Alpine image.
FROM php:8.5-fpm-alpine

ADD --chmod=0755 --checksum=sha256:27fa8d80bd4e22feb9ddcadff1ca40c8f3e8ea942eb2dd8f446ed1afd2f2f0ab \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.13.2/install-php-extensions \
    /usr/local/bin/

RUN apk add --no-cache nginx \
    && install-php-extensions bcmath gd intl mysqli pdo_mysql zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'expose_php=Off' > "$PHP_INI_DIR/conf.d/site.ini" \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

# Alpine's nginx reads /etc/nginx/http.d/, not sites-enabled.
COPY ./lifeofpablo/lifeofpablo.conf /etc/nginx/http.d/default.conf
COPY --chmod=755 ./lifeofpablo/start.sh /usr/local/bin/start.sh

WORKDIR /var/www

# PHP-FPM workers run as www-data, so the site must belong to it to be writable.
COPY --chown=www-data:www-data ./lifeofpablo.com/www /var/www/main
COPY --chown=www-data:www-data ./lifeofpablo-interactives/www /var/www/interactives
COPY --chown=www-data:www-data ./lifeofpablo-guestbook/www/guestbook /var/www/guestbook
COPY --chown=www-data:www-data ./lifeofpablo-linkinbio/www /var/www/linkinbio

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD curl -fsS -o /dev/null http://127.0.0.1/ || exit 1

CMD ["start.sh"]
