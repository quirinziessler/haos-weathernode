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
     * 5. Rewrites root-relative links in HTML responses and injects fetch/axios Ingress handlers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ingressPath = $request->header('X-Ingress-Path');

        if (!empty($ingressPath)) {
            $ingressPath = rtrim($ingressPath, '/');

            // Determine scheme and host
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
            // Remove X-Frame-Options to allow embedding in Home Assistant sidebar iframe
            $response->headers->remove('X-Frame-Options');

            // Relax CSP frame-ancestors if present
            $cspReport = $response->headers->get('Content-Security-Policy-Report-Only');
            if ($cspReport && str_contains($cspReport, 'frame-ancestors')) {
                $cspReport = preg_replace("/frame-ancestors\s+[^;]+/", "frame-ancestors * 'self'", $cspReport);
                $response->headers->set('Content-Security-Policy-Report-Only', $cspReport);
            }
            $csp = $response->headers->get('Content-Security-Policy');
            if ($csp && str_contains($csp, 'frame-ancestors')) {
                $csp = preg_replace("/frame-ancestors\s+[^;]+/", "frame-ancestors * 'self'", $csp);
                $response->headers->set('Content-Security-Policy', $csp);
            }

            // Rewrite root-relative links in HTML responses so they stay within Ingress
            $contentType = $response->headers->get('Content-Type');
            if ($contentType && str_contains($contentType, 'text/html')) {
                $content = $response->getContent();
                if ($content) {
                    $escaped = preg_quote($ingressPath, '/');
                    // Match href="/...", src="/...", action="/..." not starting with // or the ingress path
                    $pattern = '/((?:href|src|action)=["\'])\/(?!\/|' . substr($escaped, 1) . ')/i';
                    $content = preg_replace($pattern, '$1' . $ingressPath . '/', $content);

                    // Inject helper script to route frontend AJAX calls (Axios and Fetch) through Ingress
                    $encodedIngress = json_encode($ingressPath);
                    $helperScript = "<script>
window.__HA_INGRESS_PATH__ = {$encodedIngress};
if (window.axios) { window.axios.defaults.baseURL = {$encodedIngress}; }
(function() {
    var origFetch = window.fetch;
    window.fetch = function(url, options) {
        if (typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && !url.startsWith({$encodedIngress})) {
            url = {$encodedIngress} + url;
        }
        return origFetch.apply(this, [url, options]);
    };
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
