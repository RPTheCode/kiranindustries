<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftDutyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_slot_id',
        'rule_name',
        'min_minutes',
        'max_minutes',
        'status',
        'duty_value',
        'color',
        'priority',
    ];

    protected $casts = [
        'duty_value' => 'decimal:2',
    ];

    public function shiftSlot()
    {
        return $this->belongsTo(ShiftSlot::class);
    }
}
