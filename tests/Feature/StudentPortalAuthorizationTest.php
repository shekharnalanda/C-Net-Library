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

    public function test_inactive_linked_student_terminates_existing_portal_session(): void
    {
        [$user, $student] = $this->createStudentAccount('inactive-student@example.com', 'CNL-INACTIVE-STUDENT');
        $student->update(['status' => 'inactive']);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_linked_student_cannot_log_in(): void
    {
        [, $student] = $this->createStudentAccount('inactive-login@example.com', 'CNL-INACTIVE-LOGIN');
        $student->update(['status' => 'inactive']);

        $this->post(route('login.store'), [
            'email' => 'inactive-login@example.com',
            'password' => 'SecurePass123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_student_cannot_use_activation_link(): void
    {
        [, $student] = $this->createStudentAccount('activation-inactive@example.com', 'CNL-ACT-INACTIVE');
        $plainToken = Str::random(64);
        $student->update([
            'status' => 'inactive',
            'portal_activation_token' => hash('sha256', $plainToken),
            'portal_activation_expires_at' => now()->addHour(),
            'portal_activated_at' => null,
        ]);

        $this->get(route('student.activate', $plainToken))->assertGone();
    }

    public function test_student_portal_pages_are_not_cacheable_and_id_card_does_not_render_qr_secret(): void
    {
        [$user, $student] = $this->createStudentAccount('privacy-student@example.com', 'CNL-PRIVACY-STUDENT');

        $dashboard = $this->actingAs($user)->get(route('student.dashboard'));
        $dashboard->assertOk();
        $this->assertPrivateNoStoreCachePolicy($dashboard);
        $dashboard->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $idCard = $this->actingAs($user)->get(route('student.id-card'));
        $idCard->assertOk();
        $this->assertPrivateNoStoreCachePolicy($idCard);
        $idCard->assertHeader('Referrer-Policy', 'no-referrer');
        $idCard->assertDontSee($student->qr_token, false);
        $idCard->assertDontSee(route('admin.attendance.scan', ['token' => $student->qr_token]), false);
    }

    public function test_admin_can_generate_a_private_printable_student_id_card(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        [, $student] = $this->createStudentAccount('admin-card@example.com', 'CNL-ADMIN-CARD');

        $response = $this->actingAs($admin)
            ->get(route('admin.students.id-card', $student));

        $response->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee($student->student_code)
            ->assertSee('cnet-library-logo.png');

        $this->assertPrivateNoStoreCachePolicy($response);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->assertDontSee($student->qr_token, false);
    }

    private function assertPrivateNoStoreCachePolicy($response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');

        foreach (['private', 'no-store', 'no-cache', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
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
