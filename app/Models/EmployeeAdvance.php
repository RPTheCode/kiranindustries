<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Salary advance given to an employee (HR → Employee Advance Pay).
 *
 * employee_id = users.id (not employees.id).
 *
 * Status:
 * - paid      = advance disbursed; pending recovery via payroll
 * - recovered = fully deducted from salary (paid_amount >= amount)
 */
class EmployeeAdvance extends BaseModel
{
    use HasFactory;

    /** Advance paid out to employee; recover in payroll. */
    public const STATUS_DISBURSED = 'paid';

    /** Fully recovered from salary. */
    public const STATUS_RECOVERED = 'recovered';

    protected $fillable = [
        'employee_id',
        'amount',
        'pay_date',
        'status',
        'remarks',
        'created_by',
        'payroll_entry_id',
        'paid_amount',
        'branch_id',
    ];

    protected $casts = [
        'pay_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected $appends = [
        'pending_amount',
    ];

    /**
     * Remaining amount to recover from salary.
     */
    public function getPendingAmountAttribute(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function isPendingRecovery(): bool
    {
        return $this->status === self::STATUS_DISBURSED && $this->pending_amount > 0;
    }

    /**
     * Advances eligible for payroll deduction (disbursed, not fully recovered).
     */
    public function scopePendingRecovery(Builder $query, $periodEndDate = null): Builder
    {
        $query->where('status', self::STATUS_DISBURSED)
            ->whereColumn('amount', '>', 'paid_amount');

        if ($periodEndDate !== null) {
            $query->whereDate('pay_date', '<=', $periodEndDate);
        }

        return $query;
    }

    /** Login user (payroll uses this id). */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** HR employee profile (employees table). */
    public function employeeRecord()
    {
        return $this->hasOne(Employee::class, 'user_id', 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** Last payroll entry that recovered part of this advance. */
    public function payrollEntry()
    {
        return $this->belongsTo(PayrollEntry::class);
    }
}
