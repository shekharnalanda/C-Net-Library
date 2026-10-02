<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\StudySlot;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LibraryCampusSlotSetup
{
    /** Add missing duration/fee configuration without changing existing campus records. */
    public function repair(): array
    {
        return DB::transaction(function () {
            $branches = Branch::where('status', true)->orderBy('id')->lockForUpdate()->get();
            if ($branches->count() !== 2) {
                throw new RuntimeException('Expected exactly two active library campuses; no setup changed.');
            }
            $source = $branches->firstWhere('code', 'CNL-MAIN');
            if (! $source) {
                $sources = $branches->filter(fn ($b) => preg_match('/c[ -]?net|cnl|senate/i', $b->name.' '.$b->code));
                $source = $sources->count() === 1 ? $sources->first() : null;
            }
            $target = $source ? $branches->firstWhere('id', '!=', $source->id) : null;
            if (! $source || ! $target || ! preg_match('/mci/i', $target->name.' '.$target->code)) {
                throw new RuntimeException('C-Net/MCI campus identities need review; no setup changed.');
            }
            $sourceSlots = StudySlot::where('branch_id', $source->id)->where('status', true)->orderBy('id')->get();
            $result = ['source_id' => $source->id, 'target_id' => $target->id, 'source' => $source->name, 'target' => $target->name, 'added_slots' => 0, 'added_plans' => 0, 'preserved_slots' => 0, 'preserved_plans' => 0, 'disabled_skipped' => 0, 'durations' => []];
            foreach ($sourceSlots as $slot) {
                $sourcePlans = FeePlan::where('branch_id', $source->id)->where('study_slot_id', $slot->id)->where('status', true)->orderBy('id')->get();
                if ($sourcePlans->isEmpty()) {
                    continue;
                }
                $candidates = StudySlot::where('branch_id', $target->id)->where('duration_hours', $slot->duration_hours)->where('is_24x7', $slot->is_24x7)->orderByDesc('status')->orderBy('id')->get();
                $targetSlot = $candidates->firstWhere('status', true);
                if (! $targetSlot && $candidates->isNotEmpty()) {
                    $result['disabled_skipped']++;

                    continue;
                }
                if (! $targetSlot) {
                    $targetSlot = $slot->replicate();
                    $targetSlot->branch_id = $target->id;
                    $targetSlot->save();
                    $result['added_slots']++;
                } else {
                    $result['preserved_slots']++;
                }
                $existingPlans = FeePlan::where('branch_id', $target->id)->where('study_slot_id', $targetSlot->id)->get();
                if ($existingPlans->where('status', true)->isNotEmpty()) {
                    $result['preserved_plans'] += $existingPlans->where('status', true)->count();
                } elseif ($existingPlans->isNotEmpty()) {
                    // Do not bypass fee plans explicitly disabled by an administrator.
                    $result['disabled_skipped']++;

                    continue;
                } else {
                    foreach ($sourcePlans as $plan) {
                        $copy = $plan->replicate();
                        $copy->branch_id = $target->id;
                        $copy->study_slot_id = $targetSlot->id;
                        $copy->save();
                        $result['added_plans']++;
                    }
                }
                $result['durations'][] = $targetSlot->is_24x7 ? '24x7' : $targetSlot->duration_hours.' hours';
            }
            $usable = StudySlot::where('branch_id', $target->id)->where('status', true)
                ->whereHas('feePlans', fn ($q) => $q->where('branch_id', $target->id)->where('status', true))->count();
            if (! $usable) {
                throw new RuntimeException('No enabled duration and fee plan pair is available for MCI; no setup changed.');
            }

            return $result + ['available_durations' => $usable];
        }, 3);
    }
}
