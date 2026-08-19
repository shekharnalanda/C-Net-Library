<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdmissionPaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_approval_creates_student_membership_and_seat_allocation(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->where('status', true)->firstOrFail();
        $seat = Seat::query()->whereHas('studyHall', fn ($query) => $query->where('branch_id', $branch->id))->where('status', true)->firstOrFail();

        $admission = Admission::create([
            'branch_id' => $branch->id,
            'application_no' => 'CNL-ADM-INTEGRATION-1',
            'name' => 'Approval Student',
            'father_name' => 'Approval Father',
            'mobile' => '9000000001',
            'email' => 'approval-student@example.com',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)->post('/admin/admissions/'.$admission->id.'/approve', [
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'seat_id' => $seat->id,
            'start_date' => today()->toDateString(),
            'discount' => 100,
            'remarks' => 'Integration approval',
        ]);

        $response->assertRedirect(route('admin.admissions.show', $admission));
        $response->assertSessionHas('success');

        $student = Student::query()->where('email', 'approval-student@example.com')->firstOrFail();

        $this->assertDatabaseHas('student_memberships', [
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('seat_allocations', [
            'student_id' => $student->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('admissions', [
            'id' => $admission->id,
            'status' => 'converted',
        ]);

        $this->assertNotNull($student->portal_activation_token);
        $this->assertNotNull($student->user_id);
    }

    public function test_partial_then_final_payment_updates_status_and_receipt_route_loads(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->where('status', true)->firstOrFail();

        $user = User::create([
            'name' => 'Payment Student',
            'email' => 'payment-student@example.com',
            'password' => 'password123',
            'role' => 'student',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'CNL-PAYMENT-1',
            'name' => 'Payment Student',
            'mobile' => '9000000002',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        $partial = $this->actingAs($admin)->post('/admin/students/'.$student->id.'/payments', [
            'student_membership_id' => $membership->id,
            'amount' => 400,
            'payment_mode' => 'cash',
        ]);

        $partial->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'amount' => 400,
            'payment_status' => 'partial',
        ]);

        $firstPaymentId = (int) $membership->payments()->orderBy('id')->value('id');
        $this->actingAs($admin)
            ->get('/admin/payments/'.$firstPaymentId.'/receipt')
            ->assertOk();

        $final = $this->actingAs($admin)->post('/admin/students/'.$student->id.'/payments', [
            'student_membership_id' => $membership->id,
            'amount' => 600,
            'payment_mode' => 'upi',
            'transaction_ref' => 'UPI-TEST-1',
        ]);

        $final->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'amount' => 600,
            'payment_status' => 'paid',
            'transaction_ref' => 'UPI-TEST-1',
        ]);
    }

    public function test_premium_resource_requires_premium_named_plan(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resources/premium.txt', 'Premium resource');

        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $resource = DigitalResource::create([
            'title' => 'Premium Resource',
            'slug' => 'premium-resource',
            'resource_type' => 'notes',
            'file_path' => 'resources/premium.txt',
            'access_type' => 'premium',
            'download_allowed' => true,
            'status' => true,
        ]);

        $regularPlan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->firstOrFail();

        $user = User::create([
            'name' => 'Premium Test Student',
            'email' => 'premium-test@example.com',
            'password' => 'password123',
            'role' => 'student',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'CNL-PREMIUM-1',
            'name' => 'Premium Test Student',
            'mobile' => '9000000003',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $regularPlan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => $regularPlan->monthly_fee,
            'discount' => 0,
            'final_fee' => $regularPlan->monthly_fee,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/digital-library/resources/'.$resource->id)
            ->assertSessionHasErrors('resource');

        $premiumPlan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => 'Premium Digital Monthly',
            'monthly_fee' => 1500,
            'admission_fee' => 0,
            'registration_fee' => 0,
            'security_deposit' => 0,
            'late_fee' => 0,
            'validity_days' => 30,
            'status' => true,
        ]);

        $membership->update(['fee_plan_id' => $premiumPlan->id]);

        $this->actingAs($user)
            ->get('/digital-library/resources/'.$resource->id)
            ->assertOk();

        $this->assertDatabaseHas('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
            'student_id' => $student->id,
            'action' => 'view',
        ]);
    }
}
