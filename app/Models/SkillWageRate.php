<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillWageRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'skill_id',
        'wage_zone_id',
        'wage_per_day',
        'wage_per_month',
        'effective_from',
        'created_by',
    ];

    protected $casts = [
        'wage_per_day' => 'decimal:2',
        'wage_per_month' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function wageZone()
    {
        return $this->belongsTo(WageZone::class);
    }
}
