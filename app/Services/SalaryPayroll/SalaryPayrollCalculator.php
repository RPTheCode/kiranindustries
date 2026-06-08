<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\PayrollParameter;
use App\Models\ProfessionalTaxSlab;
use App\Models\SalaryComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalaryPayrollCalculator
{
    public function __construct(
        private SalaryStructureCalculator $structureCalculator,
        private SalaryComponentAssignmentService $componentAssignment,
        private SalaryPayrollAttendanceService $attendanceService
    ) {}

    /**
     * @param  array{
     *     use_attendance?: bool,
     *     pay_period_start?: string|null,
     *     pay_period_end?: string|null,
     *     branch_id?: int|null
     * }  $context
     * @return array<string, mixed>
     */
    public function calculateForEmployee(
        User $employee,
        Collection $branchComponents,
        string $financialYear,
        array $context = []
    ): array {
        $salary = EmployeeSalary::where('employee_id', $employee->id)->first();
        $rateGross = (float) ($salary?->monthly_gross ?? $salary?->basic_salary ?? 0);

        if ($rateGross <= 0) {
            return $this->emptyResult(__('Monthly gross salary is not set.'));
        }

        $emp = $employee->employee;
        $useAttendance = (bool) ($context['use_attendance'] ?? false);
        $empWorkingDays = ($emp && (float) $emp->working_days > 0) ? (float) $emp->working_days : 26.0;
        $dailyOption = (bool) ($emp?->daily_option ?? false);
        $isDayRateWorker = $dailyOption && $empWorkingDays <= 1;
        $salaryStandardDays = 26.0;
        $structureGross = $isDayRateWorker ? ($rateGross * $salaryStandardDays) : $rateGross;
        $workingDays = $salaryStandardDays;

        $attendance = [
            'working_days' => $workingDays,
            'present_days' => $useAttendance ? 0.0 : $workingDays,
            'half_days' => 0.0,
            'week_off_worked_days' => 0.0,
            'total_worked_days' => $useAttendance ? 0.0 : $workingDays,
            'paid_days' => $useAttendance ? 0.0 : $workingDays,
            'mispunch_count' => 0,
            'has_mispunch' => false,
            'mispunch_dates' => [],
        ];

        if ($useAttendance && ! empty($context['pay_period_start']) && ! empty($context['pay_period_end'])) {
            $attendance = $this->attendanceService->summarize(
                $employee,
                Carbon::parse($context['pay_period_start']),
                Carbon::parse($context['pay_period_end']),
                $context['branch_id'] ?? null
            );
        }

        $regularPresentDays = (float) $attendance['present_days'];
        $weekOffWorkedDays = (float) ($attendance['week_off_worked_days'] ?? 0);
        $totalWorkedDays = (float) ($attendance['total_worked_days'] ?? ($regularPresentDays + $weekOffWorkedDays));
        $presentDays = $totalWorkedDays;
        $paidDays = (float) $attendance['paid_days'];
        $otEnabled = (bool) ($emp?->ot_flag ?? false);
        $incentiveDays = 0.0;
        $incentiveAmount = 0.0;

        if (! $useAttendance) {
            $regularPresentDays = $salaryStandardDays;
            $totalWorkedDays = $regularPresentDays;
            $presentDays = $totalWorkedDays;
            $paidDays = $salaryStandardDays;
        } else {
            $regularPaidDays = min($regularPresentDays, $salaryStandardDays);
            $paidDays = $regularPaidDays + $weekOffWorkedDays;

            if ($otEnabled) {
                $incentiveDays = max(0.0, $totalWorkedDays - $salaryStandardDays);
            }
        }

        $proRataBaseDays = $useAttendance
            ? (min($regularPresentDays, $salaryStandardDays) + $weekOffWorkedDays)
            : $salaryStandardDays;

        $proRataFactor = $this->proRataFactor($structureGross, $salaryStandardDays, $proRataBaseDays);

        $components = $this->componentAssignment->resolveForEmployee($branchComponents, $emp);
        $options = [
            'apply_pf' => (bool) ($emp?->pf_flag ?? false),
            'apply_esi' => (bool) ($emp?->esic_flag ?? false),
        ];

        $split = $this->structureCalculator->splitFromGross($structureGross, $components, $options);
        $params = PayrollParameter::forFinancialYear($financialYear);

        $earningsBreakdown = [];
        foreach (collect($split['breakdown'])->where('type', 'earning') as $row) {
            $name = $row['name'];
            $amount = round((float) $row['amount'] * $proRataFactor, 0);
            if ($amount > 0) {
                $earningsBreakdown[$name] = $amount;
            }
        }

        $targetRegularEarnings = round($structureGross * $proRataFactor, 0);
        $earningsBreakdown = $this->reconcileEarningsToTarget($earningsBreakdown, $targetRegularEarnings);

        if ($incentiveDays > 0) {
            $incentiveAmount = round(($structureGross / $salaryStandardDays) * $incentiveDays, 0);
        }

        $deductionsBreakdown = [];
        foreach (collect($split['breakdown'])->where('type', 'deduction') as $row) {
            $amount = round((float) $row['amount'] * $proRataFactor, 0);
            if ($amount > 0) {
                $deductionsBreakdown[$row['name']] = $amount;
            }
        }

        $basic = round((float) $split['basic_amount'] * $proRataFactor, 0);
        if (isset($earningsBreakdown['BASIC'])) {
            $basic = (float) $earningsBreakdown['BASIC'];
        }
        $totalEarnings = round($targetRegularEarnings + $incentiveAmount, 0);

        $pfEmployee = 0.0;
        $pfWages = 0.0;
        $pfEmployer = 0.0;
        $pfEpsEmployer = 0.0;
        $pfEpEmployer = 0.0;
        $esiEmployee = 0.0;
        $esiEmployer = 0.0;
        $ptAmount = 0.0;

        if ($emp && $emp->pf_flag) {
            $pfPct = PayrollParameter::pfEmployeePct($params);
            $epsPct = PayrollParameter::pfEpsPct($params);
            $epfSharePct = PayrollParameter::pfEpEmployerSharePct($params);
            $adminPct = PayrollParameter::pfAdminChargePct($params);
            $maxPf = (float) ($params?->max_pf_amount ?? 15000);
            $pfBasic = ($emp->pf_basic_salary > 0) ? (float) $emp->pf_basic_salary : (float) $split['basic_amount'];
            if ($useAttendance) {
                $pfBasic = ($pfBasic / $salaryStandardDays) * $proRataBaseDays;
            }
            $pfWages = round(min($pfBasic, $maxPf), 0);
            $pfEmployee = round($pfWages * $pfPct / 100, 0);
            $pfEpsEmployer = round($pfWages * $epsPct / 100, 0);
            $pfEpEmployer = round($pfWages * $epfSharePct / 100, 0);
            $pfAdmin = round($pfWages * $adminPct / 100, 0);
            $pfEmployer = $pfEpsEmployer + $pfEpEmployer + $pfAdmin;
            $deductionsBreakdown = $this->replaceStatutoryDeduction($deductionsBreakdown, ['PF', 'PROVIDENT FUND', 'EPF'], 'Provident Fund (PF)', $pfEmployee);
        } else {
            $deductionsBreakdown = $this->removeStatutoryDeduction($deductionsBreakdown, ['PF', 'PROVIDENT FUND', 'EPF']);
        }

        if ($emp && $emp->esic_flag && $params) {
            $esiPct = PayrollParameter::esicEmployeePct($params);
            $esiEmployerPct = PayrollParameter::esicEmployerPct($params);
            $esiCeiling = PayrollParameter::esicWageLimit($params);
            $esiBase = min($totalEarnings, $esiCeiling);
            $esiEmployee = round($esiBase * $esiPct / 100, 0);
            $esiEmployer = round($esiBase * $esiEmployerPct / 100, 0);
            $deductionsBreakdown = $this->replaceStatutoryDeduction($deductionsBreakdown, ['ESI', 'ESIC'], 'ESIC', $esiEmployee);
        } else {
            $deductionsBreakdown = $this->removeStatutoryDeduction($deductionsBreakdown, ['ESI', 'ESIC']);
        }

        $ptAmount = $this->calculateProfessionalTax($totalEarnings, $financialYear);
        if ($ptAmount > 0) {
            $deductionsBreakdown['Professional Tax'] = $ptAmount;
        }

        $totalDeductions = round(array_sum($deductionsBreakdown), 0);
        $netSalary = round($totalEarnings - $totalDeductions, 0);

        return [
            'status' => 'calculated',
            'error_message' => null,
            'monthly_gross' => round($rateGross, 2),
            'basic' => $basic,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'earnings_breakdown' => $earningsBreakdown,
            'deductions_breakdown' => $deductionsBreakdown,
            'pf_employee' => $pfEmployee,
            'pf_wages' => $pfWages,
            'pf_employer' => $pfEmployer,
            'pf_eps_employer' => $pfEpsEmployer,
            'pf_epf_employer' => $pfEpEmployer,
            'esi_employee' => $esiEmployee,
            'esi_employer' => $esiEmployer,
            'pt_amount' => $ptAmount,
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'paid_days' => $paidDays,
            'week_off_worked_days' => round($weekOffWorkedDays, 2),
            'half_days' => round((float) ($attendance['half_days'] ?? 0), 2),
            'ot_enabled' => $otEnabled,
            'incentive_days' => round($incentiveDays, 2),
            'incentive_amount' => round($incentiveAmount, 2),
            'incentive_per_day_rate' => ($incentiveDays > 0)
                ? round($structureGross / $salaryStandardDays, 2)
                : 0.0,
            'regular_earnings' => round($targetRegularEarnings, 0),
            'mispunch_count' => (int) $attendance['mispunch_count'],
            'has_mispunch' => (bool) $attendance['has_mispunch'],
            'mispunch_dates' => $attendance['mispunch_dates'] ?? [],
            'use_attendance' => $useAttendance,
        ];
    }

    private function proRataFactor(float $structureGross, float $workingDays, float $paidDays): float
    {
        if ($workingDays <= 0 || $structureGross <= 0) {
            return 0.0;
        }

        // Week-off worked days are paid on top of the 26-day standard (can exceed 1.0).
        return max(0.0, $paidDays / $workingDays);
    }

    /**
     * Excel rounds total gross (rate × paid/working) to nearest rupee.
     * Component lines are rounded separately — adjust BASIC (or largest line) for the ±1 gap.
     *
     * @param  array<string, float>  $breakdown
     * @return array<string, float>
     */
    private function reconcileEarningsToTarget(array $breakdown, float $targetTotal): array
    {
        if ($targetTotal <= 0) {
            return [];
        }

        if ($breakdown === []) {
            return ['BASIC' => $targetTotal];
        }

        $sum = round(array_sum($breakdown), 0);
        $diff = (int) round($targetTotal - $sum);

        if ($diff === 0) {
            return $breakdown;
        }

        $adjustKey = array_key_exists('BASIC', $breakdown)
            ? 'BASIC'
            : array_keys($breakdown, max($breakdown), true)[0];

        $breakdown[$adjustKey] = max(0, (float) $breakdown[$adjustKey] + $diff);

        return $breakdown;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $message): array
    {
        return [
            'status' => 'excluded',
            'error_message' => $message,
            'monthly_gross' => 0,
            'basic' => 0,
            'total_earnings' => 0,
            'total_deductions' => 0,
            'net_salary' => 0,
            'earnings_breakdown' => [],
            'deductions_breakdown' => [],
            'pf_employee' => 0,
            'pf_wages' => 0,
            'pf_employer' => 0,
            'pf_eps_employer' => 0,
            'pf_epf_employer' => 0,
            'esi_employee' => 0,
            'esi_employer' => 0,
            'pt_amount' => 0,
            'working_days' => 26,
            'present_days' => 0,
            'paid_days' => 0,
            'half_days' => 0,
            'ot_enabled' => false,
            'incentive_days' => 0,
            'incentive_amount' => 0,
            'incentive_per_day_rate' => 0,
            'regular_earnings' => 0,
            'mispunch_count' => 0,
            'has_mispunch' => false,
            'mispunch_dates' => [],
            'use_attendance' => false,
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
