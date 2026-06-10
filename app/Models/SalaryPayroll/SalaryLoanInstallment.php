<?php

namespace App\Models\SalaryPayroll;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryLoanInstallment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'salary_loan_request_id',
        'installment_no',
        'due_month',
        'amount',
        'status',
        'paid_at',
        'recovered_in_salary_payroll_entry_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'installment_no' => 'integer',
    ];

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SalaryLoanRequest::class, 'salary_loan_request_id');
    }

    public function recoveredInEntry(): BelongsTo
    {
        return $this->belongsTo(SalaryPayrollEntry::class, 'recovered_in_salary_payroll_entry_id');
    }
}
