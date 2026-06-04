<?php

namespace App\Http\Controllers;

use App\Models\AttendancePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AttendancePolicyController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendancePolicy::withPermissionCheck()
            ->with(['creator', 'branch']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Global scope handles branch filtering

        // Handle overtime calculation filter
        if ($request->has('overtime_calculation') && !empty($request->overtime_calculation) && $request->overtime_calculation !== 'all') {
            $query->where('overtime_calculation', $request->overtime_calculation);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $attendancePolicies = $query->paginate($request->per_page ?? 10);

        // Get branches for filter and form
        $branches = \App\Models\Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);

        return Inertia::render('hr/attendance-policies/index', [
            'attendancePolicies' => $attendancePolicies,
            // 'branches' => $branches, // Removed
            'filters' => $request->all(['search', 'status', 'overtime_calculation', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'late_arrival_grace' => 'required|integer|min:0',
            'early_departure_grace' => 'required|integer|min:0',
            'overtime_type' => 'required|in:fixed,salary_based',
            'overtime_rate_per_hour' => 'nullable|required_if:overtime_type,fixed|numeric|min:0',
            'weekoff_full_day_hours' => 'required|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = creatorId();
        $validated['status'] = $validated['status'] ?? 'active';

        // HasBranch scope handles branch filtering
        $existsQuery = AttendancePolicy::where('name', $validated['name'])
            ->whereIn('created_by', getCompanyAndUsersId());

        $exists = $existsQuery->exists();

        if ($exists) {
            return redirect()->back()->with('error', __('Attendance policy with this name already exists for this branch.'));
        }

        AttendancePolicy::create($validated);

        return redirect()->back()->with('success', __('Attendance policy created successfully.'));
    }

    public function update(Request $request, $attendancePolicyId)
    {
        $attendancePolicy = AttendancePolicy::where('id', $attendancePolicyId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($attendancePolicy) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'late_arrival_grace' => 'required|integer|min:0',
                    'early_departure_grace' => 'required|integer|min:0',
                    'overtime_type' => 'required|in:fixed,salary_based',
                    'overtime_rate_per_hour' => 'nullable|required_if:overtime_type,fixed|numeric|min:0',
                    'weekoff_full_day_hours' => 'required|integer|min:0',
                    'status' => 'nullable|in:active,inactive',
                ]);

                // Check if policy with same name already exists (excluding current)
                // Scope automatically applied
                $existsQuery = AttendancePolicy::where('name', $validated['name'])
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->where('id', '!=', $attendancePolicyId);

                $exists = $existsQuery->exists();

                if ($exists) {
                    return redirect()->back()->with('error', __('Attendance policy with this name already exists for this branch.'));
                }

                $attendancePolicy->update($validated);

                return redirect()->back()->with('success', __('Attendance policy updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update attendance policy'));
            }
        } else {
            return redirect()->back()->with('error', __('Attendance policy Not Found.'));
        }
    }

    public function destroy($attendancePolicyId)
    {
        $attendancePolicy = AttendancePolicy::where('id', $attendancePolicyId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($attendancePolicy) {
            try {
                $attendancePolicy->delete();
                return redirect()->back()->with('success', __('Attendance policy deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete attendance policy'));
            }
        } else {
            return redirect()->back()->with('error', __('Attendance policy Not Found.'));
        }
    }

    public function toggleStatus($attendancePolicyId)
    {
        $attendancePolicy = AttendancePolicy::where('id', $attendancePolicyId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($attendancePolicy) {
            try {
                $attendancePolicy->status = $attendancePolicy->status === 'active' ? 'inactive' : 'active';
                $attendancePolicy->save();

                return redirect()->back()->with('success', __('Attendance policy status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update attendance policy status'));
            }
        } else {
            return redirect()->back()->with('error', __('Attendance policy Not Found.'));
        }
    }
}