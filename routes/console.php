<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('memberships:expire-due')
    ->dailyAt('00:01')
    ->withoutOverlapping();

Schedule::command('memberships:activate-scheduled')
    ->dailyAt('00:05')
    ->withoutOverlapping();
