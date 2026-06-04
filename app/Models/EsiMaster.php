<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EsiMaster extends Model
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'name',
        'percentage_employee',
        'percentage_employer',
        'limit',
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
}
