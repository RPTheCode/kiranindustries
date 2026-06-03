<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyIncentiveEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(MonthlyIncentiveDetail::class, 'monthly_incentive_entry_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
