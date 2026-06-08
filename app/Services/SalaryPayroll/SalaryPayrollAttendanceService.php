<?php

namespace App\Services\SalaryPayroll;

use App\Models\BiometricAttendance;
use App\Models\User;
use Carbon\Carbon;

class SalaryPayrollAttendanceService
{
    /**
     * @return array{
     *     working_days: float,
     *     present_days: float,
     *     half_days: float,
     *     week_off_worked_days: float,
     *     total_worked_days: float,
     *     paid_days: float,
     *     mispunch_count: int,
     *     has_mispunch: bool,
     *     mispunch_dates: array<int, string>
     * }
     */
    public function summarize(User $employee, Carbon $periodStart, Carbon $periodEnd, ?int $branchId = null): array
    {
        $emp = $employee->employee;
        $salaryStandardDays = 26.0;

        $codes = array_values(array_unique(array_filter([
            trim((string) ($emp?->emy_code ?? '')),
            trim((string) ($emp?->employee_id ?? '')),
        ])));

        if (empty($codes)) {
            return $this->emptySummary();
        }

        $startDate = $periodStart->copy()->startOfDay();
        $endDate = $periodEnd->copy()->startOfDay();

        $query = BiometricAttendance::query()
            ->whereIn('employee_code', $codes)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $records = $query->get()->keyBy(fn ($row) => $row->attendance_date->format('Y-m-d'));

        $mispunchDates = $records
            ->filter(fn ($row) => strtoupper((string) ($row->status ?? '')) === 'MIS')
            ->map(fn ($row) => $row->attendance_date->format('d M'))
            ->values()
            ->all();

        $presentDays = 0.0;
        $halfDays = 0.0;
        $weekOffWorkedDays = 0.0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $bio = $records[$dateString] ?? null;
            if (! $bio || ! $this->biometricHasWork($bio)) {
                continue;
            }

            $isWeekOff = $this->isEmployeeWeekOff($emp, $date);
            $this->applyBiometricDutyCounts($bio, $isWeekOff, $presentDays, $halfDays, $weekOffWorkedDays);
        }

        $regularWorkedDays = $presentDays + ($halfDays * 0.5);
        $totalWorkedDays = $regularWorkedDays + $weekOffWorkedDays;
        $paidDays = $this->resolvePaidDays($regularWorkedDays, $weekOffWorkedDays, $salaryStandardDays);

        return [
            'working_days' => $salaryStandardDays,
            'present_days' => round($regularWorkedDays, 2),
            'half_days' => round($halfDays, 2),
            'week_off_worked_days' => round($weekOffWorkedDays, 2),
            'total_worked_days' => round($totalWorkedDays, 2),
            'paid_days' => round($paidDays, 2),
            'mispunch_count' => count($mispunchDates),
            'has_mispunch' => count($mispunchDates) > 0,
            'mispunch_dates' => $mispunchDates,
        ];
    }

    /**
     * @return array{working_days: float, present_days: float, half_days: float, week_off_worked_days: float, total_worked_days: float, paid_days: float, mispunch_count: int, has_mispunch: bool, mispunch_dates: array<int, string>}
     */
    private function emptySummary(): array
    {
        return [
            'working_days' => 26.0,
            'present_days' => 0.0,
            'half_days' => 0.0,
            'week_off_worked_days' => 0.0,
            'total_worked_days' => 0.0,
            'paid_days' => 0.0,
            'mispunch_count' => 0,
            'has_mispunch' => false,
            'mispunch_dates' => [],
        ];
    }

    private function resolvePaidDays(
        float $regularWorkedDays,
        float $weekOffWorkedDays,
        float $salaryStandardDays
    ): float {
        $normalPayable = min($regularWorkedDays, $salaryStandardDays);

        return $normalPayable + $weekOffWorkedDays;
    }

    private function biometricHasWork($bio): bool
    {
        return ($bio->punch_count ?? 0) > 0
            || $bio->in_time
            || (float) ($bio->duty_value ?? 0) > 0;
    }

    private function applyBiometricDutyCounts(
        $bio,
        bool $isWeekOff,
        float &$presentDays,
        float &$halfDays,
        float &$weekOffWorkedDays
    ): bool {
        $duty = (float) ($bio->duty_value ?? 0);
        $status = strtoupper((string) ($bio->status ?? ''));

        if ($isWeekOff) {
            if ($duty >= 2.0) {
                $weekOffWorkedDays += 2;
                return true;
            }
            if ($duty >= 1.0) {
                $weekOffWorkedDays++;
                return true;
            }
            if ($duty >= 0.5 || $status === 'HD') {
                $halfDays++;
                return true;
            }
            if ($bio->is_manual && $status === 'P') {
                $weekOffWorkedDays++;
                return true;
            }

            return false;
        }

        if ($duty >= 2.0) {
            $presentDays += 2;
            return true;
        }
        if ($duty >= 1.0) {
            $presentDays++;
            return true;
        }
        if ($duty >= 0.5 || $status === 'HD') {
            $halfDays++;
            return true;
        }
        if ($bio->is_manual && $status === 'P') {
            $presentDays++;
            return true;
        }

        return false;
    }

    private function isEmployeeWeekOff($emp, Carbon $date): bool
    {
        if (! $emp || empty($emp->week_off)) {
            return false;
        }

        $raw = trim((string) $emp->week_off);
        if ($raw === '' || in_array(strtolower($raw), ['none', 'n/a', 'na', 'null'], true)) {
            return false;
        }

        $dayName = $date->format('l');
        $isMonthly = ($emp->week_off_type ?? '') === 'monthly' || str_starts_with($raw, '{');

        if ($isMonthly) {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return false;
            }
            $weekOfMonth = (int) min(5, max(1, (int) ceil($date->day / 7)));
            $weekDays = $decoded[(string) $weekOfMonth] ?? $decoded[$weekOfMonth] ?? [];

            return is_array($weekDays) && in_array($dayName, $weekDays, true);
        }

        $days = array_filter(array_map('trim', explode(',', $raw)));

        return in_array($dayName, $days, true);
    }
}
