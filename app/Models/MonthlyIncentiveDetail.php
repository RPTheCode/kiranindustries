<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyIncentiveDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_incentive_entry_id',
        'type_id',
        'deduction_type_id',
        'name',
        'type',
        'mode',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function entry()
    {
        return $this->belongsTo(MonthlyIncentiveEntry::class, 'monthly_incentive_entry_id');
    }

    public function type()
    {
        return $this->belongsTo(IncentiveDeductionType::class, 'type_id');
    }

    public function deductionType()
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id');
    }
}
