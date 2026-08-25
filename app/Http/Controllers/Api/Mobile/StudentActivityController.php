<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentActivityController extends Controller
{
    private function student(Request $request): Student
    {
        return Student::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function membership(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $membership = $student->activeMembership()
            ->with(['feePlan', 'studySlot', 'payments.adjustments'])
            ->first();

        if (! $membership) {
            return response()->json(['data' => null]);
        }

        $grossPaid = (float) $membership->payments
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount');
        $adjusted = (float) $membership->payments->sum(
            fn ($payment) => $payment->adjustments->sum('amount')
        );
        $paid = max(0, $grossPaid - $adjusted);
        $due = max(0, (float) $membership->final_fee - $paid);

        return response()->json([
            'data' => [
                'id' => $membership->id,
                'status' => $membership->status,
                'start_date' => optional($membership->start_date)->toDateString(),
                'expiry_date' => optional($membership->expiry_date)->toDateString(),
                'base_fee' => (float) $membership->base_fee,
                'discount' => (float) $membership->discount,
                'final_fee' => (float) $membership->final_fee,
                'paid' => $paid,
                'due' => $due,
                'fee_plan' => $membership->feePlan,
                'study_slot' => $membership->studySlot,
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $payments = $student->payments()
            ->with(['adjustments'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json($payments);
    }

    public function attendance(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $attendance = $student->attendances()
            ->latest('attendance_date')
            ->latest('check_in_at')
            ->paginate(min(max((int) $request->integer('per_page', 30), 1), 60));

        return response()->json($attendance);
    }

    public function seat(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $allocations = $student->seatAllocations()
            ->with(['seat.studyHall', 'studySlot'])
            ->latest('allocated_from')
            ->get();

        $active = $allocations->firstWhere('status', 'active');

        return response()->json([
            'data' => [
                'active' => $active,
                'history' => $allocations,
            ],
        ]);
    }
}
