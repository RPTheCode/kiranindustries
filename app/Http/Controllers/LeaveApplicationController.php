<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\LeavePolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\LogsActivity;
use Carbon\Carbon;

class LeaveApplicationController extends Controller
{
    use LogsActivity;
    public function index(Request $request)
    {
        $query = LeaveApplication::withPermissionCheck()
            ->with([
                'employee.employee' => function ($q) {
                    $q->withoutGlobalScopes()->with('branch');
                },
                'leaveType',
                'leavePolicy',
                'approver',
                'creator'
            ]);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('reason', 'like', '%' . $request->search . '%')
                    ->orWhereHas('employee', function ($subQ) use ($request) {
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

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $leaveApplications = $query->paginate($request->per_page ?? 10);

        $activeBranchId = session('active_branch_id');
        $selfServiceOnly = userIsLeaveApplicationSelfServiceOnly();

        if ($selfServiceOnly) {
            $employees = collect(selfServiceEmployeeOptions());
        } else {
            $employeeQuery = User::join('employees', 'users.id', '=', 'employees.user_id')
                ->where('users.type', 'employee')
                ->whereIn('users.created_by', getCompanyAndUsersId());

            if ($request->has('leave_type_id') && !empty($request->leave_type_id) && $request->leave_type_id !== 'all') {
                $policy = LeavePolicy::where('leave_type_id', $request->leave_type_id)
                    ->where(function ($q) use ($activeBranchId) {
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
                        'category_id' => $user->category_id,
                    ];
                });
        }

        // Get leave types for filter dropdown (Respect branch scope)
        $leaveTypes = LeaveType::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name', 'color']);

        $leavePolicies = LeavePolicy::where(function($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
                if ($activeBranchId) {
                    $q->orWhereNull('branch_id');
                }
            })
            ->where('status', 'active')
            ->get(['id', 'leave_type_id', 'applicable_categories']);

        return Inertia::render('hr/leave-applications/index', [
            'leaveApplications' => $leaveApplications,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leavePolicies' => $leavePolicies,
            'self_service_only' => $selfServiceOnly,
            'filters' => $request->all(['search', 'employee_id', 'leave_type_id', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        if (userIsLeaveApplicationSelfServiceOnly()) {
            $request->merge(['employee_id' => auth()->id()]);
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'attachment' => 'nullable|string',
        ]);

        $validated['created_by'] = creatorId();

        // Calculate total days excluding week offs and holidays (Using active branch rules)
        $employee = User::with([
            'employee' => function ($q) {
                $q->withoutGlobalScopes();
            }
        ])->find($validated['employee_id']);
        if (!$employee || !$employee->employee) {
            return redirect()->back()->with('error', __('Employee profile not found.'));
        }

        $activeBranchId = session('active_branch_id');
        $validated['total_days'] = $employee->employee->calculateLeaveDaysInRange($validated['start_date'], $validated['end_date'], $activeBranchId);

        if ($validated['total_days'] <= 0) {
            return redirect()->back()->with('error', __('Selected date range consists only of week offs or holidays in the current branch.'));
        }

        // Get leave policy for this leave type, matching the active branch and category
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
            return redirect()->back()->with('error', __('No applicable leave policy found for this leave type and your employee category.'));
        }

        $validated['leave_policy_id'] = $leavePolicy->id;
        $validated['branch_id'] = $activeBranchId;

        // Validate days per application
        if (
            $validated['total_days'] < $leavePolicy->min_days_per_application ||
            $validated['total_days'] > $leavePolicy->max_days_per_application
        ) {
            return redirect()->back()->with(
                'error',
                __('Leave days must be between :min and :max days.', [
                    'min' => $leavePolicy->min_days_per_application,
                    'max' => $leavePolicy->max_days_per_application
                ])
            );
        }

        // Check if employee has enough leave balance for the requested year
        $requestedYear = Carbon::parse($validated['start_date'])->year;
        $leaveBalance = \App\Models\LeaveBalance::where('employee_id', $validated['employee_id'])
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('year', $requestedYear)
            ->first();

        if (!$leaveBalance) {
            $carriedForward = \App\Models\LeaveBalance::calculateSuggestedCarryForward(
                $validated['employee_id'],
                $validated['leave_type_id'],
                $requestedYear
            );

            // Create initial balance if doesn't exist
            $leaveBalance = \App\Models\LeaveBalance::create([
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'leave_policy_id' => $leavePolicy->id,
                'year' => $requestedYear,
                'allocated_days' => $leavePolicy->accrual_rate ?? 10,
                'carried_forward' => $carriedForward,
                'used_days' => 0,
                'remaining_days' => ($leavePolicy->accrual_rate ?? 10) + $carriedForward,
                'created_by' => creatorId(),
            ]);
        }

        // Check if enough balance available
        if ($leaveBalance->remaining_days < $validated['total_days']) {
            return redirect()->back()->with(
                'error',
                __('Insufficient leave balance. Available: :available days, Requested: :requested days', [
                    'available' => $leaveBalance->remaining_days,
                    'requested' => $validated['total_days']
                ])
            );
        }

        // Handle attachment from media library
        if ($request->has('attachment')) {
            $validated['attachment'] = $request->attachment;
        }

        // Set status based on policy
        $validated['status'] = $leavePolicy->requires_approval ? 'pending' : 'approved';

        $leaveApplication = new LeaveApplication($validated);
        $leaveApplication->branch_id = $activeBranchId
            ?? $employee->employee->branch_id
            ?? session('active_branch_id');
        $leaveApplication->save();

        // Create attendance records if auto-approved
        if ($leaveApplication->status === 'approved') {
            $leaveApplication->createAttendanceRecords();
        }

        return redirect()->back()->with('success', __('Leave application created successfully.'));
    }

    public function update(Request $request, $leaveApplicationId)
    {
        $leaveApplication = LeaveApplication::where('id', $leaveApplicationId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leaveApplication) {
            if (userIsLeaveApplicationSelfServiceOnly() && (int) $leaveApplication->employee_id !== (int) auth()->id()) {
                abort(403, __('You can only update your own leave applications.'));
            }

            try {
                if (userIsLeaveApplicationSelfServiceOnly()) {
                    $request->merge(['employee_id' => auth()->id()]);
                }

                $validated = $request->validate([
                    'employee_id' => 'required|exists:users,id',
                    'leave_type_id' => 'required|exists:leave_types,id',
                    'start_date' => 'required|date',
                    'end_date' => 'required|date|after_or_equal:start_date',
                    'reason' => 'required|string',
                    'attachment' => 'nullable|string',
                ]);

                // Calculate total days excluding week offs and holidays (Using active branch rules)
                $employee = User::with([
                    'employee' => function ($q) {
                        $q->withoutGlobalScopes();
                    }
                ])->find($validated['employee_id']);
                if (!$employee || !$employee->employee) {
                    return redirect()->back()->with('error', __('Employee profile not found.'));
                }

                $activeBranchId = session('active_branch_id');
                $validated['total_days'] = $employee->employee->calculateLeaveDaysInRange($validated['start_date'], $validated['end_date'], $activeBranchId);

                if ($validated['total_days'] <= 0) {
                    return redirect()->back()->with('error', __('Selected date range consists only of week offs or holidays in the current branch.'));
                }

                // Get leave policy matching the active branch and category
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
                    return redirect()->back()->with('error', __('No applicable leave policy found for this leave type and your employee category.'));
                }

                $validated['leave_policy_id'] = $leavePolicy->id;
                $validated['branch_id'] = $activeBranchId;

                // Handle attachment from media library
                if ($request->has('attachment')) {
                    $validated['attachment'] = $request->attachment;
                }

                $leaveApplication->update($validated);

                return redirect()->back()->with('success', __('Leave application updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update leave application'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave application Not Found.'));
        }
    }

    public function destroy($leaveApplicationId)
    {
        $leaveApplication = LeaveApplication::where('id', $leaveApplicationId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leaveApplication) {
            if (userIsLeaveApplicationSelfServiceOnly() && (int) $leaveApplication->employee_id !== (int) auth()->id()) {
                abort(403, __('You can only delete your own leave applications.'));
            }

            try {
                $leaveApplication->delete();
                return redirect()->back()->with('success', __('Leave application deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete leave application'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave application Not Found.'));
        }
    }

    public function updateStatus(Request $request, $leaveApplicationId)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'manager_comments' => 'nullable|string',
        ]);

        $leaveApplication = LeaveApplication::where('id', $leaveApplicationId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leaveApplication) {
            try {
                $leaveApplication->update([
                    'status' => $validated['status'],
                    'manager_comments' => $validated['manager_comments'],
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                // Create attendance records if approved
                if ($validated['status'] === 'approved') {
                    // Double-check balance before final approval
                    $requestedYear = Carbon::parse($leaveApplication->start_date)->year;
                    $leaveBalance = \App\Models\LeaveBalance::where('employee_id', $leaveApplication->employee_id)
                        ->where('leave_type_id', $leaveApplication->leave_type_id)
                        ->where('year', $requestedYear)
                        ->first();

                    if ($leaveBalance && $leaveBalance->remaining_days < $leaveApplication->total_days) {
                        return redirect()->back()->with(
                            'error',
                            __('Cannot approve: Insufficient leave balance. Available: :available days, Required: :required days', [
                                'available' => $leaveBalance->remaining_days,
                                'required' => $leaveApplication->total_days
                            ])
                        );
                    }

                    $leaveApplication->createAttendanceRecords();
                }

                return redirect()->back()->with('success', __('Leave application status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update leave application status'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave application Not Found.'));
        }
    }
}