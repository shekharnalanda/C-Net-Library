<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
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

        $grossCollection = (float) Payment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount');

        $adjustments = (float) PaymentAdjustment::query()
            ->whereBetween('adjustment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $collection = max(0, $grossCollection - $adjustments);

        $activeMemberships = StudentMembership::query()
            ->where('status', 'active')
            ->with([
                'payments' => fn ($query) => $query
                    ->whereIn('payment_status', ['paid', 'partial'])
                    ->withSum('adjustments', 'amount'),
            ])
            ->get();

        $totalDue = $activeMemberships->sum(function (StudentMembership $membership) {
            $netPaid = $membership->payments->sum(function (Payment $payment) {
                return max(0, (float) $payment->amount - (float) ($payment->adjustments_sum_amount ?? 0));
            });

            return max(0, (float) $membership->final_fee - $netPaid);
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
            'collection' => $collection,
            'gross_collection' => $grossCollection,
            'adjustments' => $adjustments,
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
            'overdue_books' => BookIssue::query()->whereNull('returned_at')->whereDate('due_at', '<', today())->count(),
        ];

        $grossDaily = Payment::query()
            ->selectRaw('payment_date, SUM(amount) as total')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('payment_status', ['paid', 'partial'])
            ->groupBy('payment_date')
            ->pluck('total', 'payment_date');

        $dailyAdjustments = PaymentAdjustment::query()
            ->selectRaw('adjustment_date, SUM(amount) as total')
            ->whereBetween('adjustment_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('adjustment_date')
            ->pluck('total', 'adjustment_date');

        $dailyCollections = collect(array_unique(array_merge($grossDaily->keys()->all(), $dailyAdjustments->keys()->all())))
            ->sort()
            ->values()
            ->map(function ($date) use ($grossDaily, $dailyAdjustments) {
                return (object) [
                    'payment_date' => $date,
                    'total' => max(0, (float) ($grossDaily[$date] ?? 0) - (float) ($dailyAdjustments[$date] ?? 0)),
                    'gross_total' => (float) ($grossDaily[$date] ?? 0),
                    'adjustment_total' => (float) ($dailyAdjustments[$date] ?? 0),
                ];
            });

        return view('admin.reports.index', compact('metrics', 'dailyCollections', 'from', 'to'));
    }
}
