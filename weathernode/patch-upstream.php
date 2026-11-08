<?php

// 1. Patch AppServiceProvider.php to prevent hardcoded localhost root URLs
$aspFile = '/var/www/html/app/Providers/AppServiceProvider.php';
if (file_exists($aspFile)) {
    $code = file_get_contents($aspFile);

    $origBlock = 'if (! app()->runningInConsole()) {
            $configuredAppUrl = trim((string) config(\'app.url\', \'\'));
            $effectiveAppUrl = $configuredAppUrl;

            // Keep custom host ports (e.g. :8089) when APP_URL host matches the current request
            // but APP_URL does not include a port. This avoids language/alternate links dropping it.
            $requestScheme = request()->getScheme();
            $requestHost = request()->getHost();
            $requestPort = request()->getPort();
            $requestUsesNonStandardPort = ! in_array($requestPort, [80, 443], true);

            if ($configuredAppUrl === \'\') {
                $effectiveAppUrl = request()->getSchemeAndHttpHost();
            } else {
                $configuredHost = parse_url($configuredAppUrl, PHP_URL_HOST);
                $configuredPort = parse_url($configuredAppUrl, PHP_URL_PORT);

                if (
                    is_string($configuredHost)
                    && $configuredHost !== \'\'
                    && strcasecmp($configuredHost, $requestHost) === 0
                    && ! is_int($configuredPort)
                    && $requestUsesNonStandardPort
                ) {
                    $effectiveAppUrl = sprintf(\'%s://%s:%d\', $requestScheme, $requestHost, $requestPort);
                }
            }

            if ($effectiveAppUrl !== \'\') {
                URL::forceRootUrl($effectiveAppUrl);

                $scheme = parse_url($effectiveAppUrl, PHP_URL_SCHEME);
                if (is_string($scheme) && $scheme !== \'\') {
                    URL::forceScheme($scheme);
                }
            }
        }';

    $newBlock = 'if (! app()->runningInConsole()) {
            $request = request();
            $ingressPath = $request->header(\'X-Ingress-Path\');

            if (!empty($ingressPath)) {
                $ingressPath = rtrim($ingressPath, \'/\');
                $scheme = $request->header(\'X-Forwarded-Proto\') ?: $request->getScheme();
                $host = $request->header(\'X-Forwarded-Host\') ?: $request->getHttpHost();
                $effectiveAppUrl = $scheme . \'://\' . $host . $ingressPath;
            } else {
                $configuredAppUrl = trim((string) config(\'app.url\', \'\'));
                if ($configuredAppUrl === \'\' || $configuredAppUrl === \'http://localhost\' || $configuredAppUrl === \'https://localhost\') {
                    $effectiveAppUrl = $request->getSchemeAndHttpHost();
                } else {
                    $effectiveAppUrl = $configuredAppUrl;
                    $requestScheme = $request->getScheme();
                    $requestHost = $request->getHost();
                    $requestPort = $request->getPort();
                    $requestUsesNonStandardPort = ! in_array($requestPort, [80, 443], true);
                    $configuredHost = parse_url($configuredAppUrl, PHP_URL_HOST);
                    $configuredPort = parse_url($configuredAppUrl, PHP_URL_PORT);

                    if (
                        is_string($configuredHost)
                        && $configuredHost !== \'\'
                        && strcasecmp($configuredHost, $requestHost) === 0
                        && ! is_int($configuredPort)
                        && $requestUsesNonStandardPort
                    ) {
                        $effectiveAppUrl = sprintf(\'%s://%s:%d\', $requestScheme, $requestHost, $requestPort);
                    }
                }
            }

            if ($effectiveAppUrl !== \'\') {
                URL::forceRootUrl($effectiveAppUrl);

                $scheme = parse_url($effectiveAppUrl, PHP_URL_SCHEME);
                if (is_string($scheme) && $scheme !== \'\') {
                    URL::forceScheme($scheme);
                }
            }
        }';

    if (str_contains($code, $origBlock)) {
        $code = str_replace($origBlock, $newBlock, $code);
        file_put_contents($aspFile, $code);
        echo "[patch] AppServiceProvider.php updated successfully.\n";
    } else {
        echo "[patch] Warning: exact origBlock not matched in AppServiceProvider.php, trying regex fallback...\n";
        $pattern = '/if \(! app\(\)->runningInConsole\(\)\) \{.*?URL::forceScheme\(\$scheme\);\s*\}\s*\}\s*\}/s';
        if (preg_match($pattern, $code)) {
            $code = preg_replace($pattern, $newBlock, $code);
            file_put_contents($aspFile, $code);
            echo "[patch] AppServiceProvider.php updated via regex fallback.\n";
        } else {
            echo "[patch] Error: could not patch AppServiceProvider.php\n";
        }
    }
}

// 2. Patch bootstrap/app.php to register HomeAssistantIngressMiddleware
$bootstrapFile = '/var/www/html/bootstrap/app.php';
if (file_exists($bootstrapFile)) {
    $code = file_get_contents($bootstrapFile);
    if (!str_contains($code, 'HomeAssistantIngressMiddleware')) {
        $search = "\$middleware->appendToGroup('web', \\App\\Http\\Middleware\\SecurityHeaders::class);";
        $replace = "\$middleware->appendToGroup('web', \\App\\Http\\Middleware\\SecurityHeaders::class);\n        \$middleware->appendToGroup('web', \\App\\Http\\Middleware\\HomeAssistantIngressMiddleware::class);";
        $code = str_replace($search, $replace, $code);
        file_put_contents($bootstrapFile, $code);
        echo "[patch] bootstrap/app.php updated with HomeAssistantIngressMiddleware.\n";
    }
}

// 3. Patch SecurityHeaders.php to allow iframe embedding in Home Assistant sidebar
$secFile = '/var/www/html/app/Http/Middleware/SecurityHeaders.php';
if (file_exists($secFile)) {
    $code = file_get_contents($secFile);
    $code = str_replace(
        "\$response->headers->set('X-Frame-Options', 'SAMEORIGIN');",
        "if (!\$request->hasHeader('X-Ingress-Path')) { \$response->headers->set('X-Frame-Options', 'SAMEORIGIN'); }",
        $code
    );
    file_put_contents($secFile, $code);
    echo "[patch] SecurityHeaders.php updated to allow Ingress iframes.\n";
}

// 4. Patch Nginx fastcgi config to preserve client port in HTTP_HOST
$nginxSites = ['/etc/nginx/sites-available/default', '/etc/nginx/sites-enabled/default'];
foreach ($nginxSites as $siteFile) {
    if (file_exists($siteFile)) {
        $code = file_get_contents($siteFile);
        if (!str_contains($code, 'fastcgi_param HTTP_HOST $http_host;')) {
            $code = str_replace(
                'include fastcgi_params;',
                "include fastcgi_params;\n        fastcgi_param HTTP_HOST \$http_host;",
                $code
            );
            file_put_contents($siteFile, $code);
            echo "[patch] {$siteFile} updated with fastcgi_param HTTP_HOST \$http_host.\n";
        }
    }
}
$fastcgiConf = '/etc/nginx/fastcgi_params';
if (file_exists($fastcgiConf)) {
    $code = file_get_contents($fastcgiConf);
    $code = preg_replace('/fastcgi_param\s+HTTP_HOST\s+\$host;/', 'fastcgi_param  HTTP_HOST          $http_host;', $code);
    file_put_contents($fastcgiConf, $code);
    echo "[patch] {$fastcgiConf} updated.\n";
}

echo "[patch] All patches applied successfully.\n";
