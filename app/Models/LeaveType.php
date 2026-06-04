<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'name',
        'description',
        'max_days_per_year',
        'is_paid',
        'color',
        'status',
        'created_by',
        'branch_id',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
    ];

    /**
     * Get the user who created the leave type.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the branch that the leave type belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the policies for the leave type.
     */
    public function leavePolicies()
    {
        return $this->hasMany(LeavePolicy::class);
    }
}