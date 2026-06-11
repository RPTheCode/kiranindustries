<?php

namespace App\Services\Mobile;

use App\Models\BiometricAttendance;
use App\Models\User;
use Carbon\Carbon;

class MobileMispunchService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(User $user, ?int $limit = 30): array
    {
        $employee = mobileUserEmployee($user);
        if (! $employee) {
            return [];
        }

        $today = companyToday()->format('Y-m-d');

        return BiometricAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'MIS')
            ->whereDate('attendance_date', '<', $today)
            ->orderByDesc('attendance_date')
            ->limit($limit)
            ->get()
            ->map(fn (BiometricAttendance $record) => $this->formatRow($record))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(BiometricAttendance $record): array
    {
        $logDetails = getStoredLogDetailsFromRecord($record);
        $pairs = parseLogDetailsToPairs($logDetails);

        return [
            'attendance_date' => $record->attendance_date?->format('Y-m-d'),
            'status' => $record->status,
            'source' => $record->is_manual ? 'manual' : ($record->primary_source ?? 'essl'),
            'is_manual' => (bool) $record->is_manual,
            'clock_in' => formatAttendanceApiTime($record->in_time),
            'clock_out' => formatAttendanceApiTime($record->out_time),
            'punches' => array_map(fn (array $pair) => [
                'in' => $pair['in'] !== '' ? $pair['in'] : null,
                'out' => $pair['out'] !== '' ? $pair['out'] : null,
            ], $pairs),
            'message' => __('Contact HR to clear this mispunch.'),
        ];
    }
}
