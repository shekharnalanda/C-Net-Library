<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_admin_can_open_core_admin_pages(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        foreach (['/admin/dashboard', '/admin/reports', '/admin/library'] as $uri) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_student_middleware_rejects_admin_user(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/student/dashboard')
            ->assertForbidden();
    }
}
