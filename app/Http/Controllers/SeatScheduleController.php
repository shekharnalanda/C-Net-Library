<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SeatAllocation;
use App\Services\SeatScheduleService;
use App\Services\DailySeatWindow;
use App\Services\AuditService;
use App\Support\AdminBranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeatScheduleController extends Controller
{
    public function index(Request $request,SeatScheduleService $service)
    {
        $data=$request->validate(['date'=>['nullable','date_format:Y-m-d'],'branch_id'=>['nullable','integer','exists:branches,id'],'format'=>['nullable','in:csv']]);
        $admin=$request->routeIs('admin.*');
        $branchId=isset($data['branch_id'])?(int)$data['branch_id']:null;
        if($admin && !$request->user()->isGlobalAdmin()) {
            if($branchId) AdminBranchScope::authorize($request,$branchId);
            $branchId=(int)$request->user()->branch_id;
        }
        $date=$data['date']??today()->toDateString();
        $rows=$service->rows($date,$branchId);
        $headers=['Cache-Control'=>'private, no-store','X-Robots-Tag'=>'noindex, nofollow, noarchive'];
        if(($data['format']??null)==='csv') {
            return response()->streamDownload(function()use($rows,$date){
                $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
                fputcsv($out,['Date','Campus','Hall','Seat','Student / availability','Start (IST)','End (IST)','Status']);
                foreach($rows as $r) {
                    foreach($r['windows'] as $w) fputcsv($out,array_map($this->csvCell(...),[$date,$r['campus'],$r['hall'],$r['seat'],$w['name'],DailySeatWindow::label($w['start']),DailySeatWindow::label($w['end']),$w['status']]));
                    foreach($r['free'] as [$s,$e]) fputcsv($out,array_map($this->csvCell(...),[$date,$r['campus'],$r['hall'],$r['seat'],$r['enabled']?'Available':'Disabled',DailySeatWindow::label($s),DailySeatWindow::label($e),$r['enabled']?'free':'disabled']));
                }
                fclose($out);
            },'cnet-seat-report-'.$date.'.csv',$headers+['Content-Type'=>'text/csv; charset=UTF-8']);
        }
        $branches=Branch::when($admin && !$request->user()->isGlobalAdmin(),fn($q)=>$q->whereKey($branchId))->orderBy('name')->get(['id','name']);
        return response()->view('seat-report',compact('rows','date','branches','branchId','admin'),200,$headers);
    }

    private function csvCell($value): string
    {
        $value=(string)$value;
        return preg_match('/^[\s]*[=+@-]/u',$value)?"'".$value:$value;
    }

    public function destroy(Request $request,SeatAllocation $allocation)
    {
        AdminBranchScope::authorize($request,$allocation->student->branch_id);
        DB::transaction(function()use($request,$allocation){
            DB::table('seats')->where('id',$allocation->seat_id)->lockForUpdate()->first();
            $locked=SeatAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            AdminBranchScope::authorize($request,$locked->student->branch_id);
            app(AuditService::class)->log('seat.allocation_deleted',$locked,$locked->only(['seat_id','student_id','start_time','end_time','allocated_from','allocated_to']),[],$request);
            $locked->delete();
        },3);
        return back()->with('success','Seat allocation removed. Student and payment history retained.');
    }
}
