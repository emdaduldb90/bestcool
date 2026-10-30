<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tracking:deliver-pending --limit=50')->everyMinute()->withoutOverlapping();
Schedule::command('tracking:prune --limit=5000')->dailyAt('02:40')->withoutOverlapping();
