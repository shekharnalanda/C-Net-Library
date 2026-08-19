<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionApprovalService
{
    public function __construct(
        private readonly SeatAllocationService $seatAllocationService,
        private readonly SettingsService $settings
    ) {
    }

    public function approve(Admission $admission, array $data): Student
    {
        if ($admission->status === 'converted') {
            throw ValidationException::withMessages([
                'admission' => 'This admission has already been converted to a student.',
            ]);
        }

        return DB::transaction(function () use ($admission, $data) {
            $feePlan = FeePlan::findOrFail($data['fee_plan_id']);
            $slot = StudySlot::findOrFail($data['study_slot_id']);
            $seat = Seat::with('studyHall')->findOrFail($data['seat_id']);

            $branchId = $admission->branch_id ?? $feePlan->branch_id;

            if ((int) $feePlan->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['fee_plan_id' => 'Selected fee plan does not belong to the admission branch.']);
            }
            if ((int) $slot->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['study_slot_id' => 'Selected study slot does not belong to the admission branch.']);
            }
            if ((int) $seat->studyHall?->branch_id !== (int) $branchId) {
                throw ValidationException::withMessages(['seat_id' => 'Selected seat does not belong to the admission branch.']);
            }

            $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date'])->startOfDay() : today();
            $expiryDate = $startDate->copy()->addDays(max(1, (int) $feePlan->validity_days) - 1);

            $this->seatAllocationService->assertAvailable(
                seatId: $seat->id,
                allocatedFrom: $startDate->toDateString(),
                allocatedTo: $expiryDate->toDateString(),
                startTime: $slot->start_time,
                endTime: $slot->end_time,
            );

            $studentCode = $this->generateStudentCode($branchId);
            $portalEmail = $admission->email ?: strtolower($studentCode).'@student.cnetlibrary.local';
            $user = User::firstOrCreate(
                ['email' => $portalEmail],
                [
                    'name' => $admission->name,
                    'password' => Str::random(64),
                    'role' => 'student',
                    'status' => true,
                ]
            );

            $activationToken = Str::random(64);

            $student = Student::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'student_code' => $studentCode,
                'qr_token' => (string) Str::uuid(),
                'portal_activation_token' => hash('sha256', $activationToken),
                'portal_activation_expires_at' => now()->addDays(7),
                'name' => $admission->name,
                'father_name' => $admission->father_name,
                'dob' => $admission->dob,
                'gender' => $admission->gender,
                'mobile' => $admission->mobile,
                'email' => $admission->email,
                'address' => $admission->address,
                'joining_date' => $startDate->toDateString(),
                'status' => 'active',
            ]);

            $student->setAttribute('portal_activation_plain_token', $activationToken);

            $discount = (float) ($data['discount'] ?? 0);
            $baseFee = (float) $feePlan->monthly_fee;
            if ($discount > $baseFee) {
                throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the base fee.']);
            }

            $membership = StudentMembership::create([
                'student_id' => $student->id,
                'fee_plan_id' => $feePlan->id,
                'study_slot_id' => $slot->id,
                'start_date' => $startDate->toDateString(),
                'expiry_date' => $expiryDate->toDateString(),
                'base_fee' => $baseFee,
                'discount' => $discount,
                'final_fee' => max(0, $baseFee - $discount),
                'status' => 'active',
            ]);

            SeatAllocation::create([
                'student_id' => $student->id,
                'student_membership_id' => $membership->id,
                'seat_id' => $seat->id,
                'study_slot_id' => $slot->id,
                'allocated_from' => $startDate->toDateString(),
                'allocated_to' => $expiryDate->toDateString(),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'status' => 'active',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $admission->update([
                'fee_plan_id' => $feePlan->id,
                'study_slot_id' => $slot->id,
                'status' => 'converted',
                'remarks' => $data['remarks'] ?? $admission->remarks,
            ]);

            return $student;
        });
    }

    private function generateStudentCode(?int $branchId = null): string
    {
        $prefix = (string) $this->settings->get('student_code_prefix', 'CNL-STU', $branchId);
        do {
            $code = $prefix.'-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (Student::query()->where('student_code', $code)->exists());
        return $code;
    }
}
