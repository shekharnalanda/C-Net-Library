<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Seat;
use App\Models\StudySlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeatAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse|View
    {
        $user = $request->user();

        $branches = Branch::query()
            ->where('status', true)
            ->when(! $user->isGlobalAdmin(), fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $slots = StudySlot::query()
            ->where('status', true)
            ->when(! $user->isGlobalAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->orderBy('branch_id')
            ->orderBy('start_time')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'start_time', 'end_time', 'is_24x7', 'is_flexible', 'duration_hours']);

        if (! $request->filled('branch_id') || ! $request->filled('study_slot_id')) {
            return view('admin.seats.available', [
                'branches' => $branches,
                'slots' => $slots,
                'seats' => null,
                'selectedBranch' => null,
                'selectedSlot' => null,
                'from' => $request->input('allocated_from', now()->toDateString()),
                'to' => $request->input('allocated_to', now()->addDays(30)->toDateString()),
            ]);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'study_slot_id' => ['required', 'exists:study_slots,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'allocated_from' => ['nullable', 'date'],
            'allocated_to' => ['nullable', 'date', 'after_or_equal:allocated_from'],
        ]);

        if (! $user->isGlobalAdmin()) {
            abort_unless((int) $user->branch_id === (int) $data['branch_id'], 403);
        }

        $slot = StudySlot::query()->whereKey($data['study_slot_id'])->where('status', true)->firstOrFail();
        abort_unless((int) $slot->branch_id === (int) $data['branch_id'], 422, 'Study slot does not belong to the selected branch.');

        $from = $data['allocated_from'] ?? now()->toDateString();
        $to = $data['allocated_to'] ?? now()->addDays(30)->toDateString();
        [$startTime,$endTime]=app(\App\Services\SeatAllocationService::class)->resolveTimes($slot,$data);
        app(\App\Services\SeatFeeReleaseService::class)->releaseDue();
        $paddedFrom=\Carbon\Carbon::parse($from)->subDay()->toDateString();
        $paddedTo=\Carbon\Carbon::parse($to)->addDay()->toDateString();
        $seats=Seat::query()->where('status',true)
            ->whereHas('studyHall',fn($q)=>$q->where('branch_id',$data['branch_id'])->where('status',true))
            ->with(['studyHall:id,name','allocations'=>fn($q)=>$q->whereIn('status',['active','reserved'])
                ->whereDate('allocated_from','<=',$paddedTo)
                ->where(fn($q)=>$q->whereNull('allocated_to')->orWhereDate('allocated_to','>=',$paddedFrom))])
            ->orderBy('seat_no')->get()
            ->filter(function($seat)use($from,$to,$startTime,$endTime){
                foreach($seat->allocations as $a) {
                    if(\App\Services\DailySeatWindow::overlaps($from,$to,$startTime,$endTime,
                        $a->allocated_from->toDateString(),$a->allocated_to?->toDateString(),$a->start_time,$a->end_time)) return false;
                }
                return true;
            })->map(fn($seat)=>['id'=>$seat->id,'seat_no'=>$seat->seat_no,'hall'=>$seat->studyHall?->name])->values();

        if ($request->expectsJson()) {
            return response()->json($seats);
        }

        return view('admin.seats.available', [
            'branches' => $branches,
            'slots' => $slots,
            'seats' => $seats,
            'selectedBranch' => $branches->firstWhere('id', (int) $data['branch_id']),
            'selectedSlot' => $slot,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
