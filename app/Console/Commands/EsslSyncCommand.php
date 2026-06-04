<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EsslLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\EsslService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EsslSyncCommand extends Command
{
    protected $signature = 'essl:sync {--date= : Start date (alias for --from)} {--from= : Start date for synchronization} {--to= : End date for synchronization} {--employee_id= : Sync only for this employee user ID} {--branch_id= : Sync only for this branch ID}';

    protected $description = 'Synchronize biometric logs from ESSL database for a specific date range';

    /** Night shift starts ~20:00 — IN on last day of month lives in previous month table */
    private const NIGHT_SHIFT_START_HOUR = 20;

    /** Night shift OUT may appear until ~20:00 next calendar day (in next month table) */
    private const NIGHT_SHIFT_OUT_CUTOFF_HOUR = 20;

    private array $directIdMap = [];
    private array $emyCodeMap = [];
    private array $esslIdMap = [];
    private array $employeeIdMap = [];
    private array $allowedUserIds = [];
    private ?string $employeeIdFilter = null;
    private ?string $branchIdFilter = null;

    private int $newRecords = 0;
    private int $mappedRecords = 0;
    private int $unmappedCount = 0;
    private int $skippedDuplicateCount = 0;

    public function handle(EsslService $esslService)
    {
        set_time_limit(0);
        $startDate = $this->option('from') ?: ($this->option('date') ?: '2026-04-01');
        $endDate = $this->option('to') ?: Carbon::now()->toDateTimeString();

        $this->info("Starting ESSL synchronization from $startDate to $endDate...");

        try {
            $dt = Carbon::parse($startDate)->startOfDay();
            $endDt = Carbon::parse($endDate);
            if (strlen($endDate) <= 10) {
                $endDt->endOfDay();
            }

            $this->newRecords = 0;
            $this->mappedRecords = 0;
            $this->unmappedCount = 0;
            $this->skippedDuplicateCount = 0;

            $this->directIdMap = User::where('type', 'employee')->where('status', 'active')->pluck('id', 'id')->toArray();
            $this->emyCodeMap = Employee::active()->pluck('user_id', 'emy_code')->toArray();
            $this->esslIdMap = Employee::active()->pluck('user_id', 'essl_id')->toArray();
            $this->employeeIdMap = Employee::active()->pluck('user_id', 'employee_id')->toArray();

            $this->branchIdFilter = $this->option('branch_id');
            $this->employeeIdFilter = $this->option('employee_id');
            $this->allowedUserIds = [];
            if ($this->branchIdFilter && $this->branchIdFilter !== 'all') {
                $this->allowedUserIds = Employee::active()->where('branch_id', $this->branchIdFilter)->pluck('user_id')->toArray();
            }

            while ($dt->lte($endDt)) {
                $tableName = $esslService->resolveDeviceLogsTable($dt);

                if (!$tableName) {
                    $this->error("No DeviceLogs table found for {$dt->format('M Y')}. Skipping...");
                    $dt->addMonth()->startOfMonth();
                    continue;
                }

                if ($tableName !== 'AttLog' && $dt->day === 1) {
                    $this->importPreviousMonthNightIns($esslService, $dt, $endDt);
                }

                $this->info("Syncing from table: $tableName for {$dt->format('F Y')}");

                $chunkFrom = $dt->toDateTimeString();
                $chunkTo = ($dt->format('Y-m') === $endDt->format('Y-m'))
                    ? $endDt->toDateTimeString()
                    : $dt->copy()->endOfMonth()->toDateTimeString();

                $logs = $esslService->getLogsFromTable($tableName, $chunkFrom, $chunkTo);
                $this->info('Found ' . count($logs) . " logs in $tableName.");
                $this->importLogs($esslService, $logs);

                $chunkEnd = Carbon::parse($chunkTo);
                if ($tableName !== 'AttLog' && $chunkEnd->isLastOfMonth()) {
                    $this->importNextMonthNightOuts($esslService, $chunkEnd, $endDt);
                }

                $dt->addMonth()->startOfMonth();
                if ($tableName === 'AttLog') {
                    break;
                }
            }

            $this->info('Synchronization complete!');
            $this->info("New logs added: {$this->newRecords}");
            $this->info("Mapped to employees: {$this->mappedRecords}");
            if ($this->unmappedCount > 0) {
                $this->warn("Unmapped ESSL UserIds (no local employee): {$this->unmappedCount}");
            }
            if ($this->skippedDuplicateCount > 0) {
                $this->warn("Skipped (already in essl_logs): {$this->skippedDuplicateCount}");
            }

            Log::info('ESSL device log import', [
                'from' => $startDate,
                'to' => $endDate,
                'new_logs_added' => $this->newRecords,
                'rows_mapped' => $this->mappedRecords,
                'unmapped' => $this->unmappedCount,
                'skipped_duplicates' => $this->skippedDuplicateCount,
            ]);
        } catch (\Exception $e) {
            $this->error('Synchronization failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Last day of month 20:00+ IN is in previous month DeviceLogs table.
     * Example: 31 May 20:02 IN → DeviceLogs_5_2026 (when syncing June).
     */
    private function importPreviousMonthNightIns(EsslService $esslService, Carbon $monthStart, Carbon $endDt): void
    {
        $prevDay = $monthStart->copy()->subDay();
        $prevTable = $esslService->resolveDeviceLogsTable($prevDay);
        if (!$prevTable || $prevTable === 'AttLog') {
            return;
        }

        $from = $prevDay->copy()->setTime(self::NIGHT_SHIFT_START_HOUR, 0, 0);
        $to = $prevDay->copy()->endOfDay();
        if ($to->lt(Carbon::parse($this->option('from') ?: $this->option('date') ?: '2026-04-01')->startOfDay())) {
            return;
        }

        $this->info("Month boundary: night IN from {$prevTable} ({$from->toDateTimeString()} → {$to->toDateTimeString()})");
        $logs = $esslService->getLogsFromTable($prevTable, $from->toDateTimeString(), $to->toDateTimeString());
        $this->info('Found ' . count($logs) . ' previous-month night IN logs.');
        $this->importLogs($esslService, $logs);
    }

    /**
     * Next-month morning OUT closes previous month night shift.
     * Example: 1 Jun 07:56 OUT → DeviceLogs_6_2026 (when syncing May).
     */
    private function importNextMonthNightOuts(EsslService $esslService, Carbon $monthEnd, Carbon $endDt): void
    {
        $nextDay = $monthEnd->copy()->addDay()->startOfDay();
        $graceEnd = $endDt->copy()->addDay()->setTime(self::NIGHT_SHIFT_OUT_CUTOFF_HOUR, 0, 0);
        $to = $nextDay->copy()->setTime(self::NIGHT_SHIFT_OUT_CUTOFF_HOUR, 0, 0);
        if ($to->gt($graceEnd)) {
            $to = $graceEnd;
        }
        if ($nextDay->gt($graceEnd)) {
            return;
        }

        $nextTable = $esslService->resolveDeviceLogsTable($nextDay);
        if (!$nextTable || $nextTable === 'AttLog') {
            return;
        }

        $this->info("Month boundary: night OUT from {$nextTable} ({$nextDay->toDateTimeString()} → {$to->toDateTimeString()})");
        $logs = $esslService->getLogsFromTable($nextTable, $nextDay->toDateTimeString(), $to->toDateTimeString());
        $this->info('Found ' . count($logs) . ' next-month night OUT logs.');
        $this->importLogs($esslService, $logs);
    }

    private function importLogs(EsslService $esslService, array $logs): void
    {
        if (empty($logs)) {
            return;
        }

        $insertData = [];
        $now = Carbon::now()->toDateTimeString();

        foreach ($logs as $log) {
            $row = $this->mapLogToInsert($esslService, $log, $now);
            if ($row !== null) {
                $insertData[] = $row;
            }
        }

        if (empty($insertData)) {
            return;
        }

        foreach (array_chunk($insertData, 500) as $chunk) {
            $before = EsslLog::count();
            EsslLog::insertOrIgnore($chunk);
            $inserted = EsslLog::count() - $before;
            $this->newRecords += $inserted;
            $this->mappedRecords += count($chunk);
            $this->skippedDuplicateCount += count($chunk) - $inserted;
        }
    }

    private function mapLogToInsert(EsslService $esslService, object $log, string $now): ?array
    {
        $esslUserId = (string) $log->UserId;
        $trimmedId = ltrim($esslUserId, '0');

        $localUserId = $this->directIdMap[(int) $esslUserId]
            ?? ($this->emyCodeMap[$esslUserId]
                ?? ($this->emyCodeMap[$trimmedId]
                    ?? ($this->esslIdMap[$esslUserId]
                        ?? ($this->esslIdMap[$trimmedId]
                            ?? ($this->employeeIdMap[$esslUserId]
                                ?? null)))));

        if (!$localUserId) {
            $this->unmappedCount++;
            return null;
        }

        if ($this->employeeIdFilter && $localUserId != $this->employeeIdFilter) {
            return null;
        }

        if ($this->branchIdFilter && $this->branchIdFilter !== 'all' && !in_array($localUserId, $this->allowedUserIds)) {
            return null;
        }

        return [
            'device_log_id' => $esslService->rawDeviceLogId($log) ?? 0,
            'download_date' => $log->DownloadDate,
            'device_id' => $log->DeviceId,
            'user_id' => $localUserId,
            'log_date' => $log->LogDate,
            'direction' => $log->Direction,
            'att_direction' => $log->AttDirection,
            'c1' => $log->C1 ?? null,
            'c2' => $log->C2 ?? null,
            'c3' => $log->C3 ?? null,
            'c4' => $log->C4 ?? null,
            'c5' => $log->C5 ?? null,
            'c6' => $log->C6 ?? null,
            'c7' => $log->C7 ?? null,
            'work_code' => $log->WorkCode ?? null,
            'update_flag' => $log->UpdateFlag ?? null,
            'file_name' => $log->FileName ?? null,
            'longitude' => $log->Longitude ?? null,
            'latitude' => $log->Latitude ?? null,
            'is_approved' => $log->IsApproved ?? false,
            'essl_created_date' => $log->CreatedDate ?? null,
            'essl_last_modified_date' => $log->LastModifiedDate ?? null,
            'location_address' => $log->LocationAddress ?? null,
            'body_temperature' => $log->BodyTemperature ?? null,
            'is_mask_on' => $log->IsMaskOn ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
