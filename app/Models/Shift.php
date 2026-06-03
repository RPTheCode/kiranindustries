<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'branch_id',
        'name',
        'short_code',
        'description',
        'is_multi',
        'status',
        'created_by'
    ];

    protected $casts = [
        'is_night_shift' => 'boolean',
        'is_multi' => 'boolean',
    ];

    public function slots()
    {
        return $this->hasMany(ShiftSlot::class);
    }

    /**
     * Get the user who created the shift.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the branch that owns the shift.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if the shift is a night shift.
     */
    public function isNightShift()
    {
        return (bool) $this->is_night_shift;
    }

    /**
     * Scope a query to only include day shifts.
     */
    public function scopeDayShifts($query)
    {
        return $query->where('is_night_shift', false)->orWhereNull('is_night_shift');
    }

    /**
     * Scope a query to only include night shifts.
     */
    public function scopeNightShifts($query)
    {
        return $query->where('is_night_shift', true);
    }
}