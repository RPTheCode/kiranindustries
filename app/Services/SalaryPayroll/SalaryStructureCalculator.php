<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryComponent;
use Illuminate\Support\Collection;

class SalaryStructureCalculator
{
    /**
     * @param  array{apply_pf?: bool|null, apply_esi?: bool|null}  $options
     * @return array{
     *     monthly_gross: float,
     *     components: array<int, float>,
     *     breakdown: array<int, array<string, mixed>>,
     *     skipped: array<int, array<string, mixed>>,
     *     total_earnings: float,
     *     total_deductions: float,
     *     net_salary: float,
     *     basic_amount: float,
     *     apply_pf: bool,
     *     apply_esi: bool
     * }
     */
    public function splitFromGross(float $gross, Collection $components, array $options = []): array
    {
        $gross = max(0, $gross);
        $applyPf = array_key_exists('apply_pf', $options) ? (bool) $options['apply_pf'] : true;
        $applyEsi = array_key_exists('apply_esi', $options) ? (bool) $options['apply_esi'] : true;

        $skipped = [];
        $active = $components
            ->where('status', 'active')
            ->filter(function (SalaryComponent $component) use ($applyPf, $applyEsi, &$skipped) {
                if (! $applyPf && $this->isPfComponent($component)) {
                    $skipped[] = $this->skippedRow($component, __('PF not applicable for this employee'));

                    return false;
                }
                if (! $applyEsi && $this->isEsiComponent($component)) {
                    $skipped[] = $this->skippedRow($component, __('ESI not applicable for this employee'));

                    return false;
                }

                return true;
            })
            ->sortBy(fn (SalaryComponent $c) => $c->type === 'earning' ? 0 : 1)
            ->values();

        $componentAmounts = [];
        $breakdown = [];
        $basicAmount = 0.0;

        $grossComponents = $active->where('calculation_type', 'percentage_of_gross');
        foreach ($grossComponents as $component) {
            $amount = (float) $component->calculateAmount(0, $gross);
            $componentAmounts[$component->id] = $amount;
            $breakdown[] = $this->row($component, $amount, 'gross', $gross);

            if (strtoupper($component->name) === 'BASIC') {
                $basicAmount = $amount;
            }
        }

        if ($basicAmount <= 0) {
            $basicComponent = $grossComponents->first(
                fn (SalaryComponent $c) => $c->type === 'earning' && strtoupper($c->name) === 'BASIC'
            ) ?? $grossComponents->first(fn (SalaryComponent $c) => $c->type === 'earning');

            if ($basicComponent && isset($componentAmounts[$basicComponent->id])) {
                $basicAmount = $componentAmounts[$basicComponent->id];
            } else {
                $basicAmount = $gross;
            }
        }

        $basicComponents = $active->where('calculation_type', 'percentage');
        foreach ($basicComponents as $component) {
            $amount = (float) $component->calculateAmount($basicAmount, $gross);
            $componentAmounts[$component->id] = $amount;
            $breakdown[] = $this->row($component, $amount, 'basic', $basicAmount);
        }

        $totalEarnings = collect($breakdown)->where('type', 'earning')->sum('amount');
        $totalDeductions = collect($breakdown)->where('type', 'deduction')->sum('amount');

        return [
            'monthly_gross' => round($gross, 2),
            'components' => $componentAmounts,
            'breakdown' => array_values($breakdown),
            'skipped' => array_values($skipped),
            'total_earnings' => round($totalEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($totalEarnings - $totalDeductions, 2),
            'basic_amount' => round($basicAmount, 2),
            'apply_pf' => $applyPf,
            'apply_esi' => $applyEsi,
        ];
    }

    public function isPfComponent(SalaryComponent $component): bool
    {
        $name = strtoupper(trim($component->name));

        if (in_array($name, ['PF', 'PF DEDUCTION', 'PROVIDENT FUND', 'EPF'], true)) {
            return true;
        }

        return $component->type === 'deduction' && str_contains($name, 'PF');
    }

    public function isEsiComponent(SalaryComponent $component): bool
    {
        $name = strtoupper(trim($component->name));

        if (in_array($name, ['ESI', 'ESIC', 'ESI DEDUCTION'], true)) {
            return true;
        }

        return $component->type === 'deduction' && (str_contains($name, 'ESI') || str_contains($name, 'ESIC'));
    }

    private function row(SalaryComponent $component, float $amount, string $base, float $baseAmount): array
    {
        $rate = $component->calculation_type === 'percentage_of_gross'
            ? (float) $component->percentage_of_gross_pay
            : (float) $component->percentage_of_basic;

        return [
            'id' => $component->id,
            'name' => $component->name,
            'type' => $component->type,
            'calculation_type' => $component->calculation_type,
            'rate' => $rate,
            'base' => $base,
            'base_amount' => round($baseAmount, 2),
            'amount' => round($amount, 2),
            'skipped' => false,
        ];
    }

    private function skippedRow(SalaryComponent $component, string $reason): array
    {
        return [
            'id' => $component->id,
            'name' => $component->name,
            'type' => $component->type,
            'reason' => $reason,
            'skipped' => true,
        ];
    }
}
