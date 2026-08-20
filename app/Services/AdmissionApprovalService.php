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
        return DB::transaction(function () use ($admission, $data) {
            $lockedAdmission = Admission::query()
                ->whereKey($admission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAdmission->status === 'converted') {
                throw ValidationException::withMessages([
                    'admission' => 'This admission has already been converted to a student.',
                ]);
            }

            $feePlan = FeePlan::findOrFail($data['fee_plan_id']);
            $slot = StudySlot::findOrFail($data['study_slot_id']);
            $seat = Seat::query()
                ->with('studyHall')
                ->whereKey($data['seat_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $branchId = $lockedAdmission->branch_id ?? $feePlan->branch_id;

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
            $portalEmail = $lockedAdmission->email ?: strtolower($studentCode).'@student.cnetlibrary.local';
            $user = User::query()->where('email', $portalEmail)->first();

            if ($user && $user->role !== 'student') {
                throw ValidationException::withMessages([
                    'email' => 'This email is already used by a non-student account. Use a different student email before approving the admission.',
                ]);
            }

            if ($user && Student::query()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'This portal account is already linked to a student record.',
                ]);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $lockedAdmission->name,
                    'email' => $portalEmail,
                    'password' => Str::random(64),
                    'role' => 'student',
                    'status' => true,
                ]);
            }

            $activationToken = Str::random(64);

            $student = Student::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'student_code' => $studentCode,
                'qr_token' => (string) Str::uuid(),
                'portal_activation_token' => hash('sha256', $activationToken),
                'portal_activation_expires_at' => now()->addDays(7),
                'name' => $lockedAdmission->name,
                'father_name' => $lockedAdmission->father_name,
                'dob' => $lockedAdmission->dob,
                'gender' => $lockedAdmission->gender,
                'mobile' => $lockedAdmission->mobile,
                'email' => $lockedAdmission->email,
                'address' => $lockedAdmission->address,
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

            $lockedAdmission->update([
                'fee_plan_id' => $feePlan->id,
                'study_slot_id' => $slot->id,
                'status' => 'converted',
                'remarks' => $data['remarks'] ?? $lockedAdmission->remarks,
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
