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

# Register Ingress middleware in Laravel's bootstrap/app.php and allow iframe embedding in SecurityHeaders
RUN php -r ' \
    $file = "/var/www/html/bootstrap/app.php"; \
    $code = file_get_contents($file); \
    if (!str_contains($code, "HomeAssistantIngressMiddleware")) { \
        $code = str_replace( \
            "\$middleware->appendToGroup(\x27web\x27, \App\Http\Middleware\SecurityHeaders::class);", \
            "\$middleware->appendToGroup(\x27web\x27, \App\Http\Middleware\SecurityHeaders::class);\n        \$middleware->appendToGroup(\x27web\x27, \App\Http\Middleware\HomeAssistantIngressMiddleware::class);", \
            $code \
        ); \
        file_put_contents($file, $code); \
    } \
' && php -r ' \
    $file = "/var/www/html/app/Http/Middleware/SecurityHeaders.php"; \
    $code = file_get_contents($file); \
    $code = str_replace( \
        "\$response->headers->set(\x27X-Frame-Options\x27, \x27SAMEORIGIN\x27);", \
        "if (!\$request->hasHeader(\x27X-Ingress-Path\x27)) { \$response->headers->set(\x27X-Frame-Options\x27, \x27SAMEORIGIN\x27); }", \
        $code \
    ); \
    file_put_contents($file, $code); \
'

# Copy the wrapper script and ensure execution permissions
COPY run.sh /run.sh
RUN chmod +x /run.sh

# Set the wrapper script as the container entrypoint
ENTRYPOINT ["/run.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
