<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankMaster extends Model
{
    use HasFactory, \App\Traits\HasBranch, \App\Traits\RecordsActivity;

    protected $fillable = [
        'code',
        'bank_name',
        'ifsc_code',
        'branch_name',
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
