<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EsslAutoSyncRunner
{
    public function __construct(private EsslSyncOrchestrator $orchestrator) {}

    public function resolveCompanyId(): ?int
    {
        return User::where('type', 'company')->value('id');
    }

    /**
     * @return array{index: int, label: string, now: Carbon, company_id: int, tz: string}|null
     */
    public function evaluate(?int $companyId = null): ?array
    {
        $companyId ??= $this->resolveCompanyId();
        if (! $companyId) {
            return null;
        }

        if (! EsslAutoSyncConfig::isEnabled($companyId)) {
            return null;
        }

        $tz = EsslAutoSyncConfig::companyTimezone($companyId);
        $now = Carbon::now($tz);
        $active = EsslAutoSyncConfig::activeRange($now, $companyId);

        if (! $active) {
            return null;
        }

        if (! EsslAutoSyncConfig::shouldRunNow($now, $active['index'], $companyId)) {
            return null;
        }

        return [
            'index' => $active['index'],
            'label' => $active['label'],
            'now' => $now,
            'company_id' => $companyId,
            'tz' => $tz,
        ];
    }

    /**
     * @return array{status: string, message: string, result?: array}
     */
    public function run(?int $companyId = null): array
    {
        $companyId ??= $this->resolveCompanyId();
        if (! $companyId) {
            return ['status' => 'skipped', 'message' => 'No company user found'];
        }

        EsslAutoSyncConfig::applyCompanyTimezone($companyId);

        $plan = $this->evaluate($companyId);
        if (! $plan) {
            $tz = EsslAutoSyncConfig::companyTimezone($companyId);
            $now = Carbon::now($tz);

            if (! EsslAutoSyncConfig::isEnabled($companyId)) {
                return ['status' => 'skipped', 'message' => 'Auto sync disabled'];
            }

            $active = EsslAutoSyncConfig::activeRange($now, $companyId);
            if (! $active) {
                return [
                    'status' => 'skipped',
                    'message' => "Outside time ranges ({$now->format('H:i')} {$tz})",
                ];
            }

            return [
                'status' => 'skipped',
                'message' => "Waiting interval for [{$active['label']}] ({$now->format('H:i')} {$tz})",
            ];
        }

        $today = $plan['now']->format('Y-m-d');
        $label = $plan['label'];

        try {
            $result = $this->orchestrator->runAutoSyncForToday($today, $plan['company_id']);

            EsslAutoSyncConfig::markRangeRun($plan['index'], $plan['now'], $label, $plan['company_id']);

            $branchInfo = collect($result['branches'] ?? [])
                ->map(fn ($b) => "{$b['name']}:{$b['processed_count']}")
                ->implode(', ');

            $message = "ESSL auto sync [{$label}] OK: {$result['processed_count']} attendance rows, {$result['new_essl_logs']} new punches"
                . ($branchInfo ? " ({$branchInfo})" : '');

            Log::info($message, array_merge($result, ['range_index' => $plan['index']]));

            return ['status' => 'ok', 'message' => $message, 'result' => $result];
        } catch (\Throwable $e) {
            Log::error('ESSL auto sync failed', ['label' => $label, 'error' => $e->getMessage()]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function pingScheduler(): void
    {
        try {
            updateSetting('laravel_scheduler_last_ping', now('Asia/Kolkata')->format('Y-m-d H:i:s'));
        } catch (\Throwable) {
            // ignore
        }
    }
}
