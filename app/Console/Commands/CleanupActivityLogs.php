<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupActivityLogs extends Command
{
    protected $signature = 'activity-logs:cleanup
                            {--months=2 : Delete logs older than this many months}';

    protected $description = 'Delete activity logs older than the retention period (default: 2 months)';

    public function handle(): int
    {
        $months = max(1, (int) ($this->option('months') ?: env('ACTIVITY_LOG_RETENTION_MONTHS', 2)));
        $cutoff = Carbon::now()->subMonths($months);

        $this->info("Deleting activity logs created before {$cutoff->toDateTimeString()} ({$months} month(s))…");

        $totalDeleted = 0;
        $batchSize = 2000;

        do {
            $deleted = ActivityLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->line("  …{$totalDeleted} deleted so far");
            }
        } while ($deleted === $batchSize);

        $message = "Activity log cleanup finished: {$totalDeleted} record(s) removed (retention: {$months} months).";
        $this->info($message);
        Log::channel('single')->info($message);

        return self::SUCCESS;
    }
}
