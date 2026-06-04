<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Employee;
use App\Models\DailyProductionAttendanceEntry;
use App\Models\MaterialItem;
use App\Models\Shift;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

use App\Traits\LogsActivity;

class DailyProductionAttendanceEntryController extends Controller
{
    use LogsActivity;
    public function index(Request $request)
    {
        $selected_employee_id = $request->query('employee_id');
        $date = $request->query('date', date('Y-m-d'));

        $employees = Employee::active()
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->select('employees.id', 'users.name', 'employees.employee_id as scroll_no')
            ->get();

        $activeBranchId = session('active_branch_id');
        $materialsQuery = MaterialItem::where('status', 'active');
        if ($activeBranchId) {
            $materialsQuery->where('branch_id', $activeBranchId);
        }
        $materials = $materialsQuery->get();
        $shiftsQuery = Shift::where('status', 'active');
        if ($activeBranchId) {
            $shiftsQuery->where('branch_id', $activeBranchId);
        }
        $shifts = $shiftsQuery->get();

        // Fetch recent entries (today's entries by default)
        $recentEntries = DailyProductionAttendanceEntry::with(['employee.user', 'materialItem', 'shift'])
            ->where('date', $date)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('hr/payroll/daily-production-attendance-entry/index', [
            'employees' => $employees,
            'materials' => $materials,
            'shifts' => $shifts,
            'recent_entries' => $recentEntries,
            'selected_employee_id' => $selected_employee_id,
            'selected_date' => $date,
        ]);
    }

    public function getEmployeeDetails($id)
    {
        $employee = Employee::with(['user', 'department', 'designation', 'section', 'category', 'shift'])->find($id);

        if (!$employee) {
            $employee = Employee::with(['user', 'department', 'designation', 'section', 'category', 'shift'])
                ->where('user_id', $id)
                ->first();
        }

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Fetch existing entry for current date if exists
        $currentDate = date('Y-m-d');
        $existingEntry = DailyProductionAttendanceEntry::where('employee_id', $employee->id)
            ->where('date', $currentDate)
            ->first();

        // Handle Avatar URL
        $avatarUrl = null;
        if ($employee->user && $employee->user->avatar) {
            $avatarUrl = Storage::url($employee->user->avatar);
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user->name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'section' => $employee->section?->name,
                'category' => $employee->category?->name,
                'shift_id' => $employee->shift_id,
                'pf_no' => $employee->pf_number,
                'esi_no' => $employee->esic_number,
                'scroll_no' => $employee->employee_id,
                'avatar' => $avatarUrl
            ],
            'existing_entry' => $existingEntry
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'material_item_id' => 'required|exists:material_items,id',
            'production_qty' => 'required|numeric|min:0.0001',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'remark' => 'nullable|string',
        ]);

        $entry = DailyProductionAttendanceEntry::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'date' => $validated['date'],
            ],
            array_merge($validated, [
                'branch_id' => auth()->user()->branch_id ?? Employee::find($validated['employee_id'])->branch_id
            ])
        );

            return redirect()->back()->with('success', 'Attendance production entry saved successfully.');
    }

    public function destroy($id)
    {
        $entry = DailyProductionAttendanceEntry::findOrFail($id);
        $entry->delete();

            return redirect()->back()->with('success', 'Entry deleted successfully.');
    }
}
