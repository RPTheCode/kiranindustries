<?php

namespace App\Services\Mobile;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\BiometricAttendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

class MobileDashboardService
{
    public function __construct(
        private MobileTodayAttendanceResolver $attendanceResolver
    ) {}

    public function build(User $user): array
    {
        $employee = mobileUserEmployee($user);
        $employee?->loadMissing('shift.slots');
        $branchId = $employee?->branch_id;
        $companyUserIds = $this->companyUserIds($user);

        $todayAttendance = $this->attendanceResolver->resolve($user);
        $shift = $this->resolveShift($employee);

        $leaveBalances = LeaveBalance::with('leaveType')
            ->where('employee_id', $user->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn (LeaveBalance $balance) => [
                'leave_type_id' => $balance->leave_type_id,
                'leave_type' => $balance->leaveType?->name,
                'allocated_days' => (float) $balance->allocated_days,
                'used_days' => (float) $balance->used_days,
                'remaining_days' => (float) $balance->remaining_days,
                'year' => $balance->year,
            ])
            ->values()
            ->all();

        $upcomingHolidays = Holiday::query()
            ->whereIn('created_by', $companyUserIds)
            ->where('start_date', '>=', today())
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(fn (Holiday $holiday) => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'start_date' => $holiday->start_date?->format('Y-m-d'),
                'end_date' => $holiday->end_date?->format('Y-m-d'),
                'is_paid' => (bool) $holiday->is_paid,
            ])
            ->values()
            ->all();

        $announcementsCount = Announcement::whereIn('created_by', $companyUserIds)
            ->when($branchId, function ($q) use ($branchId) {
                $q->where(function ($inner) use ($branchId) {
                    $inner->where('is_company_wide', true)
                        ->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branchId));
                });
            })
            ->count();

        return [
            'today_attendance' => $todayAttendance,
            'attendance_mode' => $this->resolveAttendanceMode($employee),
            'shift' => $shift,
            'leave_balances' => $leaveBalances,
            'upcoming_holidays' => $upcomingHolidays,
            'announcements_count' => $announcementsCount,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attendanceHistory(User $user, string $month): array
    {
        $employee = mobileUserEmployee($user);
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $records = [];

        if ($employee) {
            $biometricRows = BiometricAttendance::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                ->with('logs')
                ->orderBy('attendance_date')
                ->get();

            foreach ($biometricRows as $record) {
                $records[$record->attendance_date->format('Y-m-d')] = $this->formatHistoryRow($record);
            }
        }

        $legacyRows = AttendanceRecord::query()
            ->where('employee_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();

        foreach ($legacyRows as $record) {
            $dateKey = $record->date?->format('Y-m-d');
            if ($dateKey && ! isset($records[$dateKey])) {
                $records[$dateKey] = [
                    'date' => $dateKey,
                    'clock_in' => $record->clock_in,
                    'clock_out' => $record->clock_out,
                    'status' => $record->status,
                    'source' => 'mobile',
                    'is_manual' => (bool) ($record->is_manual ?? false),
                    'total_hours' => $record->total_hours ? (string) $record->total_hours : null,
                ];
            }
        }

        ksort($records);

        return array_values($records);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function biometricHistory(User $user, string $month): array
    {
        return $this->attendanceHistory($user, $month);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHistoryRow(BiometricAttendance $record): array
    {
        $source = $record->is_manual
            ? 'manual'
            : ($record->primary_source ?? ($record->in_time || $record->out_time ? 'essl' : 'essl'));

        $logDetails = getStoredLogDetailsFromRecord($record);
        $pairs = parseLogDetailsToPairs($logDetails);

        return [
            'date' => $record->attendance_date?->format('Y-m-d'),
            'attendance_date' => $record->attendance_date?->format('Y-m-d'),
            'clock_in' => formatAttendanceApiTime($record->in_time),
            'clock_out' => formatAttendanceApiTime($record->out_time),
            'status' => $record->status,
            'source' => $source,
            'is_manual' => (bool) $record->is_manual,
            'total_hours' => $record->total_minutes > 0
                ? $this->formatMinutes($record->total_minutes)
                : null,
            'punches' => array_map(fn (array $pair) => [
                'in' => $pair['in'] !== '' ? $pair['in'] : null,
                'out' => $pair['out'] !== '' ? $pair['out'] : null,
            ], $pairs),
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveShift(?Employee $employee): ?array
    {
        if (! $employee?->shift_id) {
            return null;
        }

        $shift = Shift::withoutGlobalScopes()->with('slots')->find($employee->shift_id);
        if (! $shift) {
            return null;
        }

        $firstSlot = $shift->slots->first();
        $lastSlot = $shift->slots->last();

        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'start_time' => $firstSlot?->start_time,
            'end_time' => $lastSlot?->end_time,
        ];
    }

    private function resolveAttendanceMode(?Employee $employee): string
    {
        if (! $employee) {
            return 'both';
        }

        $mode = $employee->attendance_mode ?? 'both';

        return in_array($mode, ['essl', 'mobile', 'both'], true) ? $mode : 'both';
    }

    /**
     * @return list<int>
     */
    private function companyUserIds(User $user): array
    {
        if ($user->type === 'company') {
            $ids = User::where('created_by', $user->id)->pluck('id')->toArray();
            $ids[] = $user->id;

            return $ids;
        }

        $ownerId = User::where('id', $user->created_by)->value('id');
        $ids = User::where('created_by', $ownerId)->pluck('id')->toArray();
        $ids[] = $ownerId;

        return $ids;
    }
}
