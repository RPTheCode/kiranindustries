<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'state',
        'wage_zone_id',
        'standard_working_days',
        'use_government_wage_rules',
        'govt_wage_mode',
        'country',
        'zip_code',
        'phone',
        'email',
        'in_charge_name',
        'in_charge_contact',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'string',
        'standard_working_days' => 'integer',
        'use_government_wage_rules' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function attendancePolicies()
    {
        return $this->hasMany(AttendancePolicy::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function wageZone()
    {
        return $this->belongsTo(WageZone::class);
    }
}