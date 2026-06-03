<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AttendanceMatrixReportBuilder
{
    /**
     * Build workerwise / departmentwise matrix data (same structure as PDF).
     *
     * @return array<string, mixed>|null
     */
    public static function build(
        Request $request,
        string $reportId,
        ?Collection $employees = null,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        ?array $dates = null,
        ?string $status = null
    ): ?array {
        $branchId = $request->get('branch_id') ?? session('active_branch_id') ?? (auth()->user()->branch_id ?? 1);
        $reportType = $request->get('report_type', 'codewise');
        $fromDate = $fromDate ?? ($request->get('from_date') ? Carbon::parse($request->get('from_date')) : now());
        $toDate = $toDate ?? ($request->get('to_date') ? Carbon::parse($request->get('to_date')) : now());
        $sectionId = $request->get('section');
        $deptId = $request->get('department');
        $catId = $request->get('category');
        $poStatus = $request->get('po_status');
        $status = $status ?? $request->get('status', 'all');

        if ($employees === null) {
            $query = Employee::withoutGlobalScopes()
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->with(['user', 'department', 'section', 'shift', 'category', 'designation']);

            if ($branchId && $branchId !== 'all') {
                $query->where('branch_id', $branchId);
            }

            if ($reportType === 'dayshift') {
                $query->whereHas('shift', function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('is_night_shift', '!=', 1)->orWhereNull('is_night_shift');
                    });
                });
            } elseif ($reportType === 'nightshift') {
                $query->whereHas('shift', fn ($q) => $q->where('is_night_shift', 1));
            }

            if ($poStatus && $poStatus !== 'all') {
                $query->where('po_status', $poStatus === 'P' ? 'Permanent' : 'Other');
            }
            if ($catId && $catId !== 'all') {
                $query->where('category_id', $catId);
            }
            if ($deptId && $deptId !== 'all') {
                $query->where('department_id', $deptId);
            }
            if ($sectionId && $sectionId !== 'all') {
                $query->where('section_id', $sectionId);
            }

            $employeeId = $request->get('employee_id');
            if ($employeeId && $employeeId !== 'all') {
                $query->where('id', $employeeId);
            }

            $employees = $query->orderBy('emy_code')->get();
        }

        if ($employees->isEmpty()) {
            return null;
        }

        if ($dates === null) {
            $dates = [];
            $curr = $fromDate->copy();
            while ($curr->lte($toDate)) {
                $dates[] = $curr->format('Y-m-d');
                $curr->addDay();
            }
        }

        $hourlyType = $request->get('hourly_type', 'N');
        $cardType = $request->get('card_type', 'N');

        $attendanceQuery = \App\Models\BiometricAttendance::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        if ($status && $status !== 'all') {
            if ($status === 'overtime') {
                $attendanceQuery->where('ot_minutes', '>', 0);
            } elseif ($status === 'latein') {
                $attendanceQuery->where('late_in', '!=', '0m')->whereNotNull('late_in');
            } elseif ($status === 'earlyout') {
                $attendanceQuery->where('early_out', '!=', '0m')->whereNotNull('early_out');
            } elseif ($status === 'P') {
                $attendanceQuery->whereIn('status', ['P', 'MIS', 'HD', 'OD', 'CO']);
            } else {
                $attendanceQuery->where('status', $status);
            }
        }

        $attendanceRecords = $attendanceQuery->get()->groupBy([
            'employee_id',
            fn ($item) => Carbon::parse($item->attendance_date)->format('Y-m-d'),
        ]);

        $matrixData = [];
        foreach ($employees as $employee) {
            $totalPr = 0;
            $totalPi = 0;
            $attendance = [];
            $attendanceMis = [];
            $attendancePi = [];
            $totalHours = 0;

            foreach ($dates as $date) {
                $records = $attendanceRecords->get($employee->id)?->get($date);

                if ($records && $records->count() > 0) {
                    $totalMin = $records->sum('total_minutes');
                    $totalHours += $totalMin;
                    $val = (float) $records->sum('duty_value');
                    $isAnyManual = $records->contains('is_manual', true);
                    $isAnyMis = $records->contains('status', 'MIS');

                    if ($cardType === 'A' || $hourlyType === 'A') {
                        $attendance[$date] = $isAnyMis
                            ? 'MIS' . ($isAnyManual ? '*' : '')
                            : (($val > 0) ? 'P' . ($isAnyManual ? '*' : '') : 'A');
                    } elseif ($hourlyType === 'Y') {
                        if ($totalMin > 0) {
                            $hours = floor($totalMin / 60);
                            $minutes = $totalMin % 60;
                            $attendance[$date] = sprintf('%d.%02d', $hours, $minutes) . ($isAnyManual ? '*' : '');
                        } elseif ($isAnyMis) {
                            $attendance[$date] = 'MIS';
                        } else {
                            $attendance[$date] = '-';
                        }
                    } elseif ($hourlyType === 'T') {
                        $firstRec = $records->whereNotNull('in_time')->sortBy('in_time')->first();
                        $lastRec = $records->whereNotNull('out_time')->sortByDesc('out_time')->first();
                        $first = ($firstRec && $firstRec->in_time)
                            ? (($firstRec->in_time instanceof Carbon) ? $firstRec->in_time->format('H:i') : Carbon::parse($firstRec->in_time)->format('H:i'))
                            : '--:--';
                        $last = ($lastRec && $lastRec->out_time)
                            ? (($lastRec->out_time instanceof Carbon) ? $lastRec->out_time->format('H:i') : Carbon::parse($lastRec->out_time)->format('H:i'))
                            : '--:--';

                        if ($first === '--:--' && $last === '--:--') {
                            $attendance[$date] = '-';
                        } else {
                            $attendance[$date] = (($first === $last) ? $first : "{$first}/{$last}") . ($isAnyManual ? '*' : '');
                        }
                    } else {
                        $attendance[$date] = number_format($val, 1) . ($isAnyManual ? '*' : '');
                    }

                    $attendanceMis[$date] = $isAnyMis;
                    $attendancePi[$date] = ($val > 1.0);
                    $totalPr += min(1.0, $val);
                    $totalPi += max(0.0, $val - 1.0);
                } else {
                    $today = Carbon::today()->format('Y-m-d');
                    $attendance[$date] = ($cardType === 'A' || $hourlyType === 'A')
                        ? (($date > $today) ? '' : 'A')
                        : '-';
                    $attendanceMis[$date] = false;
                    $attendancePi[$date] = false;
                }
            }

            $row = [
                'id' => $employee->id,
                'code' => $employee->emy_code,
                'name' => optional($employee->user)->name ?? 'N/A',
                'father_name' => $employee->father_name ?? '-',
                'designation' => optional($employee->designation)->name ?? '-',
                'dept' => $employee->department->name ?? 'N/A',
                'section' => $employee->section->name ?? 'N/A',
                'shift' => $employee->shift->name ?? 'N/A',
                'shift_code' => $employee->shift->short_code ?? '',
                'is_night' => $employee->shift->is_night_shift ?? 0,
                'attendance' => $attendance,
                'attendance_mis' => $attendanceMis,
                'attendance_pi' => $attendancePi,
                'total_pr' => $totalPr,
                'total_pi' => $totalPi,
                'total_tot' => $totalPr + $totalPi,
                'total_hours' => number_format($totalHours / 60, 2) . 'h',
            ];

            if (in_array($reportId, ['att_worker', 'att_dept', 'att_shift', 'att_summary', 'emp_monthly']) && $cardType === 'Y') {
                $visibleDates = array_slice($dates, -9);
                $lwkd = 0;
                foreach ($visibleDates as $vd) {
                    if (isset($attendance[$vd]) && $attendance[$vd] !== '-') {
                        $lwkd++;
                    }
                }
                $row['l_wkd'] = $lwkd;
                $row['visible_dates'] = $visibleDates;
            }

            $hasLogs = collect($attendance)->contains(fn ($v) => $v !== '-');
            if ($hasLogs) {
                $matrixData[] = $row;
            }
        }

        $dayTotals = [];
        foreach ($dates as $date) {
            $dayTotals[$date] = 0;
            foreach ($matrixData as $row) {
                if (isset($row['attendance'][$date]) && $row['attendance'][$date] !== '-') {
                    $dayTotals[$date]++;
                }
            }
        }

        $branchName = '';
        $selectedBranchId = ($branchId && $branchId !== 'all') ? $branchId : session('active_branch_id');
        if ($selectedBranchId && $selectedBranchId !== 'all') {
            $branch = Branch::find($selectedBranchId);
            if ($branch) {
                $branchName = $branch->name;
            }
        }

        $typeLabel = match ($hourlyType) {
            'Y' => '(HOURLY)',
            'T' => '(TIMEWISE)',
            'A' => '(P/A STATUS)',
            default => '(NUMERIC)',
        };

        $data = [
            'title' => 'Workerwise Attendance Report ' . $typeLabel,
            'reportTitle' => 'Workerwise Attendance Report ' . $typeLabel,
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES') . ($branchName ? ' - ' . $branchName : ''),
            'branchName' => $branchName,
            'headerTitle' => 'ATTENDENT FOR THE PERIOD FROM ' . $fromDate->format('d/m/Y') . ' TO ' . $toDate->format('d/m/Y'),
            'fromDate' => $fromDate->format('d/m/Y'),
            'toDate' => $toDate->format('d/m/Y'),
            'period' => $fromDate->format('d/m/Y') . ' TO ' . $toDate->format('d/m/Y'),
            'dates' => $dates,
            'reportData' => $matrixData,
            'dayTotals' => $dayTotals,
            'reportType' => $reportType,
            'hourlyType' => $hourlyType,
            'cardType' => $cardType,
            'printDate' => now()->format('d/m/Y H:i:s'),
            'typeLabel' => $typeLabel,
            'reportId' => $reportId,
        ];

        $groupByKey = ($reportId === 'att_shift') ? 'shift' : 'dept';
        $grouped = collect($matrixData)->groupBy($groupByKey)->toArray();
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($grouped as $groupName => &$rows) {
            foreach ($rows as $i => &$r) {
                $r['sr'] = $i + 1;
            }
            unset($r);
        }
        unset($rows);

        $data['reportData'] = $grouped;

        if ($reportId === 'att_summary') {
            $summaryData = [];
            foreach ($matrixData as $row) {
                $deptName = $row['section'];
                $desigName = $row['designation'];
                $shiftCode = strtoupper($row['shift_code'] ?? '');
                $isNightFlag = $row['is_night'] ?? 0;
                $isNight = ($shiftCode === 'PN' || $isNightFlag == 1 || str_contains(strtoupper($row['shift']), 'NIGHT'));
                $val = (float) ($row['attendance'][$fromDate->format('Y-m-d')] ?? 0);

                if ($val > 0) {
                    if (!isset($summaryData[$deptName][$desigName])) {
                        $summaryData[$deptName][$desigName] = ['pdy' => 0, 'pnt' => 0, 'total' => 0];
                    }
                    if ($isNight) {
                        $summaryData[$deptName][$desigName]['pnt'] += $val;
                    } else {
                        $summaryData[$deptName][$desigName]['pdy'] += $val;
                    }
                    $summaryData[$deptName][$desigName]['total'] += $val;
                }
            }
            uksort($summaryData, 'strnatcasecmp');
            foreach ($summaryData as $dept => $desigs) {
                uksort($summaryData[$dept], 'strnatcasecmp');
            }
            $data['reportData'] = $summaryData;
            $data['headerTitle'] = 'SECTIONWISE WORKER SUMMARY - FOR ' . $fromDate->format('d/m/Y');
        }

        if ($cardType === 'Y' && in_array($reportId, ['att_worker', 'att_dept', 'att_shift', 'att_summary', 'emp_monthly'])) {
            $data['visibleDates'] = array_slice($dates, -9);
        }

        return $data;
    }
}
