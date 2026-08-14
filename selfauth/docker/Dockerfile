FROM alpine:3.19

# 1. Install Nginx, PHP 8.3 FPM, and required PHP extensions + build dependencies
RUN apk add --no-cache \
    nginx \
    php83 \
    php83-fpm \
    php83-pdo_sqlite \
    php83-curl \
    php83-dom \
    php83-mbstring \
    php83-opcache \
    php83-session \
    php83-json \
    supervisor \
    curl

WORKDIR /app

# 2. Configure PHP-FPM to listen on TCP 127.0.0.1:9000
RUN sed -i 's#listen = /var/run/php-fpm83.sock#listen = 127.0.0.1:9000#' /etc/php83/php-fpm.d/www.conf

# 3. Configure Nginx with complete PHP/FastCGI rules and front-controller routing
RUN mkdir -p /run/nginx /app/public \
    && echo 'server {' > /etc/nginx/http.d/default.conf \
    && echo '    listen 80;' >> /etc/nginx/http.d/default.conf \
    && echo '    server_name _;' >> /etc/nginx/http.d/default.conf \
    && echo '    root /app/public;' >> /etc/nginx/http.d/default.conf \
    && echo '    index index.php index.html;' >> /etc/nginx/http.d/default.conf \
    && echo '    location / {' >> /etc/nginx/http.d/default.conf \
    && echo '        try_files $uri $uri/ /index.php?$query_string;' >> /etc/nginx/http.d/default.conf \
    && echo '    }' >> /etc/nginx/http.d/default.conf \
    && echo '    location ~ \.php$ {' >> /etc/nginx/http.d/default.conf \
    && echo '        fastcgi_pass 127.0.0.1:9000;' >> /etc/nginx/http.d/default.conf \
    && echo '        fastcgi_index index.php;' >> /etc/nginx/http.d/default.conf \
    && echo '        include fastcgi_params;' >> /etc/nginx/http.d/default.conf \
    && echo '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' >> /etc/nginx/http.d/default.conf \
    && echo '    }' >> /etc/nginx/http.d/default.conf \
    && echo '}' >> /etc/nginx/http.d/default.conf

# 4. Configure Supervisor
RUN echo '[supervisord]' > /etc/supervisord.conf \
    && echo 'nodaemon=true' >> /etc/supervisord.conf \
    && echo '[program:php-fpm]' >> /etc/supervisord.conf \
    && echo 'command=php-fpm83 -F' >> /etc/supervisord.conf \
    && echo '[program:nginx]' >> /etc/supervisord.conf \
    && echo 'command=nginx -g "daemon off;"' >> /etc/supervisord.conf

# 5. Copy code into container
COPY . /app
COPY ./selfauth/docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh /app/selfauth/bin/verify-mentions.php

# 6. Data directory and permissions
RUN mkdir -p /app/data && chown -R nginx:nginx /app /app/data

VOLUME ["/app/data"]
EXPOSE 80

ENV SELFAUTH_DB_PATH=/app/data/selfauth.sqlite \
    SELFAUTH_SESSION_PATH=/app/data/sessions \
    SELFAUTH_TRUST_PROXY=false \
    SELFAUTH_COOKIE_SECURE=true \
    SELFAUTH_WEBMENTIONS_ENABLED=true

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]