<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentPortalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_from_student_portal(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('student.id-card'))->assertRedirect(route('login'));
        $this->get(route('student.saved-jobs.index'))->assertRedirect(route('login'));
    }

    public function test_admin_cannot_access_student_portal_routes(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('student.id-card'))->assertForbidden();
        $this->actingAs($admin)->get(route('student.saved-jobs.index'))->assertForbidden();
    }

    public function test_student_can_access_dashboard_id_card_and_saved_jobs(): void
    {
        [$user] = $this->createStudentAccount('portal-access@example.com', 'CNL-PORTAL-ACCESS');

        $this->actingAs($user)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('student.id-card'))->assertOk();
        $this->actingAs($user)->get(route('student.saved-jobs.index'))->assertOk();
    }

    public function test_student_dashboard_is_resolved_only_from_authenticated_user(): void
    {
        [$firstUser, $firstStudent] = $this->createStudentAccount('first-student@example.com', 'CNL-FIRST-STUDENT');
        [, $secondStudent] = $this->createStudentAccount('second-student@example.com', 'CNL-SECOND-STUDENT');

        $response = $this->actingAs($firstUser)->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee($firstStudent->student_code);
        $response->assertDontSee($secondStudent->student_code);
    }

    private function createStudentAccount(string $email, string $studentCode): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();

        $user = User::create([
            'name' => $studentCode,
            'email' => $email,
            'password' => 'SecurePass123!',
            'role' => 'student',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => $studentCode,
            'qr_token' => (string) Str::uuid(),
            'name' => $studentCode,
            'mobile' => (string) random_int(7000000000, 9999999999),
            'joining_date' => today(),
            'status' => 'active',
        ]);

        return [$user, $student];
    }
}
