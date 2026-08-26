<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudyHall;
use App\Models\StudySlot;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudySpaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branches = Branch::query()->where('status', true)->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get();
        $branchIds = $branches->pluck('id');
        $halls = StudyHall::query()->whereIn('branch_id', $branchIds)->withCount('seats')->with(['seats' => fn ($q) => $q->withCount('allocations')->orderBy('seat_no')])->orderBy('branch_id')->orderBy('name')->get();
        $slots = StudySlot::query()->whereIn('branch_id', $branchIds)->orderBy('branch_id')->orderBy('duration_hours')->orderBy('start_time')->get();
        $plans = FeePlan::query()->whereIn('branch_id', $branchIds)->with('studySlot')->orderBy('branch_id')->orderBy('monthly_fee')->get();
        $students = Student::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id','branch_id','student_code','name','mobile']);
        $allocations = SeatAllocation::query()->whereHas('student', fn ($q) => $q->whereIn('branch_id', $branchIds))->with(['student:id,branch_id,student_code,name,mobile','seat.studyHall:id,branch_id,name','studySlot:id,branch_id,name,start_time,end_time,duration_hours'])->latest('allocated_from')->latest('id')->paginate(30)->withQueryString();
        $summary = ['halls'=>$halls->count(),'seats'=>$halls->sum('seats_count'),'slots'=>$slots->where('status',true)->count(),'active_allocations'=>SeatAllocation::query()->whereHas('student',fn($q)=>$q->whereIn('branch_id',$branchIds))->whereIn('status',['active','reserved'])->whereDate('allocated_from','<=',today())->where(fn($q)=>$q->whereNull('allocated_to')->orWhereDate('allocated_to','>=',today()))->count()];
        return view('admin.study-space.index', compact('branches','halls','slots','plans','students','allocations','summary'));
    }

    public function storeHall(Request $request): RedirectResponse
    {
        $data=$request->validate(['branch_id'=>['required','exists:branches,id'],'name'=>['required','string','max:120'],'floor'=>['nullable','string','max:80'],'total_seats'=>['required','integer','min:1','max:1000'],'status'=>['nullable','boolean']]);
        AdminBranchScope::authorize($request,(int)$data['branch_id']); $data['status']=$request->boolean('status',true); StudyHall::create($data);
        return back()->with('success','Study hall created successfully. Add individual seat numbers below.');
    }

    public function updateHall(Request $request, StudyHall $hall): RedirectResponse
    {
        AdminBranchScope::authorize($request,$hall->branch_id);
        $data=$request->validate(['name'=>['required','string','max:120'],'floor'=>['nullable','string','max:80'],'total_seats'=>['required','integer','min:1','max:1000'],'status'=>['nullable','boolean']]);
        $data['status']=$request->boolean('status'); $hall->update($data);
        return back()->with('success','Study hall updated. Capacity is descriptive; actual seats are controlled by individual seat records.');
    }

    public function destroyHall(Request $request, StudyHall $hall): RedirectResponse
    {
        AdminBranchScope::authorize($request,$hall->branch_id);
        if ($hall->seats()->exists()) return back()->withErrors(['hall'=>'Hall cannot be deleted while seat records exist. Delete unused seats first, or disable the hall to preserve allocation history.']);
        $name=$hall->name; $hall->delete(); return back()->with('success',"Study hall {$name} deleted.");
    }

    public function storeSeat(Request $request): RedirectResponse
    {
        $data=$request->validate(['study_hall_id'=>['required','exists:study_halls,id'],'seat_no'=>['required','string','max:40'],'seat_type'=>['nullable','string','max:80'],'status'=>['nullable','boolean']]);
        $hall=StudyHall::findOrFail($data['study_hall_id']); AdminBranchScope::authorize($request,$hall->branch_id);
        $request->validate(['seat_no'=>[Rule::unique('seats')->where(fn($q)=>$q->where('study_hall_id',$hall->id))]]);
        $data['status']=$request->boolean('status',true); Seat::create($data);
        return back()->with('success','Seat added successfully.');
    }

    public function updateSeat(Request $request, Seat $seat): RedirectResponse
    {
        $seat->loadMissing('studyHall'); AdminBranchScope::authorize($request,$seat->studyHall->branch_id);
        $data=$request->validate(['seat_no'=>['required','string','max:40',Rule::unique('seats')->where(fn($q)=>$q->where('study_hall_id',$seat->study_hall_id))->ignore($seat->id)],'seat_type'=>['nullable','string','max:80'],'status'=>['nullable','boolean']]);
        $data['status']=$request->boolean('status'); $seat->update($data); return back()->with('success','Seat updated.');
    }

    public function destroySeat(Request $request, Seat $seat): RedirectResponse
    {
        $seat->loadMissing('studyHall'); AdminBranchScope::authorize($request,$seat->studyHall->branch_id);
        if ($seat->allocations()->exists()) return back()->withErrors(['seat'=>'Seat '.$seat->seat_no.' has allocation history and cannot be deleted. Disable it instead so historical records remain safe.']);
        $no=$seat->seat_no; $seat->delete(); return back()->with('success',"Seat {$no} deleted successfully.");
    }

    public function storeSlot(Request $request): RedirectResponse
    {
        $data=$request->validate(['branch_id'=>['required','exists:branches,id'],'name'=>['required','string','max:120'],'duration_hours'=>['required','integer','min:1','max:24'],'start_time'=>['nullable','date_format:H:i'],'end_time'=>['nullable','date_format:H:i'],'is_24x7'=>['nullable','boolean'],'is_flexible'=>['nullable','boolean'],'status'=>['nullable','boolean']]);
        AdminBranchScope::authorize($request,(int)$data['branch_id']); $data['is_24x7']=$request->boolean('is_24x7'); $data['is_flexible']=$request->boolean('is_flexible'); $data['status']=$request->boolean('status',true); StudySlot::create($data); return back()->with('success','Study slot created successfully.');
    }

    public function updateSlot(Request $request, StudySlot $slot): RedirectResponse
    {
        AdminBranchScope::authorize($request,$slot->branch_id); $data=$request->validate(['name'=>['required','string','max:120'],'duration_hours'=>['required','integer','min:1','max:24'],'start_time'=>['nullable','date_format:H:i'],'end_time'=>['nullable','date_format:H:i'],'is_24x7'=>['nullable','boolean'],'is_flexible'=>['nullable','boolean'],'status'=>['nullable','boolean']]);
        $data['is_24x7']=$request->boolean('is_24x7'); $data['is_flexible']=$request->boolean('is_flexible'); $data['status']=$request->boolean('status'); $slot->update($data); return back()->with('success','Study slot updated.');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data=$request->validate(['branch_id'=>['required','exists:branches,id'],'study_slot_id'=>['required','exists:study_slots,id'],'name'=>['required','string','max:120'],'monthly_fee'=>['required','numeric','min:0'],'quarterly_fee'=>['nullable','numeric','min:0'],'half_yearly_fee'=>['nullable','numeric','min:0'],'yearly_fee'=>['nullable','numeric','min:0'],'admission_fee'=>['nullable','numeric','min:0'],'registration_fee'=>['nullable','numeric','min:0'],'security_deposit'=>['nullable','numeric','min:0'],'late_fee'=>['nullable','numeric','min:0'],'validity_days'=>['required','integer','min:1','max:3660'],'status'=>['nullable','boolean']]);
        AdminBranchScope::authorize($request,(int)$data['branch_id']); $slot=StudySlot::findOrFail($data['study_slot_id']); abort_unless((int)$slot->branch_id===(int)$data['branch_id'],422,'Study slot does not belong to selected branch.'); $data['status']=$request->boolean('status',true); FeePlan::create($data); return back()->with('success','Fee plan created successfully.');
    }

    public function updatePlan(Request $request, FeePlan $plan): RedirectResponse
    {
        AdminBranchScope::authorize($request,$plan->branch_id); $data=$request->validate(['study_slot_id'=>['required','exists:study_slots,id'],'name'=>['required','string','max:120'],'monthly_fee'=>['required','numeric','min:0'],'quarterly_fee'=>['nullable','numeric','min:0'],'half_yearly_fee'=>['nullable','numeric','min:0'],'yearly_fee'=>['nullable','numeric','min:0'],'admission_fee'=>['nullable','numeric','min:0'],'registration_fee'=>['nullable','numeric','min:0'],'security_deposit'=>['nullable','numeric','min:0'],'late_fee'=>['nullable','numeric','min:0'],'validity_days'=>['required','integer','min:1','max:3660'],'status'=>['nullable','boolean']]);
        $slot=StudySlot::findOrFail($data['study_slot_id']); abort_unless((int)$slot->branch_id===(int)$plan->branch_id,422,'Study slot does not belong to plan branch.'); $data['status']=$request->boolean('status'); $plan->update($data); return back()->with('success','Fee plan updated.');
    }

    public function allocate(Request $request): RedirectResponse
    {
        $data=$request->validate(['student_id'=>['required','exists:students,id'],'seat_id'=>['required','exists:seats,id'],'study_slot_id'=>['required','exists:study_slots,id'],'allocated_from'=>['required','date'],'allocated_to'=>['nullable','date','after_or_equal:allocated_from'],'status'=>['required',Rule::in(['reserved','active'])],'remarks'=>['nullable','string','max:500']]);
        $student=Student::findOrFail($data['student_id']); $seat=Seat::with('studyHall')->findOrFail($data['seat_id']); $slot=StudySlot::findOrFail($data['study_slot_id']); AdminBranchScope::authorize($request,$student->branch_id);
        abort_unless((int)$seat->studyHall->branch_id===(int)$student->branch_id,422,'Seat is in another branch.'); abort_unless((int)$slot->branch_id===(int)$student->branch_id,422,'Study slot is in another branch.');
        $to=$data['allocated_to']??$data['allocated_from'];
        $conflict=SeatAllocation::query()->where('seat_id',$seat->id)->whereIn('status',['reserved','active'])->whereDate('allocated_from','<=',$to)->where(fn($q)=>$q->whereNull('allocated_to')->orWhereDate('allocated_to','>=',$data['allocated_from']))->when($slot->start_time&&$slot->end_time,function($q)use($slot){$q->where(function($time)use($slot){$time->whereNull('start_time')->orWhereNull('end_time')->orWhere(fn($overlap)=>$overlap->where('start_time','<',$slot->end_time)->where('end_time','>',$slot->start_time));});})->exists();
        if($conflict)return back()->withErrors(['seat_id'=>'Selected seat is already allocated for an overlapping period/slot.'])->withInput();
        $membership=$student->memberships()->where('study_slot_id',$slot->id)->whereIn('status',['active','pending'])->latest('id')->first();
        SeatAllocation::create(['student_id'=>$student->id,'student_membership_id'=>$membership?->id,'seat_id'=>$seat->id,'study_slot_id'=>$slot->id,'allocated_from'=>$data['allocated_from'],'allocated_to'=>$data['allocated_to']??null,'start_time'=>$slot->start_time,'end_time'=>$slot->end_time,'status'=>$data['status'],'remarks'=>$data['remarks']??null]); return back()->with('success','Seat allocated successfully.');
    }

    public function updateAllocation(Request $request, SeatAllocation $allocation): RedirectResponse
    {
        $allocation->loadMissing('student'); AdminBranchScope::authorize($request,$allocation->student->branch_id); $data=$request->validate(['status'=>['required',Rule::in(['reserved','active','completed','cancelled'])],'allocated_to'=>['nullable','date','after_or_equal:'.$allocation->allocated_from->toDateString()],'remarks'=>['nullable','string','max:500']]); $allocation->update($data); return back()->with('success','Seat allocation updated.');
    }
}
