<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Daily automated cron schedule to dispatch customer Birthday & Anniversary greetings
 * Runs automatically every morning at 10:20 AM IST (Indian Standard Time / Asia/Kolkata)
 */
Schedule::command('leads:send-wishes')
    ->dailyAt('10:20')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wishes-cron.log'));

