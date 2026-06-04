<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends BaseModel
{
    use HasFactory, \App\Traits\HasBranch;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_policy_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'attachment',
        'status',
        'manager_comments',
        'approved_by',
        'approved_at',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the employee who applied for leave.
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the leave type.
     */
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the leave policy.
     */
    public function leavePolicy()
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /**
     * Get the manager who approved/rejected.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who created the application.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create attendance records and update leave balance when leave is approved.
     */
    public function createAttendanceRecords()
    {
        if ($this->status === 'approved') {
            $startDate = $this->start_date;
            $endDate = $this->end_date;

            // Fetch WeekOff settings and Holidays for efficient checking
            $employeeProfile = $this->employee?->employee;
            if (!$employeeProfile) {
                return; // Should not happen if leave application exists
            }
            // Use the branch_id from the leave application (enforcing active branch rules where it was added)
            $branchId = $this->branch_id ?? $employeeProfile->branch_id;

            // Fetch WeekOff settings for the target branch and employment type
            $weekOff = WeekOff::where('branch_id', $branchId)
                ->where('employment_type', $employeeProfile->employment_type ?? 'Employee')
                ->first();

            $holidays = Holiday::where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
                ->where(function ($q) use ($branchId) {
                    $q->doesntHave('branches')
                        ->orWhereHas('branches', function ($q2) use ($branchId) {
                            $q2->where('branches.id', $branchId);
                        });
                })
                ->get();

            // Loop through each day of leave
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Check if today is a week off
                $isWeekOff = $weekOff ? $weekOff->isDateWeekOff($date) : $date->isWeekend();

                // Check if today is a holiday
                $isHoliday = $holidays->filter(function ($h) use ($date) {
                    return $date->between($h->start_date, $h->end_date);
                })->isNotEmpty();

                // Skip week offs and holidays
                if ($isWeekOff || $isHoliday) {
                    continue;
                }

                // Check if attendance record already exists
                $existingRecord = \App\Models\AttendanceRecord::where('employee_id', $this->employee_id)
                    ->where('date', $date->format('Y-m-d'))
                    ->first();

                if (!$existingRecord) {
                    \App\Models\AttendanceRecord::create([
                        'employee_id' => $this->employee_id,
                        'date' => $date->format('Y-m-d'),
                        'status' => 'on_leave',
                        'is_absent' => false,
                        'total_hours' => 0,
                        'notes' => 'Leave: ' . $this->leaveType->name,
                        'created_by' => $this->created_by,
                    ]);
                } else {
                    // Update existing record to on_leave
                    $existingRecord->update([
                        'status' => 'on_leave',
                        'notes' => 'Leave: ' . $this->leaveType->name,
                    ]);
                }
            }

            // Update leave balance - deduct used days
            $this->updateLeaveBalance();
        }
    }

    /**
     * Update employee leave balance when leave is approved.
     */
    public function updateLeaveBalance()
    {
        $requestedYear = $this->start_date->year;

        // Find or create leave balance for this employee, leave type, and year
        $leaveBalance = \App\Models\LeaveBalance::where('employee_id', $this->employee_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $requestedYear)
            ->first();

        if (!$leaveBalance) {
            $carriedForward = \App\Models\LeaveBalance::calculateSuggestedCarryForward(
                $this->employee_id,
                $this->leave_type_id,
                $requestedYear
            );

            $leaveBalance = \App\Models\LeaveBalance::create([
                'employee_id' => $this->employee_id,
                'leave_type_id' => $this->leave_type_id,
                'leave_policy_id' => $this->leave_policy_id,
                'year' => $requestedYear,
                'allocated_days' => $this->leavePolicy->accrual_rate ?? 10,
                'carried_forward' => $carriedForward,
                'used_days' => 0,
                'remaining_days' => ($this->leavePolicy->accrual_rate ?? 10) + $carriedForward,
                'created_by' => $this->created_by,
            ]);
        }

        // Deduct the leave days
        $leaveBalance->used_days += $this->total_days;
        $leaveBalance->calculateRemainingDays();
        $leaveBalance->save();
    }
}