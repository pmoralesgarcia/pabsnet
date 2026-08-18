FROM php:8.3-apache

# Extensions: pdo_sqlite for storage, curl for client-discovery/webmention
# fetches, mbstring/dom for webmention HTML parsing.
# Note: libonig-dev is required to compile php extension 'mbstring'
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libsqlite3-dev \
       libcurl4-openssl-dev \
       libxml2-dev \
       libonig-dev \
    && docker-php-ext-install pdo_sqlite curl dom mbstring \
    && apt-get purge -y --auto-remove libsqlite3-dev libcurl4-openssl-dev libxml2-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache DocumentRoot and grant permissions to /app/public
RUN a2enmod rewrite headers \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /app/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /app/public>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

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