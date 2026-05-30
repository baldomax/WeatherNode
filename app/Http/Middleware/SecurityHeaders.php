<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach baseline security headers to every web response.
     *
     * Notes:
     * - HSTS is only emitted over HTTPS (sending it over plain HTTP is ignored
     *   by browsers and would be wrong during local dev).
     * - The Content-Security-Policy is sent in Report-Only mode for now. The app
     *   relies on inline scripts/styles (ApexCharts, Alpine.js, injected chart JSON)
     *   and the admin "custom head code" feature, so enforcing a strict CSP would
     *   break the UI. Report-Only surfaces violations in the browser console so the
     *   policy can be tightened, then switched to enforcing, without downtime.
     *   TODO: once console reports are clean, rename the header to
     *   `Content-Security-Policy` to enforce it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            $this->contentSecurityPolicy()
        );

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            // 'unsafe-inline'/'unsafe-eval' are required by Alpine.js, the injected
            // chart-data JSON and the admin head_code feature. Tighten before enforcing.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            // Tiles, OG images, radar frames and remote weather imagery.
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
