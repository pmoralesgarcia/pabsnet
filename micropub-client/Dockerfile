FROM php:8.5-apache

# curl, dom, fileinfo and mbstring all ship with this image, so nothing needs compiling.
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /app/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /app/public>\n    Options -Indexes\n    AllowOverride None\n    Require all granted\n</Directory>\nServerTokens Prod\nServerSignature Off\n' >> /etc/apache2/apache2.conf \
    && printf 'upload_max_filesize=10M\npost_max_size=45M\nmax_file_uploads=4\nexpose_php=Off\ndisplay_errors=Off\n' > /usr/local/etc/php/conf.d/client.ini

COPY src /app/src
COPY public /app/public

EXPOSE 80
