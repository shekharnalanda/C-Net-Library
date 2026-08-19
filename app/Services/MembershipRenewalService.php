<?php

namespace App\Services;

use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipRenewalService
{
    public function __construct(
        private readonly SeatAllocationService $seatAllocationService,
        private readonly SettingsService $settings
    ) {
    }

    public function renew(Student $student, array $data): StudentMembership
    {
        return DB::transaction(function () use ($student, $data) {
            $feePlan = FeePlan::findOrFail($data['fee_plan_id']);
            $slot = StudySlot::findOrFail($data['study_slot_id']);
            $seat = isset($data['seat_id']) ? Seat::with('studyHall')->findOrFail($data['seat_id']) : null;

            if ((int) $feePlan->branch_id !== (int) $student->branch_id || (int) $slot->branch_id !== (int) $student->branch_id) {
                throw ValidationException::withMessages([
                    'fee_plan_id' => 'Selected fee plan or slot does not belong to the student branch.',
                ]);
            }

            if ($seat && (int) $seat->studyHall?->branch_id !== (int) $student->branch_id) {
                throw ValidationException::withMessages([
                    'seat_id' => 'Selected seat does not belong to the student branch.',
                ]);
            }

            $currentMembership = $student->memberships()
                ->where('status', 'active')
                ->latest('expiry_date')
                ->first();

            $requestedStart = isset($data['start_date'])
                ? Carbon::parse($data['start_date'])->startOfDay()
                : today();

            $graceDays = max(0, (int) $this->settings->get('membership_grace_days', 0, $student->branch_id));

            if ($currentMembership && $currentMembership->expiry_date) {
                $graceEnd = $currentMembership->expiry_date->copy()->addDays($graceDays);

                if ($graceEnd->gte(today())) {
                    $requestedStart = $currentMembership->expiry_date->copy()->addDay()->startOfDay();
                }
            }

            $expiryDate = $requestedStart->copy()->addDays(max(1, (int) $feePlan->validity_days) - 1);

            if ($seat) {
                $this->seatAllocationService->assertAvailable(
                    seatId: $seat->id,
                    allocatedFrom: $requestedStart->toDateString(),
                    allocatedTo: $expiryDate->toDateString(),
                    startTime: $slot->start_time,
                    endTime: $slot->end_time,
                );
            }

            if ($currentMembership) {
                $currentMembership->update(['status' => 'expired']);
            }

            $discount = (float) ($data['discount'] ?? 0);
            $baseFee = (float) $feePlan->monthly_fee;

            if ($discount > $baseFee) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot exceed the base fee.',
                ]);
            }

            $membership = StudentMembership::create([
                'student_id' => $student->id,
                'fee_plan_id' => $feePlan->id,
                'study_slot_id' => $slot->id,
                'start_date' => $requestedStart->toDateString(),
                'expiry_date' => $expiryDate->toDateString(),
                'base_fee' => $baseFee,
                'discount' => $discount,
                'final_fee' => max(0, $baseFee - $discount),
                'status' => 'active',
            ]);

            $currentAllocation = $student->seatAllocations()
                ->where('status', 'active')
                ->latest('allocated_from')
                ->first();

            if ($currentAllocation) {
                $currentAllocation->update([
                    'allocated_to' => min(today(), $requestedStart->copy()->subDay())->toDateString(),
                    'status' => 'released',
                ]);
            }

            if ($seat) {
                SeatAllocation::create([
                    'student_id' => $student->id,
                    'student_membership_id' => $membership->id,
                    'seat_id' => $seat->id,
                    'study_slot_id' => $slot->id,
                    'allocated_from' => $requestedStart->toDateString(),
                    'allocated_to' => $expiryDate->toDateString(),
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'status' => 'active',
                    'remarks' => $data['remarks'] ?? 'Membership renewed',
                ]);
            }

            return $membership;
        });
    }
}
