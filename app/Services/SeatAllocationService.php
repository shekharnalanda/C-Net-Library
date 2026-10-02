<?php

namespace App\Services;

use App\Models\SeatAllocation;
use App\Models\StudySlot;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SeatAllocationService
{
    public function resolveTimes(StudySlot $slot, array $data = []): array
    {
        if ($slot->is_24x7) return [null,null];
        $start=$data['start_time'] ?? $slot->start_time;
        if (!$start) throw ValidationException::withMessages(['start_time'=>'Choose the actual start time for this student.']);
        try {
            $minutes=DailySeatWindow::minutes($start);
            $duration=(int)$slot->duration_hours*60;
            if ($duration<=0 || $duration>=1440) throw new \InvalidArgumentException('Use the 24×7 slot for a full-day seat.');
            $expected=DailySeatWindow::label(($minutes+$duration)%1440);
            $end=$data['end_time'] ?? $expected;
            if (DailySeatWindow::minutes($end)!==DailySeatWindow::minutes($expected)) throw new \InvalidArgumentException('End time must match the selected slot duration.');
            return [DailySeatWindow::label($minutes).':00',$expected.':00'];
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['end_time'=>$error->getMessage()]);
        }
    }

    public function hasConflict(
        int $seatId,
        CarbonInterface|string $fromDate,
        CarbonInterface|string|null $toDate,
        string|null $startTime,
        string|null $endTime,
        ?int $ignoreAllocationId = null
    ): bool {
        app(SeatFeeReleaseService::class)->releaseDue($seatId);
        $from=Carbon::parse($fromDate)->toDateString();
        $to=$toDate===null ? null : Carbon::parse($toDate)->toDateString();
        $query = SeatAllocation::query()
            ->where('seat_id', $seatId)
            ->whereIn('status', ['reserved', 'active'])
            ->when($to!==null, fn($q)=>$q->whereDate('allocated_from','<=',Carbon::parse($to)->addDay()))
            ->where(function ($q) use ($from) {
                $q->whereNull('allocated_to')
                    ->orWhereDate('allocated_to', '>=', Carbon::parse($from)->subDay());
            });

        if ($ignoreAllocationId) {
            $query->whereKeyNot($ignoreAllocationId);
        }

        foreach($query->get() as $allocation) {
            if(DailySeatWindow::overlaps($from,$to,$startTime,$endTime,
                $allocation->allocated_from->toDateString(),$allocation->allocated_to?->toDateString(),
                $allocation->start_time,$allocation->end_time)) return true;
        }
        return false;
    }

    public function isAvailable(
        int $seatId,
        CarbonInterface|string $fromDate,
        CarbonInterface|string|null $toDate,
        string|null $startTime,
        string|null $endTime,
        ?int $ignoreAllocationId = null
    ): bool {
        return ! $this->hasConflict(
            $seatId,
            $fromDate,
            $toDate,
            $startTime,
            $endTime,
            $ignoreAllocationId,
        );
    }

    public function assertAvailable(
        int $seatId,
        CarbonInterface|string $allocatedFrom,
        CarbonInterface|string|null $allocatedTo,
        string|null $startTime,
        string|null $endTime,
        ?int $ignoreAllocationId = null
    ): void {
        if ($this->hasConflict(
            $seatId,
            $allocatedFrom,
            $allocatedTo,
            $startTime,
            $endTime,
            $ignoreAllocationId,
        )) {
            throw ValidationException::withMessages([
                'seat_id' => 'Selected seat is already allocated for the requested date and time range.',
            ]);
        }
    }
}
