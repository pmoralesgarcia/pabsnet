# Use a specific, stable version of PHP on Alpine Linux for a tiny footprint
FROM php:8.4-fpm-alpine

# 1. Install system dependencies and PHP extensions
# We use 'libpng-dev', etc., for the GD extension
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    nginx \
    bash

# 2. Use the official helper to install PHP extensions properly
RUN docker-php-ext-install \
    gd \
    bcmath \
    intl \
    mbstring \
    mysqli \
    pdo_mysql \
    zip

# 3. Security: Run as a non-root user
# We create a system user for the app
RUN addgroup -S pablo && adduser -S pablo -G pablo

# 4. Set up Nginx config
COPY ./lifeofpablo.com/nginx/lifeofpablo.com.conf /etc/nginx/http.d/default.conf

# 5. Set working directory and copy code
WORKDIR /var/www/html
COPY --chown=pablo:pablo ./lifeofpablo.com /var/www/html

# 6. Permissions: No more 'chmod 777' (a+rw)
# Files belong to our user, Nginx/PHP will run as this user
RUN chmod -R 755 /var/www/html

# Expose Nginx port
EXPOSE 80

# Use a clean entrypoint script
COPY --chmod=755 ./lifeofpablo.com/start.sh /usr/local/bin/start.sh
ENTRYPOINT ["start.sh"]