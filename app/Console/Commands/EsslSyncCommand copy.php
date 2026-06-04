<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\EsslLog;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;

class EsslSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'essl:sync {--date= : Start date (alias for --from)} {--from= : Start date for synchronization} {--to= : End date for synchronization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize biometric logs from ESSL database for a specific date range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        set_time_limit(0);
        $startDate = $this->option('from') ?: ($this->option('date') ?: '2026-04-01');
        $endDate = $this->option('to') ?: Carbon::now()->toDateTimeString();

        $this->info("Starting ESSL synchronization from $startDate to $endDate...");

        try {
            $dt = Carbon::parse($startDate)->startOfDay();
            $endDt = Carbon::parse($endDate);
            
            // If endDate is just a date (no time), make it end of day
            if (strlen($endDate) <= 10) {
                $endDt->endOfDay();
            }

            $newRecords = 0;
            $mappedRecords = 0;

            // Fetch employee names from ESSL once
            $esslEmployeeNames = DB::connection('essl')->table('Employees')
                ->pluck('EmployeeName', 'EmployeeCode')
                ->toArray();

            $directIdMap = User::where('type', 'employee')->pluck('id', 'id')->toArray();
            $emyCodeMap = Employee::withoutGlobalScopes()->pluck('user_id', 'emy_code')->toArray();
            $esslIdMap = Employee::withoutGlobalScopes()->pluck('user_id', 'essl_id')->toArray();
            $employeeIdMap = Employee::withoutGlobalScopes()->pluck('user_id', 'employee_id')->toArray();

            // Iterate through each month from start date to now
            while ($dt->lte($endDt)) {
                $currentMonth = $dt->format('n');
                $currentYear = $dt->format('Y');
                $tableName = "DeviceLogs_{$currentMonth}_{$currentYear}";

                if (!Schema::connection('essl')->hasTable($tableName)) {
                    $tableName = 'AttLog';
                    if (!Schema::connection('essl')->hasTable($tableName)) {
                        $this->error("Neither $tableName nor AttLog table found for {$dt->format('M Y')}. Skipping...");
                        $dt->addMonth()->startOfMonth();
                        continue;
                    }
                }

                $this->info("Syncing from table: $tableName for {$dt->format('F Y')}");

                $query = DB::connection('essl')->table($tableName)
                    ->where('LogDate', '>=', $dt->toDateString());

                if ($dt->format('Y-m') === $endDt->format('Y-m')) {
                    $query->where('LogDate', '<=', $endDt->toDateTimeString());
                } else {
                    $query->where('LogDate', '<=', $dt->copy()->endOfMonth()->toDateTimeString());
                }

                $logs = $query->orderBy('LogDate', 'asc')->get();
                $this->info("Found " . $logs->count() . " logs in $tableName.");

                foreach ($logs as $log) {
                    if (EsslLog::where('device_log_id', $log->DeviceLogId)->exists()) {
                        continue;
                    }

                    $esslUserId = (string) $log->UserId;
                    $trimmedId = ltrim($esslUserId, '0');

                    $localUserId = $directIdMap[(int) $esslUserId]
                        ?? ($emyCodeMap[$esslUserId]
                            ?? ($emyCodeMap[$trimmedId]
                                ?? ($esslIdMap[$esslUserId]
                                    ?? ($esslIdMap[$trimmedId]
                                        ?? ($employeeIdMap[$esslUserId]
                                            ?? null)))));

                    if (!$localUserId) {
                        continue;
                    }

                    EsslLog::create([
                        'device_log_id' => $log->DeviceLogId,
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
                    ]);

                    $newRecords++;
                    $mappedRecords++;
                }

                $dt->addMonth()->startOfMonth();
                if ($tableName === 'AttLog')
                    break;
            }

            $this->info("Synchronization complete!");
            $this->info("New logs added: $newRecords");
            $this->info("Mapped to employees: $mappedRecords");

        } catch (\Exception $e) {
            $this->error("Synchronization failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
