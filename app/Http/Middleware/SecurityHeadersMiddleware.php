<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and enforce OWASP ASVS-recommended security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 2. Clickjacking protection
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 3. Legacy XSS filter configuration
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Restrict device capabilities
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(self), geolocation=(), payment=(self)'
        );

        // 6. Content Security Policy (allows local fonts, Vite, Alpine/Livewire eval, and inline styles)
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.bunny.net",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: https: blob:",
            "media-src 'self' https: blob:",
            "connect-src 'self' https:",
            "frame-src 'self' https:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        // 7. Strict-Transport-Security (enforced on HTTPS connections)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
