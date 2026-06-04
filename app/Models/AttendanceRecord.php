<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\EmployeeSalary;
use App\Models\WeekOff;
use App\Models\Holiday;

class AttendanceRecord extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'attendance_policy_id',
        'date',
        'clock_in',
        'clock_out',
        'total_hours',
        'break_hours',
        'overtime_hours',
        'overtime_amount',
        'is_late',
        'is_early_departure',
        'is_absent',
        'is_holiday',
        'is_weekend',
        'status',
        'notes',
        'created_by',
        'branch_id',
        'overtime_amount_basic',
        'overtime_amount_minimum',
        'shortfall_amount_basic',
        'shortfall_amount_minimum',
        'shift_slot_id',
    ];

    protected $casts = [
        'date' => 'date',
        'break_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'is_late' => 'boolean',
        'is_early_departure' => 'boolean',
        'is_absent' => 'boolean',
        'is_holiday' => 'boolean',
        'is_weekend' => 'boolean',
        'overtime_amount_basic' => 'decimal:2',
        'overtime_amount_minimum' => 'decimal:2',
        'shortfall_hours' => 'decimal:2',
        'shortfall_amount' => 'decimal:2',
        'shortfall_amount_basic' => 'decimal:2',
        'shortfall_amount_minimum' => 'decimal:2',
    ];

    protected $appends = [
        'attendance_policy',
    ];

    /**
     * Get the employee.
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }

    /**
     * Get the shift.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class)->withoutGlobalScopes();
    }

    /**
     * Get the shift with fallback to first shift if not assigned.
     */
    public function getShiftAttribute()
    {
        $shift = $this->getRelationValue('shift');

        if (!$shift && !$this->shift_id) {
            // Fallback to the first shift of the branch
            $query = Shift::where('status', 'active');
            if ($this->branch_id) {
                $query->where('branch_id', $this->branch_id);
            }
            $shift = $query->first();

            // If still no shift found, get the first shift in the system
            if (!$shift) {
                $shift = Shift::where('status', 'active')->first();
            }

            // If still nothing, just get any first shift
            if (!$shift) {
                $shift = Shift::first();
            }

            // Set the relation so it's cached for the life of this instance
            if ($shift) {
                $this->setRelation('shift', $shift);
            }
        }

        return $shift;
    }

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the attendance policy.
     */
    public function attendancePolicy()
    {
        return $this->belongsTo(AttendancePolicy::class)->withoutGlobalScopes();
    }

    /**
     * Get the attendance policy with fallback to first policy if not assigned.
     */
    /**
     * Format decimal hours to clock-time format (HH:mm)
     */
    public static function formatDuration($decimalHours)
    {
        $decimalHours = (float) $decimalHours;
        if ($decimalHours <= 0) {
            return '0:00';
        }

        $totalMinutes = round($decimalHours * 60);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    public function getAttendancePolicyAttribute()
    {
        $policy = $this->getRelationValue('attendancePolicy');

        if (!$policy && !$this->attendance_policy_id) {
            // Fallback to the first policy of the branch
            $query = AttendancePolicy::where('status', 'active');
            if ($this->branch_id) {
                // Assuming branch relation exists or using created_by context
                $query->whereIn('created_by', getCompanyAndUsersId());
            }
            $policy = $query->first();

            // If still no policy found, get the first active policy in the system
            if (!$policy) {
                $policy = AttendancePolicy::where('status', 'active')->first();
            }

            // If still nothing, just get any first policy
            if (!$policy) {
                $policy = AttendancePolicy::withoutGlobalScopes()->first();
            }

            // Set the relation so it's cached
            if ($policy) {
                $this->setRelation('attendancePolicy', $policy);
            }
        }

        return $policy;
    }

    /**
     * Get the user who created the record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate total working hours.
     */
    public function calculateTotalHours()
    {
        if ($this->clock_in && $this->clock_out) {
            $clockIn = Carbon::parse($this->clock_in);
            $clockOut = Carbon::parse($this->clock_out);

            // Handle next day clock out (night shifts)
            if ($clockOut->lt($clockIn)) {
                $clockOut->addDay();
            }

            $totalMinutes = abs($clockOut->diffInMinutes($clockIn));

            // Use manual break_hours if set, otherwise calculate from shift
            $breakMinutes = 0;
            if ($this->break_hours > 0) {
                $breakMinutes = $this->break_hours * 60;
            } elseif ($this->shift && $this->shift->break_start_time && $this->shift->break_end_time) {
                $breakStart = Carbon::parse($this->shift->break_start_time);
                $breakEnd = Carbon::parse($this->shift->break_end_time);

                // Handle next day break times for night shifts
                if ($breakEnd->lt($breakStart)) {
                    $breakEnd->addDay();
                }

                // Only deduct break if employee worked through the break period
                if ($clockIn->lte($breakStart) && $clockOut->gte($breakEnd)) {
                    // Worked through entire break - deduct full break
                    $breakMinutes = $this->shift->break_duration;
                } elseif ($clockIn->lte($breakStart) && $clockOut->gt($breakStart) && $clockOut->lte($breakEnd)) {
                    // Left during break - deduct time spent on break
                    $breakMinutes = abs($clockOut->diffInMinutes($breakStart));
                } elseif ($clockIn->gt($breakStart) && $clockIn->lt($breakEnd) && $clockOut->gte($breakEnd)) {
                    // Came during break - deduct partial break (missed part of break)
                    $breakMinutes = abs($breakEnd->diffInMinutes($clockIn));
                } elseif ($clockIn->gt($breakStart) && $clockOut->lt($breakEnd)) {
                    // Came and left during break - no break deduction
                    $breakMinutes = 0;
                }
            }

            $workingMinutes = max(0, $totalMinutes - $breakMinutes);
            $calculatedHours = $workingMinutes / 60;

            $this->attributes['total_hours'] = $calculatedHours;
        } else {
            $this->attributes['total_hours'] = 0;
        }

        return $this->attributes['total_hours'] ?? 0;
    }

    /**
     * Check if employee is late.
     */
    public function checkLateArrival()
    {
        if ($this->shift && $this->clock_in && $this->attendancePolicy) {
            $expectedTime = $this->shift->start_time;
            $this->is_late = $this->attendancePolicy->isLateArrival($this->clock_in, $expectedTime);
        }

        return $this->is_late;
    }

    /**
     * Check if employee left early.
     */
    public function checkEarlyDeparture()
    {
        if ($this->shift && $this->clock_out && $this->attendancePolicy) {
            $expectedTime = $this->shift->end_time;
            $this->is_early_departure = $this->attendancePolicy->isEarlyDeparture($this->clock_out, $expectedTime);
        }

        return $this->is_early_departure;
    }

    /**
     * Process complete attendance - calculate everything automatically.
     */
    public function processAttendance()
    {
        // Resolve policy if missing (Fallback to first active policy)
        if (!$this->attendance_policy_id) {
            $this->attendance_policy_id = $this->attendancePolicy->id ?? null;
            if ($this->attendance_policy_id) {
                // Save it if we are in a context where we can save (e.g. existing record)
                if ($this->exists) {
                    $this->save();
                }
            }
        }

        // Step 1: Calculate total working hours first
        $this->calculateTotalHours();

        // Check if today is a week off
        $isWeekOff = false;
        if ($this->branch_id && $this->employee) {
            $weekOff = WeekOff::withoutGlobalScopes()
                ->where('branch_id', $this->branch_id)
                ->where('employment_type', $this->employee->employee?->employment_type ?? 'Employee')
                ->first();
            $isWeekOff = $weekOff ? $weekOff->isDateWeekOff($this->date) : $this->date->isWeekend();
        } else {
            $isWeekOff = $this->date->isWeekend();
        }
        $this->is_weekend = $isWeekOff;

        // Step 1.5: Automatic Holiday Detection (Re-evaluate on every process to handle date changes)
        if ($this->branch_id) {
            $date = $this->date;
            $branchId = $this->branch_id;
            $this->is_holiday = Holiday::where(function ($q) use ($date) {
                $q->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            })->where(function ($q) use ($branchId) {
                $q->doesntHave('branches')
                    ->orWhereHas('branches', function ($q2) use ($branchId) {
                        $q2->where('branches.id', $branchId);
                    });
            })->exists();
        }

        // Step 2: Calculate overtime using shift working hours dynamically
        // Holiday Overtime Rule: ALL hours worked on holidays are counted as overtime (standardHours = 0)
        // Week Off Overtime Rule: Overtime applies after weekoff_full_day_hours (default 6)

        if ($this->is_holiday) {
            $standardHours = 0;
            $statusThreshold = 0; // Any work = Present
        } elseif ($this->is_weekend) {
            $threshold = ($this->attendancePolicy && $this->attendancePolicy->weekoff_full_day_hours > 0)
                ? $this->attendancePolicy->weekoff_full_day_hours
                : 6; // Default to 6 if not set
            $standardHours = $threshold;
            $statusThreshold = $threshold;
        } elseif ($this->shift && $this->shift->working_hours > 0) {
            $standardHours = $this->shift->working_hours;
            $statusThreshold = $standardHours;
        } else {
            $standardHours = 8;
            $standardHours = 8;
            $statusThreshold = 8;
        }

        // --- Grace Period Deduction ---
        $excusedMinutes = 0;
        if ($this->attendancePolicy && $this->shift && $this->clock_in && $this->clock_out) {
            $lateGrace = $this->attendancePolicy->late_arrival_grace ?? 0;
            $earlyGrace = $this->attendancePolicy->early_departure_grace ?? 0;

            $clockIn = Carbon::parse($this->clock_in);
            $clockOut = Carbon::parse($this->clock_out);
            $shiftStart = Carbon::parse($this->shift->start_time);
            $shiftEnd = Carbon::parse($this->shift->end_time);

            // Handle next day clock out
            if ($clockOut->lt($clockIn)) {
                $clockOut->addDay();
            }
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            $latenessMinutes = $clockIn->gt($shiftStart) ? $shiftStart->diffInMinutes($clockIn) : 0;
            $earlyDepartureMinutes = $clockOut->lt($shiftEnd) ? $clockOut->diffInMinutes($shiftEnd) : 0;

            if ($latenessMinutes > 0 && $latenessMinutes <= $lateGrace) {
                $excusedMinutes += $latenessMinutes;
            }
            if ($earlyDepartureMinutes > 0 && $earlyDepartureMinutes <= $earlyGrace) {
                $excusedMinutes += $earlyDepartureMinutes;
            }
        }

        // Adjust statusThreshold by excused minutes to ensure people within grace are marked 'present'
        if ($excusedMinutes > 0) {
            $statusThreshold = max(0, $statusThreshold - ($excusedMinutes / 60));
        }

        $this->overtime_hours = max(0, $this->total_hours - $standardHours);

        // Step 3: Calculate Overtime (Split Bases)
        if ($this->overtime_hours > 0 && $this->attendancePolicy) {
            // 1. Basic Salary based OT
            $rateBasic = $this->calculateSalaryBasedOvertimeRate('basic');
            $this->overtime_amount_basic = round($this->overtime_hours * $rateBasic, 2);

            // 2. Minimum Wage based OT
            $rateMinimum = $this->calculateSalaryBasedOvertimeRate('minimum');
            $this->overtime_amount_minimum = round($this->overtime_hours * $rateMinimum, 2);

            // 3. Set Primary Overtime Amount (Legacy/Display support)
            if ($this->attendancePolicy->overtime_type == 'salary_based') {
                $this->overtime_amount = ($this->overtime_amount_minimum > 0) ? $this->overtime_amount_minimum : $this->overtime_amount_basic;
            } else {
                $this->overtime_amount = round($this->overtime_hours * $this->attendancePolicy->overtime_rate_per_hour, 2);
            }
        } else {
            $this->overtime_amount = 0;
            $this->overtime_amount_basic = 0;
            $this->overtime_amount_minimum = 0;
        }

        // Step 4: Shortfall Calculation (Split Bases)
        $this->shortfall_hours = 0;
        $this->shortfall_amount = 0;
        $this->shortfall_amount_basic = 0;
        $this->shortfall_amount_minimum = 0;

        if (!$this->is_holiday && !$this->is_weekend && $this->total_hours < $standardHours && $this->clock_in && $this->clock_out) {
            $this->shortfall_hours = max(0, ($standardHours - $this->total_hours) - ($excusedMinutes / 60));

            if ($this->shortfall_hours > 0) {
                $rateBasic = $this->calculateSalaryBasedOvertimeRate('basic');
                $this->shortfall_amount_basic = round($this->shortfall_hours * $rateBasic, 2);

                $rateMinimum = $this->calculateSalaryBasedOvertimeRate('minimum');
                $this->shortfall_amount_minimum = round($this->shortfall_hours * $rateMinimum, 2);

                if ($this->attendancePolicy && $this->attendancePolicy->overtime_type == 'salary_based') {
                    $this->shortfall_amount = ($this->shortfall_amount_minimum > 0) ? $this->shortfall_amount_minimum : $this->shortfall_amount_basic;
                } elseif ($this->attendancePolicy && $this->attendancePolicy->overtime_rate_per_hour > 0) {
                    // Fallback to fixed rate if policy exists
                    $this->shortfall_amount = round($this->shortfall_hours * $this->attendancePolicy->overtime_rate_per_hour, 2);
                } else {
                    // Last resort: use basic salary rate if no other rate found
                    $this->shortfall_amount = $this->shortfall_amount_basic;
                }
            }
        }

        // Step 5: Check Late Arrival and Early Departure
        if ($this->clock_in && $this->clock_out) {
            $this->checkLateArrival();
            $this->checkEarlyDeparture();
        }

        // Step 6: Final Status Determination
        if ($this->is_holiday && $this->total_hours <= 0) {
            $this->status = 'holiday';
        } elseif (!$this->exists || $this->isDirty('clock_in') || $this->isDirty('clock_out') || $this->isDirty('is_holiday') || $this->isDirty('status')) {
            // Respect manual half_day status if it's already set and there's work
            if ($this->status === 'half_day' && $this->total_hours > 0) {
                // Keep as is
            } else {
                $halfDayThreshold = $statusThreshold / 2;
                if ($this->is_holiday) {
                    $this->status = ($this->total_hours > 0) ? 'present' : 'holiday';
                } elseif ($this->is_weekend) {
                    if ($this->total_hours >= $statusThreshold) {
                        $this->status = 'present';
                    } elseif ($this->total_hours >= $halfDayThreshold) {
                        $this->status = 'half_day';
                    } else {
                        $this->status = 'absent';
                    }
                } elseif ($this->total_hours >= $statusThreshold) {
                    $this->status = 'present';
                } elseif ($this->total_hours >= $halfDayThreshold) {
                    $this->status = 'half_day';
                } else {
                    $this->status = 'absent';
                }
            }
        }

        // Final Step: Post-status shortfall reset.
        // If status is NOT present (i.e., half_day or absent), skip shortfall amount
        // to avoid double deduction (since day-count already penalizes them).
        if ($this->status !== 'present') {
            $this->shortfall_hours = 0;
            $this->shortfall_amount = 0;
            $this->shortfall_amount_basic = 0;
            $this->shortfall_amount_minimum = 0;
        }

        $this->save();
    }

    /**
     * Format clock in time for frontend (H:i format).
     */
    public function getClockInAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->format('H:i') : null;
    }

    /**
     * Format clock out time for frontend (H:i format).
     */
    public function getClockOutAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->format('H:i') : null;
    }

    /**
     * Calculate overtime rate based on salary.
     */
    public function calculateSalaryBasedOvertimeRate($baseType = 'auto')
    {
        $employeeId = $this->employee_id;
        $date = $this->date; // Carbon instance

        // 1. Get Active Salary
        $salary = EmployeeSalary::getActiveSalary($employeeId);
        if (!$salary) {
            return 0;
        }

        // Determine base salary based on type
        $baseSalary = 0;
        if ($baseType === 'basic') {
            $baseSalary = $salary->basic_salary;
        } elseif ($baseType === 'minimum') {
            $baseSalary = $salary->minimum_wages;
        } elseif (is_numeric($baseType)) {
            $baseSalary = $baseType; // Direct amount
        } else {
            // 'auto' or default: prioritize minimum if > 0
            $baseSalary = ($salary->minimum_wages > 0) ? $salary->minimum_wages : $salary->basic_salary;
        }

        if ($baseSalary <= 0) {
            return 0;
        }

        // 2. Standardized Denominator (26 for Monthly, 1 for Daily)
        $emp = $this->employee?->employee;
        $denominator = 26;
        if ($emp && $emp->daily_option == 1) {
            $denominator = 1;
        } elseif ($emp && $emp->working_days > 0) {
            $denominator = $emp->working_days;
        }

        // 3. Per Day Salary
        $perDaySalary = $baseSalary / max(1, $denominator);

        // 4. Hourly Rate - Divisor must be actual shift hours
        $shift = $this->shift;
        $shiftHours = ($shift && $shift->working_hours > 0) ? $shift->working_hours : 8;

        return $perDaySalary / $shiftHours;
    }
    public function slot()
    {
        return $this->belongsTo(ShiftSlot::class, 'shift_slot_id');
    }
}
