<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\BiometricAttendance;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Section;
use App\Models\Category;
use App\Models\Branch;
use App\Models\Shift;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Inertia\Inertia;

class AttendanceManagementController extends Controller
{
    use \App\Traits\AttendanceProcessor;
    use \App\Traits\LogsActivity;

    public function index(Request $request)
    {
        $activeBranchId = session('active_branch_id');

        // Build shift-wise duty rule summary for the System Calculation Rules dialog
        $shiftsForRules = Shift::with(['slots.dutyRules'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->when($activeBranchId, fn ($q) => $q->where('branch_id', $activeBranchId))
            ->orderBy('short_code')
            ->get()
            ->map(function ($shift) {
                return [
                    'id' => $shift->id,
                    'short_code' => $shift->short_code,
                    'name' => $shift->name,
                    'is_multi' => (bool) $shift->is_multi,
                    'slots' => $shift->slots->map(function ($slot) {
                        $hdRule = $slot->dutyRules->first(fn ($r) => (float) $r->duty_value === 0.5);
                        $fdRule = $slot->dutyRules->first(fn ($r) => (float) $r->duty_value === 1.0);
                        $start = substr($slot->start_time, 0, 5);
                        $end   = substr($slot->end_time, 0, 5);
                        // Duration in minutes (handle overnight)
                        [$sh, $sm] = array_map('intval', explode(':', $start));
                        [$eh, $em] = array_map('intval', explode(':', $end));
                        $dur = ($eh * 60 + $em) - ($sh * 60 + $sm);
                        if ($dur <= 0) $dur += 1440;

                        return [
                            'slot_name'      => $slot->slot_name,
                            'start_time'     => $start,
                            'end_time'       => $end,
                            'duration_mins'  => $dur,
                            'grace_before_in'  => (int) $slot->grace_before_in,
                            'grace_after_out'  => (int) $slot->grace_after_out,
                            'half_day_mins'  => $hdRule ? (int) $hdRule->min_minutes : (int) round($dur * 0.5),
                            'full_day_mins'  => $fdRule ? (int) $fdRule->min_minutes : (int) round($dur * 0.75),
                            'rules'          => $slot->dutyRules->sortBy('priority')->values()->map(fn ($r) => [
                                'rule_name'   => $r->rule_name,
                                'min_minutes' => (int) $r->min_minutes,
                                'max_minutes' => (int) $r->max_minutes,
                                'status'      => $r->status,
                                'duty_value'  => (float) $r->duty_value,
                                'color'       => $r->color,
                            ])->values(),
                        ];
                    })->values(),
                ];
            })->values();

        return Inertia::render('hr/attendance/AttendanceModule', [
            'branches' => Branch::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')->get(),
            'departments' => Department::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')
                ->when($activeBranchId, function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'sections' => Section::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')
                ->when($activeBranchId, function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'categories' => Category::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')
                ->when($activeBranchId, function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'designations' => \App\Models\Designation::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')
                ->when($activeBranchId, function ($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                })->get(),
            'shifts_for_rules' => $shiftsForRules,
            'initial_filters' => [
                'month' => $request->month ?? now()->format('Y-m'),
                'department_id' => $request->department_id ?? 'all',
                'section_id' => $request->section_id ?? 'all',
                'category_id' => $request->category_id ?? 'all',
                'branch_id' => (string) ($request->branch_id ?? session('active_branch_id', 'all')),
                'status' => 'all',
                'page' => 1,
                'per_page' => 25,
            ]
        ]);
    }

    public function getGridData(Request $request)
    {
        $month = $request->month ?? now()->format('Y-m');
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $query = Employee::active()->with(['user', 'department', 'designation', 'category', 'shift.slots.dutyRules']);

        // Filters
        if ($request->department_id && $request->department_id !== 'all') {
            $query->where('department_id', $request->department_id);
        }
        if ($request->section_id && $request->section_id !== 'all') {
            $query->where('section_id', $request->section_id);
        }
        if ($request->category_id && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }
        $branchId = $request->branch_id ?? session('active_branch_id', 'all');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('emy_code', 'like', "%$search%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%$search%");
                  });
            });
        }

        if ($request->status && $request->status !== 'all') {
            $status = $request->status;
            $query->whereHas('biometricAttendances', function($q) use ($status, $startDate, $endDate) {
                $q->whereBetween('attendance_date', [$startDate, $endDate]);
                if ($status === 'OT') {
                    $q->where('ot_minutes', '>', 0);
                } else {
                    $q->where('status', $status);
                }
            });
        }

        $allEmployeesIds = $query->pluck('id');
        
        $todayStr = now()->format('Y-m-d');
        $globalSummary = [
            'present' => 0, 'absent' => 0, 'half_day' => 0, 'mis' => 0, 'ot_count' => 0
        ];

        if ($allEmployeesIds->isNotEmpty()) {
            $summaryData = BiometricAttendance::whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->whereIn('employee_id', $allEmployeesIds)
                ->selectRaw("
                    COUNT(CASE WHEN status = 'P' THEN 1 END) as present,
                    COUNT(CASE WHEN status = 'A' AND attendance_date <= ? THEN 1 END) as absent,
                    COUNT(CASE WHEN status = 'HD' THEN 1 END) as half_day,
                    COUNT(CASE WHEN status = 'MIS' AND attendance_date < ? THEN 1 END) as mis,
                    COUNT(CASE WHEN ot_minutes > 0 THEN 1 END) as ot_count
                ", [$todayStr, $todayStr])
                ->first();
            
            if ($summaryData) {
                $globalSummary = [
                    'present' => (int)$summaryData->present,
                    'absent' => (int)$summaryData->absent,
                    'half_day' => (int)$summaryData->half_day,
                    'mis' => (int)$summaryData->mis,
                    'ot_count' => (int)$summaryData->ot_count
                ];
            }
        }

        $employees = $query
            ->leftJoin('users', 'employees.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('employees.*')
            ->paginate($request->per_page ?? 25);

        $attendanceData = collect();
        if ($employees->isNotEmpty()) {
            $attendanceData = BiometricAttendance::whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->whereIn('employee_id', $employees->pluck('id'))
                ->get();
        }
        $attendanceData = $attendanceData->groupBy('employee_id');

        $today = now()->startOfDay();
        $result = $employees->getCollection()->map(function ($emp) use ($attendanceData, $startDate, $daysInMonth, $today) {
            $empAttendance = $attendanceData->get($emp->id, collect())->keyBy(function($item) {
                return (int)Carbon::parse($item->attendance_date)->format('d');
            });

            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $isFuture = $startDate->copy()->day($d)->isAfter(now()->endOfDay());
                
                $record = $empAttendance->get($d);
                $days[$d] = $record ? [
                    'id' => $record->id,
                    'status' => $record->status,
                    'in_time' => $record->in_time ? Carbon::parse($record->in_time)->format('H:i') : null,
                    'out_time' => $record->out_time ? Carbon::parse($record->out_time)->format('H:i') : null,
                    'total_minutes' => $record->total_minutes,
                    'ot_minutes' => $record->ot_minutes,
                    'is_manual' => $record->is_manual,
                    'manual_remarks' => $record->manual_remarks,
                    'log_details' => $record->log_details,
                    'is_holiday' => $record->is_holiday,
                    'is_weekly_off' => $record->is_weekly_off,
                    'duty_value' => $record->duty_value,
                    'shift_slot_id' => $record->shift_slot_id ? (string) $record->shift_slot_id : '',
                ] : [
                    'status' => $isFuture ? '' : 'A',
                    'in_time' => null,
                    'out_time' => null,
                    'total_minutes' => 0,
                    'ot_minutes' => 0,
                    'shift_slot_id' => '',
                ];
            }

            $shiftSlots = $emp->shift?->slots->map(function ($slot) {
                $hdRule = $slot->dutyRules->first(fn ($r) => (float) $r->duty_value === 0.5);
                $fdRule = $slot->dutyRules->first(fn ($r) => (float) $r->duty_value === 1.0);
                $start = $slot->start_time ? substr((string) $slot->start_time, 0, 5) : '';
                $end = $slot->end_time ? substr((string) $slot->end_time, 0, 5) : '';
                [$sh, $sm] = array_map('intval', explode(':', $start ?: '0:0'));
                [$eh, $em] = array_map('intval', explode(':', $end ?: '0:0'));
                $dur = ($eh * 60 + $em) - ($sh * 60 + $sm);
                if ($dur <= 0) {
                    $dur += 1440;
                }

                return [
                    'id' => $slot->id,
                    'slot_name' => $slot->slot_name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_mins' => $dur,
                    'half_day_mins' => $hdRule ? (int) $hdRule->min_minutes : (int) round($dur * 0.5),
                    'full_day_mins' => $fdRule ? (int) $fdRule->min_minutes : (int) round($dur * 0.75),
                ];
            }) ?? collect();

            $firstSlot = $shiftSlots->first();
            $lastSlot = $shiftSlots->last();
            $shiftStart = is_array($firstSlot) ? ($firstSlot['start_time'] ?: '09:00') : '09:00';
            $shiftEnd = is_array($lastSlot) ? ($lastSlot['end_time'] ?: '18:00') : (is_array($firstSlot) ? ($firstSlot['end_time'] ?: '18:00') : '18:00');

            return [
                'employee' => [
                    'id' => $emp->id,
                    'name' => $emp->user->name ?? 'N/A',
                    'code' => $emp->emy_code,
                    'department' => $emp->department->name ?? 'N/A',
                    'designation' => $emp->designation->name ?? 'N/A',
                    'category' => $emp->category->name ?? 'N/A',
                    'shift' => $emp->shift->name ?? 'N/A',
                    'shift_start' => $shiftStart,
                    'shift_end' => $shiftEnd,
                    'is_multi_shift' => (bool) ($emp->shift->is_multi ?? false),
                    'slots' => $shiftSlots->values()->all(),
                ],
                'days' => $days,
                'summary' => [
                    'present' => $empAttendance->where('status', 'P')->count(),
                    'absent' => $empAttendance->where('status', 'A')->where('attendance_date', '<=', $today->format('Y-m-d'))->count(),
                    'half_day' => $empAttendance->where('status', 'HD')->count(),
                    'mis' => $empAttendance->where('status', 'MIS')->where('attendance_date', '<', $today->format('Y-m-d'))->count(),
                    'ot_hours' => round($empAttendance->sum('ot_minutes') / 60, 2),
                    'ot_minutes' => $empAttendance->sum('ot_minutes'),
                    'total_worked_hours' => round($empAttendance->sum('total_minutes') / 60, 2),
                    'total_worked_minutes' => $empAttendance->sum('total_minutes'),
                ]
            ];
        });

        return response()->json([
            'employees' => $result,
            'summary' => [
                'present' => $globalSummary['present'],
                'absent' => $globalSummary['absent'],
                'half_day' => $globalSummary['half_day'],
                'mis' => $globalSummary['mis'],
                'ot_count' => $globalSummary['ot_count'],
            ],
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'total' => $employees->total(),
                'per_page' => $employees->perPage(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ],
            'days_in_month' => $daysInMonth,
            'month_name' => $startDate->format('F Y'),
        ]);
    }

    public function updateRecord(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'date' => 'required|date',
            'status' => 'required',
            'in_time' => 'nullable',
            'out_time' => 'nullable',
            'source' => 'nullable|in:mispunch,attendance',
        ]);

        $date = Carbon::parse($request->date);
        $employee = Employee::find($request->employee_id);

        if (!$employee) {
            return response()->json(['error' => __('Employee not found')], 404);
        }
        
        $inTime = null;
        if ($request->in_time) {
            $inTime = Carbon::parse($request->date . ' ' . $request->in_time);
        }

        $outTime = null;
        if ($request->out_time) {
            $outTime = Carbon::parse($request->date . ' ' . $request->out_time);
            // Handle night shift or 24h shift if out_time is less than or equal to in_time
            if ($inTime && $outTime->lte($inTime)) {
                $outTime->addDay();
            }
        }

        $source = $request->input('source');
        if (! in_array($source, ['mispunch', 'attendance'], true)) {
            $source = strtoupper((string) $request->status) === 'MIS' ? 'mispunch' : 'attendance';
        }

        $wasCreated = false;
        $record = ActivityLogger::withoutLogging(function () use ($employee, $request, $date, $inTime, $outTime, &$wasCreated) {
            $existing = BiometricAttendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date->format('Y-m-d'))
                ->exists();

            $saved = $this->saveAttendanceRecord($employee, [
                'attendance_date' => $date->format('Y-m-d'),
                'in_time' => $inTime,
                'out_time' => $outTime,
                'status' => $request->status,
                'shift_slot_id' => $request->shift_slot_id,
                'is_manual' => true,
                'manual_by' => auth()->id(),
                'manual_remarks' => $request->remarks,
                'log_details' => $request->log_details,
                'actual_work_minutes' => $request->filled('actual_work_minutes')
                    ? (int) $request->actual_work_minutes
                    : sumWorkMinutesFromLogDetails($request->log_details),
            ]);

            $wasCreated = ! $existing;

            return $saved;
        });

        ActivityLogger::logAttendance($record->fresh(), $wasCreated ? 'created' : 'updated', $source);

        return response()->json(['success' => true, 'record' => $record]);
    }

    public function bulkPresent(Request $request)
    {
        $request->validate([
            'target' => 'required|in:all,department,designation,category,shift,employee',
            'department_id' => 'required_if:target,department|nullable|integer',
            'designation_id' => 'required_if:target,designation|nullable|integer',
            'category_id' => 'required_if:target,category|nullable|integer',
            'shift_id' => 'required_if:target,shift|nullable|integer',
            'employee_id' => 'required_if:target,employee|nullable|integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'status' => 'required|in:P,A,HD,MIS,W,H',
            'time_mode' => 'nullable|in:shift,custom',
            'multi_shift_slot' => 'nullable|in:first,second',
            'overwrite' => 'boolean',
        ]);

        set_time_limit(300);

        $query = Employee::active()->with('shift.slots');

        $branchId = session('active_branch_id');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($request->target === 'department') {
            $query->where('department_id', $request->department_id);
        } elseif ($request->target === 'designation') {
            $query->where('designation_id', $request->designation_id);
        } elseif ($request->target === 'category') {
            $query->where('category_id', $request->category_id);
        } elseif ($request->target === 'shift') {
            $query->where('shift_id', $request->shift_id);
        } elseif ($request->target === 'employee') {
            $query->where('id', $request->employee_id);
        }

        $employees = $query->get();
        if ($employees->isEmpty()) {
            return response()->json(['error' => __('No employees found for the selected target.')], 404);
        }

        $fromDate = Carbon::parse($request->from_date)->startOfDay();
        $toDate = Carbon::parse($request->to_date)->startOfDay();
        $overwrite = $request->boolean('overwrite');
        $timeMode = $request->time_mode ?: 'shift';
        $multiShiftSlot = $request->multi_shift_slot ?: 'first';
        $status = strtoupper((string) $request->status);
        $needsTimes = in_array($status, ['P', 'HD', 'MIS'], true);

        $inTimeStr = $request->in_time ?: '09:00';
        $outTimeStr = $request->out_time ?: '18:00';

        $totalUpdated = 0;

        ActivityLogger::withoutLogging(function () use (
            $employees,
            $fromDate,
            $toDate,
            $overwrite,
            $timeMode,
            $multiShiftSlot,
            $status,
            $needsTimes,
            $inTimeStr,
            $outTimeStr,
            $request,
            &$totalUpdated
        ) {
            foreach ($employees as $emp) {
                $empInStr = $inTimeStr;
                $empOutStr = $outTimeStr;
                $empSlotId = null;

                if ($needsTimes && $timeMode === 'shift') {
                    $slots = $emp->shift?->slots?->sortBy('priority')->values();
                    $targetSlot = null;
                    if ($slots && $slots->count() > 0) {
                        if ($emp->shift?->is_multi && $multiShiftSlot === 'second' && $slots->count() > 1) {
                            $targetSlot = $slots->get(1);
                        } else {
                            $targetSlot = $slots->get(0);
                        }
                    }

                    if ($targetSlot) {
                        $empInStr = Carbon::parse($targetSlot->start_time)->format('H:i');
                        $empOutStr = Carbon::parse($targetSlot->end_time)->format('H:i');
                        $empSlotId = $targetSlot->id;
                    }
                }

                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');

                    $existing = BiometricAttendance::where('employee_id', $emp->id)
                        ->whereDate('attendance_date', $dateStr)
                        ->first();

                    if ($existing && ! $overwrite && $existing->status !== 'A') {
                        continue;
                    }

                    $inTime = null;
                    $outTime = null;
                    $logDetails = '';

                    if ($needsTimes) {
                        $inTime = Carbon::parse($dateStr.' '.$empInStr);
                        $outTime = Carbon::parse($dateStr.' '.$empOutStr);
                        if ($outTime->lte($inTime)) {
                            $outTime->addDay();
                        }
                        $logDetails = $empInStr.' IN, '.$empOutStr.' OUT';
                    }

                    $saved = $this->saveAttendanceRecord($emp, [
                        'attendance_date' => $dateStr,
                        'in_time' => $inTime,
                        'out_time' => $outTime,
                        'status' => $status,
                        'shift_slot_id' => $empSlotId,
                        'is_manual' => true,
                        'manual_by' => auth()->id(),
                        'manual_remarks' => $request->remarks ?: 'Bulk assigned',
                        'log_details' => $logDetails,
                    ]);

                    if (in_array($status, ['W', 'H'], true)) {
                        $saved->update([
                            'status' => $status,
                            'is_weekly_off' => $status === 'W',
                            'is_holiday' => $status === 'H',
                            'in_time' => null,
                            'out_time' => null,
                            'log_details' => null,
                        ]);
                    }

                    $totalUpdated++;
                }
            }
        });

        $targetDesc = 'All Employees';
        if ($request->target === 'department' && $request->department_id) {
            $dept = \App\Models\Department::find($request->department_id);
            $targetDesc = 'Department: '.($dept->name ?? $request->department_id);
        } elseif ($request->target === 'designation' && $request->designation_id) {
            $desig = \App\Models\Designation::find($request->designation_id);
            $targetDesc = 'Designation: '.($desig->name ?? $request->designation_id);
        } elseif ($request->target === 'category' && $request->category_id) {
            $cat = \App\Models\Category::find($request->category_id);
            $targetDesc = 'Category: '.($cat->name ?? $request->category_id);
        } elseif ($request->target === 'shift' && $request->shift_id) {
            $shift = Shift::find($request->shift_id);
            $targetDesc = 'Shift: '.($shift?->short_code ?: $shift?->name ?: $request->shift_id);
        } elseif ($request->target === 'employee' && $request->employee_id) {
            $emp = Employee::withoutGlobalScopes()->find($request->employee_id);
            $emp?->loadMissing('user');
            $targetDesc = 'Employee: '.($emp?->user?->name ?? $emp?->emy_code ?? $request->employee_id);
        }

        $statusLabel = ActivityLogger::humanAttendanceStatus($status);
        $branchIdForLog = ($branchId && $branchId !== 'all') ? (int) $branchId : null;

        ActivityLogger::log(
            'Attendance',
            'bulk_updated',
            "Bulk attendance: {$totalUpdated} day(s) marked as {$statusLabel} for {$targetDesc} ({$fromDate->format('d M Y')} to {$toDate->format('d M Y')})"
            .($request->remarks ? ' — '.$request->remarks : ''),
            $branchIdForLog
        );

        return response()->json([
            'success' => true,
            'message' => "Successfully updated {$totalUpdated} records.",
            'updated_count' => $totalUpdated,
        ]);
    }
}
