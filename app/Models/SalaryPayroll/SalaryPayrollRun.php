<?php

namespace App\Models\SalaryPayroll;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPayrollRun extends Model
{
    protected $fillable = [
        'branch_id',
        'financial_year',
        'month_year',
        'pay_period_start',
        'pay_period_end',
        'title',
        'scope_mode',
        'scope_filters',
        'use_attendance',
        'status',
        'employee_count',
        'total_gross',
        'total_net',
        'total_pf_employee',
        'total_pf_employer',
        'total_esi_employee',
        'total_esi_employer',
        'created_by',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'scope_filters' => 'array',
        'use_attendance' => 'boolean',
        'finalized_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_net' => 'decimal:2',
        'total_pf_employee' => 'decimal:2',
        'total_pf_employer' => 'decimal:2',
        'total_esi_employee' => 'decimal:2',
        'total_esi_employer' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SalaryPayrollEntry::class);
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function scopeLabel(): string
    {
        return match ($this->scope_mode) {
            'category' => __('By Category'),
            'shift' => __('By Shift'),
            'employee' => __('Selected Employees'),
            default => __('All Employees'),
        };
    }
}
