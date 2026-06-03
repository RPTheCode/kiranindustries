<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasBranch;

class MaterialItem extends BaseModel
{
    use HasFactory;
    use HasBranch;

    protected $fillable = [
        'code',
        'name',
        'rate',
        'status',
        'branch_id',
        'created_by'
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
