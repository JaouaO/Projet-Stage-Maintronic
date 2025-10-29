<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $env   = app()->environment();              // 'local', 'production', …
        $debug = (bool) config('app.debug');        // APP_DEBUG

        // HTTPS robuste : vrai si TLS natif OU si le proxy annonce https
        $isHttps = $request->isSecure()
            || strtolower($request->headers->get('x-forwarded-proto', '')) === 'https';

        // --- En-têtes "classiques" sûrs partout ---
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN'); // redondant avec frame-ancestors, mais OK
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', "geolocation=(), microphone=(), camera=(), payment=(), usb=()");
        $response->headers->set('X-XSS-Protection', '0'); // obsolète, on désactive

        // --- CSP tolérante (n'interdit pas vos inline/CDN https) ---
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "manifest-src 'self'",

            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https:",
            "script-src 'self' 'unsafe-inline' https:",
            "connect-src 'self' https:",
        ];

        // En dev : report-only + tolère ws:/http: pour HMR/outils
        $cspHeader = 'Content-Security-Policy';
        if ($env === 'local' || $debug) {
            $cspHeader = 'Content-Security-Policy-Report-Only';
            foreach ($csp as &$d) {
                if (strpos($d, 'connect-src') === 0) $d .= ' http: ws: wss:';
            }
            unset($d);
        }
        $response->headers->set($cspHeader, implode('; ', $csp));

        // --- HSTS : une seule fois, PROD + HTTPS ---
        if ($env === 'production' && $isHttps) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains; preload');
        }

        // --- Anti-cache côté client pour pages authentifiées (facultatif mais recommandé) ---
        if (auth()->check()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
