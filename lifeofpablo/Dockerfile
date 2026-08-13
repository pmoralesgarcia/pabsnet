FROM php:8.4-fpm-alpine

# 1. Install dependencies[cite: 1]
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    nginx \
    bash

# 2. PHP Extensions[cite: 1]
RUN docker-php-ext-install \
    gd \
    bcmath \
    intl \
    mbstring \
    mysqli \
    pdo_mysql \
    zip

# 3. Create user[cite: 1]
RUN addgroup -S pablo && adduser -S pablo -G pablo

# 4. Copy Nginx site config
COPY ./lifeofpablo/lifeofpablo.conf /etc/nginx/http.d/default.conf

# 5. Set up application directory structures
WORKDIR /var/www

# Copy each sub-project into its respective target directory
COPY --chown=pablo:pablo ./lifeofpablo.com/www /var/www/main
COPY --chown=pablo:pablo ./lifeofpablo-interactives/www /var/www/interactives
COPY --chown=pablo:pablo ./lifeofpablo-guestbook/www/guestbook /var/www/guestbook
COPY --chown=pablo:pablo ./lifeofpablo-linkinbio/www /var/www/linkinbio

# 6. Set correct permissions[cite: 1]
RUN chmod -R 755 /var/www

EXPOSE 80

COPY --chmod=755 ./lifeofpablo/start.sh /usr/local/bin/start.sh
ENTRYPOINT ["start.sh"]