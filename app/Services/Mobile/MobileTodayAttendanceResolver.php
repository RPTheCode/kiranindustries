<?php

namespace App\Services\Mobile;

use App\Models\AttendanceRecord;
use App\Models\BiometricAttendance;
use App\Models\Employee;
use App\Models\User;

class MobileTodayAttendanceResolver
{
    public function resolve(User $user, bool $includeActions = true): ?array
    {
        $employee = mobileUserEmployee($user);

        if ($employee) {
            $employee->loadMissing('shift.slots');
            $attendanceDate = $this->resolveAttendanceDateForEmployee($employee);
            $biometric = BiometricAttendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $attendanceDate)
                ->with('logs')
                ->orderByDesc('id')
                ->first();

            if ($biometric) {
                return $this->formatBiometric($biometric, $user, $employee, $includeActions);
            }
        }

        $legacy = AttendanceRecord::query()
            ->where('employee_id', $user->id)
            ->whereDate('date', companyToday())
            ->first();

        if ($legacy) {
            return $this->formatLegacy($legacy, $user, $employee, $includeActions);
        }

        if (! $includeActions) {
            return null;
        }

        return [
            'attendance_date' => $employee ? $this->resolveAttendanceDateForEmployee($employee) : companyToday()->format('Y-m-d'),
            'date' => $employee ? $this->resolveAttendanceDateForEmployee($employee) : companyToday()->format('Y-m-d'),
            'clock_in' => null,
            'clock_out' => null,
            'status' => null,
            'source' => null,
            'is_manual' => false,
            'total_hours' => null,
            'punches' => [],
            'clock_in_location' => null,
            'clock_out_location' => null,
            'message' => null,
            'can_clock_in' => $this->canUseMobileClock($user, $employee),
            'can_clock_out' => false,
        ];
    }

    private function formatBiometric(BiometricAttendance $record, User $user, ?Employee $employee, bool $includeActions): array
    {
        $source = $this->resolveSource($record);
        $punches = $this->buildPunches($record);
        $clockIn = formatAttendanceApiTime($record->in_time);
        $clockOut = formatAttendanceApiTime($record->out_time);
        $attendanceDate = $record->attendance_date?->format('Y-m-d');

        $payload = [
            'attendance_date' => $attendanceDate,
            'date' => $attendanceDate,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'status' => $record->status,
            'source' => $source,
            'is_manual' => (bool) $record->is_manual,
            'total_hours' => $this->formatTotalHours($record->total_minutes),
            'punches' => $punches,
            'clock_in_location' => $this->locationPayload($record->clock_in_latitude, $record->clock_in_longitude),
            'clock_out_location' => $this->locationPayload($record->clock_out_latitude, $record->clock_out_longitude),
            'message' => null,
        ];

        if ($includeActions) {
            $payload['can_clock_in'] = $this->canClockIn($user, $employee, $record, $source, $punches);
            $payload['can_clock_out'] = $this->canClockOut($user, $employee, $record, $source, $punches);
            $payload['message'] = $this->resolveMessage($payload);
        }

        return $payload;
    }

    private function formatLegacy(AttendanceRecord $record, User $user, ?Employee $employee, bool $includeActions): array
    {
        $punches = [];
        if ($record->clock_in || $record->clock_out) {
            $punches[] = [
                'shift' => null,
                'in' => $record->clock_in,
                'out' => $record->clock_out,
            ];
        }

        $date = $record->date?->format('Y-m-d');
        $payload = [
            'attendance_date' => $date,
            'date' => $date,
            'clock_in' => $record->clock_in,
            'clock_out' => $record->clock_out,
            'status' => $record->status,
            'source' => 'mobile',
            'is_manual' => (bool) ($record->is_manual ?? false),
            'total_hours' => $record->total_hours ? $this->formatDecimalHours((float) $record->total_hours) : null,
            'punches' => $punches,
            'message' => null,
        ];

        if ($includeActions) {
            $hasClockIn = (bool) $record->clock_in;
            $hasClockOut = (bool) $record->clock_out;
            $canClock = $this->canUseMobileClock($user, $employee);

            $payload['can_clock_in'] = $canClock && ! $hasClockIn;
            $payload['can_clock_out'] = $canClock && $hasClockIn && ! $hasClockOut;
            $payload['message'] = $this->resolveMessage($payload);
        }

        return $payload;
    }

    /**
     * @return list<array{shift: string|null, in: string|null, out: string|null}>
     */
    private function buildPunches(BiometricAttendance $record): array
    {
        $logDetails = getStoredLogDetailsFromRecord($record);
        $pairs = parseLogDetailsToPairs($logDetails);

        if (empty($pairs) && ($record->in_time || $record->out_time)) {
            $pairs[] = [
                'in' => $record->in_time ? attendanceWallClock($record->in_time)->format('H:i') : '',
                'out' => $record->out_time ? attendanceWallClock($record->out_time)->format('H:i') : '',
            ];
        }

        $shiftName = $record->shift_code && $record->shift_code !== '---' ? $record->shift_code : null;

        return array_map(function (array $pair) use ($shiftName) {
            return [
                'shift' => $shiftName,
                'in' => $pair['in'] !== '' ? $this->formatPunchTime($pair['in']) : null,
                'out' => $pair['out'] !== '' ? $this->formatPunchTime($pair['out']) : null,
            ];
        }, $pairs);
    }

    private function formatPunchTime(string $time): string
    {
        $normalized = substr($time, 0, 5);

        return strlen($normalized) === 5 ? $normalized.':00' : $normalized;
    }

    private function resolveSource(BiometricAttendance $record): string
    {
        if ($record->is_manual) {
            return 'manual';
        }

        if ($record->primary_source) {
            return $record->primary_source;
        }

        $logSources = $record->logs
            ->pluck('source')
            ->filter()
            ->unique()
            ->values();

        if ($logSources->count() === 1) {
            return (string) $logSources->first();
        }

        if ($logSources->count() > 1) {
            return 'mixed';
        }

        if ($record->in_time || $record->out_time) {
            return 'essl';
        }

        return 'essl';
    }

    /**
     * @param  list<array{shift: string|null, in: string|null, out: string|null}>  $punches
     */
    private function canClockIn(User $user, ?Employee $employee, BiometricAttendance $record, string $source, array $punches): bool
    {
        if (! $this->canUseMobileClock($user, $employee)) {
            return false;
        }

        if ($record->is_manual || $source === 'manual') {
            return false;
        }

        if (in_array($source, ['essl', 'mixed'], true)) {
            return false;
        }

        if ($source === 'mobile') {
            if ($this->hasOpenPunch($punches)) {
                return false;
            }

            if ($record->in_time && $record->out_time) {
                return false;
            }

            if ($record->in_time && ! $record->out_time) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{shift: string|null, in: string|null, out: string|null}>  $punches
     */
    private function canClockOut(User $user, ?Employee $employee, BiometricAttendance $record, string $source, array $punches): bool
    {
        if (! $this->canUseMobileClock($user, $employee)) {
            return false;
        }

        if ($record->is_manual || $source === 'manual') {
            return false;
        }

        if (in_array($source, ['essl', 'mixed'], true)) {
            return false;
        }

        if ($source !== 'mobile') {
            return false;
        }

        if ($this->hasOpenPunch($punches)) {
            return true;
        }

        return (bool) $record->in_time && ! $record->out_time;
    }

    /**
     * @param  list<array{shift: string|null, in: string|null, out: string|null}>  $punches
     */
    private function hasOpenPunch(array $punches): bool
    {
        foreach ($punches as $punch) {
            if ($punch['in'] && ! $punch['out']) {
                return true;
            }
        }

        return false;
    }

    private function canUseMobileClock(User $user, ?Employee $employee): bool
    {
        if (! $user->can('clock-in-out')) {
            return false;
        }

        $mode = $this->resolveAttendanceMode($employee);

        return $mode !== 'essl';
    }

    private function resolveAttendanceMode(?Employee $employee): string
    {
        if (! $employee) {
            return 'both';
        }

        $mode = $employee->attendance_mode ?? 'both';

        return in_array($mode, ['essl', 'mobile', 'both'], true) ? $mode : 'both';
    }

    private function resolveAttendanceDateForEmployee(Employee $employee): string
    {
        $now = attendanceNowWallClock();

        return getShiftAttendanceDateForPunch($employee, $now, 'in');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveMessage(array $payload): ?string
    {
        $source = $payload['source'] ?? null;

        if (($payload['is_manual'] ?? false) || $source === 'manual') {
            return __('Attendance was entered manually by admin. Contact HR to make changes.');
        }

        if (in_array($source, ['essl', 'mixed'], true)) {
            return __('ESSL attendance recorded. Mobile clock disabled.');
        }

        if ($source === 'mobile' && ($payload['can_clock_out'] ?? false)) {
            return null;
        }

        if ($source === 'mobile' && ($payload['clock_in'] ?? null) && ! ($payload['clock_out'] ?? null)) {
            return null;
        }

        return null;
    }

    private function formatTotalHours(?int $minutes): ?string
    {
        if (! $minutes || $minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
    }

    private function formatDecimalHours(float $hours): string
    {
        $wholeHours = (int) floor($hours);
        $mins = (int) round(($hours - $wholeHours) * 60);

        return $wholeHours > 0 ? "{$wholeHours}h {$mins}m" : "{$mins}m";
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function locationPayload(mixed $latitude, mixed $longitude): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }
}
