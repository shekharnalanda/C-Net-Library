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
use App\Models\ExpenseAdjustment;
use App\Models\LibraryChargePayment;
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
        $membershipIncome = max(0, $grossCollection - $adjustments);

        $libraryIncomeQuery = LibraryChargePayment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query) => $query->whereHas('bookIssue.student', fn ($student) => $student->where('branch_id', $branchId)));
        $libraryFineIncome = (float) (clone $libraryIncomeQuery)->where('charge_type', 'fine')->sum('amount');
        $libraryLossIncome = (float) (clone $libraryIncomeQuery)->where('charge_type', 'loss')->sum('amount');
        $libraryIncome = $libraryFineIncome + $libraryLossIncome;
        $totalIncome = $membershipIncome + $libraryIncome;

        $expenseQuery = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $grossExpenses = (float) (clone $expenseQuery)->sum('amount');
        $expenseAdjustmentQuery = ExpenseAdjustment::query()
            ->whereHas('expense', fn ($query) => $query
                ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
                ->when($branchId, fn ($expense) => $expense->where('branch_id', $branchId)));
        $expenseAdjustments = (float) (clone $expenseAdjustmentQuery)->sum('amount');
        $expenses = max(0, $grossExpenses - $expenseAdjustments);
        $closingBalance = $totalIncome - $expenses;

        $membershipQuery = StudentMembership::query()
            ->where('status', 'active')
            ->when($branchId, fn ($query) => $query->whereHas('student', fn ($student) => $student->where('branch_id', $branchId)));

        $activeMembershipCount = (clone $membershipQuery)->count();

        $paidByMembership = Payment::query()
            ->selectRaw('student_membership_id, SUM(amount) as gross_paid')
            ->whereIn('payment_status', ['paid', 'partial'])
            ->groupBy('student_membership_id');

        $adjustedByMembership = PaymentAdjustment::query()
            ->join('payments', 'payments.id', '=', 'payment_adjustments.payment_id')
            ->selectRaw('payments.student_membership_id, SUM(payment_adjustments.amount) as adjusted_amount')
            ->groupBy('payments.student_membership_id');

        $totalDue = (float) (clone $membershipQuery)
            ->leftJoinSub($paidByMembership, 'paid_totals', function ($join) {
                $join->on('student_memberships.id', '=', 'paid_totals.student_membership_id');
            })
            ->leftJoinSub($adjustedByMembership, 'adjustment_totals', function ($join) {
                $join->on('student_memberships.id', '=', 'adjustment_totals.student_membership_id');
            })
            ->selectRaw('SUM(GREATEST(0, student_memberships.final_fee - GREATEST(0, COALESCE(paid_totals.gross_paid, 0) - COALESCE(adjustment_totals.adjusted_amount, 0)))) as total_due')
            ->value('total_due');

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
            'active_memberships' => $activeMembershipCount,
            'collection' => $membershipIncome,
            'membership_income' => $membershipIncome,
            'gross_collection' => $grossCollection,
            'adjustments' => $adjustments,
            'library_income' => $libraryIncome,
            'library_fine_income' => $libraryFineIncome,
            'library_loss_income' => $libraryLossIncome,
            'total_income' => $totalIncome,
            'gross_expenses' => $grossExpenses,
            'expense_adjustments' => $expenseAdjustments,
            'expenses' => $expenses,
            'closing_balance' => $closingBalance,
            'due' => $totalDue,
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

        $incomeCategories = collect([
            (object) ['category' => 'Membership', 'total' => $membershipIncome],
            (object) ['category' => 'Library Fine', 'total' => $libraryFineIncome],
            (object) ['category' => 'Lost Book Recovery', 'total' => $libraryLossIncome],
        ])->filter(fn ($row) => (float) $row->total > 0)->values();

        $grossDaily = (clone $payments)
            ->selectRaw('payment_date, SUM(amount) as total')
            ->groupBy('payment_date')
            ->pluck('total', 'payment_date');

        $dailyAdjustments = (clone $adjustmentQuery)
            ->selectRaw('DATE(created_at) as adjustment_day, SUM(amount) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'adjustment_day');

        $dailyLibraryIncome = (clone $libraryIncomeQuery)
            ->selectRaw('payment_date, SUM(amount) as total')
            ->groupBy('payment_date')
            ->pluck('total', 'payment_date');

        $dailyGrossExpenses = (clone $expenseQuery)
            ->selectRaw('expense_date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->pluck('total', 'expense_date');

        $dailyExpenseAdjustments = (clone $expenseAdjustmentQuery)
            ->join('expenses', 'expenses.id', '=', 'expense_adjustments.expense_id')
            ->selectRaw('expenses.expense_date, SUM(expense_adjustments.amount) as total')
            ->groupBy('expenses.expense_date')
            ->pluck('total', 'expenses.expense_date');

        $dailyCollections = collect(array_unique(array_merge(
            $grossDaily->keys()->all(),
            $dailyAdjustments->keys()->all(),
            $dailyLibraryIncome->keys()->all(),
            $dailyGrossExpenses->keys()->all(),
            $dailyExpenseAdjustments->keys()->all()
        )))
            ->sort()
            ->values()
            ->map(function ($date) use ($grossDaily, $dailyAdjustments, $dailyLibraryIncome, $dailyGrossExpenses, $dailyExpenseAdjustments) {
                $gross = (float) ($grossDaily[$date] ?? 0);
                $adjustment = (float) ($dailyAdjustments[$date] ?? 0);
                $membershipNet = max(0, $gross - $adjustment);
                $library = (float) ($dailyLibraryIncome[$date] ?? 0);
                $income = $membershipNet + $library;
                $grossExpense = (float) ($dailyGrossExpenses[$date] ?? 0);
                $expenseAdjustment = (float) ($dailyExpenseAdjustments[$date] ?? 0);
                $expense = max(0, $grossExpense - $expenseAdjustment);

                return (object) [
                    'payment_date' => $date,
                    'total' => $income,
                    'membership_total' => $membershipNet,
                    'library_total' => $library,
                    'gross_total' => $gross,
                    'adjustment_total' => $adjustment,
                    'expense_total' => $expense,
                    'cash_balance' => $income - $expense,
                ];
            });

        return view('admin.reports.index', [
            'metrics' => $metrics,
            'incomeCategories' => $incomeCategories,
            'dailyCollections' => $dailyCollections,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
        ]);
    }
}
