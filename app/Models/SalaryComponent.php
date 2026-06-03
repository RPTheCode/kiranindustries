<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'name',
        'description',
        'type',
        'calculation_type',
        'default_amount',
        'percentage_of_basic',
        'percentage_of_gross_pay',
        'rounding_method',
        'is_taxable',
        'is_mandatory',
        'status',
        'created_by',
        'branch_id',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'percentage_of_basic' => 'decimal:2',
        'percentage_of_gross_pay' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_mandatory' => 'boolean',
    ];

    /**
     * Get the user who created the component.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Calculate component amount based on basic salary.
     */
    public function calculateAmount($basicSalary = 0, $grossSalary = 0)
    {
        $amount = 0;
        if ($this->calculation_type === 'percentage' && $this->percentage_of_basic) {
            $amount = ($basicSalary * $this->percentage_of_basic) / 100;
        } elseif ($this->calculation_type === 'percentage_of_gross' && $this->percentage_of_gross_pay) {
            $amount = ($grossSalary * $this->percentage_of_gross_pay) / 100;
        } else {
            $amount = $this->default_amount;
        }

        // Apply rounding
        switch ($this->rounding_method) {
            case 'round':
                return round($amount);
            case 'ceil':
                return ceil($amount);
            case 'floor':
                return floor($amount);
            case 'none':
            default:
                return round($amount, 2);
        }
    }

    /**
     * Get earnings components.
     */
    public static function getEarnings()
    {
        return static::where('type', 'earning')
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get deductions components.
     */
    public static function getDeductions()
    {
        return static::where('type', 'deduction')
            ->where('status', 'active')
            ->get();
    }
}