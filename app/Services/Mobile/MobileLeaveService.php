<?php

namespace App\Services\Mobile;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileLeaveService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function balances(User $user): array
    {
        return LeaveBalance::with('leaveType')
            ->where('employee_id', $user->id)
            ->orderByDesc('year')
            ->get()
            ->map(fn (LeaveBalance $balance) => [
                'id' => $balance->id,
                'leave_type_id' => $balance->leave_type_id,
                'leave_type' => $balance->leaveType?->name,
                'year' => $balance->year,
                'allocated_days' => (float) $balance->allocated_days,
                'used_days' => (float) $balance->used_days,
                'remaining_days' => (float) $balance->remaining_days,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function applications(User $user): array
    {
        return LeaveApplication::with('leaveType')
            ->where('employee_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (LeaveApplication $app) => [
                'id' => $app->id,
                'leave_type' => $app->leaveType?->name,
                'start_date' => $app->start_date?->format('Y-m-d'),
                'end_date' => $app->end_date?->format('Y-m-d'),
                'total_days' => (float) $app->total_days,
                'status' => $app->status,
                'reason' => $app->reason,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leaveTypes(User $user): array
    {
        return LeaveType::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name', 'color'])
            ->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(User $user, array $data): LeaveApplication
    {
        $employee = User::with(['employee' => fn ($q) => $q->withoutGlobalScopes()])
            ->find($user->id);

        if (! $employee?->employee) {
            throw ValidationException::withMessages([
                'employee' => [__('Employee profile not found.')],
            ]);
        }

        $branchId = $employee->employee->branch_id;
        $totalDays = $employee->employee->calculateLeaveDaysInRange($data['start_date'], $data['end_date'], $branchId);

        if ($totalDays <= 0) {
            throw ValidationException::withMessages([
                'start_date' => [__('Selected date range consists only of week offs or holidays in the current branch.')],
            ]);
        }

        $employeeCategory = $employee->employee->category_id;

        $leavePolicy = LeavePolicy::where('leave_type_id', $data['leave_type_id'])
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
                if ($branchId) {
                    $q->orWhereNull('branch_id');
                }
            })
            ->where('status', 'active')
            ->where(function ($q) use ($employeeCategory) {
                $q->whereNull('applicable_categories')
                    ->orWhereJsonContains('applicable_categories', (string) $employeeCategory);
            })
            ->orderByRaw('branch_id IS NULL ASC')
            ->first();

        if (! $leavePolicy) {
            throw ValidationException::withMessages([
                'leave_type_id' => [__('No applicable leave policy found for this leave type and your employee category.')],
            ]);
        }

        if ($totalDays < $leavePolicy->min_days_per_application || $totalDays > $leavePolicy->max_days_per_application) {
            throw ValidationException::withMessages([
                'start_date' => [__('Leave days must be between :min and :max days.', [
                    'min' => $leavePolicy->min_days_per_application,
                    'max' => $leavePolicy->max_days_per_application,
                ])],
            ]);
        }

        $requestedYear = Carbon::parse($data['start_date'])->year;
        $leaveBalance = LeaveBalance::where('employee_id', $user->id)
            ->where('leave_type_id', $data['leave_type_id'])
            ->where('year', $requestedYear)
            ->first();

        if (! $leaveBalance) {
            $carriedForward = LeaveBalance::calculateSuggestedCarryForward(
                $user->id,
                $data['leave_type_id'],
                $requestedYear
            );

            $leaveBalance = LeaveBalance::create([
                'employee_id' => $user->id,
                'leave_type_id' => $data['leave_type_id'],
                'leave_policy_id' => $leavePolicy->id,
                'year' => $requestedYear,
                'allocated_days' => $leavePolicy->accrual_rate ?? 10,
                'carried_forward' => $carriedForward,
                'used_days' => 0,
                'remaining_days' => ($leavePolicy->accrual_rate ?? 10) + $carriedForward,
                'created_by' => creatorId(),
            ]);
        }

        if ($leaveBalance->remaining_days < $totalDays) {
            throw ValidationException::withMessages([
                'start_date' => [__('Insufficient leave balance.')],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $leavePolicy, $branchId, $totalDays) {
            return LeaveApplication::create([
                'employee_id' => $user->id,
                'leave_type_id' => $data['leave_type_id'],
                'leave_policy_id' => $leavePolicy->id,
                'branch_id' => $branchId,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $totalDays,
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_by' => creatorId(),
            ]);
        });
    }
}
