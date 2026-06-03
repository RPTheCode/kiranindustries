<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWeekOff extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'off_date',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'off_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
