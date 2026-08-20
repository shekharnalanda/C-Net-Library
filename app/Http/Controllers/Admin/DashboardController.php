<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudentMembership;

class DashboardController extends Controller
{
    public function index()
    {
        $todayGross = (float) Payment::query()
            ->whereDate('payment_date', today())
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount');

        $todayAdjustments = (float) PaymentAdjustment::query()
            ->whereDate('created_at', today())
            ->sum('amount');

        $todayNetCollection = max(0, $todayGross - $todayAdjustments);
        $todayExpenses = (float) Expense::query()
            ->whereDate('expense_date', today())
            ->sum('amount');

        $data = [
            'active_students' => Student::query()->where('status', 'active')->count(),
            'total_seats' => Seat::query()->where('status', true)->count(),
            'today_gross_collection' => $todayGross,
            'today_adjustments' => $todayAdjustments,
            'today_collection' => $todayNetCollection,
            'today_expenses' => $todayExpenses,
            'today_cash_position' => $todayNetCollection - $todayExpenses,
            'pending_admissions' => Admission::query()
                ->whereIn('status', ['new', 'under_review'])
                ->count(),
            'renewals_due' => StudentMembership::query()
                ->where('status', 'active')
                ->whereBetween('expiry_date', [today(), today()->addDays(7)])
                ->count(),
        ];

        return view('admin.dashboard', compact('data'));
    }
}
