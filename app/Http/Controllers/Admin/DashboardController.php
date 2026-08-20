<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Expense;
use App\Models\ExpenseAdjustment;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Services\AdminBranchScope;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, AdminBranchScope $branchScope)
    {
        $user = $request->user();

        $payments = Payment::query()
            ->whereDate('payment_date', today())
            ->whereIn('payment_status', ['paid', 'partial'])
            ->when(! $user->isGlobalAdmin(), fn ($query) => $query->whereHas('student', fn ($student) => $student->where('branch_id', $user->branch_id)));

        $todayGross = (float) (clone $payments)->sum('amount');

        $todayAdjustments = (float) PaymentAdjustment::query()
            ->whereDate('created_at', today())
            ->when(! $user->isGlobalAdmin(), fn ($query) => $query->whereHas('payment.student', fn ($student) => $student->where('branch_id', $user->branch_id)))
            ->sum('amount');

        $todayNetCollection = max(0, $todayGross - $todayAdjustments);

        $todayExpenseQuery = $branchScope->apply(Expense::query(), $user)
            ->whereDate('expense_date', today());
        $todayGrossExpenses = (float) (clone $todayExpenseQuery)->sum('amount');

        $todayExpenseAdjustments = (float) ExpenseAdjustment::query()
            ->whereDate('created_at', today())
            ->when(! $user->isGlobalAdmin(), fn ($query) => $query->whereHas('expense', fn ($expense) => $expense->where('branch_id', $user->branch_id)))
            ->sum('amount');

        $todayNetExpenses = max(0, $todayGrossExpenses - $todayExpenseAdjustments);

        $data = [
            'active_students' => $branchScope->apply(Student::query(), $user)->where('status', 'active')->count(),
            'total_seats' => $branchScope->apply(Seat::query(), $user)->where('status', true)->count(),
            'today_gross_collection' => $todayGross,
            'today_adjustments' => $todayAdjustments,
            'today_collection' => $todayNetCollection,
            'today_gross_expenses' => $todayGrossExpenses,
            'today_expense_adjustments' => $todayExpenseAdjustments,
            'today_expenses' => $todayNetExpenses,
            'today_cash_position' => $todayNetCollection - $todayNetExpenses,
            'pending_admissions' => $branchScope->apply(Admission::query(), $user)
                ->whereIn('status', ['new', 'under_review', 'pending'])
                ->count(),
            'renewals_due' => StudentMembership::query()
                ->where('status', 'active')
                ->when(! $user->isGlobalAdmin(), fn ($query) => $query->whereHas('student', fn ($student) => $student->where('branch_id', $user->branch_id)))
                ->whereBetween('expiry_date', [today(), today()->addDays(7)])
                ->count(),
        ];

        return view('admin.dashboard', compact('data'));
    }
}
