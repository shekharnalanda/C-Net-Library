<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'branch',
                'activeMembership.feePlan',
                'activeMembership.studySlot',
                'activeMembership.payments',
                'seatAllocations' => fn ($q) => $q->with('seat.studyHall')->latest(),
                'attendances' => fn ($q) => $q->latest('check_in')->limit(10),
                'bookIssues' => fn ($q) => $q->with('bookCopy.book')->latest('issued_at')->limit(10),
            ])
            ->firstOrFail();

        $membership = $student->activeMembership;
        $paid = $membership ? (float) $membership->payments->whereIn('payment_status', ['paid', 'partial'])->sum('amount') : 0;
        $due = $membership ? max(0, (float) $membership->final_fee - $paid) : 0;
        $activeSeat = $student->seatAllocations->firstWhere('status', 'active');
        $studyMinutes = (int) $student->attendances->sum('duration_minutes');

        return view('student.dashboard', compact('student', 'membership', 'paid', 'due', 'activeSeat', 'studyMinutes'));
    }

    public function idCard(Request $request): View
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with(['branch', 'activeMembership.studySlot'])
            ->firstOrFail();

        return view('student.id-card', compact('student'));
    }
}
