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

# 2. Hardcode persistent backend settings (cannot be overridden via UI)
export DB_CONNECTION="sqlite"
export DB_DATABASE="/data/weathernode.sqlite"
export DOCKER_RUN_SCHEDULER="true"

echo "[WeatherNode] Database connection configured to SQLite at ${DB_DATABASE}"
echo "[WeatherNode] Background scheduler execution enabled (DOCKER_RUN_SCHEDULER=true)"

# 3. Ensure persistent storage path and SQLite database exist with writable permissions
mkdir -p /data
touch /data/weathernode.sqlite
chmod 775 /data || true
chmod 664 /data/weathernode.sqlite || true

if id "www-data" >/dev/null 2>&1; then
    chown -R www-data:www-data /data || true
fi

# 4. Fallback default command if none supplied
if [ $# -eq 0 ]; then
    set -- /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi

# 5. Hand over control to the upstream WeatherNode entrypoint
echo "[WeatherNode] Handing over execution to upstream entrypoint: /var/www/html/docker/entrypoint.sh"
exec /var/www/html/docker/entrypoint.sh "$@"
