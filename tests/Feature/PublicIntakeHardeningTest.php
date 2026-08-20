<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\FeePlan;
use App\Models\StudySlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicIntakeHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_admission_rejects_slot_and_plan_from_another_branch(): void
    {
        $branchA = Branch::factory()->create(['status' => true]);
        $branchB = Branch::factory()->create(['status' => true]);
        $slot = StudySlot::factory()->create(['branch_id' => $branchB->id, 'status' => true]);
        $plan = FeePlan::factory()->create(['branch_id' => $branchB->id, 'study_slot_id' => $slot->id, 'status' => true]);

        $response = $this->post(route('admission.store'), [
            'branch_id' => $branchA->id,
            'name' => 'Public Applicant',
            'mobile' => '9000011000',
            'study_slot_id' => $slot->id,
            'fee_plan_id' => $plan->id,
        ]);

        $response->assertSessionHasErrors(['study_slot_id']);
        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_recent_active_admission_with_same_branch_and_mobile_is_rejected(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        Admission::create([
            'branch_id' => $branch->id,
            'application_no' => 'CNL-ADM-2026-EXISTING',
            'name' => 'Existing Applicant',
            'mobile' => '9000011001',
            'status' => 'new',
        ]);

        $response = $this->post(route('admission.store'), [
            'branch_id' => $branch->id,
            'name' => 'Repeat Applicant',
            'mobile' => '9000011001',
        ]);

        $response->assertSessionHasErrors('mobile');
        $this->assertDatabaseCount('admissions', 1);
    }

    public function test_public_enquiry_rejects_inactive_branch(): void
    {
        $branch = Branch::factory()->create(['status' => false]);

        $response = $this->post(route('enquiry.store'), [
            'branch_id' => $branch->id,
            'name' => 'Enquirer',
            'mobile' => '9000011002',
        ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_recent_duplicate_enquiry_is_rejected_with_existing_reference(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $existing = Enquiry::create([
            'branch_id' => $branch->id,
            'enquiry_no' => 'CNL-ENQ-20260820-EXISTING',
            'name' => 'Existing Lead',
            'mobile' => '9000011003',
            'status' => 'new',
        ]);

        $response = $this->from(route('enquiry.create'))->post(route('enquiry.store'), [
            'branch_id' => $branch->id,
            'name' => 'Repeat Lead',
            'mobile' => '9000011003',
        ]);

        $response->assertRedirect(route('enquiry.create'));
        $response->assertSessionHasErrors('mobile');
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseHas('enquiries', ['id' => $existing->id]);
    }
}
