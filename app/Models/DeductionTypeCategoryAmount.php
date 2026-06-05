<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionTypeCategoryAmount extends Model
{
    protected $fillable = [
        'deduction_type_id',
        'category_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
