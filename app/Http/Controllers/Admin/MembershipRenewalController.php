<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenewMembershipRequest;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\Student;
use App\Models\StudySlot;
use App\Services\AuditService;
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
        MembershipRenewalService $service,
        AuditService $audit
    ): RedirectResponse {
        $oldMembership = $student->memberships()->where('status', 'active')->latest('expiry_date')->first();
        $oldValues = $oldMembership ? [
            'membership_id' => $oldMembership->id,
            'fee_plan_id' => $oldMembership->fee_plan_id,
            'study_slot_id' => $oldMembership->study_slot_id,
            'start_date' => $oldMembership->start_date?->toDateString(),
            'expiry_date' => $oldMembership->expiry_date?->toDateString(),
            'status' => $oldMembership->status,
        ] : [];

        $membership = $service->renew($student, $request->validated());

        $audit->log('membership.renewed', $membership, $oldValues, [
            'student_id' => $student->id,
            'membership_id' => $membership->id,
            'fee_plan_id' => $membership->fee_plan_id,
            'study_slot_id' => $membership->study_slot_id,
            'start_date' => $membership->start_date?->toDateString(),
            'expiry_date' => $membership->expiry_date?->toDateString(),
            'status' => $membership->status,
        ]);

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Membership renewed successfully until '.$membership->expiry_date->format('d M Y').'.');
    }
}
