<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\LeavePolicy;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

use App\Traits\LogsActivity;

class LeaveBalanceController extends Controller
{
    use LogsActivity;
    public function index(Request $request)
    {
        $query = LeaveBalance::withPermissionCheck()
            ->with(['employee', 'leaveType', 'leavePolicy', 'creator']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('employee', function ($subQ) use ($request) {
                    $subQ->where('name', 'like', '%' . $request->search . '%');
                })
                    ->orWhereHas('leaveType', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Handle employee filter
        if ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') {
            $query->where('employee_id', $request->employee_id);
        }

        // Handle leave type filter
        if ($request->has('leave_type_id') && !empty($request->leave_type_id) && $request->leave_type_id !== 'all') {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Handle year filter
        if ($request->has('year') && !empty($request->year) && $request->year !== 'all') {
            $query->where('year', $request->year);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $leaveBalances = $query->paginate($request->per_page ?? 10);

        $activeBranchId = session('active_branch_id');

        // Get employees for filter dropdown - Show all employees from all branches with their IDs
        $employeeQuery = User::join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->whereIn('users.created_by', getCompanyAndUsersId());

        // If a leave type filter is active, only show employees who are applicable for that leave type's policy
        if ($request->has('leave_type_id') && !empty($request->leave_type_id) && $request->leave_type_id !== 'all') {
            $policy = LeavePolicy::where('leave_type_id', $request->leave_type_id)
                ->where(function($q) use ($activeBranchId) {
                    $q->where('branch_id', $activeBranchId);
                    if ($activeBranchId) {
                        $q->orWhereNull('branch_id');
                    }
                })
                ->where('status', 'active')
                ->orderByRaw('branch_id IS NULL ASC')
                ->first();
            
            if ($policy && $policy->applicable_categories && count($policy->applicable_categories) > 0) {
                $employeeQuery->whereIn('employees.category_id', $policy->applicable_categories);
            }
        }

        $employees = $employeeQuery->select('users.id', 'users.name', 'employees.employee_id', 'employees.category_id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id ?? '',
                    'category_id' => $user->category_id
                ];
            });

        // Get leave types for filter dropdown
        $leaveTypes = LeaveType::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->whereHas('leavePolicies', function ($query) {
                $query->where('status', 'active');
            })
            ->get(['id', 'name', 'color']);

        $years = LeaveBalance::whereIn('created_by', getCompanyAndUsersId())
            ->distinct()
            ->pluck('year')
            ->sort()
            ->values();

        $leavePolicies = LeavePolicy::where(function($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
                if ($activeBranchId) {
                    $q->orWhereNull('branch_id');
                }
            })
            ->where('status', 'active')
            ->get(['id', 'leave_type_id', 'applicable_categories']);

        return Inertia::render('hr/leave-balances/index', [
            'leaveBalances' => $leaveBalances,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leavePolicies' => $leavePolicies,
            'years' => $years,
            'filters' => $request->all(['search', 'employee_id', 'leave_type_id', 'year', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer|min:2020|max:2030',
            'allocated_days' => 'required|numeric|min:0',
            'carried_forward' => 'nullable|numeric|min:0',
            'manual_adjustment' => 'nullable|numeric',
            'adjustment_reason' => 'nullable|string',
        ]);

        // Validate that the employee belongs to the active branch
        $employee = User::with('employee')->find($validated['employee_id']);
        if ($employee && $employee->employee && $employee->employee->branch_id != session('active_branch_id')) {
            return redirect()->back()->with('error', __('Selected employee does not belong to the active branch.'));
        }

        $validated['created_by'] = creatorId();
        $validated['carried_forward'] = $validated['carried_forward'] ?? 0;
        $validated['manual_adjustment'] = $validated['manual_adjustment'] ?? 0;

        // Get leave policy for this leave type, matching active branch and category
        $activeBranchId = session('active_branch_id');
        $employeeCategory = $employee->employee->category_id;
        
        $leavePolicy = LeavePolicy::where('leave_type_id', $validated['leave_type_id'])
            ->where(function($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
                if ($activeBranchId) {
                    $q->orWhereNull('branch_id');
                }
            })
            ->where('status', 'active')
            ->where(function($q) use ($employeeCategory) {
                $q->whereNull('applicable_categories')
                  ->orWhereJsonContains('applicable_categories', (string)$employeeCategory);
            })
            ->orderByRaw('branch_id IS NULL ASC') // Branch-specific policies take precedence
            ->first();

        if (!$leavePolicy) {
            return redirect()->back()->with('error', __('No applicable leave policy found for this leave type and employee category.'));
        }

        $validated['leave_policy_id'] = $leavePolicy->id;

        // Check if balance already exists
        $exists = LeaveBalance::where('employee_id', $validated['employee_id'])
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('year', $validated['year'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', __('Leave balance already exists for this employee, leave type, and year.'));
        }

        // Calculate remaining days
        $validated['used_days'] = 0;
        $validated['remaining_days'] = ($validated['allocated_days'] + $validated['carried_forward'] + $validated['manual_adjustment']) - $validated['used_days'];

        LeaveBalance::create($validated);

            return redirect()->back()->with('success', __('Leave balance created successfully.'));
    }

    public function update(Request $request, $leaveBalanceId)
    {
        $leaveBalance = LeaveBalance::where('id', $leaveBalanceId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leaveBalance) {
            try {
                $validated = $request->validate([
                    'employee_id' => 'required|exists:users,id',
                    'leave_type_id' => 'required|exists:leave_types,id',
                    'year' => 'required|integer|min:2020|max:2030',
                    'allocated_days' => 'required|numeric|min:0',
                    'carried_forward' => 'nullable|numeric|min:0',
                    'manual_adjustment' => 'nullable|numeric',
                    'adjustment_reason' => 'nullable|string',
                ]);

                $validated['carried_forward'] = $validated['carried_forward'] ?? 0;
                $validated['manual_adjustment'] = $validated['manual_adjustment'] ?? 0;

                // Validate that the employee belongs to the active branch
                $employee = User::with('employee')->find($validated['employee_id']);
                if ($employee && $employee->employee && $employee->employee->branch_id != session('active_branch_id')) {
                    return redirect()->back()->with('error', __('Selected employee does not belong to the active branch.'));
                }

                // Get leave policy matching active branch and category
                $activeBranchId = session('active_branch_id');
                $employeeCategory = $employee->employee->category_id;
                
                $leavePolicy = LeavePolicy::where('leave_type_id', $validated['leave_type_id'])
                    ->where(function($q) use ($activeBranchId) {
                        $q->where('branch_id', $activeBranchId);
                        if ($activeBranchId) {
                            $q->orWhereNull('branch_id');
                        }
                    })
                    ->where('status', 'active')
                    ->where(function($q) use ($employeeCategory) {
                        $q->whereNull('applicable_categories')
                          ->orWhereJsonContains('applicable_categories', (string)$employeeCategory);
                    })
                    ->orderByRaw('branch_id IS NULL ASC') // Branch-specific policies take precedence
                    ->first();

                if (!$leavePolicy) {
                    return redirect()->back()->with('error', __('No applicable leave policy found for this leave type and employee category.'));
                }

                $validated['leave_policy_id'] = $leavePolicy->id;

                // Recalculate remaining days
                $validated['remaining_days'] = ($validated['allocated_days'] + $validated['carried_forward'] + $validated['manual_adjustment']) - $leaveBalance->used_days;

                $leaveBalance->update($validated);

            return redirect()->back()->with('success', __('Leave balance updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update leave balance'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave balance Not Found.'));
        }
    }

    public function destroy($leaveBalanceId)
    {
        $leaveBalance = LeaveBalance::where('id', $leaveBalanceId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leaveBalance) {
            try {
                $leaveBalance->delete();
            return redirect()->back()->with('success', __('Leave balance deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete leave balance'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave balance Not Found.'));
        }
    }

    public function adjust(Request $request, $leaveBalanceId)
    {
        $validated = $request->validate([
            'manual_adjustment' => 'required|numeric',
            'adjustment_reason' => 'required|string',
        ]);

        $leaveBalance = LeaveBalance::where('id', $leaveBalanceId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leaveBalance) {
            try {
                $leaveBalance->update([
                    'manual_adjustment' => $validated['manual_adjustment'],
                    'adjustment_reason' => $validated['adjustment_reason'],
                ]);

                // Recalculate remaining days
                $leaveBalance->calculateRemainingDays();
                $leaveBalance->save();

                return redirect()->back()->with('success', __('Leave balance adjusted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to adjust leave balance'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave balance Not Found.'));
        }
    }

    public function suggestCarryForward(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer',
        ]);

        $amount = LeaveBalance::calculateSuggestedCarryForward(
            $request->employee_id,
            $request->leave_type_id,
            $request->year
        );

        return response()->json(['amount' => $amount]);
    }
}