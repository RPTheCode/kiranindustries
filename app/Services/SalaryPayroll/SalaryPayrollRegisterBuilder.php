<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use Illuminate\Database\Eloquent\Builder;

class SalaryPayrollRegisterBuilder
{
    /**
     * @param  array{
     *     search?: string|null,
     *     category_id?: int|null,
     *     shift_id?: int|null,
     *     department_id?: int|null,
     *     lock_status?: string|null
     * }  $filters
     * @return array{
     *     earning_columns: array<int, string>,
     *     deduction_columns: array<int, string>,
     *     rows: array<int, array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function build(SalaryPayrollRun $run, array $filters = []): array
    {
        $entries = $this->entriesQuery($run, $filters)
            ->with([
                'employee.employee.category:id,name',
                'employee.employee.department:id,name',
                'employee.employee.shift:id,name',
            ])
            ->get();

        $earningColumns = [];
        $deductionColumns = [];

        foreach ($entries as $entry) {
            foreach (array_keys($this->earningBreakdown($entry)) as $name) {
                if (! in_array($name, $earningColumns, true)) {
                    $earningColumns[] = $name;
                }
            }
            foreach (array_keys($entry->deductions_breakdown ?? []) as $name) {
                $name = (string) $name;
                if (! in_array($name, $deductionColumns, true)) {
                    $deductionColumns[] = $name;
                }
            }
        }

        sort($earningColumns);
        sort($deductionColumns);

        $rows = [];
        $totals = [
            'regular_earnings' => 0.0,
            'total_earnings' => 0.0,
            'total_deductions' => 0.0,
            'net_salary' => 0.0,
            'incentive_amount' => 0.0,
            'attendance_extra_amount' => 0.0,
            'attendance_extra_applied_amount' => 0.0,
            'earnings' => array_fill_keys($earningColumns, 0.0),
            'deductions' => array_fill_keys($deductionColumns, 0.0),
        ];

        foreach ($entries as $index => $entry) {
            $emp = $entry->employee?->employee;
            $workingDays = (float) ($entry->working_days ?? 0);
            if ($workingDays <= 0) {
                $workingDays = BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;
            }

            $regularEarnings = $this->regularEarningsForEntry($entry);
            $displayTotalEarnings = $this->displayTotalEarningsForEntry($entry, $regularEarnings);
            $displayDeductions = $this->displayDeductionsForEntry($entry);
            $storedGross = (float) $entry->monthly_gross;
            $isDayRate = $storedGross > 0 && ($storedGross < 5000 || $storedGross < ($regularEarnings / max(1, (float) ($entry->paid_days ?? 1))));

            if ($isDayRate) {
                $dayRate = round($storedGross, 2);
                $monthlyGross = round($storedGross * $workingDays, 0);
            } else {
                $monthlyGross = round($storedGross, 0);
                $dayRate = $workingDays > 0 ? round($monthlyGross / $workingDays, 2) : 0.0;
            }

            $earnings = $this->earningBreakdown($entry);
            if ($entry->govt_wage_salary_applied && $regularEarnings > 0) {
                $earnings = $this->scaleEarningsToTarget($earnings, $regularEarnings);
            }
            $deductions = collect($entry->deductions_breakdown ?? [])
                ->mapWithKeys(fn ($amount, $name) => [(string) $name => (float) $amount])
                ->all();

            $row = [
                'sr' => $index + 1,
                'employee_code' => $emp?->employee_id ?? '',
                'name' => $entry->employee?->name ?? '',
                'category' => $emp?->category?->name ?? '',
                'department' => $emp?->department?->name ?? '',
                'shift' => $emp?->shift?->name ?? '',
                'day_rate' => $dayRate,
                'monthly_gross' => $monthlyGross,
                'ot_enabled' => (bool) ($entry->ot_enabled ?? $emp?->ot_flag ?? false),
                'working_days' => $workingDays,
                'present_days' => (float) ($entry->present_days ?? 0),
                'paid_days' => (float) ($entry->paid_days ?? 0),
                'actual_paid_days' => $this->actualPaidDaysForEntry($entry, $workingDays),
                'govt_wage_salary_applied' => (bool) ($entry->govt_wage_salary_applied ?? false),
                'week_off_worked_days' => (float) ($entry->week_off_worked_days ?? 0),
                'half_days' => (float) ($entry->half_days ?? 0),
                'incentive_days' => (float) ($entry->incentive_days ?? 0),
                'incentive_amount' => (float) ($entry->incentive_amount ?? 0),
                'attendance_extra_days' => (float) ($entry->attendance_extra_days ?? 0),
                'attendance_extra_amount' => (float) ($entry->attendance_extra_amount ?? 0),
                'attendance_extra_applied' => (bool) ($entry->attendance_extra_applied ?? false),
                'regular_earnings' => $regularEarnings,
                'total_earnings' => $displayTotalEarnings,
                'total_deductions' => $displayDeductions,
                'net_salary' => (float) $entry->net_salary,
                'pf_wages' => (float) ($entry->pf_wages ?: $entry->basic),
                'pf_employee' => (float) $entry->pf_employee,
                'pt_amount' => (float) $entry->pt_amount,
                'bank_name' => $emp?->bank_name ?? '',
                'account_number' => $emp?->account_number ?? '',
                'ifsc_code' => $emp?->bank_identifier_code ?? '',
                'is_locked' => $entry->isLocked(),
                'has_mispunch' => (bool) ($entry->has_mispunch ?? false),
                'earnings' => $earnings,
                'deductions' => $deductions,
            ];

            $rows[] = $row;

            $totals['regular_earnings'] += $row['regular_earnings'];
            $totals['total_earnings'] += $row['total_earnings'];
            $totals['total_deductions'] += $row['total_deductions'];
            $totals['net_salary'] += $row['net_salary'];
            $totals['incentive_amount'] += $row['incentive_amount'];
            $totals['attendance_extra_amount'] += $row['attendance_extra_amount'];
            if ($row['attendance_extra_applied']) {
                $totals['attendance_extra_applied_amount'] += $row['attendance_extra_amount'];
            }

            foreach ($earningColumns as $col) {
                $totals['earnings'][$col] += (float) ($earnings[$col] ?? 0);
            }
            foreach ($deductionColumns as $col) {
                $totals['deductions'][$col] += (float) ($deductions[$col] ?? 0);
            }
        }

        return [
            'earning_columns' => $earningColumns,
            'deduction_columns' => $deductionColumns,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function entriesQuery(SalaryPayrollRun $run, array $filters = []): Builder
    {
        $search = ! empty($filters['search']) ? trim((string) $filters['search']) : null;
        $categoryId = ! empty($filters['category_id']) ? (int) $filters['category_id'] : null;
        $shiftId = ! empty($filters['shift_id']) ? (int) $filters['shift_id'] : null;
        $departmentId = ! empty($filters['department_id']) ? (int) $filters['department_id'] : null;
        $lockStatus = in_array($filters['lock_status'] ?? null, ['locked', 'unlocked'], true)
            ? $filters['lock_status']
            : null;

        return SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('employee', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('employee.employee', fn ($eq) => $eq->where('employee_id', 'like', "%{$search}%"));
                });
            })
            ->when($categoryId, fn ($q) => $q->whereHas('employee.employee', fn ($eq) => $eq->where('category_id', $categoryId)))
            ->when($shiftId, fn ($q) => $q->whereHas('employee.employee', fn ($eq) => $eq->where('shift_id', $shiftId)))
            ->when($departmentId, fn ($q) => $q->whereHas('employee.employee', fn ($eq) => $eq->where('department_id', $departmentId)))
            ->when($lockStatus === 'locked', fn ($q) => $q->where('salary_payroll_entries.is_locked', true))
            ->when($lockStatus === 'unlocked', fn ($q) => $q->where('salary_payroll_entries.is_locked', false))
            ->join('users', 'salary_payroll_entries.employee_id', '=', 'users.id')
            ->select('salary_payroll_entries.*')
            ->orderBy('users.name');
    }

    /**
     * @return array<string, float>
     */
    private function earningBreakdown(SalaryPayrollEntry $entry): array
    {
        $filtered = [];
        foreach ($entry->earnings_breakdown ?? [] as $name => $amount) {
            $name = (string) $name;
            if ($this->isIncentiveLine($name)) {
                continue;
            }
            if ((float) $amount > 0) {
                $filtered[$name] = (float) $amount;
            }
        }

        return $filtered;
    }

    private function isIncentiveLine(string $name): bool
    {
        $upper = strtoupper($name);

        return str_contains($upper, 'INCENTIVE')
            || str_contains($upper, 'PI)')
            || str_contains($upper, 'OVERTIME SALARY')
            || str_contains($upper, 'EXTRA DAYS');
    }

    private function regularEarningsForEntry(SalaryPayrollEntry $entry): float
    {
        if ($entry->govt_wage_salary_applied && $entry->contract_regular_earnings !== null) {
            return round((float) $entry->contract_regular_earnings, 0);
        }

        return round(max(
            0,
            (float) $entry->total_earnings
                - (float) ($entry->incentive_amount ?? 0)
                - ((bool) ($entry->attendance_extra_applied ?? false) ? (float) ($entry->attendance_extra_amount ?? 0) : 0)
        ), 0);
    }

    private function displayTotalEarningsForEntry(SalaryPayrollEntry $entry, float $regularEarnings): float
    {
        return round(
            $regularEarnings
                + (float) ($entry->incentive_amount ?? 0)
                + ((bool) ($entry->attendance_extra_applied ?? false) ? (float) ($entry->attendance_extra_amount ?? 0) : 0),
            0
        );
    }

    private function displayDeductionsForEntry(SalaryPayrollEntry $entry): float
    {
        $total = (float) $entry->total_deductions;

        if (
            ($entry->govt_wage_salary_applied ?? false)
            && ($entry->govt_wage_adjustment_type ?? null) === 'deduction'
            && (float) ($entry->govt_wage_adjustment_amount ?? 0) > 0
        ) {
            return round(max(0, $total - (float) $entry->govt_wage_adjustment_amount), 0);
        }

        return round($total, 0);
    }

    private function actualPaidDaysForEntry(SalaryPayrollEntry $entry, float $workingDays): float
    {
        $govtPaidDays = (float) ($entry->govt_wage_paid_days ?? $entry->paid_days ?? 0);
        $stored = $entry->actual_paid_days !== null ? (float) $entry->actual_paid_days : 0.0;

        if (! ($entry->govt_wage_salary_applied ?? false)) {
            return $stored > 0 ? round($stored, 2) : round($govtPaidDays, 2);
        }

        if ($stored > $govtPaidDays + 0.009) {
            return round($stored, 2);
        }

        $contractEarnings = (float) ($entry->contract_regular_earnings ?? 0);
        $perDayRate = (float) $entry->monthly_gross;
        if ($contractEarnings > 0 && $perDayRate > 0 && $perDayRate < 5000) {
            $derivedDays = round($contractEarnings / $perDayRate, 2);
            if ($derivedDays > $govtPaidDays + 0.009) {
                return $derivedDays;
            }
        }

        $presentDays = (float) ($entry->present_days ?? 0);
        $weekOffWorkedDays = (float) ($entry->week_off_worked_days ?? 0);
        $otEnabled = (bool) ($entry->ot_enabled ?? $entry->employee?->employee?->ot_flag ?? false);
        $attendancePaidDays = min($presentDays, $workingDays) + $weekOffWorkedDays;
        if ($otEnabled && $attendancePaidDays > $workingDays) {
            $attendancePaidDays = $workingDays;
        }

        if ($attendancePaidDays > $govtPaidDays + 0.009) {
            return round($attendancePaidDays, 2);
        }

        return $stored > 0 ? round($stored, 2) : round($govtPaidDays, 2);
    }

    /**
     * @param  array<string, float>  $breakdown
     * @return array<string, float>
     */
    private function scaleEarningsToTarget(array $breakdown, float $targetTotal): array
    {
        if ($breakdown === [] || $targetTotal <= 0) {
            return $breakdown;
        }

        $currentTotal = array_sum($breakdown);
        if ($currentTotal <= 0) {
            return $breakdown;
        }

        $scaled = [];
        foreach ($breakdown as $name => $amount) {
            $scaled[$name] = round((float) $amount / $currentTotal * $targetTotal, 0);
        }

        $sum = round(array_sum($scaled), 0);
        $diff = (int) round($targetTotal - $sum);
        if ($diff !== 0) {
            $adjustKey = array_key_exists('BASIC', $scaled)
                ? 'BASIC'
                : array_key_first($scaled);
            if ($adjustKey !== null) {
                $scaled[$adjustKey] = max(0, (float) $scaled[$adjustKey] + $diff);
            }
        }

        return $scaled;
    }
}
