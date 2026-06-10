<?php

namespace App\Models\SalaryPayroll;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryLoanRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_RECOVERING = 'recovering';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const MAX_INSTALLMENTS = 6;

    public const DEFAULT_INSTALLMENTS = 3;

    public const MAX_ACTIVE_LOANS_PER_YEAR = 2;

    protected $fillable = [
        'employee_id',
        'branch_id',
        'application_date',
        'requested_amount',
        'approved_amount',
        'installment_count',
        'installment_amount',
        'amount_in_words',
        'purpose',
        'present_salary_snapshot',
        'month_year',
        'deduction_start_month',
        'status',
        'disbursement_date',
        'paid_amount',
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
        'installment_amount' => 'decimal:2',
        'present_salary_snapshot' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'installment_count' => 'integer',
    ];

    protected $appends = [
        'pending_amount',
    ];

    public function getPendingAmountAttribute(): float
    {
        $total = (float) ($this->approved_amount ?? $this->requested_amount);

        return max(0, round($total - (float) $this->paid_amount, 2));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true);
    }

    public function isActiveLoan(): bool
    {
        return in_array($this->status, [self::STATUS_DISBURSED, self::STATUS_RECOVERING], true);
    }

    public function scopeActiveForRecovery(Builder $query, ?string $periodEndDate = null): Builder
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

    public function guarantors(): HasMany
    {
        return $this->hasMany(SalaryLoanGuarantor::class)->orderBy('sort_order');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SalaryLoanInstallment::class)->orderBy('installment_no');
    }
}
