<?php

namespace App\Console\Commands;

use App\Models\SeatAllocation;
use App\Models\StudentMembership;
use App\Services\SeatAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ActivateScheduledMemberships extends Command
{
    protected $signature = 'memberships:activate-scheduled';

    protected $description = 'Activate due pending memberships and their reserved seat allocations.';

    public function handle(SeatAllocationService $seatAllocationService): int
    {
        $activated = 0;
        $skipped = 0;

        StudentMembership::query()
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', today())
            ->orderBy('id')
            ->chunkById(100, function ($memberships) use ($seatAllocationService, &$activated, &$skipped) {
                foreach ($memberships as $membership) {
                    try {
                        $didActivate = DB::transaction(function () use ($membership, $seatAllocationService) {
                            $pending = StudentMembership::query()
                                ->whereKey($membership->id)
                                ->lockForUpdate()
                                ->first();

                            if (! $pending || $pending->status !== 'pending' || $pending->start_date->gt(today())) {
                                return false;
                            }

                            $reservedAllocation = SeatAllocation::query()
                                ->where('student_membership_id', $pending->id)
                                ->where('status', 'reserved')
                                ->lockForUpdate()
                                ->first();

                            if ($reservedAllocation) {
                                $seatAllocationService->assertAvailable(
                                    seatId: $reservedAllocation->seat_id,
                                    allocatedFrom: $reservedAllocation->allocated_from,
                                    allocatedTo: $reservedAllocation->allocated_to,
                                    startTime: $reservedAllocation->start_time,
                                    endTime: $reservedAllocation->end_time,
                                    ignoreAllocationId: $reservedAllocation->id,
                                );
                            }

                            StudentMembership::query()
                                ->where('student_id', $pending->student_id)
                                ->where('status', 'active')
                                ->whereKeyNot($pending->id)
                                ->lockForUpdate()
                                ->update(['status' => 'expired']);

                            SeatAllocation::query()
                                ->where('student_id', $pending->student_id)
                                ->where('status', 'active')
                                ->lockForUpdate()
                                ->update([
                                    'allocated_to' => $pending->start_date->copy()->subDay()->toDateString(),
                                    'status' => 'released',
                                ]);

                            $pending->update(['status' => 'active']);

                            if ($reservedAllocation) {
                                $reservedAllocation->update(['status' => 'active']);
                            }

                            return true;
                        }, 3);

                        if ($didActivate) {
                            $activated++;
                        } else {
                            $skipped++;
                        }
                    } catch (ValidationException $exception) {
                        $skipped++;
                        Log::warning('Scheduled membership activation skipped due to seat conflict.', [
                            'membership_id' => $membership->id,
                            'student_id' => $membership->student_id,
                            'errors' => $exception->errors(),
                        ]);
                    } catch (\Throwable $exception) {
                        $skipped++;
                        report($exception);
                    }
                }
            });

        $this->info("Scheduled memberships activated: {$activated}; skipped: {$skipped}.");
        Log::info('Scheduled membership activation completed.', [
            'activated' => $activated,
            'skipped' => $skipped,
        ]);

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}
