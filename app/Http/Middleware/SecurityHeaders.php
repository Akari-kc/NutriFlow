<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders middleware
 *
 * Adds common HTTP security headers to every response.
 *
 * Customizing CSP:
 * If you load external scripts or use CDNs (e.g., Chart.js, Bootstrap, Google Fonts),
 * update the Content-Security-Policy "script-src", "style-src", "font-src", and
 * other directives to include those origins explicitly. For example:
 *   default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; img-src 'self' data:
 *
 * Be as restrictive as possible and list only the origins you trust.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Content Security Policy (CSP)
        // Relaxed to support current layout assets:
        // - Google Fonts (styles + font files)
        // - Bootstrap CSS/JS and Icons from jsDelivr
        // - Inline <style> blocks and inline scripts injected by Blade (@push)
        // - Images from same-origin plus data URLs (badges, toggler icon)
        // If you run Vite dev server, consider adding http://localhost:5173 and ws: to connect-src/script-src.
        $csp = [
            "default-src 'self'",
            // Allow inline scripts used in views and external CDN scripts (Chart.js, Bootstrap bundle)
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            // Allow inline styles (style blocks) and external stylesheets (Bootstrap, Google Fonts)
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            // Allow font files from Google Fonts and same-origin; data: for embedded fonts if any
            "font-src 'self' https://fonts.gstatic.com data:",
            // Allow images from same-origin and data: URIs used in CSS (e.g., navbar toggler icon)
            "img-src 'self' data:",
            // Limit XHR/fetch/websocket to same-origin by default
            "connect-src 'self'",
            // Prevent this app from being framed (alternative to X-Frame-Options)
            "frame-ancestors 'none'",
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        // X-Frame-Options: Prevent the site from being framed to mitigate clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Strict-Transport-Security (HSTS): Force HTTPS for one year (applies only over HTTPS)
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Referrer-Policy: Limit referrer information sent to other sites
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        return $response;
    }
}
