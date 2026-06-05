<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    /**
     * Reset salary components to gross-based structure: Basic 60%, HRA 40%, LTA 10%.
     */
    public function run(): void
    {
        SalaryComponent::withoutGlobalScopes()->delete();

        $branches = Branch::query()->get(['id', 'created_by']);

        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Salary components not seeded.');

            return;
        }

        $components = [
            [
                'name' => 'BASIC',
                'percentage_of_gross_pay' => 60,
                'is_mandatory' => true,
            ],
            [
                'name' => 'HRA',
                'percentage_of_gross_pay' => 40,
                'is_mandatory' => false,
            ],
            [
                'name' => 'LTA',
                'percentage_of_gross_pay' => 10,
                'is_mandatory' => false,
            ],
        ];

        $labels = [
            'BASIC' => 'Basic Salary',
            'HRA' => 'House Rent Allowance',
            'LTA' => 'Leave Travel Allowance',
        ];

        foreach ($branches as $branch) {
            foreach ($components as $componentData) {
                $label = $labels[$componentData['name']] ?? $componentData['name'];
                $pct = $componentData['percentage_of_gross_pay'];

                SalaryComponent::create([
                    'name' => $componentData['name'],
                    'description' => "{$label} — {$pct}% of gross",
                    'type' => 'earning',
                    'calculation_type' => 'percentage_of_gross',
                    'default_amount' => 0,
                    'percentage_of_basic' => null,
                    'percentage_of_gross_pay' => $componentData['percentage_of_gross_pay'],
                    'rounding_method' => 'round',
                    'is_taxable' => true,
                    'is_mandatory' => $componentData['is_mandatory'],
                    'status' => 'active',
                    'created_by' => $branch->created_by ?? 1,
                    'branch_id' => $branch->id,
                ]);
            }
        }

        $this->command->info('Salary components reset: BASIC 60%, HRA 40%, LTA 10% per branch.');
    }
}
