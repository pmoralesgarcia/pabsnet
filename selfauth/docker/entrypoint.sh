#!/bin/sh
set -e

mkdir -p /app/data/sessions
chown -R www-data:www-data /app/data || true

if [ -z "$SELFAUTH_APP_URL" ] || [ -z "$SELFAUTH_USER_URL" ]; then
    echo "WARNING: SELFAUTH_APP_URL and/or SELFAUTH_USER_URL are not set."
    echo "Selfauth will show a configuration error until these (and SELFAUTH_ADMIN_PASSWORD"
    echo "on first boot) are provided. See the README for the full list of variables."
fi

# Background loop that periodically re-verifies pending webmentions, since
# the Webmention spec recommends verifying asynchronously rather than
# blocking the sender's request. No cron daemon needed for a single
# lightweight PHP script.
(
    while true; do
        sleep 300
        su -s /bin/sh www-data -c "php /app/bin/verify-mentions.php" 2>&1 | sed 's/^/[webmention-verify] /'
    done
) &

exec "$@"
