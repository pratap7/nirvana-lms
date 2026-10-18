<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('enrollment:release-expired-holds')->hourly();
Schedule::command('lms:send-scheduled-reminders')->everyFifteenMinutes();
Schedule::command('lms:apply-communication-retention')->dailyAt('02:00');
