<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\WeekOff;
use App\Models\EmployeeWeekOff;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeekOffController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function index(Request $request)
    {
        $activeBranchId = session('active_branch_id');
        $employmentType = $request->get('employment_type', 'Employee');

        $weekOff = null;
        if ($activeBranchId) {
            $weekOff = WeekOff::where('branch_id', $activeBranchId)
                ->where('employment_type', $employmentType)
                ->first();
        }

        $branches = Branch::where('status', 'active')->get();

        $departmentsQuery = \App\Models\Department::where('status', 'active');
        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }
        $departments = $departmentsQuery->get();

        $designationsQuery = \App\Models\Designation::where('status', 'active');
        if ($activeBranchId) {
            $designationsQuery->where('branch_id', $activeBranchId);
        }
        $designations = $designationsQuery->get();

        $sectionsQuery = \App\Models\Section::withoutGlobalScopes()->where('status', 'active');
        if ($activeBranchId) {
            $sectionsQuery->where('branch_id', $activeBranchId);
        }
        $sections = $sectionsQuery->get();

        $shiftsQuery = \App\Models\Shift::where('status', 'active');
        if ($activeBranchId) {
            $shiftsQuery->where('branch_id', $activeBranchId);
        }
        $shifts = $shiftsQuery->get();

        $categoriesQuery = \App\Models\Category::withoutGlobalScopes()->where('status', 'active');
        if ($activeBranchId) {
            $categoriesQuery->where('branch_id', $activeBranchId);
        }
        $categories = $categoriesQuery->get();

        $employeesQuery = Employee::active()
            ->with(['user', 'shift', 'department', 'designation', 'section', 'category']);
        if ($activeBranchId) {
            $employeesQuery->where('branch_id', $activeBranchId);
        }
        $employees = $employeesQuery->get();

        return Inertia::render('hr/week_off/index', [
            'weekOff' => $weekOff,
            'activeBranchId' => $activeBranchId,
            'branches' => $branches,
            'departments' => $departments,
            'designations' => $designations,
            'sections' => $sections,
            'shifts' => $shifts,
            'categories' => $categories,
            'employmentType' => $employmentType,
            'employees' => $employees,
        ]);
    }

    public function storeIndividual(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'recurring_week_offs' => 'nullable',
            'week_off_type' => 'nullable|string|in:weekly,monthly',
            'off_dates' => 'nullable|array',
            'off_dates.*' => 'date',
            'reason' => 'nullable|string',
            'remove' => 'nullable|boolean'
        ]);

        $employeeIds = $request->input('employee_ids', []);
        $recurringWeekOffs = $request->input('recurring_week_offs');
        $weekOffType = $request->input('week_off_type', 'weekly');
        $isRemoval = $request->input('remove', false);

        // Prepare the value for database
        if ($isRemoval) {
            $weekOffStr = null;
        } else if (is_array($recurringWeekOffs)) {
            $weekOffStr = empty($recurringWeekOffs) ? 'None' : implode(',', $recurringWeekOffs);
        } else {
            // It's already a JSON string from frontend for monthly
            $weekOffStr = $recurringWeekOffs ?: 'None';
        }

        foreach ($employeeIds as $id) {
            $employee = Employee::find($id);
            if ($employee) {
                // 1. Update Recurring Week-Off and Type
                $employee->update([
                    'week_off' => $weekOffStr,
                    'week_off_type' => $isRemoval ? 'weekly' : $weekOffType
                ]);

                // 2. Update Specific Off Dates
                EmployeeWeekOff::where('employee_id', $employee->user_id)->delete();

                if (!empty($request->off_dates) && !$isRemoval) {
                    foreach ($request->off_dates as $date) {
                        EmployeeWeekOff::create([
                            'employee_id' => $employee->user_id,
                            'off_date' => $date,
                            'reason' => $request->reason,
                            'created_by' => creatorId(),
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Individual week offs updated successfully.');
    }

    public function store(Request $request)
    {
        $activeBranchId = session('active_branch_id');
        if (!$activeBranchId) {
            return redirect()->back()->with('error', 'No active branch selected.');
        }

        $validated = $request->validate([
            'type' => 'required|in:weekly,monthly',
            'employment_type' => 'required|in:Employee,Labour',
            'apply_mode' => 'required|in:current,all,selected',
            'branch_ids' => 'required_if:apply_mode,selected|array',
            'branch_ids.*' => 'exists:branches,id',
            'weekly_settings' => 'required_if:type,weekly|array',
            'monthly_settings' => 'required_if:type,monthly|array',
        ]);

        $settings = $validated['type'] === 'weekly' ? $validated['weekly_settings'] : $validated['monthly_settings'];

        $targetBranchIds = [];
        if ($validated['apply_mode'] === 'current') {
            $targetBranchIds = [$activeBranchId];
        } elseif ($validated['apply_mode'] === 'all') {
            $targetBranchIds = Branch::pluck('id')->toArray();
        } else {
            $targetBranchIds = $validated['branch_ids'];
        }

        foreach ($targetBranchIds as $branchId) {
            $weekOff = WeekOff::updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'employment_type' => $validated['employment_type']
                ],
                [
                    'type' => $validated['type'],
                    'settings' => $settings,
                    'created_by' => creatorId(),
                ]
            );
            $weekOff->wasRecentlyCreated
                ? $this->logMasterCreated($weekOff)
                : $this->logMasterUpdated($weekOff);
        }

        return redirect()->back()->with('success', 'Week off settings saved successfully.');
    }
}
