<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class HomeAssistantIngressMiddleware
{
    /**
     * Handle an incoming request for Home Assistant Ingress.
     *
     * 1. Detects Home Assistant Ingress requests via the X-Ingress-Path header.
     * 2. Dynamically forces Laravel's URL generator to root all routes, links, and assets
     *    under the Home Assistant Ingress subpath prefix (/api/hassio_ingress/<token>).
     * 3. Automatically signs in the administrator user so Home Assistant admins land
     *    directly in the WeatherNode interface without needing a second login screen.
     * 4. Strips X-Frame-Options and adjusts frame-ancestors to permit embedding in the
     *    Home Assistant sidebar iframe.
     * 5. Intercepts and rewrites all HTTP redirects (Location header) so internal navigation
     *    never breaks out of the Home Assistant Ingress iframe.
     * 6. Rewrites root-relative links in HTML responses and injects fetch/XHR/history Ingress routers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ingressPath = $request->header('X-Ingress-Path');

        if (!empty($ingressPath)) {
            $ingressPath = rtrim($ingressPath, '/');

            // Determine scheme and host from proxy headers or current request
            $scheme = $request->header('X-Forwarded-Proto') ?: $request->getScheme();
            $host = $request->header('X-Forwarded-Host') ?: $request->getHttpHost();

            // Force Laravel's URL generator to build all URLs with the ingress root
            URL::forceRootUrl($scheme . '://' . $host . $ingressPath);
            URL::forceScheme($scheme);

            // Auto-login as administrator if not already authenticated
            if (!Auth::check()) {
                $admin = User::where('is_admin', true)->first() ?? User::first();
                if ($admin) {
                    Auth::login($admin, true);
                }
            }
        }

        $response = $next($request);

        if (!empty($ingressPath)) {
            // 1. Remove X-Frame-Options to allow embedding in Home Assistant sidebar iframe
            $response->headers->remove('X-Frame-Options');

            // 2. Relax CSP frame-ancestors if present
            $cspReport = $response->headers->get('Content-Security-Policy-Report-Only');
            if ($cspReport && str_contains($cspReport, 'frame-ancestors')) {
                $cspReport = preg_replace('#frame-ancestors\s+[^;]+#i', "frame-ancestors * 'self'", $cspReport);
                $response->headers->set('Content-Security-Policy-Report-Only', $cspReport);
            }
            $csp = $response->headers->get('Content-Security-Policy');
            if ($csp && str_contains($csp, 'frame-ancestors')) {
                $csp = preg_replace('#frame-ancestors\s+[^;]+#i', "frame-ancestors * 'self'", $csp);
                $response->headers->set('Content-Security-Policy', $csp);
            }

            // 3. Fix Redirect Responses (Location header)
            if ($response->isRedirection() || $response->headers->has('Location')) {
                $location = $response->headers->get('Location');
                if (!empty($location)) {
                    $scheme = $request->header('X-Forwarded-Proto') ?: $request->getScheme();
                    $host = $request->header('X-Forwarded-Host') ?: $request->getHttpHost();
                    $requestHostname = parse_url($scheme . '://' . $host, PHP_URL_HOST);

                    // If relative redirect (e.g. /dashboard or /admin/setup)
                    if (str_starts_with($location, '/') && !str_starts_with($location, '//')) {
                        if (!str_starts_with($location, $ingressPath)) {
                            $response->headers->set('Location', $ingressPath . $location);
                        }
                    } else {
                        // If absolute URL redirect (e.g. http://ha:8123/dashboard or http://localhost/...)
                        $parsed = parse_url($location);
                        if (!empty($parsed['host'])) {
                            // Check if redirect points to Home Assistant or localhost/container
                            if (
                                strcasecmp($parsed['host'], $requestHostname) === 0
                                || in_array($parsed['host'], ['localhost', '127.0.0.1'], true)
                            ) {
                                $path = $parsed['path'] ?? '/';
                                if (!str_starts_with($path, $ingressPath)) {
                                    $newPath = $ingressPath . '/' . ltrim($path, '/');
                                    $newUrl = ($parsed['scheme'] ?? $scheme) . '://' . $host . $newPath;
                                    if (!empty($parsed['query'])) {
                                        $newUrl .= '?' . $parsed['query'];
                                    }
                                    if (!empty($parsed['fragment'])) {
                                        $newUrl .= '#' . $parsed['fragment'];
                                    }
                                    $response->headers->set('Location', $newUrl);
                                }
                            }
                        }
                    }
                }
            }

            // 4. Rewrite root-relative links in HTML responses so they stay within Ingress
            $contentType = $response->headers->get('Content-Type');
            if ($contentType && str_contains($contentType, 'text/html')) {
                $content = $response->getContent();
                if ($content) {
                    $clean = ltrim($ingressPath, '/');
                    // Match href="/...", src="/...", action="/..." not starting with // or the ingress path
                    $pattern = '#((?:href|src|action)=["\'])\/(?!\/|' . preg_quote($clean, '#') . ')#i';
                    $content = preg_replace($pattern, '$1' . $ingressPath . '/', $content);

                    // Inject helper script to route frontend AJAX calls (Axios, Fetch, XHR, History) through Ingress
                    $encodedIngress = json_encode($ingressPath);
                    $helperScript = "<script>
window.__HA_INGRESS_PATH__ = {$encodedIngress};
if (window.axios) { window.axios.defaults.baseURL = {$encodedIngress}; }
(function() {
    var prefix = {$encodedIngress};
    
    // Intercept Fetch API
    var origFetch = window.fetch;
    if (origFetch) {
        window.fetch = function(url, options) {
            if (typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && !url.startsWith(prefix)) {
                url = prefix + url;
            }
            return origFetch.apply(this, [url, options]);
        };
    }

    // Intercept XMLHttpRequest
    if (window.XMLHttpRequest) {
        var origOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url) {
            if (typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && !url.startsWith(prefix)) {
                url = prefix + url;
            }
            return origOpen.apply(this, [method, url].concat(Array.prototype.slice.call(arguments, 2)));
        };
    }

    // Intercept History API (Single Page Navigation)
    if (window.history) {
        var origPush = history.pushState;
        history.pushState = function(state, unused, url) {
            if (typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && !url.startsWith(prefix)) {
                url = prefix + url;
            }
            return origPush.apply(this, arguments);
        };
        var origReplace = history.replaceState;
        history.replaceState = function(state, unused, url) {
            if (typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && !url.startsWith(prefix)) {
                url = prefix + url;
            }
            return origReplace.apply(this, arguments);
        };
    }
})();
</script>";
                    if (str_contains($content, '</head>')) {
                        $content = str_replace('</head>', $helperScript . '</head>', $content);
                    } elseif (str_contains($content, '</body>')) {
                        $content = str_replace('</body>', $helperScript . '</body>', $content);
                    }

                    $response->setContent($content);
                }
            }
        }

        return $response;
    }
}
