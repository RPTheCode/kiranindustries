<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeNominee extends Model
{
    protected $fillable = [
        'employee_id',
        'name',
        'aadhar_number',
        'relation',
        'percentage',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
