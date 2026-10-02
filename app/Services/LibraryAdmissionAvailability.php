<?php

namespace App\Services;

use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\StudySlot;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LibraryAdmissionAvailability
{
    public function options(int $branch, int $slotId, int $planId, string $from): array
    {
        $slot = StudySlot::whereKey($slotId)->where('branch_id', $branch)->where('status', true)->firstOrFail();
        $plan = FeePlan::whereKey($planId)->where('branch_id', $branch)->where('study_slot_id', $slotId)->where('status', true)->firstOrFail();
        $expiry = CarbonImmutable::parse($from)->addDays(max(1, (int) $plan->validity_days) - 1)->toDateString();
        $to = app(SeatFeeReleaseService::class)->holdUntil($expiry);
        app(SeatFeeReleaseService::class)->releaseDue();
        $seats = Seat::with('studyHall')->where('status', true)->whereHas('studyHall', fn ($q) => $q->where('branch_id', $branch)->where('status', true))->orderBy('seat_no')->get();
        $allocations = SeatAllocation::whereIn('status', ['active', 'reserved'])->whereIn('seat_id', $seats->pluck('id'))->whereDate('allocated_from', '<=', CarbonImmutable::parse($to)->addDay())->where(fn ($q) => $q->whereNull('allocated_to')->orWhereDate('allocated_to', '>=', CarbonImmutable::parse($from)->subDay()))->get()->groupBy('seat_id');
        $hours = (int) $slot->duration_hours;
        if (! $slot->is_24x7 && ($hours < 1 || $hours > 23)) {
            throw ValidationException::withMessages(['study_slot_id' => 'इस अवधि के लिए फीस और घंटे एडमिन से कॉन्फ़िगर करवाएँ।']);
        }
        $times = [];
        foreach ($slot->is_24x7 ? [0] : range(0, 23) as $hour) {
            $start = $slot->is_24x7 ? null : sprintf('%02d:00', $hour);
            $end = $slot->is_24x7 ? null : sprintf('%02d:00', ($hour + $hours) % 24);
            $free = [];
            foreach ($seats as $seat) {
                $conflict = false;
                foreach ($allocations->get($seat->id, collect()) as $a) {
                    if (DailySeatWindow::overlaps($from, $to, $start, $end, $a->allocated_from->toDateString(), $a->allocated_to?->toDateString(), $a->start_time, $a->end_time)) {
                        $conflict = true;
                        break;
                    }
                }
                if (! $conflict) {
                    $free[] = ['id' => $seat->id, 'number' => $seat->seat_no, 'hall' => $seat->studyHall->name];
                }
            }
            $label = $slot->is_24x7 ? '24×7' : CarbonImmutable::createFromTime($hour, 0)->format('g:i A').' – '.CarbonImmutable::createFromTime(($hour + $hours) % 24, 0)->format('g:i A').($hour + $hours >= 24 ? ' (अगले दिन)' : '');
            $times[] = ['start' => $start, 'end' => $end, 'label' => $label, 'available' => count($free), 'seats' => $free];
        }

        return ['times' => $times, 'expiry' => $expiry, 'held_until' => $to, 'monthly_fee' => $plan->monthly_fee, 'admission_fee' => $plan->admission_fee, 'registration_fee' => $plan->registration_fee, 'security_deposit' => $plan->security_deposit];
    }

    public function assertSelection(array $data): void
    {
        $options = $this->options((int) $data['branch_id'], (int) $data['study_slot_id'], (int) $data['fee_plan_id'], $data['preferred_start_date']);
        foreach ($options['times'] as $time) {
            if (($time['start'] ?? '') === ($data['preferred_start_time'] ?? '') && ($time['end'] ?? '') === ($data['preferred_end_time'] ?? '')) {
                foreach ($time['seats'] as $seat) {
                    if ($seat['id'] == $data['preferred_seat_id']) {
                        return;
                    }
                }
            }
        }
        throw ValidationException::withMessages(['preferred_seat_id' => 'यह सीट अब आपके चुने समय में उपलब्ध नहीं है। समय और सीट फिर चुनें।']);
    }
}
