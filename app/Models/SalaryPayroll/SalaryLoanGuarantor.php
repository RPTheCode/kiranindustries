<?php

namespace App\Models\SalaryPayroll;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryLoanGuarantor extends Model
{
    protected $fillable = [
        'salary_loan_request_id',
        'guarantor_employee_id',
        'name',
        'employee_code',
        'department',
        'sort_order',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SalaryLoanRequest::class, 'salary_loan_request_id');
    }

    public function guarantorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guarantor_employee_id');
    }
}
