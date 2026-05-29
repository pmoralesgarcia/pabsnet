# 1. Use the lightweight PHP 8.4 FPM Alpine base
FROM php:8.4-fpm-alpine

# 2. Install Nginx and system dependencies for PHP extensions
# --no-cache keeps the image small by not storing the index locally
RUN apk add --no-cache \
    nginx \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    bash

# 3. Install and enable PHP extensions
RUN docker-php-ext-install \
    gd \
    intl \
    mbstring \
    zip \
    bcmath

# 4. Security: Create a non-privileged user
RUN addgroup -S pablo && adduser -S pablo -G pablo

# 5. Configure Nginx
# Note: Alpine Nginx uses /etc/nginx/http.d/ instead of sites-enabled
COPY ./korea.lifeofpablo.com/nginx/korea.lifeofpablo.com.conf /etc/nginx/http.d/default.conf

# 6. Set up the working directory
WORKDIR /var/www/html

# 7. Copy code and set ownership immediately (No 'chmod 777')
COPY --chown=pablo:pablo ./korea.lifeofpablo.com /var/www/html

# 8. Set permissions (Folders: 755, Files: 644 is handled by default)
RUN chmod -R 755 /var/www/html

EXPOSE 80

# 9. Optimized Start Script
COPY --chmod=755 ./korea.lifeofpablo.com/start.sh /usr/local/bin/start.sh
ENTRYPOINT ["start.sh"]