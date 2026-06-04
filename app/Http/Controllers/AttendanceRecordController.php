<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Shift;
use App\Models\AttendancePolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class AttendanceRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceRecord::withPermissionCheck()
            ->with([
                'employee' => function ($q) {
                    $q->withTrashed();
                },
                'employee.employee' => function ($q) {
                    $q->withoutGlobalScopes();
                },
                'shift',
                'attendancePolicy',
                'creator',
                'branch'
            ]);

        // Remove global branch scope if any branch-overriding filter is applied
        if (
            ($request->has('branch_id') && !empty($request->branch_id)) ||
            ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') ||
            ($request->has('search') && !empty($request->search))
        ) {
            $query->withoutGlobalScope(\App\Models\Scopes\BranchScope::class);
        }

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('employee', function ($subQ) use ($request) {
                    $subQ->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        // Handle employee filter
        if ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') {
            $query->where('employee_id', $request->employee_id);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle branch filter
        if ($request->has('branch_id') && !empty($request->branch_id) && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }

        // Handle date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('date', '<=', $request->date_to);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $attendanceRecords = $query->paginate($request->per_page ?? 10);

        // Add leave type information for on_leave records
        $attendanceRecords->getCollection()->transform(function ($record) {
            if ($record->status === 'on_leave') {
                $leaveApplication = \App\Models\LeaveApplication::where('employee_id', $record->employee_id)
                    ->whereDate('start_date', '<=', $record->date)
                    ->whereDate('end_date', '>=', $record->date)
                    ->where('status', 'approved')
                    ->with('leaveType')
                    ->first();

                $record->leave_type = $leaveApplication?->leaveType;
            }
            return $record;
        });

        // Get employees for filter dropdown
        $employees = User::join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->whereIn('users.created_by', getCompanyAndUsersId())
            ->select('users.id', 'users.name', 'employees.employee_id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id ?? ''
                ];
            });

        // Get branches for filter
        $branches = \App\Models\Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);

        return Inertia::render('hr/attendance-records/index', [
            'attendanceRecords' => $attendanceRecords,
            'employees' => $employees,
            'branches' => $branches,
            'filters' => $request->all(['search', 'employee_id', 'status', 'branch_id', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'break_hours' => 'nullable|numeric|min:0',
            'is_holiday' => 'boolean',
            'status' => 'required|in:present,absent,half_day,on_leave,holiday',
            'notes' => 'nullable|string',
        ]);

        // Ensure date is Y-m-d format to avoid time component issues
        $validated['date'] = Carbon::parse($validated['date'])->format('Y-m-d');

        // Get employee with shift and policy
        $employee = \App\Models\Employee::where('user_id', $validated['employee_id'])->first();

        // Determine branch: Prioritize active branch from session, otherwise use employee's branch
        $activeBranchId = session('active_branch_id');
        $branchId = ($activeBranchId && $activeBranchId !== 'all')
            ? $activeBranchId
            : $employee?->branch_id;

        $validated['branch_id'] = $branchId;

        if (!$branchId) {
            return redirect()->back()->with('error', __('Selected employee does not belong to any branch and no active branch is selected.'));
        }

        // Use employee's assigned shift and policy, or get defaults
        $shift = $employee && $employee->shift_id ?
            Shift::find($employee->shift_id) :
            Shift::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')->first();

        // Auto-resolve Policy:
        // 1. Try Employee's assigned policy first
        $policy = null;
        if ($employee && $employee->attendance_policy_id) {
            $policy = AttendancePolicy::find($employee->attendance_policy_id);
        }

        // 2. If no employee policy, try to find active policy for the specific branch
        if (!$policy && $branchId) {
            $policy = AttendancePolicy::where('branch_id', $branchId)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->where('status', 'active')
                ->first();
        }

        // 3. Fallback to global or any active policy
        if (!$policy) {
            $policy = AttendancePolicy::where(function($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->first();
        }

        $validated['shift_id'] = $shift?->id;
        $validated['attendance_policy_id'] = $policy?->id;
        $validated['created_by'] = creatorId();
        $validated['is_holiday'] = $validated['is_holiday'] ?? false;
        $validated['break_hours'] = $validated['break_hours'] ?? 0;

        // Check if record already exists across ALL branches
        $existingRecord = AttendanceRecord::withoutGlobalScopes()
            ->with('branch')
            ->where('employee_id', $validated['employee_id'])
            ->where('date', $validated['date'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($existingRecord) {
            $branchName = $existingRecord->branch ? $existingRecord->branch->name : __('Unknown');
            return redirect()->back()->with('error', __("Attendance record already exists for this employee on this date in :branch branch.", ['branch' => $branchName]));
        }

        // Set weekend flag
        $validated['is_weekend'] = Carbon::parse($validated['date'])->isWeekend();

        $record = AttendanceRecord::create($validated);

        // Process complete attendance calculation
        $record->fresh(); // Reload to get relationships
        $record->processAttendance();

        return redirect()->back()->with('success', __('Attendance record created successfully.'));
    }

    public function update(Request $request, $attendanceRecordId)
    {
        $attendanceRecord = AttendanceRecord::where('id', $attendanceRecordId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($attendanceRecord) {
            try {
                $validated = $request->validate([
                    'employee_id' => 'required|exists:users,id',
                    'date' => 'required|date',
                    'clock_in' => 'nullable|date_format:H:i',
                    'clock_out' => 'nullable|date_format:H:i',
                    'break_hours' => 'nullable|numeric|min:0',
                    'is_holiday' => 'boolean',
                    'status' => 'required|in:present,absent,half_day,on_leave,holiday',
                    'notes' => 'nullable|string',
                ]);

                // Ensure date is Y-m-d format to avoid time component issues
                $validated['date'] = Carbon::parse($validated['date'])->format('Y-m-d');

                // Check if employee or date changed and if duplicate exists
                // Use format('Y-m-d') on model attribute to ensure string comparison
                $currentDate = $attendanceRecord->date instanceof \Carbon\Carbon ? $attendanceRecord->date->format('Y-m-d') : $attendanceRecord->date;


                if ($attendanceRecord->employee_id != $validated['employee_id'] || $currentDate != $validated['date']) {
                    $existingRecord = AttendanceRecord::withoutGlobalScopes()
                        ->with('branch')
                        ->where('employee_id', $validated['employee_id'])
                        ->where('date', $validated['date'])
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->where('id', '!=', $attendanceRecordId)
                        ->first();

                    if ($existingRecord) {
                        $branchName = $existingRecord->branch ? $existingRecord->branch->name : __('Unknown');
                        return redirect()->back()->with('error', __("Attendance record already exists for this employee on this date in :branch branch.", ['branch' => $branchName]));
                    }
                }

                // Get employee with shift and policy
                $employee = \App\Models\Employee::where('user_id', $validated['employee_id'])->first();

                // Determine branch: Prioritize active branch from session, otherwise use employee's branch
                $activeBranchId = session('active_branch_id');
                $branchId = ($activeBranchId && $activeBranchId !== 'all')
                    ? $activeBranchId
                    : $employee?->branch_id;

                $validated['branch_id'] = $branchId;

                if (!$branchId) {
                    return redirect()->back()->with('error', __('Selected employee does not belong to any branch and no active branch is selected.'));
                }

                // Use employee's assigned shift and policy, or get defaults
                $shift = $employee && $employee->shift_id ?
                    Shift::find($employee->shift_id) :
                    Shift::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')->first();

                // Auto-resolve Policy:
                // 1. Try Employee's assigned policy first
                $policy = null;
                if ($employee && $employee->attendance_policy_id) {
                    $policy = AttendancePolicy::find($employee->attendance_policy_id);
                }

                // 2. If no employee policy, try to find active policy for the specific branch
                if (!$policy && $branchId) {
                    $policy = AttendancePolicy::where('branch_id', $branchId)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->where('status', 'active')
                        ->first();
                }

                // 3. Fallback to global or any active policy
                if (!$policy) {
                    $policy = AttendancePolicy::where(function($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->where('status', 'active')
                    ->first();
                }

                $validated['shift_id'] = $shift?->id;
                $validated['attendance_policy_id'] = $policy?->id;

                // Set weekend flag
                $validated['is_weekend'] = Carbon::parse($validated['date'])->isWeekend();

                $attendanceRecord->update($validated);

                // Process complete attendance calculation
                $attendanceRecord->fresh(); // Reload to get relationships
                $attendanceRecord->processAttendance();

                return redirect()->back()->with('success', __('Attendance record updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update attendance record'));
            }
        } else {
            return redirect()->back()->with('error', __('Attendance record Not Found.'));
        }
    }

    public function destroy($attendanceRecordId)
    {
        $attendanceRecord = AttendanceRecord::where('id', $attendanceRecordId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($attendanceRecord) {
            try {
                $attendanceRecord->delete();
                return redirect()->back()->with('success', __('Attendance record deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete attendance record'));
            }
        } else {
            return redirect()->back()->with('error', __('Attendance record Not Found.'));
        }
    }

    public function clockIn(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:users,id',
            ]);

            $today = Carbon::today();
            $now = Carbon::now();

            // Check if already clocked in today
            $existingRecord = AttendanceRecord::where('employee_id', $validated['employee_id'])
                ->where('date', $today)
                ->first();

            if ($existingRecord && $existingRecord->clock_in) {
                return redirect()->back()->with('error', __('Already clocked in today.'));
            }

            // Get employee with shift and policy
            $employee = \App\Models\Employee::where('user_id', $validated['employee_id'])->first();

            if (!$employee) {
                return redirect()->back()->with('error', __('Employee profile not found.'));
            }

            // Use employee's assigned shift and policy, or get defaults
            $shift = $employee->shift_id ?
                Shift::find($employee->shift_id) :
                Shift::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')->first();

            $policy = $employee->attendance_policy_id ?
                AttendancePolicy::find($employee->attendance_policy_id) :
                AttendancePolicy::whereIn('created_by', getCompanyAndUsersId())->where('status', 'active')->first();


            if (!$shift || !$policy) {
                return redirect()->back()->with('error', __('No active shift or attendance policy found. Please contact HR.'));
            }

            if ($existingRecord) {
                $existingRecord->update([
                    'clock_in' => $now->format('H:i:s'),
                    'shift_id' => $shift->id,
                    'attendance_policy_id' => $policy->id,
                    'status' => 'present',
                ]);
                $record = $existingRecord;
            } else {
                $record = AttendanceRecord::create([
                    'employee_id' => $validated['employee_id'],
                    'date' => $today,
                    'clock_in' => $now->format('H:i:s'),
                    'shift_id' => $shift->id,
                    'attendance_policy_id' => $policy->id,
                    'is_weekend' => $today->isWeekend(),
                    'status' => 'present',
                    'created_by' => creatorId(),
                ]);
            }

            // Check for late arrival if methods exist
            if (method_exists($record, 'checkLateArrival')) {
                $record->checkLateArrival();
                $record->save();
            }

            return redirect()->back()->with('success', __('Clocked in successfully.'));
        } catch (\Exception $e) {
            \Log::error('Clock in failed: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to clock in. Please try again.'));
        }
    }

    public function clockOut(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:users,id',
            ]);

            $today = Carbon::today();
            $now = Carbon::now();

            $record = AttendanceRecord::where('employee_id', $validated['employee_id'])
                ->where('date', $today)
                ->first();

            if (!$record || !$record->clock_in) {
                return redirect()->back()->with('error', __('Must clock in first.'));
            }

            if ($record->clock_out) {
                return redirect()->back()->with('error', __('Already clocked out today.'));
            }

            $record->update([
                'clock_out' => $now->format('H:i:s'),
            ]);

            // Process complete attendance calculation if method exists
            if (method_exists($record, 'processAttendance')) {
                $record->processAttendance();
            }

            return redirect()->back()->with('success', __('Clocked out successfully.'));
        } catch (\Exception $e) {
            \Log::error('Clock out failed: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Failed to clock out. Please try again.'));
        }
    }

    public function getTodayAttendance(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
        ]);

        $today = Carbon::today();
        $attendance = AttendanceRecord::where('employee_id', $validated['employee_id'])
            ->where('date', $today)
            ->first();

        return Inertia::render('employee-dashboard', [
            'attendance' => $attendance
        ]);
    }

    public function exportDailyReport(Request $request)
    {
        $branchId = $request->branch_id;
        if (!$branchId || $branchId === 'all') {
            $branchId = session('active_branch_id');
        }
        try {
            $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->format('Y-m-d') : ($request->date ?? Carbon::today()->format('Y-m-d'));
            $dateTo = $request->date_to ? Carbon::parse($request->date_to)->format('Y-m-d') : $dateFrom;
        } catch (\Exception $e) {
            // Fallback if parsing fails
            $dateFrom = Carbon::today()->format('Y-m-d');
            $dateTo = $dateFrom;
        }
        $employeeId = $request->employee_id ?? 'all';

        $filename = 'Attendance_Report_';
        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $filename .= preg_replace('/[^A-Za-z0-9_\-]/', '_', $branch->name) . '_';
            }
        }

        if ($dateFrom === $dateTo) {
            $filename .= Carbon::parse($dateFrom)->format('d-m-Y');
        } else {
            $filename .= Carbon::parse($dateFrom)->format('d-m-Y') . '_to_' . Carbon::parse($dateTo)->format('d-m-Y');
        }

        $filename .= '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DailyAttendanceExport($dateFrom, $dateTo, $branchId, $employeeId),
            $filename
        );
    }
}
