<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_intended_url_is_not_used_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'StrongPass!123',
            'role' => 'super_admin',
            'status' => true,
        ]);

        $response = $this->withSession([
            'url.intended' => 'https://evil.example/phish',
        ])->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StrongPass!123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_protocol_relative_intended_url_is_not_used_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin2@example.com',
            'password' => 'StrongPass!123',
            'role' => 'super_admin',
            'status' => true,
        ]);

        $response = $this->withSession([
            'url.intended' => '//evil.example/phish',
        ])->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StrongPass!123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_same_origin_intended_path_is_preserved_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin3@example.com',
            'password' => 'StrongPass!123',
            'role' => 'super_admin',
            'status' => true,
        ]);

        $response = $this->withSession([
            'url.intended' => '/admin/reports?from=2026-08-01',
        ])->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StrongPass!123',
        ]);

        $response->assertRedirect('/admin/reports?from=2026-08-01');
    }
}
