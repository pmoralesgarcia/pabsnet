FROM php:8.5-apache

# curl, dom, mbstring and pdo_sqlite all ship with this image, so nothing needs compiling.
RUN a2enmod rewrite \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /app/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /app/public>\n    Options -Indexes\n    AllowOverride None\n    Require all granted\n    RewriteEngine On\n    RewriteRule ^authorize$ authorize.php [L,QSA]\n    RewriteRule ^token$ token.php [L,QSA]\n    RewriteRule ^callback$ callback.php [L,QSA]\n    RewriteRule ^register$ register.php [L,QSA]\n    RewriteRule ^\\.well-known/oauth-authorization-server$ metadata.php [L]\n</Directory>\nServerTokens Prod\nServerSignature Off\n' >> /etc/apache2/apache2.conf \
    && printf 'expose_php=Off\ndisplay_errors=Off\nfile_uploads=Off\npost_max_size=64K\n' > /usr/local/etc/php/conf.d/login.ini \
    && mkdir -p /app/data && chown www-data:www-data /app/data

COPY src /app/src
COPY public /app/public

VOLUME ["/app/data"]
EXPOSE 80
