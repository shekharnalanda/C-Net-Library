<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\QrCodeService;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $base = AdminBranchScope::apply(Student::query(), $request);
        $summary = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
            'blocked' => (clone $base)->where('status', 'blocked')->count(),
        ];
        $students = $base->with(['branch','activeMembership.studySlot','activeMembership.feePlan','seatAllocations.seat.studyHall'])
            ->withCount(['memberships','payments','attendances','seatAllocations','bookIssues'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($q) => $q->where('name','like',"%{$search}%")->orWhere('mobile','like',"%{$search}%")->orWhere('email','like',"%{$search}%")->orWhere('student_code','like',"%{$search}%"));
            })->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();
        $user = $request->user();
        $branches = Branch::query()->where('status', true)->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereKey($user->branch_id))->orderBy('name')->get();
        return view('admin.students.index', compact('students','summary','branches'));
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $this->validateStudent($request); AdminBranchScope::authorize($request, (int) $data['branch_id']);
        if (blank($data['student_code'] ?? null)) { do { $data['student_code']='STU-'.now()->format('ymd').'-'.strtoupper(Str::random(5)); } while (Student::query()->where('student_code',$data['student_code'])->exists()); }
        $data['joining_date']=$data['joining_date'] ?? now()->toDateString(); $data['status']=$data['status'] ?? 'active'; $data['qr_token']=(string) Str::uuid();
        $student=Student::create($data); $audit->log('student.created',$student,newValues:['student_code'=>$student->student_code,'branch_id'=>$student->branch_id],request:$request);
        return back()->with('success','Student added successfully: '.$student->student_code);
    }

    public function update(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        AdminBranchScope::authorize($request,$student->branch_id); $data=$this->validateStudent($request,$student); AdminBranchScope::authorize($request,(int)$data['branch_id']);
        $old=$student->only(['branch_id','student_code','name','mobile','email','status']); $student->update($data); $audit->log('student.updated',$student,oldValues:$old,newValues:$student->only(['branch_id','student_code','name','mobile','email','status']),request:$request);
        return back()->with('success','Student record updated successfully.');
    }

    public function destroy(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        AdminBranchScope::authorize($request,$student->branch_id);
        $history=$this->historyCounts($student); $used=array_filter($history,fn($count)=>$count>0);
        if($used!==[]){$details=collect($used)->map(fn($count,$label)=>$label.': '.$count)->implode(', ');throw ValidationException::withMessages(['student_delete'=>'Permanent delete is blocked because this student has operational/financial history ('.$details.'). Set the student to Inactive or Blocked, or use Super Admin Force Delete when a complete purge is intentionally required.']);}
        $this->deleteStudentRecord($request,$student,$audit,false);
        return redirect()->route('admin.students.index')->with('success','Student deleted permanently.');
    }

    public function forceDestroy(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()?->isGlobalAdmin(),403);
        AdminBranchScope::authorize($request,$student->branch_id);
        $data=$request->validate(['confirm_code'=>['required','string','max:100']]);
        if(! hash_equals((string)$student->student_code,trim((string)$data['confirm_code']))){throw ValidationException::withMessages(['confirm_code'=>'Force delete confirmation failed. Enter the exact Student ID / Code: '.$student->student_code]);}
        $this->deleteStudentRecord($request,$student,$audit,true);
        return redirect()->route('admin.students.index')->with('success','Student and all dependent operational records were permanently deleted by Super Admin.');
    }

    private function deleteStudentRecord(Request $request, Student $student, AuditService $audit, bool $force): void
    {
        $snapshot=$student->only(['id','student_code','name','branch_id','mobile','email']); $snapshot['force_delete']=$force; $snapshot['history']=$this->historyCounts($student);
        $photo=$student->photo; $linkedUser=$student->user;
        DB::transaction(function() use($student,$linkedUser,$force){
            if($force){$visited=[];$this->purgeDependentRows('students',(int)$student->id,$visited);}else{$student->savedJobs()->detach();}
            $student->delete();
            if($linkedUser && $linkedUser->role==='student'){$linkedUser->delete();}
        });
        if($photo) Storage::disk('public')->delete($photo);
        $audit->log($force?'student.force_deleted':'student.deleted',null,oldValues:$snapshot,request:$request);
    }

    private function purgeDependentRows(string $parentTable, int $parentId, array &$visited): void
    {
        $key=$parentTable.':'.$parentId; if(isset($visited[$key])) return; $visited[$key]=true;
        $schema=DB::getDatabaseName();
        $foreignKeys=DB::table('information_schema.KEY_COLUMN_USAGE')->select(['TABLE_NAME','COLUMN_NAME'])->where('TABLE_SCHEMA',$schema)->where('REFERENCED_TABLE_SCHEMA',$schema)->where('REFERENCED_TABLE_NAME',$parentTable)->where('REFERENCED_COLUMN_NAME','id')->get();
        foreach($foreignKeys as $fk){
            $childTable=(string)$fk->TABLE_NAME; $childColumn=(string)$fk->COLUMN_NAME;
            if($childTable==='audit_logs') continue;
            $query=DB::table($childTable)->where($childColumn,$parentId);
            if(Schema::hasColumn($childTable,'id')){$ids=$query->pluck('id')->map(fn($id)=>(int)$id)->all();foreach($ids as $childId){$this->purgeDependentRows($childTable,$childId,$visited);DB::table($childTable)->where('id',$childId)->delete();}}
            else{$query->delete();}
        }
    }

    private function historyCounts(Student $student): array
    {
        $history=['memberships'=>$student->memberships()->count(),'payments'=>$student->payments()->count(),'attendance'=>$student->attendances()->count(),'seat allocations'=>$student->seatAllocations()->count(),'book issues'=>$student->bookIssues()->count()];
        if(Schema::hasTable('locker_allocations')) $history['locker allocations']=DB::table('locker_allocations')->where('student_id',$student->id)->count();
        return $history;
    }

    private function validateStudent(Request $request, ?Student $student=null): array
    {
        return $request->validate(['branch_id'=>['required','integer','exists:branches,id'],'student_code'=>['nullable','string','max:60',Rule::unique('students','student_code')->ignore($student?->id)],'name'=>['required','string','max:150'],'father_name'=>['nullable','string','max:150'],'mother_name'=>['nullable','string','max:150'],'dob'=>['nullable','date','before_or_equal:today'],'gender'=>['nullable',Rule::in(['male','female','other'])],'mobile'=>['required','string','max:20'],'alternate_mobile'=>['nullable','string','max:20'],'email'=>['nullable','email','max:190'],'address'=>['nullable','string','max:1000'],'id_proof_type'=>['nullable','string','max:80'],'id_proof_no'=>['nullable','string','max:100'],'guardian_name'=>['nullable','string','max:150'],'guardian_mobile'=>['nullable','string','max:20'],'joining_date'=>['required','date'],'status'=>['required',Rule::in(['active','inactive','blocked'])]]);
    }

    public function show(Request $request, Student $student)
    {
        AdminBranchScope::authorize($request,$student->branch_id); $student->load(['branch','memberships.studySlot','memberships.feePlan','memberships.payments.adjustments','seatAllocations.seat.studyHall','payments.adjustments']);
        $activeMembership=$student->memberships->where('status','active')->sortByDesc('id')->first(); $allocation=$student->seatAllocations->where('status','active')->sortByDesc('id')->first(); $openAttendance=$student->attendances()->whereNull('check_out_at')->latest('id')->first();
        $grossPaid=$activeMembership?(float)$activeMembership->payments->whereIn('payment_status',['paid','partial'])->sum('amount'):0.0; $adjusted=$activeMembership?(float)$activeMembership->payments->sum(fn($payment)=>(float)$payment->adjustments->sum('amount')):0.0; $paid=max(0,$grossPaid-$adjusted); $due=$activeMembership?max(0,(float)$activeMembership->final_fee-$paid):0.0;
        return view('admin.students.show',compact('student','activeMembership','allocation','openAttendance','adjusted','paid','due'));
    }

    public function updatePhoto(Request $request, Student $student): RedirectResponse
    {
        AdminBranchScope::authorize($request,$student->branch_id); $validated=$request->validate(['photo'=>['required','image','mimes:jpg,jpeg,png,webp','max:2048','dimensions:min_width=150,min_height=150,max_width=4000,max_height=4000']]); $newPhoto=$validated['photo']->store('student-photos','public'); $oldPhoto=$student->photo; $student->update(['photo'=>$newPhoto]); if($oldPhoto&&$oldPhoto!==$newPhoto) Storage::disk('public')->delete($oldPhoto); return back()->with('success','Student photo updated successfully.');
    }

    public function idCard(Request $request, Student $student, QrCodeService $qrCode): Response
    {
        AdminBranchScope::authorize($request,$student->branch_id); $student->load(['branch','activeMembership.studySlot','activeMembership.feePlan']); if(blank($student->qr_token)) $student->forceFill(['qr_token'=>(string)Str::uuid()])->save(); $scanUrl=route('admin.attendance.qr',['token'=>$student->qr_token]); $qrDataUri=$qrCode->svgDataUri($scanUrl); $adminView=true;
        return response()->view('student.id-card',compact('student','qrDataUri','adminView'))->header('Cache-Control','private, no-store, no-cache, must-revalidate')->header('Pragma','no-cache')->header('X-Robots-Tag','noindex, nofollow, noarchive')->header('Referrer-Policy','no-referrer');
    }

    public function rotateQr(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        AdminBranchScope::authorize($request,$student->branch_id); $student->update(['qr_token'=>(string)Str::uuid()]); $audit->log(action:'student.qr_rotated',auditable:$student,newValues:['qr_token'=>'[ROTATED]'],request:$request); return back()->with('success','Student QR code has been rotated. The previous QR is no longer valid.');
    }
}
