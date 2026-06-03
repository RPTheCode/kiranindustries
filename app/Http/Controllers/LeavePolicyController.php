<?php

namespace App\Http\Controllers;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Traits\LogsActivity;

class LeavePolicyController extends Controller
{
    use LogsActivity;
    public function index(Request $request)
    {
        $query = LeavePolicy::withPermissionCheck()
            ->with(['leaveType', 'creator', 'branch']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhereHas('leaveType', function ($subQ) use ($request) {
                        $subQ->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Handle leave type filter
        if ($request->has('leave_type_id') && !empty($request->leave_type_id) && $request->leave_type_id !== 'all') {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Global scope handles branch filtering automatically

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $leavePolicies = $query->paginate($request->per_page ?? 10);

        // Get leave types for filter dropdown
        $leaveTypes = LeaveType::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name', 'color', 'branch_id']);

        $categories = \App\Models\Category::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);

        return Inertia::render('hr/leave-policies/index', [
            'leavePolicies' => $leavePolicies,
            'leaveTypes' => $leaveTypes,
            'categories' => $categories,
            'filters' => $request->all(['search', 'leave_type_id', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'leave_type_id' => 'required|exists:leave_types,id',
            'accrual_type' => 'required|in:monthly,yearly',
            'accrual_rate' => 'required|numeric|min:0',
            'carry_forward_limit' => 'required|integer|min:0',
            'min_days_per_application' => 'required|integer|min:1',
            'max_days_per_application' => 'required|integer|min:1',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'exists:categories,id',
            'requires_approval' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = creatorId();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['requires_approval'] = $validated['requires_approval'] ?? true;

        // Check if leave type belongs to the current user's company and active branch
        // HasBranch scope on LeaveType ensures we only see types of this branch
        $leaveType = LeaveType::where('id', $validated['leave_type_id'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$leaveType) {
            return redirect()->back()->with('error', __('Invalid leave type selected for this branch.'));
        }

        LeavePolicy::create($validated);

            return redirect()->back()->with('success', __('Leave policy created successfully.'));
    }

    public function update(Request $request, $leavePolicyId)
    {
        $leavePolicy = LeavePolicy::where('id', $leavePolicyId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leavePolicy) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'leave_type_id' => 'required|exists:leave_types,id',
                    'accrual_type' => 'required|in:monthly,yearly',
                    'accrual_rate' => 'required|numeric|min:0',
                    'carry_forward_limit' => 'required|integer|min:0',
                    'min_days_per_application' => 'required|integer|min:1',
                    'max_days_per_application' => 'required|integer|min:1',
                    'applicable_categories' => 'nullable|array',
                    'applicable_categories.*' => 'exists:categories,id',
                    'requires_approval' => 'boolean',
                    'status' => 'nullable|in:active,inactive',
                ]);

                // Check if leave type belongs to the current user's company and active branch
                $leaveType = LeaveType::where('id', $validated['leave_type_id'])
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if (!$leaveType) {
                    return redirect()->back()->with('error', __('Invalid leave type selected for this branch.'));
                }

                $leavePolicy->update($validated);

            return redirect()->back()->with('success', __('Leave policy updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update leave policy'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave policy Not Found.'));
        }
    }

    public function destroy($leavePolicyId)
    {
        $leavePolicy = LeavePolicy::where('id', $leavePolicyId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leavePolicy) {
            try {
                $leavePolicy->delete();
            return redirect()->back()->with('success', __('Leave policy deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete leave policy'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave policy Not Found.'));
        }
    }

    public function toggleStatus($leavePolicyId)
    {
        $leavePolicy = LeavePolicy::where('id', $leavePolicyId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($leavePolicy) {
            try {
                $leavePolicy->status = $leavePolicy->status === 'active' ? 'inactive' : 'active';
                $leavePolicy->save();

            return redirect()->back()->with('success', __('Leave policy status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update leave policy status'));
            }
        } else {
            return redirect()->back()->with('error', __('Leave policy Not Found.'));
        }
    }
}