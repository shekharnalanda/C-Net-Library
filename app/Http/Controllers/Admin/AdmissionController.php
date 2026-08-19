<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveAdmissionRequest;
use App\Models\Admission;
use App\Models\FeePlan;
use App\Models\StudySlot;
use App\Services\AdmissionApprovalService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    public function index(): View
    {
        $admissions = Admission::with(['branch', 'studySlot', 'feePlan'])
            ->latest()
            ->paginate(20);

        return view('admin.admissions.index', compact('admissions'));
    }

    public function show(Admission $admission): View
    {
        $admission->load(['branch', 'studySlot', 'feePlan']);

        $studySlots = StudySlot::query()
            ->where('branch_id', $admission->branch_id)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $feePlans = FeePlan::query()
            ->where('branch_id', $admission->branch_id)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return view('admin.admissions.show', compact('admission', 'studySlots', 'feePlans'));
    }

    public function approve(
        ApproveAdmissionRequest $request,
        Admission $admission,
        AdmissionApprovalService $approvalService,
        AuditService $auditService
    ): RedirectResponse {
        $oldValues = $admission->only(['status', 'fee_plan_id', 'study_slot_id', 'remarks']);
        $student = $approvalService->approve($admission, $request->validated());
        $admission->refresh();

        $auditService->log(
            action: 'admission.approved',
            auditable: $admission,
            oldValues: $oldValues,
            newValues: array_merge(
                $admission->only(['status', 'fee_plan_id', 'study_slot_id', 'remarks']),
                ['student_id' => $student->id, 'student_code' => $student->student_code]
            ),
            request: $request,
        );

        return redirect()
            ->route('admin.admissions.show', $admission)
            ->with('success', "Admission approved. Student ID: {$student->student_code}");
    }
}
