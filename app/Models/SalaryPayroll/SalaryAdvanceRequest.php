<?php

namespace App\Models\SalaryPayroll;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdvanceRequest extends Model
{
    public const TYPE_GENERAL = 'general';

    /** @deprecated Legacy values kept for existing rows */
    public const TYPE_20 = 'type_20';

    public const TYPE_25 = 'type_25';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_RECOVERING = 'recovering';

    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'branch_id',
        'application_date',
        'advance_type',
        'requested_amount',
        'approved_amount',
        'amount_in_words',
        'purpose',
        'present_salary_snapshot',
        'earned_salary_snapshot',
        'allowed_amount_snapshot',
        'month_year',
        'status',
        'disbursement_date',
        'paid_amount',
        'recovered_in_salary_payroll_entry_id',
        'created_by',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'application_date' => 'date',
        'disbursement_date' => 'date',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'present_salary_snapshot' => 'decimal:2',
        'earned_salary_snapshot' => 'decimal:2',
        'allowed_amount_snapshot' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'pending_amount',
        'recovery_amount',
    ];

    public function getPendingAmountAttribute(): float
    {
        $total = (float) ($this->approved_amount ?? $this->requested_amount);

        return max(0, round($total - (float) $this->paid_amount, 2));
    }

    public function getRecoveryAmountAttribute(): float
    {
        return $this->pending_amount;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true);
    }

    public function isPendingRecovery(): bool
    {
        return in_array($this->status, [self::STATUS_DISBURSED, self::STATUS_RECOVERING], true)
            && $this->pending_amount > 0;
    }

    public function scopePendingRecovery(Builder $query, ?string $periodEndDate = null): Builder
    {
        $query->whereIn('status', [self::STATUS_DISBURSED, self::STATUS_RECOVERING])
            ->whereRaw('paid_amount < COALESCE(approved_amount, requested_amount)');

        if ($periodEndDate !== null) {
            $query->whereDate('disbursement_date', '<=', $periodEndDate);
        }

        return $query;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recoveredInEntry(): BelongsTo
    {
        return $this->belongsTo(SalaryPayrollEntry::class, 'recovered_in_salary_payroll_entry_id');
    }

}
