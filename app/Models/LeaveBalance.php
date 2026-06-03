<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LeaveBalance extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_policy_id',
        'year',
        'allocated_days',
        'used_days',
        'remaining_days',
        'carried_forward',
        'manual_adjustment',
        'adjustment_reason',
        'created_by'
    ];

    protected $casts = [
        'allocated_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
        'carried_forward' => 'decimal:2',
        'manual_adjustment' => 'decimal:2',
    ];

    /**
     * Get the employee.
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
     * Get the user who created the balance.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate remaining days.
     */
    public function calculateRemainingDays()
    {
        $this->remaining_days = ($this->allocated_days + $this->carried_forward + $this->manual_adjustment) - $this->used_days;
        return $this->remaining_days;
    }

    /**
     * Calculate suggested carry forward from previous year.
     * 
     * @param int $userId
     * @param int $leaveTypeId
     * @param int $year
     * @return float
     */
    public static function calculateSuggestedCarryForward($userId, $leaveTypeId, $year)
    {
        $leaveType = LeaveType::find($leaveTypeId);
        if (!$leaveType || !$leaveType->is_paid) {
            return 0;
        }

        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee || !$user->employee->date_of_joining) {
            return 0;
        }

        $joiningDate = Carbon::parse($user->employee->date_of_joining);
        $joiningYear = $joiningDate->year;

        // As per user requirement:
        // Joined 2023
        // 2024: CF from 2023 (Year 1 of CF)
        // 2025: CF from 2024 (Year 2 of CF)
        // 2026: Reset to 0 (Year 3 of CF / 4th Year of employment)
        // Calculation: (Current Year - Joining Year) % 3 === 0 means reset if it's not the joining year.
        $diff = $year - $joiningYear;
        if ($diff > 0 && $diff % 3 === 0) {
            return 0;
        }

        // Get previous year balance
        $prevBalance = self::where('employee_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year - 1)
            ->first();

        if (!$prevBalance) {
            return 0;
        }

        $amount = $prevBalance->remaining_days;

        // Apply policy limit if exists
        $policy = LeavePolicy::where('leave_type_id', $leaveTypeId)
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                if ($user->employee && $user->employee->branch_id) {
                    $q->where('branch_id', $user->employee->branch_id);
                }
            })
            ->first();

        if ($policy && $policy->carry_forward_limit !== null && $policy->carry_forward_limit > 0) {
            $amount = min($amount, $policy->carry_forward_limit);
        }

        return $amount;
    }
}