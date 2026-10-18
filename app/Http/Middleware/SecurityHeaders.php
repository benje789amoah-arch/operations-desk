<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every web response. HSTS is only sent over
 * HTTPS so local HTTP development keeps working.
 */
class SecurityHeaders
{
    /**
     * Only this application's own origin may supply scripts, styles, data
     * connections and form targets; nothing may frame the app. Livewire's
     * bundled Alpine.js evaluates directive expressions, which needs
     * 'unsafe-eval', and inline style attributes need 'unsafe-inline'.
     */
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data:; "
        ."font-src 'self' data:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // The Vite dev server serves assets from another origin, so the
        // policy is skipped while it is running (local development only).
        if (config('app.content_security_policy') && ! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
