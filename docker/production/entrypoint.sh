#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html
: "${APP_KEY:?Set a persistent APP_KEY before starting production}"
mkdir -p storage/app/runtime storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/uploads
chown -R dbcms:dbcms storage bootstrap/cache public/uploads
# Set directory permissions without making secret files world-readable.
find storage bootstrap/cache public/uploads -type d -exec chmod 775 {} +
find storage bootstrap/cache public/uploads -type f ! -name '*.key' ! -name '*.pem' ! -name '.env*' -exec chmod 664 {} +
find storage bootstrap/cache -type f \( -name '*.key' -o -name '*.pem' -o -name '.env*' \) -exec chmod 600 {} +
if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    su-exec dbcms php artisan passport:keys --no-interaction
fi
chmod 600 storage/oauth-private.key
chmod 644 storage/oauth-public.key
# Public code remains root-owned; create only the fixed storage link as root.
test ! -e public/storage || test -L public/storage
ln -sfn ../storage/app/public public/storage
# Migrations run once as a deployment job, never implicitly in each replica.
if [ "${RUN_MIGRATIONS:-false}" = true ]; then
    su-exec dbcms php artisan migrate --force --no-interaction
fi
if [ "${APP_ENV:-production}" = production ]; then
    su-exec dbcms php artisan config:cache
    su-exec dbcms php artisan route:cache
    su-exec dbcms php artisan view:cache
fi
if [ "$#" -gt 0 ]; then
    exec su-exec dbcms "$@"
fi
exec /usr/bin/supervisord -c /etc/supervisord.conf
