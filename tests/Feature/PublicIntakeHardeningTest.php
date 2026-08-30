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
            'wants_locker' => false,
        ]);

        $response->assertSessionHasErrors(['study_slot_id']);
        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_recent_active_admission_duplicate_does_not_reveal_reference(): void
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
            'wants_locker' => false,
        ]);

        $response->assertRedirect(route('admission.create'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('errors');
        $this->assertStringNotContainsString('CNL-ADM-2026-EXISTING', (string) session('success'));
        $this->assertDatabaseCount('admissions', 1);
    }

    public function test_public_enquiry_rejects_inactive_branch(): void
    {
        $branch = Branch::factory()->create(['status' => false]);
        $response = $this->post(route('enquiry.store'), ['branch_id'=>$branch->id,'name'=>'Enquirer','mobile'=>'9000011002']);
        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_recent_duplicate_enquiry_does_not_reveal_reference(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $existing = Enquiry::create(['branch_id'=>$branch->id,'enquiry_no'=>'CNL-ENQ-20260820-EXISTING','name'=>'Existing Lead','mobile'=>'9000011003','status'=>'new']);
        $response = $this->from(route('enquiry.create'))->post(route('enquiry.store'), ['branch_id'=>$branch->id,'name'=>'Repeat Lead','mobile'=>'9000011003']);
        $response->assertRedirect(route('enquiry.create'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('errors');
        $this->assertStringNotContainsString($existing->enquiry_no, (string) session('success'));
        $this->assertDatabaseCount('enquiries', 1);
    }

    public function test_honeypot_blocks_public_admission_and_enquiry(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $this->post(route('admission.store'), ['website'=>'https://spam.example','branch_id'=>$branch->id,'name'=>'Bot Applicant','mobile'=>'9000011004'])->assertSessionHasErrors('website');
        $this->post(route('enquiry.store'), ['website'=>'https://spam.example','branch_id'=>$branch->id,'name'=>'Bot Lead','mobile'=>'9000011005'])->assertSessionHasErrors('website');
        $this->assertDatabaseCount('admissions', 0);
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_public_input_is_normalized_before_storage(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $this->post(route('enquiry.store'), ['branch_id'=>$branch->id,'name'=>'  Normalized Lead  ','mobile'=>'90000 11006','email'=>'USER@EXAMPLE.COM'])->assertSessionHas('success');
        $this->assertDatabaseHas('enquiries', ['branch_id'=>$branch->id,'name'=>'Normalized Lead','mobile'=>'9000011006','email'=>'user@example.com']);
    }
}
