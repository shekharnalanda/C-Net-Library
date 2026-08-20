<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersAndThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_has_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://www.google.com', $csp);
    }

    public function test_public_submission_routes_are_throttled(): void
    {
        $admission = Route::getRoutes()->getByName('admission.store');
        $enquiry = Route::getRoutes()->getByName('enquiry.store');

        $this->assertNotNull($admission);
        $this->assertNotNull($enquiry);
        $this->assertContains('throttle:8,1', $admission->gatherMiddleware());
        $this->assertContains('throttle:10,1', $enquiry->gatherMiddleware());
    }

    public function test_qr_mark_route_requires_backoffice_authorization_and_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('admin.attendance.scan.mark');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('admin', $middleware);
        $this->assertContains('permission:attendance.manage', $middleware);
        $this->assertContains('throttle:30,1', $middleware);
    }
}
