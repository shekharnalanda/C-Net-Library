<?php

namespace App\Console\Commands;

use App\Models\SeatAllocation;
use App\Models\StudentMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivateScheduledMemberships extends Command
{
    protected $signature = 'memberships:activate-scheduled';

    protected $description = 'Activate due pending memberships and their reserved seat allocations.';

    public function handle(): int
    {
        StudentMembership::query()
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', today())
            ->orderBy('id')
            ->chunkById(100, function ($memberships) {
                foreach ($memberships as $membership) {
                    DB::transaction(function () use ($membership) {
                        $pending = StudentMembership::query()
                            ->whereKey($membership->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $pending || $pending->status !== 'pending' || $pending->start_date->gt(today())) {
                            return;
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

                        SeatAllocation::query()
                            ->where('student_membership_id', $pending->id)
                            ->where('status', 'reserved')
                            ->update(['status' => 'active']);
                    });
                }
            });

        return self::SUCCESS;
    }
}
