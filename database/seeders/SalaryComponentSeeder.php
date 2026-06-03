<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all companies
        $companies = User::where('type', 'company')->get();

        if ($companies->isEmpty()) {
            $this->command->warn('No company users found. Please run DefaultCompanySeeder first.');
            return;
        }

        // Fixed salary components for consistent data
        $salaryComponents = [
            [
                'name' => 'BASIC',
                'description' => 'Basic Salary',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 0.00,
                'percentage_of_basic' => null,
                'is_taxable' => true,
                'is_mandatory' => true,
                'status' => 'active'
            ],
            [
                'name' => 'HRA',
                'description' => 'House Rent Allowance',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 0.00,
                'percentage_of_basic' => null,
                'is_taxable' => true,
                'is_mandatory' => false,
                'status' => 'active'
            ],
            [
                'name' => 'LTA',
                'description' => 'Leave Travel Allowance',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 0.00,
                'percentage_of_basic' => null,
                'is_taxable' => true,
                'is_mandatory' => false,
                'status' => 'active'
            ],
            [
                'name' => 'PF BASIC',
                'description' => 'PF Basic Salary',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 0.00,
                'percentage_of_basic' => null,
                'is_taxable' => true,
                'is_mandatory' => false,
                'status' => 'active'
            ],
            [
                'name' => 'ALLOWANCE',
                'description' => 'Special Allowance',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 0.00,
                'percentage_of_basic' => null,
                'is_taxable' => true,
                'is_mandatory' => false,
                'status' => 'active'
            ],
            [
                'name' => 'MEDICLAIM',
                'description' => 'Medical Insurance/Allowance',
                'type' => 'earning',
                'calculation_type' => 'fixed',
                'default_amount' => 0.00,
                'percentage_of_basic' => null,
                'is_taxable' => true,
                'is_mandatory' => false,
                'status' => 'active'
            ],
        ];

        foreach ($companies as $company) {
            foreach ($salaryComponents as $componentData) {
                // Check if salary component already exists for this company
                if (SalaryComponent::where('name', $componentData['name'])->where('created_by', $company->id)->exists()) {
                    continue;
                }

                try {
                    SalaryComponent::create([
                        'name' => $componentData['name'],
                        'description' => $componentData['description'],
                        'type' => $componentData['type'],
                        'calculation_type' => $componentData['calculation_type'],
                        'default_amount' => $componentData['default_amount'],
                        'percentage_of_basic' => $componentData['percentage_of_basic'],
                        'is_taxable' => $componentData['is_taxable'],
                        'is_mandatory' => $componentData['is_mandatory'],
                        'status' => $componentData['status'],
                        'created_by' => $company->id,
                    ]);
                } catch (\Exception $e) {
                    $this->command->error('Failed to create salary component: ' . $componentData['name'] . ' for company: ' . $company->name);
                    continue;
                }
            }
        }

        $this->command->info('SalaryComponent seeder completed successfully!');
    }
}
