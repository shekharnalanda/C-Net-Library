<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPortalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createStudentAccount(string $email, string $studentCode, string $status = 'active'): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $user = User::query()->create([
            'name' => 'Student '.$studentCode,
            'email' => $email,
            'password' => Hash::make('StudentPass123!'),
            'role' => 'student',
            'status' => true,
            'branch_id' => $branch->id,
        ]);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'student_code' => $studentCode,
            'name' => $user->name,
            'email' => $email,
            'mobile' => '9000000000',
            'joining_date' => today(),
            'status' => $status,
        ]);

        return [$user, $student];
    }

    private function assertPrivateNoStoreCachePolicy($response): void
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
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
        [$user] = $this->createStudentAccount('student-access@example.com', 'CNL-ACCESS');

        $this->actingAs($user)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('student.id-card'))->assertOk();
        $this->actingAs($user)->get(route('student.saved-jobs.index'))->assertOk();
    }

    public function test_student_dashboard_is_resolved_only_from_authenticated_user(): void
    {
        [$firstUser, $firstStudent] = $this->createStudentAccount('first@example.com', 'CNL-FIRST');
        [, $secondStudent] = $this->createStudentAccount('second@example.com', 'CNL-SECOND');

        $response = $this->actingAs($firstUser)->get(route('student.dashboard'));

        $response->assertOk()
            ->assertSee($firstStudent->student_code)
            ->assertDontSee($secondStudent->student_code);
    }

    public function test_inactive_linked_student_terminates_existing_portal_session(): void
    {
        [$user] = $this->createStudentAccount('inactive-session@example.com', 'CNL-INACTIVE-SESSION', 'inactive');

        $this->actingAs($user)->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_linked_student_cannot_log_in(): void
    {
        [$user] = $this->createStudentAccount('inactive-login@example.com', 'CNL-INACTIVE-LOGIN', 'inactive');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StudentPass123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_student_cannot_use_activation_link(): void
    {
        [, $student] = $this->createStudentAccount('inactive-activation@example.com', 'CNL-INACTIVE-ACT', 'inactive');
        $student->forceFill(['portal_activation_token' => hash('sha256', 'inactive-token')])->save();

        $this->get(route('student.activate', ['token' => 'inactive-token']))->assertStatus(410);
    }

    public function test_student_portal_pages_are_not_cacheable_and_id_card_does_not_render_raw_qr_token(): void
    {
        [$user, $student] = $this->createStudentAccount('privacy@example.com', 'CNL-PRIVACY');
        $student->forceFill(['qr_token' => 'super-secret-qr-token'])->save();

        $dashboard = $this->actingAs($user)->get(route('student.dashboard'));
        $this->assertPrivateNoStoreCachePolicy($dashboard);

        $idCard = $this->actingAs($user)->get(route('student.id-card'));
        $this->assertPrivateNoStoreCachePolicy($idCard);
        $idCard->assertDontSee($student->qr_token, false);
        $idCard->assertDontSee(route('admin.attendance.scan', ['token' => $student->qr_token]), false);
    }

    public function test_admin_can_generate_a_private_printable_student_id_card(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        [, $student] = $this->createStudentAccount('admin-card@example.com', 'CNL-ADMIN-CARD');
        $student->forceFill(['qr_token' => null])->save();

        $response = $this->actingAs($admin)
            ->get(route('admin.students.id-card', $student));

        $response->assertOk()
            ->assertSee('Print ID + Lanyard Design')
            ->assertSee('Member Services & Identification', false)
            ->assertSee('width:85.6mm;height:128mm', false)
            ->assertSee($student->student_code)
            ->assertSee('cnet-library-logo.png');

        $this->assertPrivateNoStoreCachePolicy($response);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $student->refresh();
        $this->assertNotNull($student->qr_token);
        $response->assertDontSee($student->qr_token, false);
    }

    public function test_admin_can_upload_and_render_student_photo_on_id_card(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        [, $student] = $this->createStudentAccount('photo-card@example.com', 'CNL-PHOTO-CARD');

        $this->actingAs($admin)
            ->post(route('admin.students.photo.update', $student), [
                'photo' => UploadedFile::fake()->image('student.jpg', 300, 400),
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertNotNull($student->photo);
        Storage::disk('public')->assertExists($student->photo);

        $this->actingAs($admin)
            ->get(route('admin.students.id-card', $student))
            ->assertOk()
            ->assertSee('storage/'.$student->photo, false);
    }
}
