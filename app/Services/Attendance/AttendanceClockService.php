<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\BiometricAttendance;
use App\Models\Employee;
use App\Services\Mobile\MobileTodayAttendanceResolver;
use App\Traits\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceClockService
{
    use AttendanceProcessor;

    public function __construct(
        private MobileTodayAttendanceResolver $attendanceResolver
    ) {}

    /**
     * @return array{success: bool, message: string, attendance?: array<string, mixed>|null}
     */
    public function clockIn(int $userId, ?float $latitude = null, ?float $longitude = null): array
    {
        try {
            $user = \App\Models\User::find($userId);
            if (! $user) {
                return ['success' => false, 'message' => __('User not found.')];
            }

            $today = $this->attendanceResolver->resolve($user);
            if ($today && ! ($today['can_clock_in'] ?? false)) {
                return ['success' => false, 'message' => $this->blockedMessage($today)];
            }

            $employee = Employee::withoutGlobalScopes()->where('user_id', $userId)->with('shift.slots')->first();
            if (! $employee) {
                return ['success' => false, 'message' => __('Employee profile not found.')];
            }

            if (! $employee->shift_id) {
                return ['success' => false, 'message' => __('No active shift found. Please contact HR.')];
            }

            $now = attendanceNowWallClock();
            $dateStr = getShiftAttendanceDateForPunch($employee, $now, 'in');
            $logDetails = $now->format('H:i').' IN';

            $record = $this->saveAttendanceRecord($employee, [
                'attendance_date' => $dateStr,
                'in_time' => $now,
                'out_time' => null,
                'status' => 'MIS',
                'log_details' => $logDetails,
                'is_manual' => false,
                'primary_source' => 'mobile',
                'in_count' => 1,
                'out_count' => 0,
                'clock_in_latitude' => $latitude,
                'clock_in_longitude' => $longitude,
            ]);

            $this->markLogsAsMobile($record, 'IN', $latitude, $longitude);

            return [
                'success' => true,
                'message' => __('Clocked in successfully.'),
                'attendance' => $this->attendanceResolver->resolve($user),
            ];
        } catch (\Throwable $e) {
            Log::error('Clock in failed: '.$e->getMessage());

            return ['success' => false, 'message' => __('Failed to clock in. Please try again.')];
        }
    }

    /**
     * @return array{success: bool, message: string, attendance?: array<string, mixed>|null}
     */
    public function clockOut(int $userId, ?float $latitude = null, ?float $longitude = null): array
    {
        try {
            $user = \App\Models\User::find($userId);
            if (! $user) {
                return ['success' => false, 'message' => __('User not found.')];
            }

            $today = $this->attendanceResolver->resolve($user);
            if (! $today || ! ($today['can_clock_out'] ?? false)) {
                return ['success' => false, 'message' => $today ? $this->blockedMessage($today) : __('Must clock in first.')];
            }

            $employee = Employee::withoutGlobalScopes()->where('user_id', $userId)->with('shift.slots')->first();
            if (! $employee) {
                return ['success' => false, 'message' => __('Employee profile not found.')];
            }

            $now = attendanceNowWallClock();
            $dateStr = $today['attendance_date'] ?? getShiftAttendanceDateForPunch($employee, $now, 'out');

            $existing = BiometricAttendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $dateStr)
                ->orderByDesc('id')
                ->first();

            $inTime = $existing?->in_time ? attendanceWallClock($existing->in_time) : $now;
            $logDetails = getStoredLogDetailsFromRecord($existing ?? new BiometricAttendance);
            if ($logDetails === '') {
                $logDetails = $inTime->format('H:i').' IN';
            }
            $logDetails .= ', '.$now->format('H:i').' OUT';

            $record = $this->saveAttendanceRecord($employee, [
                'attendance_date' => $dateStr,
                'in_time' => $inTime,
                'out_time' => $now,
                'status' => 'P',
                'log_details' => $logDetails,
                'is_manual' => false,
                'primary_source' => 'mobile',
                'in_count' => 1,
                'out_count' => 1,
                'clock_out_latitude' => $latitude,
                'clock_out_longitude' => $longitude,
            ]);

            $this->markLogsAsMobile($record, 'OUT', $latitude, $longitude);

            return [
                'success' => true,
                'message' => __('Clocked out successfully.'),
                'attendance' => $this->attendanceResolver->resolve($user),
            ];
        } catch (\Throwable $e) {
            Log::error('Clock out failed: '.$e->getMessage());

            return ['success' => false, 'message' => __('Failed to clock out. Please try again.')];
        }
    }

    public function todayPayload(int $userId): ?array
    {
        $user = \App\Models\User::find($userId);

        return $user ? $this->attendanceResolver->resolve($user) : null;
    }

    /**
     * @deprecated Use todayPayload() — kept for backward compatibility.
     */
    public function todayRecord(int $userId): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $userId)
            ->where('date', Carbon::today())
            ->first();
    }

    private function markLogsAsMobile(
        BiometricAttendance $record,
        string $punchType,
        ?float $latitude = null,
        ?float $longitude = null
    ): void {
        $record->logs()->update([
            'source' => 'mobile',
            'is_manual' => false,
            'manually_by' => null,
        ]);

        $latestPunch = $record->logs()
            ->where('punch_type', strtoupper($punchType))
            ->orderByDesc('punch_time')
            ->first();

        if ($latestPunch && ($latitude !== null || $longitude !== null)) {
            $latestPunch->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $today
     */
    private function blockedMessage(array $today): string
    {
        if (! empty($today['message'])) {
            return (string) $today['message'];
        }

        $source = $today['source'] ?? null;

        if (($today['is_manual'] ?? false) || $source === 'manual') {
            return __('Attendance was entered manually by admin. Contact HR to make changes.');
        }

        if (in_array($source, ['essl', 'mixed'], true)) {
            return __('Already marked via biometric machine.');
        }

        if ($today['clock_in'] && ! $today['clock_out']) {
            return __('Already clocked in today.');
        }

        if ($today['clock_in'] && $today['clock_out']) {
            return __('Already clocked out today.');
        }

        return __('Clock action is not available.');
    }
}
