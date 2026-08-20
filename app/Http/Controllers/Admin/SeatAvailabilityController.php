<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seat;
use App\Models\StudySlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'study_slot_id' => ['required', 'exists:study_slots,id'],
            'allocated_from' => ['nullable', 'date'],
            'allocated_to' => ['nullable', 'date', 'after_or_equal:allocated_from'],
        ]);

        $slot = StudySlot::query()->whereKey($data['study_slot_id'])->firstOrFail();
        abort_unless((int) $slot->branch_id === (int) $data['branch_id'], 422, 'Study slot does not belong to the selected branch.');

        $from = $data['allocated_from'] ?? now()->toDateString();
        $to = $data['allocated_to'] ?? now()->addDays(30)->toDateString();
        $startTime = $slot->start_time;
        $endTime = $slot->end_time;

        $seats = Seat::query()
            ->whereHas('studyHall', fn ($query) => $query->where('branch_id', $data['branch_id']))
            ->where('status', true)
            ->whereDoesntHave('allocations', function ($query) use ($from, $to, $startTime, $endTime) {
                $query->whereIn('status', ['reserved', 'active'])
                    ->whereDate('allocated_from', '<=', $to)
                    ->where(function ($dateQuery) use ($from) {
                        $dateQuery->whereNull('allocated_to')
                            ->orWhereDate('allocated_to', '>=', $from);
                    });

                if ($startTime && $endTime) {
                    $query->where(function ($timeQuery) use ($startTime, $endTime) {
                        $timeQuery->whereNull('start_time')
                            ->orWhereNull('end_time')
                            ->orWhere(function ($overlapQuery) use ($startTime, $endTime) {
                                $overlapQuery->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            });
                    });
                }
            })
            ->with('studyHall:id,name')
            ->orderBy('seat_no')
            ->get(['id', 'study_hall_id', 'seat_no'])
            ->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'seat_no' => $seat->seat_no,
                'hall' => $seat->studyHall?->name,
            ]);

        return response()->json($seats);
    }
}
