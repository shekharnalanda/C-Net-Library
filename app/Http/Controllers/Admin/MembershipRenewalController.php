<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenewMembershipRequest;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudySlot;
use App\Services\MembershipRenewalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MembershipRenewalController extends Controller
{
    public function create(Student $student): View
    {
        $student->load([
            'branch',
            'activeMembership.feePlan',
            'activeMembership.studySlot',
            'seatAllocations.seat.studyHall',
        ]);

        $feePlans = FeePlan::query()
            ->where('branch_id', $student->branch_id)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $studySlots = StudySlot::query()
            ->where('branch_id', $student->branch_id)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $seats = Seat::query()
            ->whereHas('studyHall', fn ($query) => $query->where('branch_id', $student->branch_id))
            ->where('status', true)
            ->with('studyHall')
            ->orderBy('seat_no')
            ->get();

        return view('admin.students.renew', compact('student', 'feePlans', 'studySlots', 'seats'));
    }

    public function store(
        RenewMembershipRequest $request,
        Student $student,
        MembershipRenewalService $service
    ): RedirectResponse {
        $membership = $service->renew($student, $request->validated());

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Membership renewed successfully until '.$membership->expiry_date->format('d M Y').'.');
    }
}
