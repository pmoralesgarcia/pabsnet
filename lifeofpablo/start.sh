#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "Starting PHP-FPM..."
# Start PHP-FPM in the background (&)
php-fpm -D

echo "Starting Nginx..."
# Start Nginx in the foreground so the container doesn't exit immediately
nginx -g 'daemon off;'