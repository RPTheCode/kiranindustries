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
            SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->where('is_locked', false)
                ->delete();

            foreach ($employees as $employee) {
                if (in_array($employee->id, $lockedEmployeeIds, true)) {
                    continue;
                }

                $result = $this->calculator->calculateForEmployee(
                    $employee,
                    $components,
                    $run->financial_year
                );

                SalaryPayrollEntry::create([
                    'salary_payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'monthly_gross' => $result['monthly_gross'],
                    'basic' => $result['basic'],
                    'total_earnings' => $result['total_earnings'],
                    'total_deductions' => $result['total_deductions'],
                    'net_salary' => $result['net_salary'],
                    'earnings_breakdown' => $result['earnings_breakdown'],
                    'deductions_breakdown' => $result['deductions_breakdown'],
                    'pf_employee' => $result['pf_employee'],
                    'pf_employer' => $result['pf_employer'],
                    'esi_employee' => $result['esi_employee'],
                    'esi_employer' => $result['esi_employer'],
                    'pt_amount' => $result['pt_amount'],
                    'status' => $result['status'],
                    'error_message' => $result['error_message'],
                ]);
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

        $result = $this->calculator->calculateForEmployee(
            $employee,
            $components,
            $run->financial_year
        );

        DB::transaction(function () use ($run, $employeeId, $result) {
            SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->where('employee_id', $employeeId)
                ->update([
                    'monthly_gross' => $result['monthly_gross'],
                    'basic' => $result['basic'],
                    'total_earnings' => $result['total_earnings'],
                    'total_deductions' => $result['total_deductions'],
                    'net_salary' => $result['net_salary'],
                    'earnings_breakdown' => $result['earnings_breakdown'],
                    'deductions_breakdown' => $result['deductions_breakdown'],
                    'pf_employee' => $result['pf_employee'],
                    'pf_employer' => $result['pf_employer'],
                    'esi_employee' => $result['esi_employee'],
                    'esi_employer' => $result['esi_employer'],
                    'pt_amount' => $result['pt_amount'],
                    'status' => $result['status'],
                    'error_message' => $result['error_message'],
                ]);

            $this->runService->refreshRunTotals($run);
        });

        return $run->fresh(['entries.employee.employee']);
    }
}
