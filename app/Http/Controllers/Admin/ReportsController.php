<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Branch;
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

        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        if ($branchId) {
            Branch::query()->whereKey($branchId)->where('status', true)->firstOrFail();
        }

        $payments = Payment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('payment_status', ['paid', 'partial'])
            ->when($branchId, fn ($query) => $query->whereHas('student', fn ($student) => $student->where('branch_id', $branchId)));

        $grossCollection = (float) (clone $payments)->sum('amount');

        $adjustmentQuery = PaymentAdjustment::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->whereHas('payment.student', fn ($student) => $student->where('branch_id', $branchId)));
        $adjustments = (float) (clone $adjustmentQuery)->sum('amount');

        $collection = max(0, $grossCollection - $adjustments);

        $expenseQuery = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $expenses = (float) (clone $expenseQuery)->sum('amount');
        $closingBalance = $collection - $expenses;

        $activeMemberships = StudentMembership::query()
            ->where('status', 'active')
            ->when($branchId, fn ($query) => $query->whereHas('student', fn ($student) => $student->where('branch_id', $branchId)))
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

        $seatQuery = Seat::query()
            ->where('status', true)
            ->when($branchId, fn ($query) => $query->whereHas('studyHall', fn ($hall) => $hall->where('branch_id', $branchId)));
        $totalSeats = (clone $seatQuery)->count();

        $occupiedSeats = SeatAllocation::query()
            ->where('status', 'active')
            ->whereDate('allocated_from', '<=', today())
            ->where(function ($query) {
                $query->whereNull('allocated_to')->orWhereDate('allocated_to', '>=', today());
            })
            ->when($branchId, fn ($query) => $query->whereHas('seat.studyHall', fn ($hall) => $hall->where('branch_id', $branchId)))
            ->distinct('seat_id')
            ->count('seat_id');

        $attendanceMinutes = Attendance::query()
            ->whereBetween('check_in_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('study_minutes');

        $admissions = Admission::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $admissionCount = (clone $admissions)->count();
        $convertedAdmissions = (clone $admissions)->where('status', 'converted')->count();

        $enquiries = Enquiry::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $enquiryCount = (clone $enquiries)->count();
        $convertedEnquiries = (clone $enquiries)->where('status', 'converted')->count();

        $students = Student::query()
            ->where('status', 'active')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $bookCopies = BookCopy::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $bookIssues = BookIssue::query()
            ->when($branchId, fn ($query) => $query->whereHas('student', fn ($student) => $student->where('branch_id', $branchId)));

        $metrics = [
            'students' => (clone $students)->count(),
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
            'books_available' => (clone $bookCopies)->where('status', 'available')->count(),
            'books_issued' => (clone $bookIssues)->whereNull('returned_at')->count(),
            'overdue_books' => (clone $bookIssues)->whereNull('returned_at')->whereDate('due_at', '<', today())->count(),
        ];

        $grossDaily = (clone $payments)
            ->selectRaw('payment_date, SUM(amount) as total')
            ->groupBy('payment_date')
            ->pluck('total', 'payment_date');

        $dailyAdjustments = (clone $adjustmentQuery)
            ->selectRaw('DATE(created_at) as adjustment_day, SUM(amount) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'adjustment_day');

        $dailyExpenses = (clone $expenseQuery)
            ->selectRaw('expense_date, SUM(amount) as total')
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

        return view('admin.reports.index', [
            'metrics' => $metrics,
            'dailyCollections' => $dailyCollections,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
        ]);
    }
}
