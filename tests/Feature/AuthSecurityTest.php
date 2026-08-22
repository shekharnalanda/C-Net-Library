<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $email = 'rate-limit@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->from('/login')->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_successful_login_clears_rate_limiter_key(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $password = 'TestAdmin123!';
        $admin->forceFill(['password' => Hash::make($password)])->save();
        $key = strtolower($admin->email).'|127.0.0.1';

        RateLimiter::hit($key, 60);
        $this->assertGreaterThan(0, RateLimiter::attempts($key));

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_student_activation_routes_are_throttled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach (['student.activate', 'student.activate.store'] as $routeName) {
            $route = $routes->first(fn ($route) => $route->getName() === $routeName);

            $this->assertNotNull($route);
            $this->assertContains('throttle:10,1', $route->gatherMiddleware());
        }
    }
}
