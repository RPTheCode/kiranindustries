<?php

namespace App\Models\SalaryPayroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayrollPayslip extends Model
{
    protected $fillable = [
        'salary_payroll_entry_id',
        'salary_payroll_run_id',
        'employee_id',
        'payslip_number',
        'file_path',
        'generated_at',
        'downloaded_at',
        'generated_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SalaryPayrollEntry::class, 'salary_payroll_entry_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SalaryPayrollRun::class, 'salary_payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
