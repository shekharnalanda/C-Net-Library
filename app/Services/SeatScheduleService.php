<?php

namespace App\Services;

use App\Models\Seat;

class SeatScheduleService
{
    public function rows(string $date, ?int $branchId=null): array
    {
        app(SeatFeeReleaseService::class)->releaseDue();
        $previous=\Carbon\Carbon::parse($date)->subDay()->toDateString();
        $seats=Seat::with(['studyHall.branch','allocations'=>fn($q)=>$q
            ->whereIn('status',['active','reserved'])->whereDate('allocated_from','<=',$date)
            ->where(fn($q)=>$q->whereNull('allocated_to')->orWhereDate('allocated_to','>=',$previous))
            ->with('student:id,name')])
            ->when($branchId,fn($q)=>$q->whereHas('studyHall',fn($q)=>$q->where('branch_id',$branchId)))
            ->orderBy('study_hall_id')->orderBy('seat_no')->get();
        $rows=[];
        foreach($seats as $seat) {
            $windows=[];
            foreach($seat->allocations as $a) {
                foreach(DailySeatWindow::onDate($a->allocated_from->toDateString(),$a->allocated_to?->toDateString(),$a->start_time,$a->end_time,$date) as [$start,$end]) {
                    $windows[]=['id'=>$a->id,'name'=>$a->student?->name??'Enrolled student','start'=>$start,'end'=>$end,'status'=>$a->status];
                }
            }
            usort($windows,fn($a,$b)=>[$a['start'],$a['end']]<=>[$b['start'],$b['end']]);
            $free=[];$cursor=0;
            foreach($windows as $w) { if($cursor<$w['start']) $free[]=[$cursor,$w['start']];$cursor=max($cursor,$w['end']); }
            if($cursor<1440) $free[]=[$cursor,1440];
            $rows[]=['campus'=>$seat->studyHall?->branch?->name??'Campus','hall'=>$seat->studyHall?->name??'Hall',
                'seat'=>$seat->seat_no,'enabled'=>$seat->status && $seat->studyHall?->status && $seat->studyHall?->branch?->status,
                'windows'=>$windows,'free'=>$free];
        }
        return $rows;
    }
}
