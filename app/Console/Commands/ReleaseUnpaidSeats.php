<?php

namespace App\Console\Commands;

use App\Services\SeatFeeReleaseService;
use Illuminate\Console\Command;

class ReleaseUnpaidSeats extends Command
{
    protected $signature='memberships:release-unpaid-seats';
    protected $description='Release time-specific seats after the inclusive monthly 10th-day payment deadline.';
    public function handle(SeatFeeReleaseService $service): int
    {
        $this->info('Released overdue seat allocations: '.$service->releaseDue());
        return self::SUCCESS;
    }
}
