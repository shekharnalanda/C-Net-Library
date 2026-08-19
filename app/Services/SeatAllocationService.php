<?php

namespace App\Services;

use App\Models\SeatAllocation;
use Carbon\CarbonInterface;

class SeatAllocationService
{
    public function hasConflict(
        int $seatId,
        CarbonInterface|string $fromDate,
        CarbonInterface|string|null $toDate,
        string|null $startTime,
        string|null $endTime,
        ?int $ignoreAllocationId = null
    ): bool {
        $query = SeatAllocation::query()
            ->where('seat_id', $seatId)
            ->whereIn('status', ['reserved', 'active'])
            ->whereDate('allocated_from', '<=', $toDate ?? $fromDate)
            ->where(function ($q) use ($fromDate) {
                $q->whereNull('allocated_to')
                    ->orWhereDate('allocated_to', '>=', $fromDate);
            });

        if ($ignoreAllocationId) {
            $query->whereKeyNot($ignoreAllocationId);
        }

        if ($startTime && $endTime) {
            $query->where(function ($q) use ($startTime, $endTime) {
                $q->whereNull('start_time')
                    ->orWhereNull('end_time')
                    ->orWhere(function ($timeQuery) use ($startTime, $endTime) {
                        $timeQuery->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    });
            });
        }

        return $query->exists();
    }
}
