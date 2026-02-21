#!/bin/bash
set -e

# ---------------------------------------------------
# 1. Substitute the PORT env var into the Nginx config
#    Render provides PORT (default 10000)
# ---------------------------------------------------
export NGINX_PORT="${PORT:-10000}"
envsubst '${NGINX_PORT}' < /etc/nginx/sites-available/default > /etc/nginx/sites-enabled/default

# ---------------------------------------------------
# 2. Laravel bootstrap (run every deploy)
# ---------------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# ---------------------------------------------------
# 3. Default ENABLE_WORKER to false if not set
# ---------------------------------------------------
export ENABLE_WORKER="${ENABLE_WORKER:-false}"

# ---------------------------------------------------
# 4. Start services via Supervisor
#    Supervisor manages: php-fpm, nginx, and optionally queue:work
# ---------------------------------------------------
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
