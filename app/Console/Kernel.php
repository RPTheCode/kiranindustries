<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('biometric:sync-all')
                 ->everyThirtyMinutes()
                 ->appendOutputTo(storage_path('logs/biometric_sync.log'));

        $schedule->command('reports:cleanup')
                 ->daily()
                 ->appendOutputTo(storage_path('logs/reports_cleanup.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}