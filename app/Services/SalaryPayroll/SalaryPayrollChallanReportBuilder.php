<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;

class SalaryPayrollChallanReportBuilder
{
    public function __construct(
        private SalaryPayrollRegisterBuilder $registerBuilder,
        private PfChallanBreakdownService $pfChallan
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     totals: array<string, float|int>,
     *     pf_employee_count: int
     * }
     */
    public function build(SalaryPayrollRun $run, array $filters = []): array
    {
        $entries = $this->registerBuilder
            ->entriesQuery($run, $filters)
            ->with([
                'employee.employee.category:id,name',
                'employee.employee.department:id,name',
                'employee.employee.shift:id,name',
            ])
            ->where('salary_payroll_entries.status', 'calculated')
            ->get();

        $rows = [];
        $totals = $this->emptyTotals();
        $pfEmployeeCount = 0;

        foreach ($entries as $index => $entry) {
            $row = $this->rowFromEntry($entry, $index + 1);
            $rows[] = $row;

            if ((float) $row['pf_employee'] > 0) {
                $pfEmployeeCount++;
            }

            foreach (array_keys($totals) as $key) {
                if ($key === 'employee_count') {
                    continue;
                }
                $totals[$key] += (float) ($row[$key] ?? 0);
            }
        }

        $totals['employee_count'] = count($rows);
        $totals['pf_employee_count'] = $pfEmployeeCount;

        return [
            'rows' => $rows,
            'totals' => $totals,
            'pf_employee_count' => $pfEmployeeCount,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyTotals(): array
    {
        return [
            'employee_count' => 0,
            'pf_employee_count' => 0,
            'total_earnings' => 0.0,
            'pf_wages' => 0.0,
            'pf_employee' => 0.0,
            'pf_eps_employer' => 0.0,
            'pf_epf_employer' => 0.0,
            'pf_admin_employer' => 0.0,
            'pf_employer' => 0.0,
            'pf_challan_ac1' => 0.0,
            'pf_challan_ac2' => 0.0,
            'pf_challan_ac10' => 0.0,
            'pf_challan_total' => 0.0,
            'esi_employee' => 0.0,
            'esi_employer' => 0.0,
            'pt_amount' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromEntry(SalaryPayrollEntry $entry, int $sr): array
    {
        $emp = $entry->employee?->employee;
        $pfEmployee = (float) $entry->pf_employee;
        $pfEps = (float) ($entry->pf_eps_employer ?? 0);
        $pfEpf = (float) ($entry->pf_epf_employer ?? 0);
        $pfEmployer = (float) ($entry->pf_employer ?? 0);

        $storedChallanTotal = (float) ($entry->pf_challan_total ?? 0);
        if ($storedChallanTotal > 0) {
            $challan = [
                'pf_admin_employer' => (float) ($entry->pf_admin_employer ?? 0),
                'pf_challan_ac1' => (float) ($entry->pf_challan_ac1 ?? 0),
                'pf_challan_ac2' => (float) ($entry->pf_challan_ac2 ?? 0),
                'pf_challan_ac10' => (float) ($entry->pf_challan_ac10 ?? 0),
                'pf_challan_total' => $storedChallanTotal,
            ];
        } elseif ($pfEmployee > 0) {
            $challan = $this->pfChallan->build($pfEmployee, $pfEps, $pfEpf, $pfEmployer);
        } else {
            $challan = [
                'pf_admin_employer' => 0.0,
                'pf_challan_ac1' => 0.0,
                'pf_challan_ac2' => 0.0,
                'pf_challan_ac10' => 0.0,
                'pf_challan_total' => 0.0,
            ];
        }

        return [
            'sr' => $sr,
            'employee_code' => $emp?->employee_id ?? '',
            'name' => $entry->employee?->name ?? '',
            'category' => $emp?->category?->name ?? '',
            'department' => $emp?->department?->name ?? '',
            'shift' => $emp?->shift?->name ?? '',
            'uan_number' => $emp?->uan_number ?? '',
            'pf_number' => $emp?->pf_number ?? '',
            'esic_number' => $emp?->esic_number ?? '',
            'paid_days' => (float) ($entry->paid_days ?? 0),
            'total_earnings' => (float) $entry->total_earnings,
            'pf_wages' => (float) ($entry->pf_wages ?: $entry->basic),
            'pf_employee' => $pfEmployee,
            'pf_eps_employer' => $pfEps,
            'pf_epf_employer' => $pfEpf,
            'pf_admin_employer' => (float) $challan['pf_admin_employer'],
            'pf_employer' => $pfEmployer > 0 ? $pfEmployer : ($pfEps + $pfEpf + (float) $challan['pf_admin_employer']),
            'pf_challan_ac1' => (float) $challan['pf_challan_ac1'],
            'pf_challan_ac2' => (float) $challan['pf_challan_ac2'],
            'pf_challan_ac10' => (float) $challan['pf_challan_ac10'],
            'pf_challan_total' => (float) $challan['pf_challan_total'],
            'esi_employee' => (float) $entry->esi_employee,
            'esi_employer' => (float) $entry->esi_employer,
            'pt_amount' => (float) $entry->pt_amount,
            'govt_min_wage_used' => (float) ($entry->govt_min_wage_used ?? 0),
            'is_locked' => $entry->isLocked(),
        ];
    }
}
