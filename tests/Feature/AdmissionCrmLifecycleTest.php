<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudyHall;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\AdmissionApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdmissionCrmLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_enquiry_conversion_creates_new_admission_and_marks_enquiry_converted(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $enquiry = Enquiry::create([
            'branch_id' => $branch->id,
            'enquiry_no' => 'ENQ-'.Str::upper(Str::random(8)),
            'name' => 'CRM Student',
            'mobile' => '9000010001',
            'status' => 'qualified',
        ]);

        $controller = app(\App\Http\Controllers\Admin\EnquiryController::class);
        $response = $controller->convert($this->adminRequest(), $enquiry, app(\App\Services\AuditService::class));

        $enquiry->refresh();
        $this->assertSame('converted', $enquiry->status);
        $this->assertNotNull($enquiry->converted_admission_id);
        $this->assertDatabaseHas('admissions', [
            'id' => $enquiry->converted_admission_id,
            'branch_id' => $branch->id,
            'mobile' => '9000010001',
            'status' => 'new',
        ]);
        $this->assertTrue($response->isRedirect());
    }

    public function test_lost_enquiry_cannot_be_converted(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $enquiry = Enquiry::create([
            'branch_id' => $branch->id,
            'enquiry_no' => 'ENQ-'.Str::upper(Str::random(8)),
            'name' => 'Lost Prospect',
            'mobile' => '9000010002',
            'status' => 'lost',
        ]);

        $this->expectException(ValidationException::class);
        app(\App\Http\Controllers\Admin\EnquiryController::class)
            ->convert($this->adminRequest(), $enquiry, app(\App\Services\AuditService::class));
    }

    public function test_duplicate_active_student_blocks_enquiry_conversion(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'STU-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Existing Student',
            'mobile' => '9000010003',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $enquiry = Enquiry::create([
            'branch_id' => $branch->id,
            'enquiry_no' => 'ENQ-'.Str::upper(Str::random(8)),
            'name' => 'Duplicate Prospect',
            'mobile' => '9000010003',
            'status' => 'qualified',
        ]);

        try {
            app(\App\Http\Controllers\Admin\EnquiryController::class)
                ->convert($this->adminRequest(), $enquiry, app(\App\Services\AuditService::class));
            $this->fail('Expected duplicate student validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('enquiry', $exception->errors());
        }

        $this->assertDatabaseMissing('admissions', ['mobile' => '9000010003']);
    }

    private function adminRequest(): Request
    {
        $request = Request::create('/admin/enquiries/convert', 'POST');
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => true,
        ]);

        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_rejected_admission_cannot_be_approved(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $hall = StudyHall::factory()->create(['branch_id' => $branch->id]);
        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => true,
        ]);
        $plan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => 'Monthly',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $seat = Seat::create([
            'study_hall_id' => $hall->id,
            'seat_no' => 'CRM-1',
            'status' => true,
        ]);
        $admission = Admission::create([
            'branch_id' => $branch->id,
            'application_no' => 'ADM-'.Str::upper(Str::random(8)),
            'name' => 'Rejected Applicant',
            'mobile' => '9000010004',
            'status' => 'rejected',
        ]);

        $this->expectException(ValidationException::class);
        app(AdmissionApprovalService::class)->approve($admission, [
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'seat_id' => $seat->id,
            'start_date' => today()->toDateString(),
            'discount' => 0,
        ]);
    }
}
