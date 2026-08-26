<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveAdmissionRequest;
use App\Models\Admission;
use App\Models\FeePlan;
use App\Models\StudySlot;
use App\Services\AdmissionApprovalService;
use App\Services\AuditService;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    public function index(Request $request): View
    {
        $baseQuery = AdminBranchScope::apply(Admission::query(), $request);
        $summary = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->whereIn('status', ['new','pending','under_review'])->count(),
            'approved' => (clone $baseQuery)->whereIn('status', ['approved','converted'])->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];
        $admissions = $baseQuery->with(['branch','studySlot','feePlan'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search=trim($request->string('search')->toString());
                $query->where(fn($q)=>$q->where('application_no','like',"%{$search}%")->orWhere('name','like',"%{$search}%")->orWhere('mobile','like',"%{$search}%")->orWhere('email','like',"%{$search}%"));
            })
            ->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')->toString()))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.admissions.index',compact('admissions','summary'));
    }

    public function show(Request $request, Admission $admission): View
    {
        AdminBranchScope::authorize($request,$admission->branch_id);
        $admission->load(['branch','studySlot','feePlan']);
        $studySlots=StudySlot::query()->where('branch_id',$admission->branch_id)->where('status',true)->orderBy('name')->get();
        $feePlans=FeePlan::query()->where('branch_id',$admission->branch_id)->where('status',true)->orderBy('name')->get();
        return view('admin.admissions.show',compact('admission','studySlots','feePlans'));
    }

    public function review(Request $request, Admission $admission, AuditService $audit): RedirectResponse
    {
        AdminBranchScope::authorize($request,$admission->branch_id);
        if (in_array($admission->status,['converted','approved'],true)) {
            throw ValidationException::withMessages(['status'=>'Approved/converted admission cannot be moved back into review.']);
        }
        $data=$request->validate([
            'status'=>['required',Rule::in(['new','pending','under_review','rejected'])],
            'remarks'=>['nullable','string','max:3000'],
        ]);
        if ($data['status']==='rejected' && trim((string)($data['remarks']??''))==='') {
            throw ValidationException::withMessages(['remarks'=>'A rejection reason is required.']);
        }
        $old=$admission->only(['status','remarks']);
        $admission->update($data);
        $audit->log('admission.reviewed',$admission,$old,$admission->fresh()->only(['status','remarks']),$request);
        return back()->with('success','Admission review status updated.');
    }

    public function approve(ApproveAdmissionRequest $request, Admission $admission, AdmissionApprovalService $approvalService, AuditService $auditService): RedirectResponse
    {
        AdminBranchScope::authorize($request,$admission->branch_id);
        if ($admission->status==='rejected') {
            throw ValidationException::withMessages(['status'=>'Rejected admission cannot be approved until it is moved back to review.']);
        }
        $oldValues=$admission->only(['status','fee_plan_id','study_slot_id','remarks']);
        $student=$approvalService->approve($admission,$request->validated());
        $admission->refresh();
        $auditService->log(action:'admission.approved',auditable:$admission,oldValues:$oldValues,newValues:array_merge($admission->only(['status','fee_plan_id','study_slot_id','remarks']),['student_id'=>$student->id,'student_code'=>$student->student_code]),request:$request);
        $plainToken=$student->getAttribute('portal_activation_plain_token');
        $activationUrl=$plainToken?route('student.activate',['token'=>$plainToken]):null;
        $message="Admission approved. Student ID: {$student->student_code}.";
        if($activationUrl)$message.=" Portal setup link (valid 7 days): {$activationUrl}";
        return redirect()->route('admin.admissions.show',$admission)->with('success',$message);
    }
}
