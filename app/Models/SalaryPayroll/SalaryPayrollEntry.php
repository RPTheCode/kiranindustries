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
        'basic',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'earnings_breakdown',
        'deductions_breakdown',
        'pf_employee',
        'pf_employer',
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
        'basic' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'earnings_breakdown' => 'array',
        'deductions_breakdown' => 'array',
        'pf_employee' => 'decimal:2',
        'pf_employer' => 'decimal:2',
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
