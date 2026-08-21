<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https:; font-src 'self' data: https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline'; media-src 'self'; worker-src 'self' blob:; manifest-src 'self'; frame-src https://www.google.com https://maps.google.com; connect-src 'self'; upgrade-insecure-requests"
        );

        if ($this->isPrivatePortalRequest($request)) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        if ($this->shouldSendHsts($request)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function isPrivatePortalRequest(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'student', 'student/*')
            || ($request->user() !== null && $request->routeIs('logout'));
    }

    private function shouldSendHsts(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        if (! app()->environment('production')) {
            return false;
        }

        return Str::startsWith((string) config('app.url'), 'https://');
    }
}
