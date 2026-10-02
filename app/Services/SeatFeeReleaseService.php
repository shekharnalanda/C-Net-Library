<?php

namespace App\Services;

use App\Models\SeatAllocation;
use App\Models\StudentMembership;
use Illuminate\Support\Facades\DB;

class SeatFeeReleaseService
{
    public function enabled(): bool
    {
        return (int)app(SettingsService::class)->get('seat_monthly_cutoff_day',0)===10;
    }

    public function holdUntil(string $expiry): string
    {
        return $this->enabled() ? DailySeatWindow::renewalDeadline($expiry) : $expiry;
    }

    public function dueReason(StudentMembership $membership, ?string $date=null): ?string
    {
        if(!$this->enabled()) return null;
        $date ??= today()->toDateString();
        $gross=(float)$membership->payments()->whereIn('payment_status',['paid','partial'])->sum('amount');
        $adjusted=(float)DB::table('payment_adjustments')->whereIn('payment_id',$membership->payments()->whereIn('payment_status',['paid','partial'])->select('id'))->sum('amount');
        $balance=max(0,(int)round((float)$membership->final_fee*100)-(int)round(($gross-$adjusted)*100));
        if($balance>0 && $date>DailySeatWindow::unpaidDeadline($membership->start_date->toDateString())) return 'unpaid';
        if($date>DailySeatWindow::renewalDeadline($membership->expiry_date->toDateString())) return 'unrenewed';
        return null;
    }

    public function assertMembership(StudentMembership $membership, int $slotId, string $from, ?string $to): void
    {
        if(!$this->enabled()) return;
        $until=$this->holdUntil($membership->expiry_date->toDateString());
        if(!in_array($membership->status,['active','pending'],true) || $this->dueReason($membership)
            || (int)$membership->study_slot_id!==$slotId || $from<$membership->start_date->toDateString()
            || !$to || $to>$until) {
            throw \Illuminate\Validation\ValidationException::withMessages(['student_id'=>'Renew/update the matching membership and fee payment before assigning this seat or changing its duration.']);
        }
    }

    public function releaseDue(?int $seatId = null): int
    {
        if(!$this->enabled()) return 0;
        $count=0;
        $today=today()->toDateString();
        SeatAllocation::query()->whereIn('status',['active','reserved'])
            ->whereDate('allocated_from','<=',$today)
            ->whereNotNull('student_membership_id')
            ->when($seatId!==null,fn($q)=>$q->where('seat_id',$seatId))
            ->orderBy('id')->chunkById(100,function($allocations)use(&$count,$today){
                foreach($allocations as $row) {
                    $released=DB::transaction(function()use($row,$today){
                        // Same seat lock used by all allocation writers.
                        DB::table('seats')->where('id',$row->seat_id)->lockForUpdate()->first();
                        $allocation=SeatAllocation::whereKey($row->id)->lockForUpdate()->first();
                        if(!$allocation || !in_array($allocation->status,['active','reserved'],true)) return false;
                        $membership=StudentMembership::whereKey($allocation->student_membership_id)->lockForUpdate()->first();
                        if(!$membership) return false;
                        $reason=$this->dueReason($membership,$today);
                        if(!$reason) return false;
                        $releaseDate=$reason==='unpaid' ? DailySeatWindow::unpaidDeadline($membership->start_date->toDateString()) : DailySeatWindow::renewalDeadline($membership->expiry_date->toDateString());
                        app(AuditService::class)->log('seat.auto_released',$allocation,
                            ['status'=>$allocation->status],['status'=>'released','reason'=>$reason,'cutoff'=>$releaseDate]);
                        $allocation->update(['status'=>'released','allocated_to'=>$releaseDate]);
                        $membership->update(['status'=>'expired']);
                        return true;
                    },3);
                    if($released) $count++;
                }
            });
        return $count;
    }
}
