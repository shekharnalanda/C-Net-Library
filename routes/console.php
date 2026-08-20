<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('memberships:activate-scheduled')
    ->dailyAt('00:05')
    ->withoutOverlapping();
