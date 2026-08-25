<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_mobile_routes_are_registered(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

        foreach ([
            'api/mobile/v1/login',
            'api/mobile/v1/logout',
            'api/mobile/v1/dashboard',
            'api/mobile/v1/profile',
            'api/mobile/v1/membership',
            'api/mobile/v1/payments',
            'api/mobile/v1/attendance',
            'api/mobile/v1/seat-allocation',
            'api/mobile/v1/books',
            'api/mobile/v1/issued-books',
            'api/mobile/v1/digital-resources',
            'api/mobile/v1/jobs',
            'api/mobile/v1/qr-member-id',
            'api/mobile/v1/support',
        ] as $uri) {
            $this->assertTrue($uris->contains($uri), "Missing mobile API route: {$uri}");
        }
    }

    public function test_protected_mobile_endpoint_rejects_missing_token(): void
    {
        $this->getJson('/api/mobile/v1/dashboard')->assertUnauthorized();
    }

    public function test_mobile_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/mobile/v1/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
            'device_name' => 'test-device',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid login credentials or inactive account.');
    }

    public function test_active_student_can_login_and_access_profile(): void
    {
        $student = Student::query()->whereNotNull('user_id')->first();
        $this->assertNotNull($student, 'Seeder must provide a student linked to a user.');

        $user = User::query()->findOrFail($student->user_id);
        $password = 'MobileTest123!';

        $user->forceFill([
            'role' => 'student',
            'status' => true,
            'password' => Hash::make($password),
        ])->save();
        $student->forceFill(['status' => 'active'])->save();

        $login = $this->postJson('/api/mobile/v1/login', [
            'email' => $user->email,
            'password' => $password,
            'device_name' => 'phpunit',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/v1/profile')
            ->assertOk();
    }
}
