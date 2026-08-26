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

    public function test_seeded_admin_can_open_all_critical_admin_pages(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        foreach ([
            '/admin/dashboard',
            '/admin/admissions',
            '/admin/enquiries',
            '/admin/students',
            '/admin/study-space',
            '/admin/available-seats',
            '/admin/lockers',
            '/admin/attendance',
            '/admin/attendance/scan',
            '/admin/cashbook',
            '/admin/library',
            '/admin/digital-library',
            '/admin/jobs',
            '/admin/communications',
            '/admin/staff',
            '/admin/reports',
            '/admin/settings',
            '/admin/cms',
            '/admin/security',
        ] as $uri) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_admin_dashboard_exposes_critical_operational_navigation(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Study Hall & Seats')
            ->assertSee('Locker Management')
            ->assertSee('Admissions')
            ->assertSee('Students & Memberships')
            ->assertSee('Cashbook')
            ->assertSee('Physical Library')
            ->assertSee('Digital Library')
            ->assertSee('Reports & Analytics');
    }

    public function test_student_middleware_rejects_admin_user(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/student/dashboard')
            ->assertForbidden();
    }
}
