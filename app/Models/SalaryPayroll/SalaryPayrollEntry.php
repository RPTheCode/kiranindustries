<?php

namespace App\Models\SalaryPayroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalaryPayrollEntry extends Model
{
    protected $fillable = [
        'salary_payroll_run_id',
        'employee_id',
        'monthly_gross',
        'working_days',
        'present_days',
        'half_days',
        'week_off_worked_days',
        'paid_days',
        'incentive_days',
        'incentive_amount',
        'attendance_extra_days',
        'attendance_extra_amount',
        'apply_attendance_extra',
        'attendance_extra_applied',
        'mispunch_count',
        'has_mispunch',
        'ot_enabled',
        'basic',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'earnings_breakdown',
        'deductions_breakdown',
        'pf_employee',
        'pf_wages',
        'govt_min_wage_per_day',
        'govt_min_wage_used',
        'govt_wage_salary_applied',
        'actual_paid_days',
        'govt_wage_equiv_days_raw',
        'govt_wage_paid_days',
        'contract_regular_earnings',
        'govt_wage_computed_earnings',
        'govt_wage_adjustment_amount',
        'govt_wage_adjustment_type',
        'working_days_source',
        'pf_employer',
        'pf_eps_employer',
        'pf_epf_employer',
        'esi_employee',
        'esi_employer',
        'pt_amount',
        'status',
        'error_message',
        'is_locked',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'monthly_gross' => 'decimal:2',
        'working_days' => 'decimal:2',
        'present_days' => 'decimal:2',
        'half_days' => 'decimal:2',
        'week_off_worked_days' => 'decimal:2',
        'paid_days' => 'decimal:2',
        'incentive_days' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'attendance_extra_days' => 'decimal:2',
        'attendance_extra_amount' => 'decimal:2',
        'apply_attendance_extra' => 'boolean',
        'attendance_extra_applied' => 'boolean',
        'mispunch_count' => 'integer',
        'has_mispunch' => 'boolean',
        'ot_enabled' => 'boolean',
        'basic' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'earnings_breakdown' => 'array',
        'deductions_breakdown' => 'array',
        'pf_employee' => 'decimal:2',
        'pf_wages' => 'decimal:2',
        'govt_min_wage_per_day' => 'decimal:2',
        'govt_min_wage_used' => 'decimal:2',
        'govt_wage_salary_applied' => 'boolean',
        'actual_paid_days' => 'decimal:2',
        'govt_wage_equiv_days_raw' => 'decimal:4',
        'govt_wage_paid_days' => 'decimal:2',
        'contract_regular_earnings' => 'decimal:2',
        'govt_wage_computed_earnings' => 'decimal:2',
        'govt_wage_adjustment_amount' => 'decimal:2',
        'pf_employer' => 'decimal:2',
        'pf_eps_employer' => 'decimal:2',
        'pf_epf_employer' => 'decimal:2',
        'esi_employee' => 'decimal:2',
        'esi_employer' => 'decimal:2',
        'pt_amount' => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SalaryPayrollRun::class, 'salary_payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(SalaryPayrollPayslip::class, 'salary_payroll_entry_id');
    }

    public function isLocked(): bool
    {
        if ($this->is_locked) {
            return true;
        }

        return $this->run?->isFinalized() ?? false;
    }
}
