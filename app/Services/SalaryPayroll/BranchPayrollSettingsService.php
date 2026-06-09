<?php

namespace App\Services\SalaryPayroll;

use App\Models\Branch;
use App\Models\PayrollParameter;
use App\Models\SkillWageRate;
use App\Models\User;
use Carbon\Carbon;

class BranchPayrollSettingsService
{
    public const DEFAULT_WORKING_DAYS = 26.0;

    public const DEFAULT_GOVT_WAGE_MODE = 'pf_compliance';

    /**
     * @return array{
     *     branch_id: int,
     *     branch_name: ?string,
     *     standard_working_days: ?int,
     *     use_government_wage_rules: bool,
     *     govt_wage_mode: string,
     *     wage_zone_id: ?int,
     *     wage_zone_name: ?string,
     *     working_days: float,
     *     working_days_source: string
     * }
     */
    public function resolve(int $branchId, ?string $financialYear = null): array
    {
        $branch = Branch::with('wageZone')->find($branchId);
        $workingDays = $this->resolveWorkingDays($branchId, $financialYear);
        $source = $this->resolveWorkingDaysSource($branch, $financialYear);

        return [
            'branch_id' => $branchId,
            'branch_name' => $branch?->name,
            'standard_working_days' => $branch?->standard_working_days !== null
                ? (int) $branch->standard_working_days
                : null,
            'use_government_wage_rules' => (bool) ($branch?->use_government_wage_rules ?? false),
            'govt_wage_mode' => $branch?->govt_wage_mode ?? self::DEFAULT_GOVT_WAGE_MODE,
            'wage_zone_id' => $branch?->wage_zone_id ? (int) $branch->wage_zone_id : null,
            'wage_zone_name' => $branch?->wageZone?->displayLabel() ?? $branch?->wageZone?->name,
            'working_days' => $workingDays,
            'working_days_source' => $source,
        ];
    }

    public function resolveWorkingDays(int $branchId, ?string $financialYear = null): float
    {
        $branch = Branch::with('wageZone')->find($branchId);

        if ($branch && $branch->standard_working_days !== null && (int) $branch->standard_working_days > 0) {
            return (float) $branch->standard_working_days;
        }

        if ($branch?->wageZone && (int) $branch->wageZone->working_days > 0) {
            return (float) $branch->wageZone->working_days;
        }

        if ($financialYear) {
            $params = PayrollParameter::forFinancialYear($financialYear);
            if ($params && $params->standard_working_days !== null && (int) $params->standard_working_days > 0) {
                return (float) $params->standard_working_days;
            }
        }

        return self::DEFAULT_WORKING_DAYS;
    }

    /**
     * @return array{
     *     wage_per_day: float,
     *     wage_per_month: float,
     *     basic_for_pf: float,
     *     skill_id: int,
     *     skill_name: ?string,
     *     wage_zone_id: int,
     *     missing_reason: null
     * }|array{missing_reason: string}
     */
    public function resolveGovtMinWageForEmployee(User $employee, int $branchId, ?Carbon $asOf = null): ?array
    {
        $branch = Branch::find($branchId);
        if (! $branch || ! $branch->use_government_wage_rules) {
            return null;
        }

        if (! $branch->wage_zone_id) {
            return ['missing_reason' => 'no_wage_zone'];
        }

        $emp = $employee->employee;
        $skillIds = is_array($emp?->skill_id) ? $emp->skill_id : [];
        $skillId = ! empty($skillIds) ? (int) ($skillIds[0] ?? 0) : 0;

        if ($skillId <= 0) {
            return ['missing_reason' => 'no_skill'];
        }

        $asOfDate = ($asOf ?? now())->toDateString();

        $rate = SkillWageRate::query()
            ->where('skill_id', $skillId)
            ->where('wage_zone_id', $branch->wage_zone_id)
            ->where('effective_from', '<=', $asOfDate)
            ->orderByDesc('effective_from')
            ->with('skill')
            ->first();

        if (! $rate || (float) $rate->wage_per_day <= 0) {
            return ['missing_reason' => 'no_zone_rate'];
        }

        $workingDays = $this->resolveWorkingDays($branchId);
        $wagePerDay = (float) $rate->wage_per_day;
        $wagePerMonth = (float) ($rate->wage_per_month ?? ($wagePerDay * $workingDays));

        return [
            'wage_per_day' => $wagePerDay,
            'wage_per_month' => $wagePerMonth,
            'basic_for_pf' => $wagePerMonth,
            'skill_id' => $skillId,
            'skill_name' => $rate->skill?->name,
            'wage_zone_id' => (int) $branch->wage_zone_id,
            'missing_reason' => null,
        ];
    }

    private function resolveWorkingDaysSource(?Branch $branch, ?string $financialYear = null): string
    {
        if ($branch && $branch->standard_working_days !== null && (int) $branch->standard_working_days > 0) {
            return 'branch';
        }

        if ($branch?->wageZone && (int) $branch->wageZone->working_days > 0) {
            return 'zone';
        }

        if ($financialYear) {
            $params = PayrollParameter::forFinancialYear($financialYear);
            if ($params && $params->standard_working_days !== null && (int) $params->standard_working_days > 0) {
                return 'company';
            }
        }

        return 'default';
    }
}
