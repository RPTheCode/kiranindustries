<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeekOff extends Model
{
    use HasFactory, \App\Traits\RecordsActivity;

    protected $fillable = [
        'branch_id',
        'employment_type',
        'type',
        'settings',
        'total_days',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if a given date is a week off based on settings.
     *
     * @param \Carbon\Carbon $date
     * @return bool
     */
    public function isDateWeekOff(\Carbon\Carbon $date)
    {
        if ($this->type === 'weekly') {
            // Weekly settings: ['Monday', 'Sunday']
            return in_array($date->format('l'), $this->settings ?? []);
        } elseif ($this->type === 'monthly') {
            // Monthly settings: ['1' => ['Sunday'], '2' => ['Saturday', 'Sunday'], ...]
            // Adjustment: Align Sunday with the preceding Monday (Monday-to-Sunday week)
            // If it's Sunday, we treat it as being in the same week as the previous day (Saturday)
            $checkDate = $date->copy();
            if ($date->format('l') === 'Sunday') {
                $checkDate->subDay();
            }

            $dayName = $date->format('l');
            $occurrence = ceil($checkDate->day / 7);

            if (isset($this->settings[$occurrence]) && is_array($this->settings[$occurrence])) {
                return in_array($dayName, $this->settings[$occurrence]);
            }
        }

        return false;
    }
}
