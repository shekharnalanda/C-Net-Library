<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\FeePlan;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_admission_submission_creates_application(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $response = $this->post('/admission', [
            'branch_id' => $branch->id,
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mobile' => '9999999999',
            'email' => 'admission-test@example.com',
            'study_slot_id' => $slot->id,
            'fee_plan_id' => $plan->id,
        ]);

        $response->assertRedirect('/admission');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('admissions', [
            'branch_id' => $branch->id,
            'name' => 'Test Student',
            'mobile' => '9999999999',
            'status' => 'new',
        ]);
    }

    public function test_public_enquiry_submission_creates_crm_lead(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();

        $response = $this->post('/enquiry', [
            'branch_id' => $branch->id,
            'name' => 'Enquiry Student',
            'mobile' => '8888888888',
            'email' => 'enquiry-test@example.com',
            'source' => 'website',
            'interested_plan' => '6 hours',
            'message' => 'Please contact me.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enquiries', [
            'branch_id' => $branch->id,
            'name' => 'Enquiry Student',
            'mobile' => '8888888888',
            'status' => 'new',
        ]);
    }

    public function test_student_activation_sets_password_logs_in_and_invalidates_token(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $token = Str::random(64);

        $user = User::create([
            'name' => 'Portal Student',
            'email' => 'portal-student@example.com',
            'password' => Str::password(24),
            'role' => 'student',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'CNL-TEST-PORTAL',
            'qr_token' => (string) Str::uuid(),
            'portal_activation_token' => hash('sha256', $token),
            'portal_activation_expires_at' => now()->addDay(),
            'name' => 'Portal Student',
            'mobile' => '7777777777',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $this->get('/student/activate/'.$token)->assertOk();

        $response = $this->post('/student/activate/'.$token, [
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        $student->refresh();
        $this->assertNull($student->portal_activation_token);
        $this->assertNull($student->portal_activation_expires_at);
        $this->assertNotNull($student->portal_activated_at);
    }

    public function test_public_digital_resource_can_be_viewed_and_is_logged(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/public-note.txt', 'Public resource');

        $resource = DigitalResource::create([
            'title' => 'Public Note',
            'slug' => 'public-note',
            'resource_type' => 'notes',
            'file_path' => 'digital-resources/public-note.txt',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => true,
        ]);

        $this->get('/digital-library/resources/'.$resource->id)->assertOk();

        $this->assertDatabaseHas('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
            'student_id' => null,
            'action' => 'view',
        ]);
    }

    public function test_member_resource_requires_student_with_active_membership(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/member-note.txt', 'Member resource');

        $resource = DigitalResource::create([
            'title' => 'Member Note',
            'slug' => 'member-note',
            'resource_type' => 'notes',
            'file_path' => 'digital-resources/member-note.txt',
            'access_type' => 'members',
            'download_allowed' => true,
            'status' => true,
        ]);

        $this->get('/digital-library/resources/'.$resource->id)
            ->assertSessionHasErrors('resource');

        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $user = User::create([
            'name' => 'Member Student',
            'email' => 'member-student@example.com',
            'password' => 'password123',
            'role' => 'student',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'CNL-TEST-MEMBER',
            'qr_token' => (string) Str::uuid(),
            'name' => 'Member Student',
            'mobile' => '6666666666',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(30),
            'base_fee' => $plan->monthly_fee,
            'discount' => 0,
            'final_fee' => $plan->monthly_fee,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/digital-library/resources/'.$resource->id)
            ->assertOk();

        $this->assertDatabaseHas('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
            'student_id' => $student->id,
            'action' => 'view',
        ]);
    }

    public function test_download_restriction_is_enforced_server_side(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/no-download.txt', 'Read only');

        $resource = DigitalResource::create([
            'title' => 'Read Only Resource',
            'slug' => 'read-only-resource',
            'resource_type' => 'notes',
            'file_path' => 'digital-resources/no-download.txt',
            'access_type' => 'public',
            'download_allowed' => false,
            'status' => true,
        ]);

        $this->get('/digital-library/resources/'.$resource->id.'?download=1')
            ->assertForbidden();
    }
}
