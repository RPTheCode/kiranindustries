<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'name',
        'code',
        'short_code',
        'branch_id',
        'description',
        'sanction_strength',
        'status',
        'created_by'
    ];

    protected $casts = [
        'sanction_strength' => 'integer',
    ];

    /**
     * Get the branch that owns the department.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who created the department.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the employees assigned to this department.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function desginations()
    {
        return $this->hasMany(Designation::class, 'department_id', 'id');
    }
}