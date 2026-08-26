<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Locker;
use App\Models\LockerAllocation;
use App\Models\LockerPayment;
use App\Models\Student;
use App\Models\StudyHall;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LockerController extends Controller
{
    public function index(Request $request): View
    {
        $user=$request->user();
        $branches=Branch::query()->where('status',true)->when(!$user->isGlobalAdmin(),fn($q)=>$q->whereKey($user->branch_id))->orderBy('name')->get();
        $allowedIds=$branches->pluck('id');
        $selectedBranchId=$request->integer('branch_id') ?: null;
        if(!$user->isGlobalAdmin()) $selectedBranchId=(int)$user->branch_id;
        if($selectedBranchId && !$allowedIds->contains($selectedBranchId)) abort(403,'You cannot manage this branch.');
        $scopeIds=$selectedBranchId ? collect([$selectedBranchId]) : $allowedIds;
        $halls=StudyHall::query()->whereIn('branch_id',$scopeIds)->where('status',true)->orderBy('branch_id')->orderBy('name')->get(['id','branch_id','name','floor']);
        $selectedHallId=$request->integer('study_hall_id') ?: null;
        if($selectedHallId && !$halls->pluck('id')->contains($selectedHallId)) abort(403,'You cannot manage this hall.');

        $lockers=Locker::query()->whereIn('branch_id',$scopeIds)->when($selectedHallId,fn($q)=>$q->where('study_hall_id',$selectedHallId))->with(['branch:id,name','studyHall:id,branch_id,name,floor'])
            ->withCount('allocations')
            ->withCount(['allocations as active_allocations_count'=>fn($q)=>$q->whereIn('status',['reserved','active'])->whereDate('allocated_from','<=',today())->where(fn($d)=>$d->whereNull('allocated_to')->orWhereDate('allocated_to','>=',today()))])
            ->orderBy('branch_id')->orderBy('study_hall_id')->orderBy('locker_no')->get();
        $students=Student::query()->whereIn('branch_id',$scopeIds)->where('status','active')->orderBy('name')->get(['id','branch_id','student_code','name','mobile']);
        $allocations=LockerAllocation::query()->whereHas('student',fn($q)=>$q->whereIn('branch_id',$scopeIds))->with(['locker:id,branch_id,study_hall_id,locker_no,location,monthly_charge','locker.studyHall:id,name','student:id,branch_id,student_code,name,mobile'])->withSum(['payments as total_paid'=>fn($q)=>$q->where('status','paid')],'amount')->latest('allocated_from')->latest('id')->paginate(30)->withQueryString();
        $payments=LockerPayment::query()->whereHas('student',fn($q)=>$q->whereIn('branch_id',$scopeIds))->with(['student:id,student_code,name','allocation.locker:id,locker_no'])->latest('payment_date')->latest('id')->limit(50)->get();
        $summary=['total'=>$lockers->count(),'active'=>$lockers->where('status',true)->count(),'occupied'=>$lockers->where('active_allocations_count','>',0)->count(),'available'=>$lockers->where('status',true)->where('active_allocations_count',0)->count(),'due'=>LockerAllocation::query()->whereHas('student',fn($q)=>$q->whereIn('branch_id',$scopeIds))->where('status','active')->where(fn($q)=>$q->whereNull('paid_through')->orWhereDate('paid_through','<',today()))->count(),'month_collection'=>(float)LockerPayment::query()->whereHas('student',fn($q)=>$q->whereIn('branch_id',$scopeIds))->where('status','paid')->whereBetween('payment_date',[now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString()])->sum('amount')];
        return view('admin.lockers.index',compact('branches','halls','lockers','students','allocations','payments','summary','selectedBranchId','selectedHallId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate(['branch_id'=>['required','exists:branches,id'],'study_hall_id'=>['nullable','exists:study_halls,id'],'locker_no'=>['required','string','max:50'],'location'=>['nullable','string','max:120'],'monthly_charge'=>['required','numeric','min:0'],'status'=>['nullable','boolean']]);
        AdminBranchScope::authorize($request,(int)$data['branch_id']); $this->assertHallBelongsToBranch($data['study_hall_id']??null,(int)$data['branch_id']);
        $request->validate(['locker_no'=>[Rule::unique('lockers')->where(fn($q)=>$q->where('branch_id',$data['branch_id']))]]); $data['status']=$request->boolean('status',true); Locker::create($data);
        return back()->with('success','Locker created successfully.');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $data=$request->validate(['branch_id'=>['required','exists:branches,id'],'study_hall_id'=>['nullable','exists:study_halls,id'],'prefix'=>['nullable','string','max:20','regex:/^[A-Za-z0-9_-]*$/'],'start_no'=>['required','integer','min:1','max:9999'],'count'=>['required','integer','min:1','max:500'],'padding'=>['required','integer','min:1','max:4'],'location'=>['nullable','string','max:120'],'monthly_charge'=>['required','numeric','min:0']]);
        AdminBranchScope::authorize($request,(int)$data['branch_id']); $this->assertHallBelongsToBranch($data['study_hall_id']??null,(int)$data['branch_id']);
        $prefix=$data['prefix']??''; $numbers=[]; for($i=0;$i<(int)$data['count'];$i++)$numbers[]=$prefix.str_pad((string)((int)$data['start_no']+$i),(int)$data['padding'],'0',STR_PAD_LEFT);
        $existing=Locker::query()->where('branch_id',$data['branch_id'])->whereIn('locker_no',$numbers)->pluck('locker_no')->all();
        if($existing)return back()->withErrors(['bulk_lockers'=>'Bulk generation stopped. These locker numbers already exist: '.implode(', ',array_slice($existing,0,12)).(count($existing)>12?' ...':'')])->withInput();
        DB::transaction(function()use($data,$numbers){foreach($numbers as $no)Locker::create(['branch_id'=>$data['branch_id'],'study_hall_id'=>$data['study_hall_id']??null,'locker_no'=>$no,'location'=>$data['location']??null,'monthly_charge'=>$data['monthly_charge'],'status'=>true]);});
        return back()->with('success',count($numbers).' lockers created successfully.');
    }

    public function update(Request $request, Locker $locker): RedirectResponse
    {
        AdminBranchScope::authorize($request,$locker->branch_id);
        $data=$request->validate(['study_hall_id'=>['nullable','exists:study_halls,id'],'locker_no'=>['required','string','max:50',Rule::unique('lockers')->where(fn($q)=>$q->where('branch_id',$locker->branch_id))->ignore($locker->id)],'location'=>['nullable','string','max:120'],'monthly_charge'=>['required','numeric','min:0'],'status'=>['nullable','boolean']]);
        $this->assertHallBelongsToBranch($data['study_hall_id']??null,(int)$locker->branch_id); $data['status']=$request->boolean('status'); $locker->update($data);
        return back()->with('success','Locker updated.');
    }

    public function destroy(Request $request, Locker $locker): RedirectResponse
    {
        AdminBranchScope::authorize($request,$locker->branch_id); if($locker->allocations()->exists())return back()->withErrors(['locker'=>'This locker has allocation/history and cannot be deleted. Disable it instead so billing and audit history remain safe.']); $no=$locker->locker_no; $locker->delete(); return back()->with('success',"Locker {$no} deleted successfully.");
    }

    public function allocate(Request $request): RedirectResponse
    {
        $data=$request->validate(['locker_id'=>['required','exists:lockers,id'],'student_id'=>['required','exists:students,id'],'allocated_from'=>['required','date'],'allocated_to'=>['nullable','date','after_or_equal:allocated_from'],'status'=>['required',Rule::in(['reserved','active'])],'remarks'=>['nullable','string','max:500']]);
        $locker=Locker::findOrFail($data['locker_id']); $student=Student::findOrFail($data['student_id']); AdminBranchScope::authorize($request,$student->branch_id); abort_unless((int)$locker->branch_id===(int)$student->branch_id,422,'Locker is in another branch.'); abort_unless($locker->status,422,'Locker is disabled.');
        $to=$data['allocated_to']??$data['allocated_from']; $conflict=LockerAllocation::query()->where('locker_id',$locker->id)->whereIn('status',['reserved','active'])->whereDate('allocated_from','<=',$to)->where(fn($q)=>$q->whereNull('allocated_to')->orWhereDate('allocated_to','>=',$data['allocated_from']))->exists(); if($conflict)return back()->withErrors(['locker_id'=>'Selected locker is already allocated for this period.'])->withInput();
        LockerAllocation::create(['locker_id'=>$locker->id,'student_id'=>$student->id,'allocated_from'=>$data['allocated_from'],'allocated_to'=>$data['allocated_to']??null,'monthly_charge'=>$locker->monthly_charge,'paid_through'=>null,'status'=>$data['status'],'remarks'=>$data['remarks']??null]); return back()->with('success','Locker allocated successfully. Monthly charge has been captured on the allocation.');
    }

    public function collectPayment(Request $request, LockerAllocation $allocation): RedirectResponse
    {
        $allocation->loadMissing(['student','locker']); AdminBranchScope::authorize($request,$allocation->student->branch_id); $data=$request->validate(['billing_months'=>['required','integer','min:1','max:24'],'payment_date'=>['required','date'],'payment_mode'=>['required',Rule::in(['cash','upi','card','bank','other'])],'transaction_ref'=>['nullable','string','max:120'],'remarks'=>['nullable','string','max:500']]);
        DB::transaction(function()use($allocation,$data,$request){$allocation=LockerAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();$months=(int)$data['billing_months'];$monthlyCharge=(float)$allocation->monthly_charge;$amount=$monthlyCharge*$months;$periodFrom=$allocation->paid_through?$allocation->paid_through->copy()->addDay():$allocation->allocated_from->copy();if($periodFrom->lt($allocation->allocated_from))$periodFrom=$allocation->allocated_from->copy();$periodTo=$periodFrom->copy()->addMonthsNoOverflow($months)->subDay();if($allocation->allocated_to&&$periodTo->gt($allocation->allocated_to))$periodTo=$allocation->allocated_to->copy();$receipt='LKR-'.now()->format('YmdHis').'-'.str_pad((string)$allocation->id,4,'0',STR_PAD_LEFT);LockerPayment::create(['locker_allocation_id'=>$allocation->id,'student_id'=>$allocation->student_id,'receipt_no'=>$receipt,'billing_months'=>$months,'monthly_charge'=>$monthlyCharge,'amount'=>$amount,'period_from'=>$periodFrom->toDateString(),'period_to'=>$periodTo->toDateString(),'payment_date'=>$data['payment_date'],'payment_mode'=>$data['payment_mode'],'transaction_ref'=>$data['transaction_ref']??null,'received_by'=>$request->user()->id,'status'=>'paid','remarks'=>$data['remarks']??null]);$allocation->update(['paid_through'=>$periodTo->toDateString()]);}); return back()->with('success','Locker payment collected and paid-through date updated.');
    }

    public function updateAllocation(Request $request, LockerAllocation $allocation): RedirectResponse
    {
        $allocation->loadMissing('student'); AdminBranchScope::authorize($request,$allocation->student->branch_id); $data=$request->validate(['status'=>['required',Rule::in(['reserved','active','completed','cancelled'])],'allocated_to'=>['nullable','date','after_or_equal:'.$allocation->allocated_from->toDateString()],'remarks'=>['nullable','string','max:500']]); $allocation->update($data); return back()->with('success','Locker allocation updated.');
    }

    private function assertHallBelongsToBranch(?int $hallId,int $branchId): void
    {
        if(!$hallId)return; $hall=StudyHall::findOrFail($hallId); abort_unless((int)$hall->branch_id===$branchId,422,'Selected hall does not belong to selected branch.');
    }
}
