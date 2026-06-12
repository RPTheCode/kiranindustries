<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EsslAutoSyncConfig;
use App\Services\EsslSyncOrchestrator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EsslAutoSyncCommand extends Command
{
    protected $signature = 'essl:auto-sync';

    protected $description = 'Run scheduled ESSL biometric sync inside configured time ranges';

    public function handle(EsslSyncOrchestrator $orchestrator): int
    {
        $companyUser = User::where('type', 'company')->first();
        if (!$companyUser) {
            return self::SUCCESS;
        }

        $companyId = $companyUser->id;

        if (!EsslAutoSyncConfig::isEnabled($companyId)) {
            return self::SUCCESS;
        }

        $tz = data_get(settings($companyId), 'defaultTimezone', config('app.timezone', 'Asia/Kolkata'));
        if (!$tz || $tz === 'UTC') {
            $tz = 'Asia/Kolkata';
        }

        $now = Carbon::now($tz);
        $active = EsslAutoSyncConfig::activeRange($now, $companyId);

        if (!$active) {
            return self::SUCCESS;
        }

        if (!EsslAutoSyncConfig::shouldRunNow($now, $active['index'], $companyId)) {
            return self::SUCCESS;
        }

        $today = $now->format('Y-m-d');
        $label = $active['label'];

        $this->info("ESSL auto sync [{$label}] for {$today} at {$now->format('H:i')} ({$tz})");

        try {
            $result = $orchestrator->runAutoSyncForToday($today, $companyId);

            EsslAutoSyncConfig::markRangeRun($active['index'], $now, $label, $companyId);

            $branchInfo = collect($result['branches'] ?? [])
                ->map(fn ($b) => "{$b['name']}:{$b['processed_count']}")
                ->implode(', ');

            $message = "ESSL auto sync [{$label}] OK: {$result['processed_count']} attendance rows, {$result['new_essl_logs']} new punches"
                . ($branchInfo ? " ({$branchInfo})" : '');
            $this->info($message);
            Log::info($message, array_merge($result, ['range_index' => $active['index']]));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('ESSL auto sync failed', ['label' => $label, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
