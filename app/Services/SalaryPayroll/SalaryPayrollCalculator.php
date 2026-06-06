<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\PayrollParameter;
use App\Models\ProfessionalTaxSlab;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Support\Collection;

class SalaryPayrollCalculator
{
    public function __construct(
        private SalaryStructureCalculator $structureCalculator,
        private SalaryComponentAssignmentService $componentAssignment
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculateForEmployee(
        User $employee,
        Collection $branchComponents,
        string $financialYear
    ): array {
        $salary = EmployeeSalary::where('employee_id', $employee->id)->first();
        $gross = (float) ($salary?->monthly_gross ?? $salary?->basic_salary ?? 0);

        if ($gross <= 0) {
            return [
                'status' => 'excluded',
                'error_message' => __('Monthly gross salary is not set.'),
                'monthly_gross' => 0,
                'basic' => 0,
                'total_earnings' => 0,
                'total_deductions' => 0,
                'net_salary' => 0,
                'earnings_breakdown' => [],
                'deductions_breakdown' => [],
                'pf_employee' => 0,
                'pf_employer' => 0,
                'esi_employee' => 0,
                'esi_employer' => 0,
                'pt_amount' => 0,
            ];
        }

        $emp = $employee->employee;
        $components = $this->componentAssignment->resolveForEmployee($branchComponents, $emp);
        $options = [
            'apply_pf' => (bool) ($emp?->pf_flag ?? false),
            'apply_esi' => (bool) ($emp?->esic_flag ?? false),
        ];

        $split = $this->structureCalculator->splitFromGross($gross, $components, $options);
        $params = PayrollParameter::forFinancialYear($financialYear);

        $earnings = collect($split['breakdown'])->where('type', 'earning')->values();
        $deductions = collect($split['breakdown'])->where('type', 'deduction')->values();

        $earningsBreakdown = $earnings->mapWithKeys(fn ($row) => [$row['name'] => $row['amount']])->all();
        $deductionsBreakdown = $deductions->mapWithKeys(fn ($row) => [$row['name'] => $row['amount']])->all();

        $basic = (float) $split['basic_amount'];
        $totalEarnings = (float) $split['total_earnings'];

        $pfEmployee = 0.0;
        $pfEmployer = 0.0;
        $esiEmployee = 0.0;
        $esiEmployer = 0.0;
        $ptAmount = 0.0;

        if ($emp && $emp->pf_flag) {
            $pfPct = PayrollParameter::pfEmployeePct($params);
            $pfEmployerPct = PayrollParameter::pfEmployerPct($params);
            $maxPf = (float) ($params?->max_pf_amount ?? 15000);
            $pfBasic = ($emp->pf_basic_salary > 0) ? (float) $emp->pf_basic_salary : $basic;
            $pfBase = min($pfBasic, $maxPf);
            $pfEmployee = round($pfBase * $pfPct / 100, 2);
            $pfEmployer = round($pfBase * $pfEmployerPct / 100, 2);
            $deductionsBreakdown = $this->replaceStatutoryDeduction($deductionsBreakdown, ['PF', 'PROVIDENT FUND', 'EPF'], 'Provident Fund (PF)', $pfEmployee);
        } else {
            $deductionsBreakdown = $this->removeStatutoryDeduction($deductionsBreakdown, ['PF', 'PROVIDENT FUND', 'EPF']);
        }

        if ($emp && $emp->esic_flag && $params) {
            $esiPct = PayrollParameter::esicEmployeePct($params);
            $esiEmployerPct = PayrollParameter::esicEmployerPct($params);
            $esiCeiling = PayrollParameter::esicWageLimit($params);
            $esiBase = min($totalEarnings, $esiCeiling);
            $esiEmployee = round($esiBase * $esiPct / 100, 2);
            $esiEmployer = round($esiBase * $esiEmployerPct / 100, 2);
            $deductionsBreakdown = $this->replaceStatutoryDeduction($deductionsBreakdown, ['ESI', 'ESIC'], 'ESIC', $esiEmployee);
        } else {
            $deductionsBreakdown = $this->removeStatutoryDeduction($deductionsBreakdown, ['ESI', 'ESIC']);
        }

        $ptAmount = $this->calculateProfessionalTax($totalEarnings, $financialYear);
        if ($ptAmount > 0) {
            $deductionsBreakdown['Professional Tax'] = $ptAmount;
        }

        $totalDeductions = round(array_sum($deductionsBreakdown), 2);
        $netSalary = round($totalEarnings - $totalDeductions, 2);

        return [
            'status' => 'calculated',
            'error_message' => null,
            'monthly_gross' => round($gross, 2),
            'basic' => round($basic, 2),
            'total_earnings' => round($totalEarnings, 2),
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'earnings_breakdown' => $earningsBreakdown,
            'deductions_breakdown' => $deductionsBreakdown,
            'pf_employee' => $pfEmployee,
            'pf_employer' => $pfEmployer,
            'esi_employee' => $esiEmployee,
            'esi_employer' => $esiEmployer,
            'pt_amount' => $ptAmount,
        ];
    }

    /**
     * @return Collection<int, SalaryComponent>
     */
    public function branchComponents(?int $branchId): Collection
    {
        $query = SalaryComponent::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->orderBy('type')
            ->orderBy('name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    private function calculateProfessionalTax(float $monthlyGross, string $financialYear): float
    {
        if ($monthlyGross <= 0) {
            return 0;
        }

        $slabs = ProfessionalTaxSlab::where('financial_year', $financialYear)
            ->orderBy('min_amt')
            ->get();

        if ($slabs->isEmpty()) {
            $slabs = ProfessionalTaxSlab::orderBy('min_amt')->get();
        }

        foreach ($slabs as $slab) {
            $min = (float) $slab->min_amt;
            $max = $slab->max_amt !== null ? (float) $slab->max_amt : null;

            if ($monthlyGross >= $min && ($max === null || $monthlyGross <= $max)) {
                return round((float) $slab->pt_amt, 2);
            }
        }

        return 0;
    }

    /**
     * @param  array<string, float>  $breakdown
     * @param  array<int, string>  $keys
     * @return array<string, float>
     */
    private function replaceStatutoryDeduction(array $breakdown, array $keys, string $label, float $amount): array
    {
        $breakdown = $this->removeStatutoryDeduction($breakdown, $keys);
        if ($amount > 0) {
            $breakdown[$label] = $amount;
        }

        return $breakdown;
    }

    /**
     * @param  array<string, float>  $breakdown
     * @param  array<int, string>  $keys
     * @return array<string, float>
     */
    private function removeStatutoryDeduction(array $breakdown, array $keys): array
    {
        foreach ($breakdown as $name => $value) {
            $upper = strtoupper($name);
            foreach ($keys as $key) {
                if ($upper === strtoupper($key) || str_contains($upper, strtoupper($key))) {
                    unset($breakdown[$name]);
                    break;
                }
            }
        }

        return $breakdown;
    }
}
