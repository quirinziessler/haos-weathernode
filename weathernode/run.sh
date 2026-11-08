#!/usr/bin/env bash
set -e

OPTIONS_FILE="/data/options.json"

echo "===================================================="
echo " Starting WeatherNode Home Assistant Add-on wrapper"
echo "===================================================="

# 1. Parse Home Assistant options and export as environment variables
if [ -f "$OPTIONS_FILE" ]; then
    echo "[WeatherNode] Reading configuration from ${OPTIONS_FILE}..."
    while IFS="=" read -r key value; do
        if [ -n "$key" ]; then
            export "$key=$value"
            if [ "$key" = "APP_KEY" ] || [ "$key" = "ADMIN_PASSWORD" ]; then
                echo "[WeatherNode] Configured ${key}=********"
            else
                echo "[WeatherNode] Configured ${key}=${value}"
            fi
        fi
    done < <(jq -r 'to_entries[] | select(.value != null and .value != "") | "\(.key)=\(.value)"' "$OPTIONS_FILE")
else
    echo "[WeatherNode] Warning: ${OPTIONS_FILE} not found. Running with default environment."
fi

# 2. Guarantee Admin user credentials for direct Home Assistant Ingress access
if [ -z "$ADMIN_EMAIL" ]; then
    export ADMIN_EMAIL="admin@homeassistant.local"
fi
if [ -z "$ADMIN_PASSWORD" ]; then
    # Generate a secure 24-character random password if not explicitly set in options
    export ADMIN_PASSWORD=$(head -c 32 /dev/urandom | tr -dc 'a-zA-Z0-9' | head -c 24)
fi
if [ -z "$ADMIN_NAME" ]; then
    export ADMIN_NAME="Home Assistant Admin"
fi

# 3. Dynamic URL and Cookie Handling
# Ensure session cookies work across plain HTTP and Home Assistant Ingress
export SESSION_SECURE_COOKIE="false"

# 4. Hardcode persistent backend settings (cannot be overridden via UI)
export DB_CONNECTION="sqlite"
export DB_DATABASE="/data/weathernode.sqlite"
export DOCKER_RUN_SCHEDULER="true"

echo "[WeatherNode] Database connection configured to SQLite at ${DB_DATABASE}"
echo "[WeatherNode] Background scheduler execution enabled (DOCKER_RUN_SCHEDULER=true)"
echo "[WeatherNode] Ingress Administrator configured for seamless Home Assistant access (${ADMIN_EMAIL})"

# 5. Ensure persistent storage path and SQLite database exist with writable permissions
mkdir -p /data
touch /data/weathernode.sqlite
chmod 775 /data || true
chmod 664 /data/weathernode.sqlite || true

if id "www-data" >/dev/null 2>&1; then
    chown -R www-data:www-data /data || true
fi

# 6. Clear any stale compiled views so updated templates take effect immediately
rm -f /var/www/html/storage/framework/views/*.php 2>/dev/null || true

# 7. Fallback default command if none supplied
if [ $# -eq 0 ]; then
    set -- /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi

# 8. Hand over control to the upstream WeatherNode entrypoint
echo "[WeatherNode] Handing over execution to upstream entrypoint: /var/www/html/docker/entrypoint.sh"
exec /var/www/html/docker/entrypoint.sh "$@"
