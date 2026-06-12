<?php

namespace App\Console\Commands;

use App\Jobs\EsslSchedulerTickJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EsslEnsureSchedulerCommand extends Command
{
    protected $signature = 'essl:ensure-scheduler';

    protected $description = 'Start queue-based ESSL scheduler tick (needs queue:work running)';

    public function handle(): int
    {
        $lastTick = Cache::get('essl_scheduler_tick_active');
        if ($lastTick && (now()->timestamp - (int) $lastTick) < 120) {
            $this->info('ESSL queue scheduler already active (tick within last 2 minutes).');

            return self::SUCCESS;
        }

        EsslSchedulerTickJob::dispatch();
        $this->info('ESSL queue scheduler started — tick every 1 minute via queue worker.');
        $this->line('Requires: php artisan queue:work (or supervisor kiran-queue-worker)');

        return self::SUCCESS;
    }
}
