<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Enquiry;
use App\Models\Expense;
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
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $collection = max(0, $grossCollection - $adjustments);
        $expenses = (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
        $closingBalance = $collection - $expenses;

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
            'expenses' => $expenses,
            'closing_balance' => $closingBalance,
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
            ->selectRaw('DATE(created_at) as adjustment_day, SUM(amount) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'adjustment_day');

        $dailyExpenses = Expense::query()
            ->selectRaw('expense_date, SUM(amount) as total')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('expense_date')
            ->pluck('total', 'expense_date');

        $dailyCollections = collect(array_unique(array_merge(
            $grossDaily->keys()->all(),
            $dailyAdjustments->keys()->all(),
            $dailyExpenses->keys()->all()
        )))
            ->sort()
            ->values()
            ->map(function ($date) use ($grossDaily, $dailyAdjustments, $dailyExpenses) {
                $gross = (float) ($grossDaily[$date] ?? 0);
                $adjustment = (float) ($dailyAdjustments[$date] ?? 0);
                $expense = (float) ($dailyExpenses[$date] ?? 0);
                $net = max(0, $gross - $adjustment);

                return (object) [
                    'payment_date' => $date,
                    'total' => $net,
                    'gross_total' => $gross,
                    'adjustment_total' => $adjustment,
                    'expense_total' => $expense,
                    'cash_balance' => $net - $expense,
                ];
            });

        return view('admin.reports.index', compact('metrics', 'dailyCollections', 'from', 'to'));
    }
}
