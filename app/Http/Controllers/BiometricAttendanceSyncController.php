<?php

namespace App\Http\Controllers;

use App\Models\BiometricAttendance;
use App\Models\Employee;
use App\Models\EsslLog;
use App\Models\Holiday;
use App\Models\WeekOff;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiometricAttendanceSyncController extends Controller
{
    use \App\Traits\AttendanceProcessor;

    /**
     * Display the sync dashboard.
     */
    public function index(Request $request)
    {
        $activeBranchId = $this->resolveBranchFilter($request);

        $activeEmployeeIds = Employee::withoutGlobalScopes()
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->pluck('id');

        $query = BiometricAttendance::query()
            ->with(['employee.user', 'employee.department', 'employee.designation', 'employee.branch', 'employee.shift.slots', 'logs', 'manualUser'])
            ->whereIn('employee_id', $activeEmployeeIds)
            ->where('punch_count', '>', 0)
            ->orderByDesc('attendance_date')
            ->orderBy('employee_code');

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%$search%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->withoutGlobalScopes()
                            ->whereHas('user', function ($qu) use ($search) {
                                $qu->where('name', 'like', "%$search%");
                            });
                    });
            });
        }

        // Branch Filter — direct column (indexed), no whereHas
        if ($activeBranchId && $activeBranchId !== 'all') {
            $query->where('branch_id', $activeBranchId);
        }

        // Department Filter
        if ($request->filled('department_id') && $request->department_id !== 'all') {
            $query->where('department_id', $request->department_id);
        }

        // Category Filter
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        // Section Filter
        if ($request->filled('section_id') && $request->section_id !== 'all') {
            $query->where('section_id', $request->section_id);
        }

        // Status Filter
        $status = $request->get('status', 'MIS');
        if ($status !== 'all') {
            if ($status === 'MIS') {
                $query->where('status', 'MIS')
                    ->where('attendance_date', '<', now()->format('Y-m-d'));
            } elseif ($status === 'manual_cleared') {
                $query->where('is_manual', true)
                    ->where('status', 'P');
            } elseif ($status === 'P') {
                $query->where(function ($q) {
                    $q->where('status', 'P')
                        ->orWhere(function ($sq) {
                            $sq->where('status', 'MIS')
                                ->where('attendance_date', now()->format('Y-m-d'));
                        });
                });
            } elseif ($status === 'HD') {
                $query->where('status', 'HD');
            } elseif ($status === 'A') {
                $query->where('status', 'A');
            } else {
                $query->where('status', $status);
            }
        }

        // Entry source: device sync vs manual correction
        $entrySource = $request->get('entry_source', 'all');
        if ($entrySource === 'manual') {
            $query->where('is_manual', true);
        } elseif ($entrySource === 'auto') {
            $query->where(function ($q) {
                $q->where('is_manual', false)->orWhereNull('is_manual');
            });
        }

        // Date Range Filter — only when user explicitly applied dates (use_dates=1)
        $applyDateFilter = $request->boolean('use_dates');
        if ($applyDateFilter) {
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('attendance_date', [
                    Carbon::parse($request->from_date)->format('Y-m-d'),
                    Carbon::parse($request->to_date)->format('Y-m-d'),
                ]);
            } elseif ($request->filled('from_date')) {
                $query->where('attendance_date', '>=', Carbon::parse($request->from_date)->format('Y-m-d'));
            } elseif ($request->filled('to_date')) {
                $query->where('attendance_date', '<=', Carbon::parse($request->to_date)->format('Y-m-d'));
            }
        }

        return \Inertia\Inertia::render('hr/attendance/sync', [
            'branches' => \App\Models\Branch::where('status', 'active')->get(),
            'departments' => \App\Models\Department::where('status', 'active')
                ->when($activeBranchId && $activeBranchId !== 'all', function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'sections' => \App\Models\Section::withoutGlobalScopes()->where('status', 'active')
                ->when($activeBranchId && $activeBranchId !== 'all', function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'categories' => \App\Models\Category::withoutGlobalScopes()->where('status', 'active')
                ->when($activeBranchId && $activeBranchId !== 'all', function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'last_sync' => BiometricAttendance::max('updated_at'),
            'records' => tap($query->paginate(25), function ($paginator) {
                $paginator->setCollection(
                    $paginator->getCollection()
                        ->filter(fn ($record) => !($record->status === 'MIS' && recordIsDeferredOpenInMispunch($record)))
                        ->values()
                );
            })->through(function ($record) {
                $record->setAttribute('log_details', getStoredLogDetailsFromRecord($record));
                $employee = resolveEmployeeForBiometricRecord($record);
                if ($employee && !$record->relationLoaded('employee')) {
                    $record->setRelation('employee', $employee);
                }
                $record->setAttribute('employee_display_name', getEmployeeDisplayNameForRecord($record));

                $isInManual = false;
                $isOutManual = false;
                if ($record->is_manual) {
                    if ($record->logs && $record->logs->isNotEmpty()) {
                        $isInManual = $record->logs->where('punch_type', 'IN')->where('is_manual', true)->isNotEmpty();
                        $isOutManual = $record->logs->where('punch_type', 'OUT')->where('is_manual', true)->isNotEmpty();
                    } elseif ($record->status === 'MIS') {
                        $isInManual = (bool) ($record->in_time && $record->in_count > 0);
                        $isOutManual = (bool) ($record->out_time && $record->out_count > 0);
                    } else {
                        $isInManual = (bool) $record->in_time;
                        $isOutManual = (bool) $record->out_time;
                    }
                }

                $record->setAttribute('is_in_manual', $isInManual);
                $record->setAttribute('is_out_manual', $isOutManual);
                $record->setAttribute('manual_by_name', $record->manualUser?->name);
                $record->setAttribute('is_mispunch_cleared', $record->is_manual && $record->status === 'P');

                $shiftSlot = $record->shift_slot_id
                    ? \App\Models\ShiftSlot::find($record->shift_slot_id)
                    : null;
                $shiftStart = $shiftSlot?->start_time ?? $record->employee?->shift?->slots?->first()?->start_time;
                $shiftEnd = $shiftSlot?->end_time ?? $record->employee?->shift?->slots?->last()?->end_time;
                $record->setAttribute('shift_start', $shiftStart ? substr((string) $shiftStart, 0, 5) : null);
                $record->setAttribute('shift_end', $shiftEnd ? substr((string) $shiftEnd, 0, 5) : null);

                return $record;
            })->withQueryString(),
            'filters' => [
                'search' => $request->input('search', ''),
                'from_date' => $applyDateFilter ? ($request->input('from_date') ?: '') : '',
                'to_date' => $applyDateFilter ? ($request->input('to_date') ?: '') : '',
                'use_dates' => $applyDateFilter,
                'branch_id' => $activeBranchId ?: 'all',
                'department_id' => $request->input('department_id', 'all'),
                'category_id' => $request->input('category_id', 'all'),
                'section_id' => $request->input('section_id', 'all'),
                'status' => $status,
                'entry_source' => $entrySource,
            ],
        ]);
    }

    /**
     * HTTP sync — redirects with flash message.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = $this->runSync($request);

        return redirect()->back()->with(
            'success',
            "Attendance processed for {$result['processed_count']} records between {$result['from_label']} and {$result['to_label']}!"
        );
    }

    /**
     * Core attendance processing — used by ESSL chunk sync, artisan, and cron.
     *
     * @return array{processed_count: int, from_label: string, to_label: string}
     */
    public function runSync(Request $request): array
    {
        set_time_limit(0);

        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = ActivityLogger::withoutLogging(function () use ($request) {
            return $this->performRunSync($request);
        });

        if (auth()->check()) {
            ActivityLogger::log(
                'Attendance Sync',
                'synced',
                "Synced biometric attendance for {$result['processed_count']} records ({$result['from_label']} to {$result['to_label']})"
            );
        }

        return $result;
    }

    protected function performRunSync(Request $request): array
    {
        $fromDate = Carbon::parse($request->from_date)->startOfDay();
        $toDate = Carbon::parse($request->to_date)->endOfDay();

        // Fetch window: ±1 day; at month-end extend +1 day through 20:00 for night OUT in next month table
        $logFetchFrom = $fromDate->copy()->subDay();
        $logFetchTo = $toDate->copy()->addDay();
        if ($toDate->isLastOfMonth()) {
            $logFetchTo = $toDate->copy()->addDay()->setTime(20, 0, 0);
        }

        $activeBranchId = $this->resolveBranchFilter($request);
        $employeesQuery = Employee::with(['shift.slots', 'branch', 'department']);
        if ($activeBranchId && $activeBranchId !== 'all') {
            $employeesQuery->where('branch_id', $activeBranchId);
        }
        if ($request->has('employee_id') && $request->employee_id) {
            $employeesQuery->where('user_id', $request->employee_id);
        }
        $employees = $employeesQuery->get();
        $employeeUserIds = $employees->pluck('user_id')->filter()->unique()->toArray();

        // Bulk fetch all logs for the period
        $allLogsGrouped = EsslLog::whereIn('user_id', $employeeUserIds)
            ->whereBetween('log_date', [$logFetchFrom, $logFetchTo])
            ->orderBy('log_date', 'asc')
            ->get()
            ->groupBy('user_id');

        \Log::info('Sync Process Started', [
            'total_employees' => $employees->count(),
            'total_logs_found' => $allLogsGrouped->flatten()->count(),
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString()
        ]);

        $processedCount = 0;
        foreach ($employees as $emp) {
            $userId = $emp->user_id;
            $userLogs = $allLogsGrouped->get($userId, collect());

            if ($userLogs->isEmpty()) {
                $this->processNoLogs($emp, $fromDate, $toDate);
                continue;
            }

            // 2. Pair punches sequentially, assign to attendance date — no skipping
            $dedupedLogs = $this->deduplicateRawLogs($userLogs);
            $punchesByDate = groupEmployeePunchesByAttendanceDate($emp, $dedupedLogs);

            foreach ($punchesByDate as $attDate => $punches) {
                if (!Carbon::parse($attDate)->between($fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay())) {
                    continue;
                }
                if (empty($punches)) {
                    continue;
                }

                $analysis = analyzePunchSequence($punches);
                $isMisPunch = $analysis['is_mis_punch'];
                $openInCarbon = getOpenInCarbonFromLogDetails($attDate, $analysis['log_details']);
                if (
                    $isMisPunch
                    && logDetailsHasOpenIn($analysis['log_details'])
                    && shouldDeferOpenInMispunch($emp, $attDate, null, $openInCarbon)
                ) {
                    $isMisPunch = false;
                }

                $consolidated = [
                    'attendance_date' => $attDate,
                    'in_time' => $analysis['first_in'],
                    'out_time' => $analysis['last_out'],
                    'in_count' => $analysis['in_count'],
                    'out_count' => $analysis['out_count'],
                    'is_mis_punch' => $isMisPunch,
                    'actual_work_minutes' => $analysis['work_minutes'],
                    'log_details' => $analysis['log_details'],
                    'primary_source' => 'essl',
                ];

                $this->saveAttendanceRecord($emp, $consolidated);
                $processedCount++;
            }

            $this->clearMisplacedManualAttendanceInRange($emp, $fromDate, $toDate, $dedupedLogs, $punchesByDate);
            $this->reconcileGapDaysInSyncRange($emp, $fromDate, $toDate, $punchesByDate);
        }

        \Log::info("Sync Process Completed. Total processed: $processedCount");

        if ($activeBranchId && $activeBranchId !== 'all') {
            updateSetting('last_biometric_sync_date_branch_' . $activeBranchId, $toDate->format('Y-m-d'));
        }
        updateSetting('last_biometric_sync_date', $toDate->format('Y-m-d'));

        return [
            'processed_count' => $processedCount,
            'from_label' => $fromDate->format('d-m-Y'),
            'to_label' => $toDate->format('d-m-Y'),
        ];
    }

    /**
     * Remove duplicate device punches (same direction at the same minute).
     */
    private function deduplicateRawLogs($allLogs)
    {
        $deduped = [];
        $prevKey = null;

        foreach ($allLogs as $log) {
            $dir = strtolower($log->direction);
            if ($dir === '0') {
                $dir = 'in';
            }
            if ($dir === '1') {
                $dir = 'out';
            }
            $key = $dir . '|' . Carbon::parse($log->log_date)->format('Y-m-d H:i');
            if ($key === $prevKey) {
                continue;
            }
            $deduped[] = $log;
            $prevKey = $key;
        }

        return $deduped;
    }

    /**
     * Manually update a biometric attendance record.
     */
    public function updateRecord(Request $request, BiometricAttendance $record)
    {
        $request->validate([
            'in_time' => 'nullable|date',
            'out_time' => 'nullable|date',
            'status' => 'required|string|in:P,MIS',
        ]);

        $emp = $record->employee;
        if (!$emp) {
            return redirect()->back()->with('error', 'Employee not found for this record.');
        }

        $attendanceDate = $record->attendance_date;

        $inTime = null;
        if ($request->in_time) {
            $inTime = Carbon::parse($request->in_time);
            // Force the date to be the attendance date
            $inTime->setDate($attendanceDate->year, $attendanceDate->month, $attendanceDate->day);
        }

        $outTime = null;
        if ($request->out_time) {
            $outTime = Carbon::parse($request->out_time);
            // Force the date to be the attendance date initially
            $outTime->setDate($attendanceDate->year, $attendanceDate->month, $attendanceDate->day);

            // If out_time is before in_time, it's a night shift (next day)
            if ($inTime && $outTime->lt($inTime)) {
                $outTime->addDay();
            }
        }

        $inCount = $inTime ? 1 : 0;
        $outCount = $outTime ? 1 : 0;
        if ($request->has('log_details') && !empty($request->log_details)) {
            $inCount = substr_count(strtoupper($request->log_details), ' IN');
            $outCount = substr_count(strtoupper($request->log_details), ' OUT');
        }

        $logDetails = $request->log_details;
        if ($logDetails) {
            $logDetails = normalizeLogDetails($logDetails);
        }
        $actualWorkMinutes = $request->filled('actual_work_minutes')
            ? (int) $request->actual_work_minutes
            : sumWorkMinutesFromLogDetails($logDetails);

        // Prepare record data for saveAttendance logic
        $recordData = [
            'attendance_date' => $attendanceDate->format('Y-m-d'),
            'in_time' => $inTime,
            'out_time' => $outTime,
            'in_count' => max($inCount, $inTime ? 1 : 0),
            'out_count' => max($outCount, $outTime ? 1 : 0),
            'is_mis_punch' => (!$inTime || !$outTime) && $request->status !== 'A',
            'is_manual' => true,
            'manual_by' => auth()->id(),
            'log_details' => $logDetails,
            'actual_work_minutes' => $actualWorkMinutes,
        ];

        ActivityLogger::withoutLogging(function () use ($emp, $recordData, $record, $request, $inTime, $outTime) {
            $this->saveAttendanceRecord($emp, $recordData);

            $record->refresh();
            $record->status = resolveManualMispunchStatus(
                $request->log_details,
                $request->status,
                (bool) $inTime,
                (bool) $outTime
            );
            $record->is_manual = true;
            if (auth()->id()) {
                $record->manual_by = auth()->id();
            }
            if ($request->has('log_details')) {
                $record->log_details = $request->log_details;
            }
            $record->save();
        });

        $source = $request->status === 'MIS' || (! $inTime || ! $outTime) ? 'mispunch' : 'sync_engine';
        ActivityLogger::logAttendance($record->fresh(), 'updated', $source);

        return redirect()->back()->with('success', 'Attendance record updated successfully.');
    }

    public function bulkUpdate(Request $request)
    {
        if ($request->has('edits')) {
            $request->validate([
                'edits' => 'required|array',
                'edits.*.in_time' => 'nullable|string',
                'edits.*.out_time' => 'nullable|string',
                'edits.*.status' => 'required|string|in:P,MIS',
                'edits.*.log_details' => 'nullable|string',
            ]);

            $edits = $request->edits;
            $updatedCount = 0;

            \DB::transaction(function () use ($edits, &$updatedCount) {
                $ids = array_keys($edits);
                $records = BiometricAttendance::whereIn('id', $ids)->get()->keyBy('id');

                foreach ($edits as $id => $editData) {
                    $record = $records->get($id);
                    if (!$record) {
                        continue;
                    }

                    $emp = $record->employee;
                    if (!$emp) {
                        continue;
                    }

                    $attendanceDate = $record->attendance_date;
                    $inTimeStr = $editData['in_time'];
                    $outTimeStr = $editData['out_time'];
                    $status = $editData['status'];

                    $empShift = $emp->shift;
                    $sInStr = $empShift?->slots->first()?->start_time ?? '08:00';
                    $sOutStr = $empShift?->slots->last()?->end_time ?? '18:00';

                    if ($record->shift_slot_id) {
                        $matchedSlot = \App\Models\ShiftSlot::find($record->shift_slot_id);
                        if ($matchedSlot) {
                            $sInStr = $matchedSlot->start_time;
                            $sOutStr = $matchedSlot->end_time;
                        }
                    }

                    $inTime = null;
                    if ($inTimeStr) {
                        $inTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $inTimeStr);
                    } else {
                        if ($record->in_time) {
                            $inTime = Carbon::parse($record->in_time);
                        } else {
                            $inTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $sInStr);
                        }
                    }

                    $outTime = null;
                    if ($outTimeStr) {
                        $outTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $outTimeStr);
                    } else {
                        if ($record->out_time) {
                            $outTime = Carbon::parse($record->out_time);
                        } else {
                            $outTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $sOutStr);
                        }
                    }

                    // If out_time is before in_time, it's a night shift (next day)
                    if ($inTime && $outTime && $outTime->lt($inTime)) {
                        $outTime->addDay();
                    }

                    $inCount = $inTime ? 1 : 0;
                    $outCount = $outTime ? 1 : 0;
                    if (isset($editData['log_details']) && !empty($editData['log_details'])) {
                        $inCount = substr_count(strtoupper($editData['log_details']), ' IN');
                        $outCount = substr_count(strtoupper($editData['log_details']), ' OUT');
                    }

                    $logDetails = $editData['log_details'] ?? null;
                    if ($logDetails) {
                        $logDetails = normalizeLogDetails($logDetails);
                    }
                    $actualWorkMinutes = isset($editData['actual_work_minutes'])
                        ? (int) $editData['actual_work_minutes']
                        : sumWorkMinutesFromLogDetails($logDetails);

                    // Prepare record data for saveAttendance logic
                    $recordData = [
                        'attendance_date' => $attendanceDate->format('Y-m-d'),
                        'in_time' => $inTime,
                        'out_time' => $outTime,
                        'in_count' => max($inCount, $inTime ? 1 : 0),
                        'out_count' => max($outCount, $outTime ? 1 : 0),
                        'is_mis_punch' => (!$inTime || !$outTime) && $status !== 'A',
                        'is_manual' => true,
                        'manual_by' => auth()->id(),
                        'log_details' => $logDetails,
                        'actual_work_minutes' => $actualWorkMinutes,
                    ];

                    ActivityLogger::withoutLogging(function () use ($emp, $recordData, $record, $editData, $inTime, $outTime, $status) {
                        $this->saveAttendanceRecord($emp, $recordData);

                        $record->refresh();
                        $record->status = resolveManualMispunchStatus(
                            $editData['log_details'] ?? null,
                            $status,
                            (bool) $inTime,
                            (bool) $outTime
                        );
                        $record->is_manual = true;
                        if (isset($editData['log_details'])) {
                            $record->log_details = $editData['log_details'];
                        }
                        $record->save();
                    });

                    $logSource = $status === 'MIS' || (! $inTime || ! $outTime) ? 'mispunch' : 'sync_engine';
                    ActivityLogger::logAttendance($record->fresh(), 'updated', $logSource);

                    $updatedCount++;
                }
            });

            if ($updatedCount > 0) {
                }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully updated {$updatedCount} attendance records."
                ]);
            }

            return redirect()->back()->with('success', "Successfully updated {$updatedCount} attendance records.");
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:biometric_attendances,id',
            'in_time' => 'nullable|string',
            'out_time' => 'nullable|string',
            'status' => 'required|string|in:P,MIS',
        ]);

        $ids = $request->ids;
        $inTimeStr = $request->in_time;
        $outTimeStr = $request->out_time;
        $status = $request->status;

        $updatedCount = 0;

        \DB::transaction(function () use ($ids, $inTimeStr, $outTimeStr, $status, &$updatedCount) {
            $records = BiometricAttendance::whereIn('id', $ids)->get();

            foreach ($records as $record) {
                $emp = $record->employee;
                if (!$emp) {
                    continue;
                }

                $attendanceDate = $record->attendance_date;

                $empShift = $emp->shift;
                $sInStr = $empShift?->slots->first()?->start_time ?? '08:00';
                $sOutStr = $empShift?->slots->last()?->end_time ?? '18:00';

                if ($record->shift_slot_id) {
                    $matchedSlot = \App\Models\ShiftSlot::find($record->shift_slot_id);
                    if ($matchedSlot) {
                        $sInStr = $matchedSlot->start_time;
                        $sOutStr = $matchedSlot->end_time;
                    }
                }

                $inTime = null;
                if ($inTimeStr) {
                    $inTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $inTimeStr);
                } else {
                    if ($record->in_time) {
                        $inTime = Carbon::parse($record->in_time);
                    } else {
                        $inTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $sInStr);
                    }
                }

                $outTime = null;
                if ($outTimeStr) {
                    $outTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $outTimeStr);
                } else {
                    if ($record->out_time) {
                        $outTime = Carbon::parse($record->out_time);
                    } else {
                        $outTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $sOutStr);
                    }
                }

                // If out_time is before in_time, it's a night shift (next day)
                if ($inTime && $outTime && $outTime->lt($inTime)) {
                    $outTime->addDay();
                }

                // Prepare record data for saveAttendance logic
                $recordData = [
                    'attendance_date' => $attendanceDate->format('Y-m-d'),
                    'in_time' => $inTime,
                    'out_time' => $outTime,
                    'in_count' => $inTime ? 1 : 0,
                    'out_count' => $outTime ? 1 : 0,
                    'is_mis_punch' => (!$inTime || !$outTime) && $status !== 'A',
                ];

                ActivityLogger::withoutLogging(function () use ($emp, $recordData, $record, $inTime, $outTime, $status) {
                    $this->saveAttendanceRecord($emp, $recordData);

                    $record->refresh();
                    $record->status = resolveManualMispunchStatus(
                        null,
                        $status,
                        (bool) $inTime,
                        (bool) $outTime
                    );
                    $record->is_manual = true;
                    $record->manual_by = auth()->id();
                    $record->save();
                });

                $logSource = $status === 'MIS' || (! $inTime || ! $outTime) ? 'mispunch' : 'sync_engine';
                ActivityLogger::logAttendance($record->fresh(), 'updated', $logSource);

                $updatedCount++;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} attendance records."
            ]);
        }

        return redirect()->back()->with('success', "Successfully updated {$updatedCount} attendance records.");
    }


    /**
     * Manual row jisme punches is calendar date par ESSL mein nahi — galat shift-day placement.
     */
    private function clearMisplacedManualAttendanceInRange($emp, Carbon $fromDate, Carbon $toDate, array $esslLogs, array $punchesByDate): void
    {
        $records = BiometricAttendance::where('employee_id', $emp->id)
            ->whereBetween('attendance_date', [
                $fromDate->format('Y-m-d'),
                $toDate->format('Y-m-d'),
            ])
            ->where('is_manual', true)
            ->get();

        foreach ($records as $existing) {
            $dateStr = Carbon::parse($existing->attendance_date)->format('Y-m-d');
            $logDetails = getStoredLogDetailsFromRecord($existing);

            if ($logDetails === '') {
                continue;
            }

            $onThisShiftDay = array_key_exists($dateStr, $punchesByDate);
            $matchesEssl = attendanceLogDetailsMatchEsslOnDate($esslLogs, $dateStr, $logDetails);

            if ($onThisShiftDay && $matchesEssl) {
                continue;
            }

            if (!$matchesEssl) {
                $existing->forceFill([
                    'is_manual' => false,
                    'manual_by' => null,
                    'manual_remarks' => null,
                ])->save();
            }
        }
    }

    /**
     * Sync range mein jin dinon par koi punch group nahi — purani galat MIS/log hata kar A/H/W.
     */
    private function reconcileGapDaysInSyncRange($emp, Carbon $fromDate, Carbon $toDate, array $punchesByDate): void
    {
        $activeDates = array_keys($punchesByDate);
        $current = $fromDate->copy();

        while ($current->lte($toDate)) {
            $dateStr = $current->format('Y-m-d');

            if (in_array($dateStr, $activeDates, true)) {
                $current->addDay();
                continue;
            }

            $existing = BiometricAttendance::where('employee_id', $emp->id)
                ->whereDate('attendance_date', $dateStr)
                ->first();

            if ($existing && ($existing->is_manual || $existing->primary_source === 'mobile')) {
                $current->addDay();
                continue;
            }

            $isHoliday = Holiday::whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->exists();
            $employeeWeekOffs = explode(',', $emp->week_off ?? 'Sunday');
            $isWeeklyOff = in_array($current->format('l'), $employeeWeekOffs);
            $status = $isHoliday ? 'H' : ($isWeeklyOff ? 'W' : 'A');

            $this->saveAttendanceRecord($emp, [
                'attendance_date' => $dateStr,
                'in_time' => null,
                'out_time' => null,
                'in_count' => 0,
                'out_count' => 0,
                'is_mis_punch' => false,
                'log_details' => '',
                'actual_work_minutes' => 0,
                'status' => $status,
            ]);

            $current->addDay();
        }
    }

    private function processNoLogs($emp, $fromDate, $toDate)
    {
        $current = $fromDate->copy();
        while ($current->lte($toDate)) {
            $dateStr = $current->format('Y-m-d');

            $isHoliday = Holiday::whereDate('start_date', '<=', $dateStr)->whereDate('end_date', '>=', $dateStr)->exists();

            $employeeWeekOffs = explode(',', $emp->week_off ?? 'Sunday');
            $isWeeklyOff = in_array($current->format('l'), $employeeWeekOffs);

            $status = 'A';
            if ($isHoliday)
                $status = 'H';
            elseif ($isWeeklyOff)
                $status = 'W';

            $existing = BiometricAttendance::where('employee_id', $emp->id)
                ->whereDate('attendance_date', $dateStr)
                ->first();

            if ($existing && ($existing->is_manual || $existing->primary_source === 'mobile')) {
                $current->addDay();
                continue;
            }

            BiometricAttendance::updateOrCreate(
                ['employee_id' => $emp->id, 'attendance_date' => $dateStr],
                [
                    'employee_code' => $emp->emy_code,
                    'department_id' => $emp->department_id,
                    'branch_id' => $emp->branch_id,
                    'status' => $status,
                    'is_holiday' => $isHoliday,
                    'is_weekly_off' => $isWeeklyOff,
                    'duty_value' => ($isHoliday || $isWeeklyOff) ? 0.0 : 0.0 // Change to 0.0 to avoid over-counting in shift details
                ]
            );
            $current->addDay();
        }
    }

    /**
     * Use global active branch by default; "all" only when explicitly chosen in filters.
     */
    private function resolveBranchFilter(Request $request): mixed
    {
        $branchParam = $request->query('branch_id');

        if ($branchParam === null) {
            return session('active_branch_id');
        }

        if ($branchParam === 'all') {
            return 'all';
        }

        return $branchParam;
    }
}
