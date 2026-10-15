<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Boost upkeep: expire ended boosts, tell vendors when a scheduled boost
// starts and three days before one ends. Buyers' feeds check dates live, so
// this only drives flags and notices; hourly keeps the notices timely.
Schedule::command('boost:expire')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
