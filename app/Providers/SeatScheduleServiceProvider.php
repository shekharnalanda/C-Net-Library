<?php

namespace App\Providers;

use App\Console\Commands\ConfigureSeatScheduling;
use App\Console\Commands\ReleaseUnpaidSeats;
use App\Console\Commands\ResetEnrollments;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class SeatScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/seat-schedule.php'));
        if($this->app->runningInConsole()) {
            $this->commands([ConfigureSeatScheduling::class,ReleaseUnpaidSeats::class,ResetEnrollments::class]);
            $this->app->booted(function(){
                $this->app->make(Schedule::class)->command('memberships:release-unpaid-seats')->everyMinute()->withoutOverlapping();
            });
        }
    }
}
