<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSlot extends Model
{
    protected $fillable = [
        'shift_id',
        'slot_name',
        'start_time',
        'end_time',
        'grace_before_in',
        'grace_after_out',
        'priority',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function dutyRules()
    {
        return $this->hasMany(ShiftDutyRule::class)->orderBy('priority', 'asc');
    }
}
