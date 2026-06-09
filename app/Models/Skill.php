<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory, \App\Traits\HasBranch, \App\Traits\RecordsActivity;

    protected $fillable = [
        'name',
        'code',
        'status',
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employeeWorkHistories()
    {
        return $this->belongsToMany(EmployeeWorkHistory::class, 'employee_work_history_skill');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_skill');
    }

    public function wageRates()
    {
        return $this->hasMany(SkillWageRate::class);
    }
}
