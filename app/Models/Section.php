<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends BaseModel
{
    use \App\Traits\HasBranch;

    protected $fillable = [
        'name',
        'code',
        'status',
        'created_by',
        'branch_id',
    ];


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
