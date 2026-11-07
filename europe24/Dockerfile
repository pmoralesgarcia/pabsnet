# nginx + PHP-FPM on the official PHP image (Debian, so www-data keeps UID 33
# and bind-mounted site folders stay writable).
FROM php:8.5-fpm-trixie

ADD --chmod=0755 --checksum=sha256:27fa8d80bd4e22feb9ddcadff1ca40c8f3e8ea942eb2dd8f446ed1afd2f2f0ab \
    https://github.com/mlocati/docker-php-extension-installer/releases/download/2.13.2/install-php-extensions \
    /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions bz2 gd intl zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'expose_php=Off' > "$PHP_INI_DIR/conf.d/site.ini" \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY ./europe24/nginx/europe24.conf /etc/nginx/sites-enabled/default
COPY --chmod=755 ./europe24/start.sh /usr/local/bin/start.sh

WORKDIR /var/www/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD curl -fsS -o /dev/null http://127.0.0.1/ || exit 1

CMD ["start.sh"]
