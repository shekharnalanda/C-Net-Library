<?php

namespace App\Console\Commands;

use App\Models\SeatAllocation;
use App\Models\StudentMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireDueMemberships extends Command
{
    protected $signature = 'memberships:expire-due';

    protected $description = 'Expire memberships whose expiry date has passed and release their active seat allocations.';

    public function handle(): int
    {
        $expired = 0;
        $skipped = 0;

        StudentMembership::query()
            ->where('status', 'active')
            ->whereDate('expiry_date', '<', today())
            ->orderBy('id')
            ->chunkById(100, function ($memberships) use (&$expired, &$skipped) {
                foreach ($memberships as $membership) {
                    try {
                        $didExpire = DB::transaction(function () use ($membership) {
                            $locked = StudentMembership::query()
                                ->whereKey($membership->id)
                                ->lockForUpdate()
                                ->first();

                            if (! $locked || $locked->status !== 'active' || ! $locked->expiry_date->lt(today())) {
                                return false;
                            }

                            SeatAllocation::query()
                                ->where('student_membership_id', $locked->id)
                                ->where('status', 'active')
                                ->lockForUpdate()
                                ->update([
                                    'allocated_to' => $locked->expiry_date->toDateString(),
                                    'status' => 'released',
                                ]);

                            $locked->update(['status' => 'expired']);

                            return true;
                        }, 3);

                        $didExpire ? $expired++ : $skipped++;
                    } catch (\Throwable $exception) {
                        $skipped++;
                        report($exception);
                    }
                }
            });

        $this->info("Expired memberships: {$expired}; skipped: {$skipped}.");
        Log::info('Expired membership cleanup completed.', compact('expired', 'skipped'));

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}
