<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SalaryPayrollBatchProcessor
{
    public function __construct(
        private SalaryPayrollScopeService $scopeService,
        private SalaryPayrollCalculator $calculator,
        private SalaryPayrollRunService $runService
    ) {}

    public function process(SalaryPayrollRun $run): SalaryPayrollRun
    {
        if ($run->isFinalized()) {
            throw new \InvalidArgumentException(__('Finalized payroll runs cannot be recalculated.'));
        }

        $branchId = $run->branch_id;
        $components = $this->calculator->branchComponents($branchId);
        if ($components->isEmpty()) {
            throw new \InvalidArgumentException(__('No active salary components for this branch.'));
        }

        $filters = $run->scope_filters ?? [];
        $employees = $this->scopeService->readyEmployees($run->branch_id, $run->scope_mode, $filters);

        $lockedEmployeeIds = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->where('is_locked', true)
            ->pluck('employee_id')
            ->all();

        DB::transaction(function () use ($run, $employees, $components, $lockedEmployeeIds) {
            $preservedApplyFlags = SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->where('is_locked', false)
                ->pluck('apply_attendance_extra', 'employee_id')
                ->all();

            SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->where('is_locked', false)
                ->delete();

            $context = $this->runContext($run);

            foreach ($employees as $employee) {
                if (in_array($employee->id, $lockedEmployeeIds, true)) {
                    continue;
                }

                $entryApply = array_key_exists($employee->id, $preservedApplyFlags)
                    ? (bool) $preservedApplyFlags[$employee->id]
                    : (bool) ($run->apply_attendance_extra ?? false);

                $employeeContext = array_merge($context, [
                    'entry_apply_attendance_extra' => $entryApply,
                ]);

                $result = $this->calculator->calculateForEmployee(
                    $employee,
                    $components,
                    $run->financial_year,
                    $employeeContext
                );

                SalaryPayrollEntry::create($this->entryPayload($run->id, $employee->id, $result, $entryApply));
            }

            $this->runService->refreshRunTotals($run);
        });

        return $run->fresh(['entries.employee.employee']);
    }

    public function processEmployee(SalaryPayrollRun $run, int $employeeId): SalaryPayrollRun
    {
        if ($run->isFinalized()) {
            throw new \InvalidArgumentException(__('Locked payroll runs cannot be recalculated.'));
        }

        $entryExists = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->first();

        if (! $entryExists) {
            throw new \InvalidArgumentException(__('Employee is not part of this payroll run.'));
        }

        if ($entryExists->is_locked) {
            throw new \InvalidArgumentException(__('This employee is locked and cannot be regenerated.'));
        }

        $branchId = $run->branch_id;
        $components = $this->calculator->branchComponents($branchId);
        if ($components->isEmpty()) {
            throw new \InvalidArgumentException(__('No active salary components for this branch.'));
        }

        $employee = User::query()
            ->where('id', $employeeId)
            ->where('type', 'employee')
            ->with('employee')
            ->first();

        if (! $employee) {
            throw new \InvalidArgumentException(__('Employee not found.'));
        }

        $entryApply = (bool) ($entryExists->apply_attendance_extra ?? false);

        $result = $this->calculator->calculateForEmployee(
            $employee,
            $components,
            $run->financial_year,
            array_merge($this->runContext($run), [
                'entry_apply_attendance_extra' => $entryApply,
            ])
        );

        DB::transaction(function () use ($run, $employeeId, $result, $entryApply) {
            SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->where('employee_id', $employeeId)
                ->update($this->entryPayload($run->id, $employeeId, $result, $entryApply, false));

            $this->runService->refreshRunTotals($run);
        });

        return $run->fresh(['entries.employee.employee']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runContext(SalaryPayrollRun $run): array
    {
        return [
            'use_attendance' => (bool) ($run->use_attendance ?? true),
            'apply_attendance_extra' => (bool) ($run->apply_attendance_extra ?? false),
            'pay_period_start' => $run->pay_period_start?->toDateString(),
            'pay_period_end' => $run->pay_period_end?->toDateString(),
            'branch_id' => $run->branch_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function entryPayload(int $runId, int $employeeId, array $result, bool $applyAttendanceExtra = false, bool $includeKeys = true): array
    {
        $payload = [
            'monthly_gross' => $result['monthly_gross'],
            'working_days' => $result['working_days'] ?? 26,
            'present_days' => $result['present_days'] ?? 0,
            'half_days' => $result['half_days'] ?? 0,
            'week_off_worked_days' => $result['week_off_worked_days'] ?? 0,
            'paid_days' => $result['paid_days'] ?? 0,
            'incentive_days' => $result['incentive_days'] ?? 0,
            'incentive_amount' => $result['incentive_amount'] ?? 0,
            'attendance_extra_days' => $result['attendance_extra_days'] ?? 0,
            'attendance_extra_amount' => $result['attendance_extra_amount'] ?? 0,
            'apply_attendance_extra' => $applyAttendanceExtra,
            'attendance_extra_applied' => $result['attendance_extra_applied'] ?? false,
            'ot_enabled' => $result['ot_enabled'] ?? false,
            'mispunch_count' => $result['mispunch_count'] ?? 0,
            'has_mispunch' => $result['has_mispunch'] ?? false,
            'basic' => $result['basic'],
            'total_earnings' => $result['total_earnings'],
            'total_deductions' => $result['total_deductions'],
            'net_salary' => $result['net_salary'],
            'earnings_breakdown' => $result['earnings_breakdown'],
            'deductions_breakdown' => $result['deductions_breakdown'],
            'pf_employee' => $result['pf_employee'],
            'pf_wages' => $result['pf_wages'] ?? 0,
            'govt_min_wage_per_day' => $result['govt_min_wage_per_day'] ?? null,
            'govt_min_wage_used' => $result['govt_min_wage_used'] ?? null,
            'govt_wage_salary_applied' => $result['govt_wage_salary_applied'] ?? false,
            'actual_paid_days' => $result['actual_paid_days'] ?? null,
            'govt_wage_equiv_days_raw' => $result['govt_wage_equiv_days_raw'] ?? null,
            'govt_wage_paid_days' => $result['govt_wage_paid_days'] ?? null,
            'contract_regular_earnings' => $result['contract_regular_earnings'] ?? null,
            'govt_wage_computed_earnings' => $result['govt_wage_computed_earnings'] ?? null,
            'govt_wage_adjustment_amount' => $result['govt_wage_adjustment_amount'] ?? 0,
            'govt_wage_adjustment_type' => $result['govt_wage_adjustment_type'] ?? null,
            'working_days_source' => $result['working_days_source'] ?? null,
            'pf_employer' => $result['pf_employer'],
            'pf_eps_employer' => $result['pf_eps_employer'] ?? 0,
            'pf_epf_employer' => $result['pf_epf_employer'] ?? 0,
            'esi_employee' => $result['esi_employee'],
            'esi_employer' => $result['esi_employer'],
            'pt_amount' => $result['pt_amount'],
            'status' => $result['status'],
            'error_message' => $result['error_message'],
        ];

        if ($includeKeys) {
            $payload['salary_payroll_run_id'] = $runId;
            $payload['employee_id'] = $employeeId;
        }

        return $payload;
    }
}
