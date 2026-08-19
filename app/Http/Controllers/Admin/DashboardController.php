<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudentMembership;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'active_students' => Student::query()->where('status', 'active')->count(),
            'total_seats' => Seat::query()->where('status', true)->count(),
            'today_collection' => Payment::query()
                ->whereDate('payment_date', today())
                ->whereIn('payment_status', ['paid', 'partial'])
                ->sum('amount'),
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
