<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\AdminBranchScope;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminBranchScope::apply(Student::query(), $request);

        $students = $query
            ->with([
                'branch',
                'activeMembership.studySlot',
                'activeMembership.feePlan',
                'seatAllocations.seat.studyHall',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('student_code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', compact('students'));
    }

    public function show(Student $student)
    {
        $student->load([
            'branch',
            'memberships.studySlot',
            'memberships.feePlan',
            'memberships.payments.adjustments',
            'seatAllocations.seat.studyHall',
            'payments.adjustments',
        ]);

        return view('admin.students.show', compact('student'));
    }
}
