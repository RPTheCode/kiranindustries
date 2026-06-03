<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'component_earnings',
        'total_earnings',
        'total_deductions',
        'gross_pay',
        'net_pay',
        'working_days',
        'present_days',
        'week_off_present_days',
        'full_present_days',
        'half_days',
        'holiday_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'absent_days',
        'overtime_hours',
        'overtime_amount',
        'shortfall_hours',
        'shortfall_amount',
        'per_day_salary',
        'unpaid_leave_deduction',
        'earnings_breakdown',
        'deductions_breakdown',
        'advance_allocations',
        'notes',
        'created_by',
        'overtime_amount_basic',
        'overtime_amount_minimum',
        'shortfall_amount_basic',
        'shortfall_amount_minimum',
        'pf_amount_employee',
        'pf_amount_employer',
        'esi_amount_employee',
        'esi_amount_employer',
        'overtime_days',
        'overtime_salary',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'component_earnings' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'present_days' => 'decimal:2',
        'week_off_present_days' => 'decimal:2',
        'half_days' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'shortfall_hours' => 'decimal:2',
        'shortfall_amount' => 'decimal:2',
        'per_day_salary' => 'decimal:2',
        'unpaid_leave_deduction' => 'decimal:2',
        'earnings_breakdown' => 'array',
        'deductions_breakdown' => 'array',
        'advance_allocations' => 'array',
        'overtime_amount_basic' => 'decimal:2',
        'overtime_amount_minimum' => 'decimal:2',
        'shortfall_amount_basic' => 'decimal:2',
        'shortfall_amount_minimum' => 'decimal:2',
        'pf_amount_employee' => 'decimal:2',
        'pf_amount_employer' => 'decimal:2',
        'esi_amount_employee' => 'decimal:2',
        'esi_amount_employer' => 'decimal:2',
        'overtime_days' => 'decimal:2',
        'overtime_salary' => 'decimal:2',
    ];

    /**
     * Get the payroll run.
     */
    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * Get the employee.
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }

    /**
     * Get the user who created the entry.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the employee advances associated with this payroll entry.
     */
    public function employeeAdvances()
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    /**
     * Get the payslips associated with this payroll entry.
     */
    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Get the primary payslip for this payroll entry.
     */
    public function payslip()
    {
        return $this->hasOne(Payslip::class);
    }

    /**
     * Get attendance percentage.
     */
    public function getAttendancePercentageAttribute()
    {
        if ($this->working_days == 0) {
            return 0;
        }

        return round(($this->present_days / $this->working_days) * 100, 2);
    }

    /**
     * Get complete salary breakdown showing all interconnections.
     */
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function ($payrollEntry) {
            if (!empty($payrollEntry->advance_allocations)) {
                foreach ($payrollEntry->advance_allocations as $allocation) {
                    $advanceId = $allocation['advance_id'] ?? null;
                    $amount = $allocation['amount'] ?? 0;

                    if ($advanceId && $amount > 0) {
                        $advance = EmployeeAdvance::find($advanceId);
                        if ($advance) {
                            $advance->paid_amount -= $amount;
                            if ($advance->paid_amount < 0)
                                $advance->paid_amount = 0; // Safety check

                            // Revert status if strictly 'recovered' -> 'paid'
                            // Assuming 'recovered' means fully paid. If paid_amount < amount, it should be 'paid' (active).
                            if ($advance->paid_amount < $advance->amount) {
                                $advance->status = 'paid';
                            }
                            $advance->save();
                        }
                    }
                }
            }

            // Fallback for old system (if payroll_entry_id was used directy)
            if ($payrollEntry->employeeAdvances()->exists()) {
                foreach ($payrollEntry->employeeAdvances as $advance) {
                    $advance->payroll_entry_id = null;
                    $advance->status = 'paid';
                    $advance->save();
                }
            }
        });
    }

    public function getCompleteSalaryBreakdown()
    {
        $breakdown = [
            'employee_name' => $this->employee->name,
            'pay_period' => $this->payrollRun->pay_period_start->format('M Y'),

            // Attendance Data (from Attendance Management)
            'attendance' => [
                'total_working_days' => $this->working_days,
                'present_days' => $this->present_days,
                'attendance_percentage' => $this->attendance_percentage . '%',
                'overtime_hours' => $this->overtime_hours,
            ],

            // Leave Data (from Leave Management)
            'leave_info' => [
                'leave_days_taken' => $this->working_days - $this->present_days,
                'note' => 'Leave days are counted as present for salary calculation'
            ],

            // Salary Components (from Payroll Management)
            'earnings' => $this->earnings_breakdown,
            'deductions' => $this->deductions_breakdown,

            // Final Calculation
            'calculation' => [
                'gross_pay' => $this->gross_pay,
                'total_deductions' => $this->total_deductions,
                'net_pay' => $this->net_pay,
                'formula' => 'Net Pay = Gross Pay - Total Deductions'
            ]
        ];

        return $breakdown;
    }
}