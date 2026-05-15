#!/bin/bash

# Inject Environment Variables into PHP-FPM
# This allows getenv() to work inside the container
FPM_CONF="/etc/php/8.2/fpm/pool.d/www.conf"

echo "env[SA_APP_URL] = '$SA_APP_URL'" >> $FPM_CONF
echo "env[SA_APP_KEY] = '$SA_APP_KEY'" >> $FPM_CONF
echo "env[SA_USER_URL] = '$SA_USER_URL'" >> $FPM_CONF
echo "env[SA_USER_HASH] = '$SA_USER_HASH'" >> $FPM_CONF

# Clear existing log/pid files if they exist from a crash
rm -f /run/php/php8.2-fpm.pid

# Start services
service php8.2-fpm start
nginx -g "daemon off;"