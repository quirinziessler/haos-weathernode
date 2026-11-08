FROM ghcr.io/centauri/weathernode:v2026.10.3

# Install jq if missing in the base image (supports Debian/Ubuntu and Alpine)
RUN which jq > /dev/null 2>&1 || ( \
    if command -v apt-get > /dev/null 2>&1; then \
        apt-get update && apt-get install -y --no-install-recommends jq && rm -rf /var/lib/apt/lists/*; \
    elif command -v apk > /dev/null 2>&1; then \
        apk add --no-cache jq; \
    fi \
)

# Copy the Home Assistant Ingress & Auto-Login Middleware
COPY HomeAssistantIngressMiddleware.php /var/www/html/app/Http/Middleware/HomeAssistantIngressMiddleware.php

# Apply upstream patches for dynamic asset URLs, Ingress routing, and iframe embedding
COPY patch-upstream.php /tmp/patch-upstream.php
RUN php /tmp/patch-upstream.php && rm /tmp/patch-upstream.php

# Copy the wrapper script and ensure execution permissions
COPY run.sh /run.sh
RUN chmod +x /run.sh

# Set the wrapper script as the container entrypoint
ENTRYPOINT ["/run.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
