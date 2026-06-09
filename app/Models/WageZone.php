<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WageZone extends Model
{
    use HasFactory, \App\Traits\RecordsActivity;

    protected $fillable = [
        'name',
        'code',
        'state',
        'region',
        'country',
        'working_days',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'working_days' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rates()
    {
        return $this->hasMany(SkillWageRate::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function displayLabel(): string
    {
        $parts = array_filter([$this->state, $this->region, $this->name]);

        return implode(' · ', $parts);
    }

    /**
     * @return array{wage_per_day: ?float, wage_per_month: ?float}
     */
    public static function syncWageFields(?float $perDay, ?float $perMonth, int $workingDays = 26): array
    {
        $days = max(1, $workingDays);
        $day = $perDay !== null && $perDay > 0 ? round($perDay, 2) : null;
        $month = $perMonth !== null && $perMonth > 0 ? round($perMonth, 2) : null;

        if ($day !== null && $month === null) {
            $month = round($day * $days, 2);
        } elseif ($month !== null && $day === null) {
            $day = round($month / $days, 2);
        }

        return [
            'wage_per_day' => $day,
            'wage_per_month' => $month,
        ];
    }
}
