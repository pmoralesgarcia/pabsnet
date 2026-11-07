FROM php:8.5-apache

# pdo_sqlite (storage), curl (client discovery and webmention fetches) and
# mbstring/dom (webmention HTML parsing) all ship with this image, so nothing
# needs compiling.

# Configure Apache DocumentRoot and grant permissions to /app/public
RUN a2enmod rewrite headers \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /app/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /app/public>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

# Micropub media uploads go up to 10 MB (PHP's default limit is 2 MB).
RUN printf 'upload_max_filesize=10M\npost_max_size=12M\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app

COPY . /app
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh /app/bin/verify-mentions.php

# Data volume for the SQLite database. Created here so it exists (and is
# owned correctly) even if no volume is mounted.
RUN mkdir -p /app/data && chown -R www-data:www-data /app/data

VOLUME ["/app/data"]
EXPOSE 80

ENV SELFAUTH_DB_PATH=/app/data/selfauth.sqlite \
    SELFAUTH_SESSION_PATH=/app/data/sessions \
    SELFAUTH_TRUST_PROXY=false \
    SELFAUTH_COOKIE_SECURE=true \
    SELFAUTH_WEBMENTIONS_ENABLED=true

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]