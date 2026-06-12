<?php

namespace App\Jobs;

use App\Services\EsslAutoSyncConfig;
use App\Services\EsslAutoSyncRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Queue-based scheduler tick — runs every minute while queue worker is active.
 * Use when cron / schedule:work is not available (Windows XAMPP, or server without supervisor scheduler).
 */
class EsslSchedulerTickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function handle(EsslAutoSyncRunner $runner): void
    {
        Cache::put('essl_scheduler_tick_active', now()->timestamp, now()->addMinutes(3));

        // schedule:work / cron already running — only keep tick alive, do not duplicate sync
        if (EsslAutoSyncConfig::isSchedulerRunning()) {
            self::dispatch()->delay(now()->addMinute());

            return;
        }

        EsslAutoSyncRunner::pingScheduler();

        $plan = $runner->evaluate();
        if ($plan) {
            Log::info("ESSL scheduler tick — dispatching auto sync [{$plan['label']}]");
            EsslAutoSyncJob::dispatch();
        }

        self::dispatch()->delay(now()->addMinute());
    }
}
