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
        private SalaryPayrollAttendanceService $attendanceService,
        private BranchPayrollSettingsService $branchPayrollSettings,
        private GovernmentWageSalaryService $govtWageSalary
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
        $structureGross = (float) ($salary?->monthly_gross ?? $salary?->basic_salary ?? 0);
        $grossInputMode = $salary?->gross_input_mode ?? 'month';
        $perDayRate = (float) ($salary?->per_day_salary ?? 0);

        if ($structureGross <= 0) {
            return $this->emptyResult(__('Monthly gross salary is not set.'));
        }

        $emp = $employee->employee;
        $useAttendance = (bool) ($context['use_attendance'] ?? false);
        $branchId = isset($context['branch_id']) ? (int) $context['branch_id'] : null;
        $branchSettings = $branchId
            ? $this->branchPayrollSettings->resolve($branchId, $financialYear)
            : null;
        $salaryStandardDays = $branchSettings['working_days'] ?? BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;
        $workingDaysSource = $branchSettings['working_days_source'] ?? 'default';
        $workingDays = $salaryStandardDays;

        if ($perDayRate <= 0 && $salaryStandardDays > 0) {
            $perDayRate = round($structureGross / $salaryStandardDays, 2);
        }

        // Day-wise gross must not use monthly pro-rata (473×23/26=418). Resolve before attendance.
        $perDayRate = $this->resolvePerDayRate($structureGross, $grossInputMode, $perDayRate, $salaryStandardDays);
        $monthlyStructureGross = $this->resolveMonthlyStructureGross($structureGross, $grossInputMode, $perDayRate, $salaryStandardDays);
        $isDayWiseGross = $grossInputMode === 'day';

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
                $branchId,
                $salaryStandardDays
            );
        }

        $regularPresentDays = (float) $attendance['present_days'];
        $weekOffWorkedDays = (float) ($attendance['week_off_worked_days'] ?? 0);
        $totalWorkedDays = (float) ($attendance['total_worked_days'] ?? ($regularPresentDays + $weekOffWorkedDays));
        $presentDays = $regularPresentDays;
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
        }

        // OT Yes: cap regular pay at standard days; extra days → incentive (after deductions).
        // OT No: regular capped at standard days; extra → adjust column (optional via run flag).
        if ($otEnabled && $useAttendance) {
            $regularDaysForEarnings = min($paidDays, $salaryStandardDays);
            $incentiveDays = max(0.0, $paidDays - $salaryStandardDays);
            $proRataFactor = $this->proRataFactor($salaryStandardDays, $regularDaysForEarnings, false);
            $attendanceExtraDays = 0.0;
        } elseif ($useAttendance && $paidDays > $salaryStandardDays) {
            $regularDaysForEarnings = $salaryStandardDays;
            $incentiveDays = 0.0;
            $attendanceExtraDays = $paidDays - $salaryStandardDays;
            $proRataFactor = 1.0;
        } else {
            $regularDaysForEarnings = $paidDays;
            $incentiveDays = 0.0;
            $attendanceExtraDays = 0.0;
            $proRataFactor = $this->proRataFactor($salaryStandardDays, $regularDaysForEarnings, false);
        }

        $applyAttendanceExtra = array_key_exists('entry_apply_attendance_extra', $context)
            ? (bool) $context['entry_apply_attendance_extra']
            : (bool) ($context['apply_attendance_extra'] ?? false);

        $actualPaidDaysForPf = $paidDays;
        $pfDaysForCalc = $useAttendance
            ? min($actualPaidDaysForPf, $salaryStandardDays)
            : $salaryStandardDays;

        $components = $this->componentAssignment->resolveForEmployee($branchComponents, $emp);
        $options = [
            'apply_pf' => (bool) ($emp?->pf_flag ?? false),
            'apply_esi' => (bool) ($emp?->esic_flag ?? false),
        ];

        $split = $this->structureCalculator->splitFromGross(
            $isDayWiseGross ? $perDayRate : $monthlyStructureGross,
            $components,
            $options
        );
        $params = PayrollParameter::forFinancialYear($financialYear);

        $earningsBreakdown = [];
        if ($isDayWiseGross) {
            $targetRegularEarnings = round($perDayRate * $regularDaysForEarnings, 0);
            foreach (collect($split['breakdown'])->where('type', 'earning') as $row) {
                $name = $row['name'];
                $amount = round((float) $row['amount'] * $regularDaysForEarnings, 0);
                if ($amount > 0) {
                    $earningsBreakdown[$name] = $amount;
                }
            }
            $earningsBreakdown = $this->reconcileEarningsToTarget($earningsBreakdown, $targetRegularEarnings);

            if ($incentiveDays > 0) {
                $incentiveAmount = round($perDayRate * $incentiveDays, 0);
            }
        } else {
            foreach (collect($split['breakdown'])->where('type', 'earning') as $row) {
                $name = $row['name'];
                $amount = round((float) $row['amount'] * $proRataFactor, 0);
                if ($amount > 0) {
                    $earningsBreakdown[$name] = $amount;
                }
            }

            $targetRegularEarnings = round($monthlyStructureGross * $proRataFactor, 0);
            $earningsBreakdown = $this->reconcileEarningsToTarget($earningsBreakdown, $targetRegularEarnings);

            if ($incentiveDays > 0) {
                $incentiveAmount = round(($monthlyStructureGross / $salaryStandardDays) * $incentiveDays, 0);
            }
        }

        $deductionsBreakdown = [];
        foreach (collect($split['breakdown'])->where('type', 'deduction') as $row) {
            $amount = $isDayWiseGross
                ? round((float) $row['amount'] * $regularDaysForEarnings, 0)
                : round((float) $row['amount'] * $proRataFactor, 0);
            if ($amount > 0) {
                $deductionsBreakdown[$row['name']] = $amount;
            }
        }

        $basic = $isDayWiseGross
            ? round((float) $split['basic_amount'] * $regularDaysForEarnings, 0)
            : round((float) $split['basic_amount'] * $proRataFactor, 0);
        if (isset($earningsBreakdown['BASIC'])) {
            $basic = (float) $earningsBreakdown['BASIC'];
        }
        $regularEarnings = $targetRegularEarnings;
        $attendanceExtraAmount = 0.0;

        if ($attendanceExtraDays > 0) {
            $attendanceExtraAmount = $isDayWiseGross
                ? round($perDayRate * $attendanceExtraDays, 0)
                : round(($monthlyStructureGross / $salaryStandardDays) * $attendanceExtraDays, 0);
        }

        $attendanceExtraApplied = $applyAttendanceExtra && $attendanceExtraAmount > 0;

        $govtMinWagePerDay = null;
        $govtMinWageUsed = null;
        $govtWageBasicForPf = null;
        $govtWageMissingReason = $this->govtWageMissingReason($branchId, $branchSettings, $employee, $context);

        if ($branchId && ($branchSettings['use_government_wage_rules'] ?? false)) {
            $asOf = ! empty($context['pay_period_end'])
                ? Carbon::parse($context['pay_period_end'])
                : null;
            $govtWage = $this->branchPayrollSettings->resolveGovtMinWageForEmployee($employee, $branchId, $asOf);
            if ($govtWage && empty($govtWage['missing_reason'])) {
                $govtMinWagePerDay = (float) $govtWage['wage_per_day'];
                $govtWageBasicForPf = (float) $govtWage['basic_for_pf'];
            }
        }

        $actualPaidDays = $actualPaidDaysForPf;
        $govtWageSalaryApplied = false;
        $govtWageEquivDaysRaw = null;
        $govtWagePaidDays = null;
        $contractRegularEarnings = $regularEarnings;
        $govtWageComputedEarnings = null;
        $govtWageAdjustmentAmount = 0.0;
        $govtWageAdjustmentType = null;
        $govtWageIncentive = 0.0;

        $contractPerDay = $perDayRate;

        if ($govtMinWagePerDay && $this->govtWageSalary->shouldApplySalaryConversion($branchSettings)) {
            $govtConversion = $this->govtWageSalary->convert(
                $contractPerDay,
                $regularEarnings,
                $actualPaidDays,
                $govtMinWagePerDay
            );

            if ($govtConversion) {
                $govtWageSalaryApplied = true;
                $contractRegularEarnings = (float) $govtConversion['contract_regular_earnings'];
                $govtWageEquivDaysRaw = (float) $govtConversion['govt_equiv_days_raw'];
                $govtWagePaidDays = (float) $govtConversion['govt_wage_paid_days'];
                $govtWageComputedEarnings = (float) $govtConversion['govt_wage_computed_earnings'];
                $govtWageAdjustmentAmount = (float) $govtConversion['govt_wage_adjustment_amount'];
                $govtWageAdjustmentType = $govtConversion['govt_wage_adjustment_type'];

                $earningsBreakdown = $this->govtWageSalary->scaleEarningsBreakdown(
                    $earningsBreakdown,
                    $govtWageComputedEarnings
                );
                $basic = (float) ($earningsBreakdown['BASIC'] ?? $basic);

                if ($govtWageAdjustmentType === 'incentive') {
                    $govtWageIncentive = $govtWageAdjustmentAmount;
                    $earningsBreakdown['Govt Wage Incentive'] = $govtWageIncentive;
                }

                $paidDays = $govtWagePaidDays;
            }
        }

        $totalEarnings = round(
            ($govtWageSalaryApplied ? $govtWageComputedEarnings : $regularEarnings)
                + $incentiveAmount
                + $govtWageIncentive
                + ($attendanceExtraApplied ? $attendanceExtraAmount : 0),
            0
        );

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

            if ($govtWageSalaryApplied && $govtWageComputedEarnings !== null && $govtWageComputedEarnings > 0) {
                // Excel: PF wages = govt salary (govt days × rounded govt rate), e.g. 22 × 502 = 11,044
                $pfBasic = (float) $govtWageComputedEarnings;
                $govtMinWageUsed = round($pfBasic, 0);
            } else {
                $pfBasic = ($emp->pf_basic_salary > 0) ? (float) $emp->pf_basic_salary : (float) $split['basic_amount'];
                if ($useAttendance) {
                    $pfBasic = $isDayWiseGross
                        ? $pfBasic * $pfDaysForCalc
                        : ($pfBasic / $salaryStandardDays) * $pfDaysForCalc;
                }

                $govtMinBasic = null;
                if ($govtMinWagePerDay !== null) {
                    $govtMinBasic = $useAttendance
                        ? $govtMinWagePerDay * $pfDaysForCalc
                        : (float) ($govtWageBasicForPf ?? ($govtMinWagePerDay * $salaryStandardDays));
                }

                if ($govtMinBasic !== null && $govtMinBasic > $pfBasic) {
                    $govtMinWageUsed = round($govtMinBasic, 0);
                    $pfBasic = $govtMinBasic;
                }
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

        $statutoryEarningsBase = $govtWageSalaryApplied ? $contractRegularEarnings : $regularEarnings;
        if ($otEnabled && $incentiveAmount > 0) {
            // PT/ESI on regular only when OT Yes — incentive added after deductions.
        } elseif ($attendanceExtraApplied) {
            $statutoryEarningsBase += $attendanceExtraAmount;
        }

        if ($emp && $emp->esic_flag && $params) {
            $esiPct = PayrollParameter::esicEmployeePct($params);
            $esiEmployerPct = PayrollParameter::esicEmployerPct($params);
            $esiCeiling = PayrollParameter::esicWageLimit($params);
            $esiBase = min($statutoryEarningsBase, $esiCeiling);
            $esiEmployee = round($esiBase * $esiPct / 100, 0);
            $esiEmployer = round($esiBase * $esiEmployerPct / 100, 0);
            $deductionsBreakdown = $this->replaceStatutoryDeduction($deductionsBreakdown, ['ESI', 'ESIC'], 'ESIC', $esiEmployee);
        } else {
            $deductionsBreakdown = $this->removeStatutoryDeduction($deductionsBreakdown, ['ESI', 'ESIC']);
        }

        $ptAmount = $this->calculateProfessionalTax($statutoryEarningsBase, $financialYear);
        if ($ptAmount > 0) {
            $deductionsBreakdown['Professional Tax'] = $ptAmount;
        }

        if ($govtWageSalaryApplied && $govtWageAdjustmentType === 'deduction' && $govtWageAdjustmentAmount > 0) {
            $deductionsBreakdown['Govt Wage Adjustment'] = $govtWageAdjustmentAmount;
        }

        $totalDeductions = round(array_sum($deductionsBreakdown), 0);
        $takeHomeRegular = $govtWageSalaryApplied ? $contractRegularEarnings : $regularEarnings;
        $netSalary = round(
            $takeHomeRegular - $totalDeductions + $incentiveAmount + ($attendanceExtraApplied ? $attendanceExtraAmount : 0),
            0
        );

        return [
            'status' => 'calculated',
            'error_message' => null,
            'monthly_gross' => $grossInputMode === 'day' ? round($perDayRate, 2) : round($structureGross, 2),
            'gross_input_mode' => $grossInputMode,
            'per_day_rate' => round($perDayRate, 2),
            'structure_gross' => round($structureGross, 2),
            'basic' => $basic,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'earnings_breakdown' => $earningsBreakdown,
            'deductions_breakdown' => $deductionsBreakdown,
            'pf_employee' => $pfEmployee,
            'pf_wages' => $pfWages,
            'govt_min_wage_per_day' => $govtMinWagePerDay,
            'govt_min_wage_used' => $govtMinWageUsed,
            'pf_employer' => $pfEmployer,
            'pf_eps_employer' => $pfEpsEmployer,
            'pf_epf_employer' => $pfEpEmployer,
            'esi_employee' => $esiEmployee,
            'esi_employer' => $esiEmployer,
            'pt_amount' => $ptAmount,
            'working_days' => $workingDays,
            'working_days_source' => $workingDaysSource,
            'use_government_wage_rules' => (bool) ($branchSettings['use_government_wage_rules'] ?? false),
            'govt_wage_missing_reason' => $govtWageMissingReason,
            'govt_wage_salary_applied' => $govtWageSalaryApplied,
            'actual_paid_days' => round($actualPaidDays, 2),
            'govt_wage_equiv_days_raw' => $govtWageEquivDaysRaw,
            'govt_wage_paid_days' => $govtWagePaidDays,
            'contract_regular_earnings' => $govtWageSalaryApplied ? round($contractRegularEarnings, 0) : null,
            'govt_wage_computed_earnings' => $govtWageComputedEarnings,
            'govt_wage_adjustment_amount' => round($govtWageAdjustmentAmount, 0),
            'govt_wage_adjustment_type' => $govtWageAdjustmentType,
            'present_days' => $presentDays,
            'total_worked_days' => round($totalWorkedDays, 2),
            'paid_days' => $govtWageSalaryApplied ? $govtWagePaidDays : $paidDays,
            'week_off_worked_days' => round($weekOffWorkedDays, 2),
            'half_days' => round((float) ($attendance['half_days'] ?? 0), 2),
            'ot_enabled' => $otEnabled,
            'incentive_days' => round($incentiveDays, 2),
            'incentive_amount' => round($incentiveAmount, 2),
            'incentive_per_day_rate' => ($incentiveDays > 0 || $isDayWiseGross)
                ? round($perDayRate, 2)
                : ($salaryStandardDays > 0 ? round($monthlyStructureGross / $salaryStandardDays, 2) : 0.0),
            'regular_earnings' => round($govtWageSalaryApplied ? $contractRegularEarnings : $regularEarnings, 0),
            'attendance_extra_days' => round($attendanceExtraDays, 2),
            'attendance_extra_amount' => round($attendanceExtraAmount, 0),
            'attendance_extra_applied' => $attendanceExtraApplied,
            'mispunch_count' => (int) $attendance['mispunch_count'],
            'has_mispunch' => (bool) $attendance['has_mispunch'],
            'mispunch_dates' => $attendance['mispunch_dates'] ?? [],
            'use_attendance' => $useAttendance,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $branchSettings
     * @param  array<string, mixed>  $context
     */
    private function govtWageMissingReason(?int $branchId, ?array $branchSettings, User $employee, array $context): ?string
    {
        if (! $branchId || ! ($branchSettings['use_government_wage_rules'] ?? false)) {
            return null;
        }

        $asOf = ! empty($context['pay_period_end'])
            ? Carbon::parse($context['pay_period_end'])
            : null;
        $govtWage = $this->branchPayrollSettings->resolveGovtMinWageForEmployee($employee, $branchId, $asOf);

        return $govtWage['missing_reason'] ?? null;
    }

    private function proRataFactor(float $workingDays, float $paidDays, bool $allowOverOne = false): float
    {
        if ($workingDays <= 0) {
            return 0.0;
        }

        $factor = max(0.0, $paidDays / $workingDays);

        return $allowOverOne ? $factor : min(1.0, $factor);
    }

    private function resolvePerDayRate(float $structureGross, string $grossInputMode, float $storedPerDay, float $standardDays): float
    {
        if ($storedPerDay > 0) {
            return round($storedPerDay, 2);
        }

        if ($grossInputMode === 'day') {
            return round($structureGross, 2);
        }

        return $standardDays > 0 ? round($structureGross / $standardDays, 2) : round($structureGross, 2);
    }

    private function resolveMonthlyStructureGross(float $structureGross, string $grossInputMode, float $perDayRate, float $standardDays): float
    {
        if ($grossInputMode !== 'day') {
            return round($structureGross, 2);
        }

        if ($structureGross > $perDayRate * max(2.0, $standardDays * 0.5)) {
            return round($structureGross, 2);
        }

        return round($perDayRate * $standardDays, 2);
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
            'working_days' => BranchPayrollSettingsService::DEFAULT_WORKING_DAYS,
            'working_days_source' => 'default',
            'use_government_wage_rules' => false,
            'govt_wage_missing_reason' => null,
            'govt_min_wage_per_day' => null,
            'govt_min_wage_used' => null,
            'present_days' => 0,
            'paid_days' => 0,
            'half_days' => 0,
            'ot_enabled' => false,
            'incentive_days' => 0,
            'incentive_amount' => 0,
            'incentive_per_day_rate' => 0,
            'regular_earnings' => 0,
            'attendance_extra_days' => 0,
            'attendance_extra_amount' => 0,
            'attendance_extra_applied' => false,
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
