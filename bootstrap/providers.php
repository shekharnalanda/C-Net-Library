<?php

use App\Providers\LibraryPortalFlowServiceProvider;
use App\Providers\LibraryPracticeServiceProvider;
use App\Providers\SeatScheduleServiceProvider;

return [
    SeatScheduleServiceProvider::class,
    LibraryPracticeServiceProvider::class,
    LibraryPortalFlowServiceProvider::class,
];
