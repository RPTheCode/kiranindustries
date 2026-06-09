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
            $structureGross = max(
                (float) $entry->total_earnings
                    - (float) ($entry->incentive_amount ?? 0)
                    - ((bool) ($entry->attendance_extra_applied ?? false) ? (float) ($entry->attendance_extra_amount ?? 0) : 0),
                (float) $entry->monthly_gross
            );
            $workingDays = (float) ($entry->working_days ?? 0);
            if ($workingDays <= 0) {
                $workingDays = BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;
            }
            $dayRate = $workingDays > 0 ? $structureGross / $workingDays : 0;
            $monthlyGross = (float) $entry->monthly_gross;
            if ($monthlyGross > 0 && $monthlyGross < $structureGross / 2) {
                $monthlyGross = round($monthlyGross * $workingDays, 0);
            }
            $earnings = $this->earningBreakdown($entry);
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
                'day_rate' => round($dayRate, 2),
                'monthly_gross' => round($monthlyGross > 0 ? $monthlyGross : $structureGross, 0),
                'ot_enabled' => (bool) ($entry->ot_enabled ?? $emp?->ot_flag ?? false),
                'working_days' => $workingDays,
                'present_days' => (float) ($entry->present_days ?? 0),
                'paid_days' => (float) ($entry->paid_days ?? 0),
                'week_off_worked_days' => (float) ($entry->week_off_worked_days ?? 0),
                'half_days' => (float) ($entry->half_days ?? 0),
                'incentive_days' => (float) ($entry->incentive_days ?? 0),
                'incentive_amount' => (float) ($entry->incentive_amount ?? 0),
                'attendance_extra_days' => (float) ($entry->attendance_extra_days ?? 0),
                'attendance_extra_amount' => (float) ($entry->attendance_extra_amount ?? 0),
                'attendance_extra_applied' => (bool) ($entry->attendance_extra_applied ?? false),
                'regular_earnings' => max(
                    0,
                    (float) $entry->total_earnings
                        - (float) ($entry->incentive_amount ?? 0)
                        - ((bool) ($entry->attendance_extra_applied ?? false) ? (float) ($entry->attendance_extra_amount ?? 0) : 0)
                ),
                'total_earnings' => (float) $entry->total_earnings,
                'total_deductions' => (float) $entry->total_deductions,
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
}
