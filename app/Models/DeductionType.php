<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionType extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    public const MODE_DAY = 'day';

    public const MODE_MONTH = 'month';

    public const AMOUNT_FIXED = 'fixed';

    public const AMOUNT_CATEGORY_WISE = 'category_wise';

    protected $fillable = [
        'name',
        'default_amount',
        'amount_type',
        'calculation_mode',
        'sort_order',
        'status',
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'category_amounts_list',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function monthlyIncentiveDetails()
    {
        return $this->hasMany(MonthlyIncentiveDetail::class, 'deduction_type_id');
    }

    public function categoryAmounts(): HasMany
    {
        return $this->hasMany(DeductionTypeCategoryAmount::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function usesCategoryAmounts(): bool
    {
        return $this->amount_type === self::AMOUNT_CATEGORY_WISE;
    }

    public function getRateForCategory(?int $categoryId): float
    {
        if ($this->usesCategoryAmounts() && $categoryId) {
            $match = $this->relationLoaded('categoryAmounts')
                ? $this->categoryAmounts->firstWhere('category_id', $categoryId)
                : $this->categoryAmounts()->where('category_id', $categoryId)->first();

            if ($match) {
                return (float) $match->amount;
            }
        }

        return (float) $this->default_amount;
    }

    public function resolveAmount(float $value, ?int $categoryId = null): float
    {
        $rate = $this->getRateForCategory($categoryId);

        if ($this->calculation_mode === self::MODE_DAY) {
            return round(max(0, $value) * $rate, 2);
        }

        return round($rate, 2);
    }

    public function entryMode(): string
    {
        return $this->calculation_mode === self::MODE_DAY ? 'day' : 'amount';
    }

    public function defaultEntryValue(?int $categoryId = null): float
    {
        if ($this->calculation_mode === self::MODE_DAY) {
            return 1;
        }

        return $this->getRateForCategory($categoryId);
    }

    public function getCategoryAmountsListAttribute(): array
    {
        if (! $this->relationLoaded('categoryAmounts')) {
            return [];
        }

        return $this->categoryAmounts->map(fn ($row) => [
            'category_id' => $row->category_id,
            'category_name' => $row->category?->name,
            'amount' => (float) $row->amount,
        ])->values()->all();
    }
}
