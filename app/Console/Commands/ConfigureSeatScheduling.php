<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

class ConfigureSeatScheduling extends Command
{
    protected $signature='seats:configure-monthly-cutoff';
    protected $description='Enable the monthly 10th-day seat fee deadline without changing slots, seats or fee amounts.';
    public function handle(SettingsService $settings): int
    {
        $settings->set('seat_monthly_cutoff_day',10,'membership','integer');
        $this->info('Seat payment deadline: 10th inclusive; release starts on the 11th.');
        return self::SUCCESS;
    }
}
