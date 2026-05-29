#!/bin/bash

# Path to the PHP-FPM pool config
FPM_CONF="/etc/php/8.2/fpm/pool.d/www.conf"

# Inject variables into the FPM config so getenv() can see them
# We use single quotes to prevent the shell from mangling the BCrypt hash symbols ($)
echo "env[SA_APP_URL] = '$SA_APP_URL'" >> $FPM_CONF
echo "env[SA_APP_KEY] = '$SA_APP_KEY'" >> $FPM_CONF
echo "env[SA_USER_URL] = '$SA_USER_URL'" >> $FPM_CONF
echo "env[SA_USER_HASH] = '$SA_USER_HASH'" >> $FPM_CONF

# Ensure the PHP run directory exists
mkdir -p /run/php

# Start PHP-FPM
service php8.2-fpm start

# Start Nginx in the foreground
nginx -g "daemon off;"