<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with('branch')
            ->firstOrFail();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'dob' => optional($student->dob)->toDateString(),
                'gender' => $student->gender,
                'mobile' => $student->mobile,
                'alternate_mobile' => $student->alternate_mobile,
                'email' => $student->email,
                'address' => $student->address,
                'photo' => $student->photo,
                'joining_date' => optional($student->joining_date)->toDateString(),
                'status' => $student->status,
                'branch' => $student->branch ? [
                    'id' => $student->branch->id,
                    'name' => $student->branch->name,
                ] : null,
            ],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'branch',
                'activeMembership.feePlan',
                'activeMembership.studySlot',
                'activeMembership.payments',
                'seatAllocations' => fn ($q) => $q->with('seat.studyHall')->latest(),
                'attendances' => fn ($q) => $q->latest('check_in_at')->limit(10),
                'bookIssues' => fn ($q) => $q->with('bookCopy.book')->latest('issued_at')->limit(10),
            ])
            ->firstOrFail();

        $membership = $student->activeMembership;
        $grossPaid = $membership ? (float) $membership->payments->whereIn('payment_status', ['paid', 'partial'])->sum('amount') : 0;
        $adjusted = $membership ? (float) PaymentAdjustment::query()
            ->whereHas('payment', fn ($query) => $query->where('student_membership_id', $membership->id))
            ->sum('amount') : 0;
        $paid = max(0, $grossPaid - $adjusted);
        $due = $membership ? max(0, (float) $membership->final_fee - $paid) : 0;
        $activeSeat = $student->seatAllocations->firstWhere('status', 'active');
        $studyMinutes = (int) $student->attendances->sum('study_minutes');

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'photo' => $student->photo,
                'branch_name' => $student->branch?->name,
            ],
            'membership' => $membership ? [
                'id' => $membership->id,
                'status' => $membership->status,
                'start_date' => optional($membership->start_date)->toDateString(),
                'expiry_date' => optional($membership->expiry_date)->toDateString(),
                'fee_plan' => $membership->feePlan?->name,
                'study_slot' => $membership->studySlot?->name,
                'final_fee' => (float) $membership->final_fee,
                'paid' => $paid,
                'due' => $due,
            ] : null,
            'active_seat' => $activeSeat ? [
                'allocation_id' => $activeSeat->id,
                'seat_id' => $activeSeat->seat_id,
                'seat_number' => $activeSeat->seat?->seat_number,
                'study_hall' => $activeSeat->seat?->studyHall?->name,
                'status' => $activeSeat->status,
            ] : null,
            'attendance' => [
                'recent_count' => $student->attendances->count(),
                'study_minutes' => $studyMinutes,
            ],
            'books' => [
                'recent_issue_count' => $student->bookIssues->count(),
            ],
        ]);
    }
}
