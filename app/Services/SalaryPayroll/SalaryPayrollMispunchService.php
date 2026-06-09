<?php

namespace App\Services\SalaryPayroll;

use App\Models\BiometricAttendance;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\ShiftSlot;
use App\Services\ActivityLogger;
use App\Traits\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalaryPayrollMispunchService
{
    use AttendanceProcessor;

    /**
     * @return Collection<int, BiometricAttendance>
     */
    public function mispunchRecordsForEntry(SalaryPayrollEntry $entry): Collection
    {
        $run = $entry->run;
        $emp = $entry->employee?->employee;

        if (! $run || ! $emp) {
            return collect();
        }

        $codes = array_values(array_unique(array_filter([
            trim((string) ($emp->emy_code ?? '')),
            trim((string) ($emp->employee_id ?? '')),
        ])));

        if (empty($codes)) {
            return collect();
        }

        return BiometricAttendance::query()
            ->with(['employee.shift.slots'])
            ->whereIn('employee_code', $codes)
            ->whereBetween('attendance_date', [
                $run->pay_period_start->format('Y-m-d'),
                $run->pay_period_end->format('Y-m-d'),
            ])
            ->when($run->branch_id, fn ($q) => $q->where('branch_id', $run->branch_id))
            ->where('status', 'MIS')
            ->orderBy('attendance_date')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recordsPayloadForEntry(SalaryPayrollEntry $entry): array
    {
        return $this->mispunchRecordsForEntry($entry)
            ->map(fn (BiometricAttendance $record) => $this->formatRecord($record))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRecord(BiometricAttendance $record): array
    {
        [$shiftStart, $shiftEnd] = $this->shiftTimesForRecord($record);
        $punch = $this->punchStateForRecord($record);

        return [
            'id' => $record->id,
            'attendance_date' => $record->attendance_date->format('Y-m-d'),
            'display_date' => $record->attendance_date->format('d M Y'),
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'can_quick_clear' => filled($shiftStart) && filled($shiftEnd),
            'in_time' => $punch['inTime'],
            'out_time' => $punch['outTime'],
            'missing_in' => $punch['missingIn'],
            'missing_out' => $punch['missingOut'],
            'missing_summary' => $punch['missingSummary'],
            'log_details' => getStoredLogDetailsFromRecord($record),
        ];
    }

    /**
     * @return array{
     *     inTime: ?string,
     *     outTime: ?string,
     *     missingIn: bool,
     *     missingOut: bool,
     *     missingSummary: ?string,
     *     pairs: array<int, array{in: string, out: string}>
     * }
     */
    private function punchStateForRecord(BiometricAttendance $record): array
    {
        $logDetails = getStoredLogDetailsFromRecord($record);
        if ($logDetails && function_exists('normalizeLogDetails')) {
            $logDetails = normalizeLogDetails($logDetails);
        }

        $pairs = parseLogDetailsToPairs($logDetails ?: '');

        if (empty($pairs)) {
            $in = $record->in_time ? Carbon::parse($record->in_time)->format('H:i') : '';
            $out = $record->out_time ? Carbon::parse($record->out_time)->format('H:i') : '';
            $pairs = [['in' => $in, 'out' => $out]];
        } else {
            $first = $pairs[0];
            if (empty($first['in']) && $record->in_time) {
                $pairs[0]['in'] = Carbon::parse($record->in_time)->format('H:i');
            }
            if (empty($first['out']) && $record->out_time) {
                $pairs[0]['out'] = Carbon::parse($record->out_time)->format('H:i');
            }
        }

        $first = $pairs[0] ?? ['in' => '', 'out' => ''];
        $inTime = ! empty($first['in']) ? substr((string) $first['in'], 0, 5) : null;
        $outTime = ! empty($first['out']) ? substr((string) $first['out'], 0, 5) : null;
        $missingIn = empty($first['in']);
        $missingOut = empty($first['out']);

        $parts = [];
        if ($missingIn) {
            $parts[] = 'IN missing';
        }
        if ($missingOut) {
            $parts[] = 'OUT missing';
        }

        if (! empty($parts)) {
            $missingSummary = implode(', ', $parts);
        } else {
            $issues = getMispunchIssuesFromPairs($pairs);
            $missingSummary = ! empty($issues) ? implode('; ', $issues) : null;
        }

        return [
            'inTime' => $inTime,
            'outTime' => $outTime,
            'missingIn' => $missingIn,
            'missingOut' => $missingOut,
            'missingSummary' => $missingSummary,
            'pairs' => $pairs,
        ];
    }

    public function assertRecordBelongsToEntry(BiometricAttendance $record, SalaryPayrollEntry $entry): void
    {
        $emp = $entry->employee?->employee;
        if (! $emp || (int) $record->employee_id !== (int) $emp->id) {
            throw new \InvalidArgumentException(__('This attendance record does not belong to the selected employee.'));
        }

        $run = $entry->run;
        if (! $run) {
            throw new \InvalidArgumentException(__('Payroll run not found.'));
        }

        $date = $record->attendance_date->format('Y-m-d');
        $start = $run->pay_period_start->format('Y-m-d');
        $end = $run->pay_period_end->format('Y-m-d');

        if ($date < $start || $date > $end) {
            throw new \InvalidArgumentException(__('This attendance date is outside the payroll period.'));
        }

        if (strtoupper((string) ($record->status ?? '')) !== 'MIS') {
            throw new \InvalidArgumentException(__('This attendance is no longer a mispunch.'));
        }
    }

    public function clearWithShiftTimes(BiometricAttendance $record): BiometricAttendance
    {
        [$shiftStart, $shiftEnd] = $this->shiftTimesForRecord($record);
        if (! filled($shiftStart) || ! filled($shiftEnd)) {
            throw new \InvalidArgumentException(__('Shift times not available — enter IN/OUT time manually.'));
        }

        $state = $this->punchStateForRecord($record);
        $inTime = $state['inTime'] ?: $shiftStart;
        $outTime = $state['outTime'] ?: $shiftEnd;

        return $this->clearWithManualTimes($record, $inTime, $outTime);
    }

    public function clearWithManualTimes(BiometricAttendance $record, string $inTimeStr, string $outTimeStr): BiometricAttendance
    {
        $emp = $record->employee;
        if (! $emp) {
            throw new \InvalidArgumentException(__('Employee not found for this attendance record.'));
        }

        $inTimeStr = trim($inTimeStr);
        $outTimeStr = trim($outTimeStr);
        if ($inTimeStr === '' || $outTimeStr === '') {
            throw new \InvalidArgumentException(__('Both IN and OUT time are required.'));
        }

        $attendanceDate = $record->attendance_date;
        $inTime = $this->parsePunchTime($attendanceDate, $inTimeStr);
        $outTime = $this->parsePunchTime($attendanceDate, $outTimeStr);
        if ($outTime->lt($inTime)) {
            $outTime->addDay();
        }

        $inFormatted = $inTime->format('H:i');
        $outFormatted = $outTime->format('H:i');
        $logDetails = normalizeLogDetails("{$inFormatted} IN, {$outFormatted} OUT");
        $actualWorkMinutes = sumWorkMinutesFromLogDetails($logDetails);

        $recordData = [
            'attendance_date' => $attendanceDate->format('Y-m-d'),
            'in_time' => $inTime,
            'out_time' => $outTime,
            'in_count' => 1,
            'out_count' => 1,
            'is_mis_punch' => false,
            'is_manual' => true,
            'manual_by' => auth()->id(),
            'log_details' => $logDetails,
            'actual_work_minutes' => $actualWorkMinutes,
        ];

        ActivityLogger::withoutLogging(function () use ($emp, $recordData, $record, $inTime, $outTime, $logDetails) {
            $this->saveAttendanceRecord($emp, $recordData);

            $record->refresh();
            $record->status = resolveManualMispunchStatus(
                $logDetails,
                'P',
                (bool) $inTime,
                (bool) $outTime
            );
            $record->is_manual = true;
            if (auth()->id()) {
                $record->manual_by = auth()->id();
            }
            $record->log_details = $logDetails;
            $record->save();
        });

        ActivityLogger::logAttendance($record->fresh(), 'updated', 'payroll_mispunch_clear');

        return $record->fresh();
    }

    private function parsePunchTime(Carbon $attendanceDate, string $timeValue): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $timeValue)) {
            return Carbon::parse($timeValue);
        }

        $normalized = strlen($timeValue) === 5 ? $timeValue : substr($timeValue, 0, 5);

        return Carbon::parse($attendanceDate->format('Y-m-d').' '.$normalized);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function shiftTimesForRecord(BiometricAttendance $record): array
    {
        $shiftSlot = $record->shift_slot_id
            ? ShiftSlot::find($record->shift_slot_id)
            : null;
        $shiftStart = $shiftSlot?->start_time ?? $record->employee?->shift?->slots?->first()?->start_time;
        $shiftEnd = $shiftSlot?->end_time ?? $record->employee?->shift?->slots?->last()?->end_time;

        return [
            $shiftStart ? substr((string) $shiftStart, 0, 5) : null,
            $shiftEnd ? substr((string) $shiftEnd, 0, 5) : null,
        ];
    }
}
