<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'monthly_gross',
        'gross_input_mode',
        'basic_salary',
        'per_day_salary',
        'minimum_wages',
        'min_wage_per_day',
        'components',
        'is_active',
        'calculation_status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'monthly_gross' => 'decimal:2',
        'per_day_salary' => 'decimal:2',
        'minimum_wages' => 'decimal:2',
        'min_wage_per_day' => 'decimal:2',
        'components' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee.
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }



    /**
     * Get the user who created the salary.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get active salary for employee.
     */
    public static function getActiveSalary($employeeId)
    {
        return static::where('employee_id', $employeeId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get basic salary for employee.
     */
    public static function getBasicSalary($employeeId)
    {
        $salary = static::getActiveSalary($employeeId);
        return $salary ? $salary->basic_salary : 0;
    }

    /**
     * Calculate salary components based on selected components.
     */
    public function calculateAllComponents($calculationType = 'basic_pay')
    {
        $baseSalary = ($calculationType === 'minimum_wages') ? ($this->minimum_wages ?? 0) : $this->basic_salary;

        $componentsData = $this->components ?? [];
        $selectedComponentIds = is_numeric(key($componentsData)) && key($componentsData) === 0 ? $componentsData : array_keys($componentsData);

        /** @var \Illuminate\Database\Eloquent\Collection<int, SalaryComponent> $components */
        $components = SalaryComponent::withoutGlobalScopes()
            ->whereIn('id', $selectedComponentIds)
            ->where('status', 'active')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        $earnings = ['Basic Salary' => $baseSalary];

        $deductions = [];
        $totalEarnings = $baseSalary;
        $totalDeductions = 0;

        // Separate components into those that depend on gross and those that don't
        $basicComponents = $components->filter(fn($c) => $c->calculation_type !== 'percentage_of_gross');
        $grossComponents = $components->filter(fn($c) => $c->calculation_type === 'percentage_of_gross');

        // First pass: Calculate basic components
        foreach ($basicComponents as $component) {
            $amount = 0;
            $compName = strtolower(str_replace([' ', '.', '_'], '', $component->name));
            $emp = $this->employee ? $this->employee->employee : null;

            // Priority 1: Check if specific amount is stored in the components JSON [id => amount]
            if (isset($componentsData[$component->id]) && is_numeric($componentsData[$component->id])) {
                $amount = $componentsData[$component->id];
            } 
            // Priority 2: Check legacy mapping to employee fields
            elseif ($emp && $compName === 'hra') {
                $amount = $emp->hra_allowance;
            } elseif ($emp && $compName === 'lta') {
                $amount = $emp->lta_allowance;
            } elseif ($emp && ($compName === 'conveyance' || $compName === 'convey')) {
                $amount = $emp->conveyance_allowance;
            } elseif ($emp && ($compName === 'allowance' || $compName === 'specialallowance')) {
                $amount = $emp->special_allowance;
            } elseif ($emp && ($compName === 'medical' || $compName === 'mediclaim')) {
                $amount = $emp->medical_allowance;
            } elseif ($emp && $compName === 'education') {
                $amount = $emp->education_allowance;
            } elseif ($emp && $compName === 'pfbasic') {
                $amount = $emp->pf_basic_salary;
            } else {
                $amount = $component->calculateAmount($baseSalary);
            }

            if ($component->type === 'earning') {
                $earnings[$component->name] = $amount;
                $totalEarnings += $amount;
            } else {
                $deductions[$component->name] = $amount;
                $totalDeductions += $amount;
            }
        }

        // Second pass: Calculate components based on gross (total earnings so far)
        foreach ($grossComponents as $component) {
            $amount = $component->calculateAmount($baseSalary, $totalEarnings);
            if ($component->type === 'earning') {
                $earnings[$component->name] = $amount;
                $totalEarnings += $amount;
            } else {
                $deductions[$component->name] = $amount;
                $totalDeductions += $amount;
            }
        }

        return [
            'basic_salary' => $baseSalary,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'gross_salary' => $totalEarnings,
            'net_salary' => $totalEarnings - $totalDeductions,
        ];
    }
}
