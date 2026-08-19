<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $payments = Payment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]);

        $collection = (clone $payments)
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount');

        $activeMemberships = StudentMembership::query()
            ->where('status', 'active')
            ->withSum(['payments as paid_amount' => fn ($query) => $query->whereIn('payment_status', ['paid', 'partial'])], 'amount')
            ->get();

        $totalDue = $activeMemberships->sum(function (StudentMembership $membership) {
            return max(0, (float) $membership->final_fee - (float) ($membership->paid_amount ?? 0));
        });

        $totalSeats = Seat::query()->where('status', true)->count();
        $occupiedSeats = SeatAllocation::query()
            ->where('status', 'active')
            ->whereDate('allocated_from', '<=', today())
            ->where(function ($query) {
                $query->whereNull('allocated_to')->orWhereDate('allocated_to', '>=', today());
            })
            ->distinct('seat_id')
            ->count('seat_id');

        $attendanceMinutes = Attendance::query()
            ->whereBetween('check_in_at', [$from, $to])
            ->sum('study_minutes');

        $admissionCount = Admission::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $convertedAdmissions = Admission::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'converted')
            ->count();

        $enquiryCount = Enquiry::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $convertedEnquiries = Enquiry::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'converted')
            ->count();

        $metrics = [
            'students' => Student::query()->where('status', 'active')->count(),
            'active_memberships' => $activeMemberships->count(),
            'collection' => (float) $collection,
            'due' => (float) $totalDue,
            'seat_occupancy_percent' => $totalSeats > 0 ? round(($occupiedSeats / $totalSeats) * 100, 1) : 0,
            'occupied_seats' => $occupiedSeats,
            'total_seats' => $totalSeats,
            'study_hours' => round(((int) $attendanceMinutes) / 60, 1),
            'admissions' => $admissionCount,
            'admission_conversion_percent' => $admissionCount > 0 ? round(($convertedAdmissions / $admissionCount) * 100, 1) : 0,
            'enquiries' => $enquiryCount,
            'crm_conversion_percent' => $enquiryCount > 0 ? round(($convertedEnquiries / $enquiryCount) * 100, 1) : 0,
            'books_available' => BookCopy::query()->where('status', 'available')->count(),
            'books_issued' => BookIssue::query()->whereNull('returned_at')->count(),
            'overdue_books' => BookIssue::query()->whereNull('returned_at')->whereDate('due_date', '<', today())->count(),
        ];

        $dailyCollections = Payment::query()
            ->selectRaw('payment_date, SUM(amount) as total')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('payment_status', ['paid', 'partial'])
            ->groupBy('payment_date')
            ->orderBy('payment_date')
            ->get();

        return view('admin.reports.index', compact('metrics', 'dailyCollections', 'from', 'to'));
    }
}
