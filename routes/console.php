<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('reports:cleanup')->daily();

\Illuminate\Support\Facades\Schedule::command('activity-logs:cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/activity_logs_cleanup.log'));

\Illuminate\Support\Facades\Schedule::call(function () {
    if (! function_exists('updateSetting')) {
        return;
    }
    try {
        updateSetting('laravel_scheduler_last_ping', now('Asia/Kolkata')->format('Y-m-d H:i:s'));
    } catch (\Throwable) {
        // DB not ready during install
    }
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::command('essl:auto-sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/essl_auto_sync.log'));
