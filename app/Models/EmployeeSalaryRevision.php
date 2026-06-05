<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryRevision extends Model
{
    protected $fillable = [
        'employee_id',
        'monthly_gross',
        'basic_salary',
        'components',
        'effective_from',
        'effective_to',
        'revision_type',
        'previous_gross',
        'increment_percentage',
        'increment_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'monthly_gross' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'components' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'previous_gross' => 'decimal:2',
        'increment_percentage' => 'decimal:2',
        'increment_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('effective_to');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId)->orderByDesc('effective_from');
    }
}
