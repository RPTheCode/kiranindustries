<?php

namespace App\Services\SalaryPayroll;

class GovernmentWageSalaryService
{
    public const ROUNDING_INTEGER = 'integer';

    public const ROUNDING_HALF = 'half';

    /**
     * When contract daily rate is below government minimum, convert earned amount
     * to equivalent days at govt rate and balance difference via incentive/deduction.
     *
     * Example: ₹473 × 23 = ₹10,879 → ÷ ₹502 = 21.67 → 22 days → 22 × 502 = ₹11,044
     * Contract target ₹10,879 → deduction ₹165 so net regular stays at contract amount.
     *
     * @return array<string, mixed>|null
     */
    public function convert(
        float $contractPerDay,
        float $contractRegularEarnings,
        float $actualPaidDays,
        float $govtWagePerDay,
        string $rounding = self::ROUNDING_INTEGER
    ): ?array {
        if ($govtWagePerDay <= 0 || $contractRegularEarnings <= 0 || $contractPerDay <= 0) {
            return null;
        }

        if ($contractPerDay >= $govtWagePerDay) {
            return null;
        }

        // Excel/register use whole-rupee govt day rate (e.g. 501.5 → 502 for 22×502=11,044).
        $govtRateForSalary = round($govtWagePerDay, 0);

        $equivDaysRaw = $contractRegularEarnings / $govtRateForSalary;
        $govtPaidDays = $this->roundEquivalentDays($equivDaysRaw, $rounding);
        $govtComputedEarnings = round($govtPaidDays * $govtRateForSalary, 0);
        $diff = round($contractRegularEarnings - $govtComputedEarnings, 0);

        $adjustmentAmount = 0.0;
        $adjustmentType = null;

        if ($diff > 0) {
            $adjustmentAmount = $diff;
            $adjustmentType = 'incentive';
        } elseif ($diff < 0) {
            $adjustmentAmount = abs($diff);
            $adjustmentType = 'deduction';
        }

        return [
            'applied' => true,
            'contract_per_day' => round($contractPerDay, 2),
            'contract_regular_earnings' => round($contractRegularEarnings, 0),
            'actual_paid_days' => round($actualPaidDays, 2),
            'govt_wage_per_day' => round($govtRateForSalary, 0),
            'govt_wage_per_day_exact' => round($govtWagePerDay, 2),
            'govt_equiv_days_raw' => round($equivDaysRaw, 4),
            'govt_wage_paid_days' => $govtPaidDays,
            'govt_wage_computed_earnings' => $govtComputedEarnings,
            'govt_wage_adjustment_amount' => $adjustmentAmount,
            'govt_wage_adjustment_type' => $adjustmentType,
            'target_regular_earnings' => round($contractRegularEarnings, 0),
        ];
    }

    /**
     * Scale component earnings breakdown to a new total (govt-rate salary).
     *
     * @param  array<string, float>  $breakdown
     * @return array<string, float>
     */
    public function scaleEarningsBreakdown(array $breakdown, float $newTotal): array
    {
        if ($newTotal <= 0) {
            return [];
        }

        if ($breakdown === []) {
            return ['BASIC' => $newTotal];
        }

        $oldTotal = array_sum($breakdown);
        if ($oldTotal <= 0) {
            return ['BASIC' => $newTotal];
        }

        $scaled = [];
        foreach ($breakdown as $name => $amount) {
            $scaled[$name] = round((float) $amount / $oldTotal * $newTotal, 0);
        }

        $sum = round(array_sum($scaled), 0);
        $diff = (int) round($newTotal - $sum);

        if ($diff !== 0) {
            $adjustKey = array_key_exists('BASIC', $scaled)
                ? 'BASIC'
                : array_keys($scaled, max($scaled), true)[0];
            $scaled[$adjustKey] = max(0, (float) $scaled[$adjustKey] + $diff);
        }

        return $scaled;
    }

    public function shouldApplySalaryConversion(?array $branchSettings): bool
    {
        if (! ($branchSettings['use_government_wage_rules'] ?? false)) {
            return false;
        }

        $mode = $branchSettings['govt_wage_mode'] ?? BranchPayrollSettingsService::DEFAULT_GOVT_WAGE_MODE;

        return in_array($mode, ['salary_floor', 'both'], true);
    }

    private function roundEquivalentDays(float $rawDays, string $rounding): float
    {
        if ($rounding === self::ROUNDING_HALF) {
            return round($rawDays * 2) / 2;
        }

        return (float) round($rawDays);
    }
}
