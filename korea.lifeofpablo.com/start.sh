#!/bin/sh
set -e

# PHP-FPM runs in the background; exec makes nginx PID 1 so it receives stop signals.
php-fpm -D
exec nginx -g 'daemon off;'
