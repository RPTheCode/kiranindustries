<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class EsslSyncOrchestrator
{
    public function runSyncForDateRange(string $from, string $to, $employeeId = null, $branchId = null): array
    {
        return $this->withLock(function () use ($from, $to, $employeeId, $branchId) {
            return $this->executeSync($from, $to, $employeeId, $branchId);
        });
    }

    /**
     * Auto sync: import device logs once, then process attendance branch-by-branch.
     */
    public function runAutoSyncForToday(string $date, int $companyUserId): array
    {
        return $this->withLock(function () use ($date, $companyUserId) {
            $newEsslLogs = $this->importDeviceLogs($date, $date);

            $companyUserIds = $this->companyUserIds($companyUserId);
            $branches = Branch::query()
                ->whereIn('created_by', $companyUserIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']);

            $biometricController = app(\App\Http\Controllers\BiometricAttendanceSyncController::class);
            $totalProcessed = 0;
            $branchSummary = [];

            if ($branches->isEmpty()) {
                $result = $biometricController->runSync(new Request([
                    'from_date' => $date,
                    'to_date' => $date,
                ]));
                $totalProcessed = (int) ($result['processed_count'] ?? 0);
                updateSetting('last_biometric_sync_date', $date, $companyUserId);

                return [
                    'processed_count' => $totalProcessed,
                    'new_essl_logs' => $newEsslLogs,
                    'branches' => [],
                ];
            }

            foreach ($branches as $branch) {
                $result = $biometricController->runSync(new Request([
                    'from_date' => $date,
                    'to_date' => $date,
                    'branch_id' => $branch->id,
                ]));

                $count = (int) ($result['processed_count'] ?? 0);
                $totalProcessed += $count;
                updateSetting('last_biometric_sync_date_branch_' . $branch->id, $date, $companyUserId);

                $branchSummary[] = [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'processed_count' => $count,
                ];
            }

            updateSetting('last_biometric_sync_date', $date, $companyUserId);

            return [
                'processed_count' => $totalProcessed,
                'new_essl_logs' => $newEsslLogs,
                'branches' => $branchSummary,
            ];
        });
    }

    protected function withLock(callable $callback): array
    {
        $lock = Cache::lock('essl_sync_running', 900);

        if (!$lock->get()) {
            throw new \RuntimeException(__('Another ESSL sync is already running. Please wait.'));
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    protected function executeSync(string $from, string $to, $employeeId = null, $branchId = null): array
    {
        set_time_limit(0);

        $newEsslLogs = $this->importDeviceLogs($from, $to, $employeeId, $branchId);

        $biometricController = app(\App\Http\Controllers\BiometricAttendanceSyncController::class);

        $requestArgs = [
            'from_date' => $from,
            'to_date' => $to,
        ];
        if ($employeeId) {
            $requestArgs['employee_id'] = $employeeId;
        }
        if ($branchId) {
            $requestArgs['branch_id'] = $branchId;
        }

        $result = $biometricController->runSync(new Request($requestArgs));

        if ($branchId && $branchId !== 'all') {
            updateSetting('last_biometric_sync_date_branch_' . $branchId, $to);
        } else {
            updateSetting('last_biometric_sync_date', $to);
        }

        return [
            'processed_count' => $result['processed_count'],
            'new_essl_logs' => $newEsslLogs,
        ];
    }

    protected function importDeviceLogs(string $from, string $to, $employeeId = null, $branchId = null): int
    {
        $args = [
            '--from' => $from,
            '--to' => $to,
        ];
        if ($employeeId) {
            $args['--employee_id'] = $employeeId;
        }
        if ($branchId && $branchId !== 'all') {
            $args['--branch_id'] = $branchId;
        }

        Artisan::call('essl:sync', $args);

        $esslOutput = Artisan::output();
        if (preg_match('/New logs added:\s*(\d+)/', $esslOutput, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /** @return array<int, int> */
    protected function companyUserIds(int $companyUserId): array
    {
        $ids = User::where('created_by', $companyUserId)->pluck('id')->toArray();
        $ids[] = $companyUserId;

        return array_values(array_unique($ids));
    }
}
