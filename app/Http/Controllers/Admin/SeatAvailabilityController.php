<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seat;
use App\Models\StudySlot;
use App\Services\SeatAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatAvailabilityController extends Controller
{
    public function __invoke(Request $request, SeatAllocationService $seatAllocationService): JsonResponse
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

        $seats = Seat::query()
            ->whereHas('studyHall', fn ($query) => $query->where('branch_id', $data['branch_id']))
            ->where('status', true)
            ->with('studyHall')
            ->get()
            ->filter(fn (Seat $seat) => $seatAllocationService->isAvailable(
                $seat->id,
                $from,
                $to,
                $slot->start_time,
                $slot->end_time
            ))
            ->values()
            ->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'seat_no' => $seat->seat_no,
                'hall' => $seat->studyHall?->name,
            ]);

        return response()->json($seats);
    }
}
