<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MonthlyIncentiveDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;
use App\Models\MonthlyIncentiveEntry;
use Inertia\Inertia;

use App\Traits\LogsActivity;

class MonthlyIncentiveController extends Controller
{
    use LogsActivity;
    public function index(Request $request)
    {
        $selected_employee_id = $request->query('employee_id');

        $employees = Employee::active()
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->select('employees.id', 'users.name', 'employees.employee_id as scroll_no')
            ->get();

        $incentiveDeductionTypes = \App\Models\IncentiveDeductionType::active()
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('hr/payroll/monthly-incentives/index', [
            'employees' => $employees,
            'selected_employee_id' => $selected_employee_id,
            'incentiveDeductionTypes' => $incentiveDeductionTypes,
        ]);
    }

    public function getEmployeeDetails(Request $request, $id)
    {
        $date = $request->query('date');
        $month = $request->query('month', date('Y-m'));

        // Try finding by Employee ID first, then by User ID
        $employee = Employee::with(['user', 'department', 'designation'])->find($id);

        if (!$employee) {
            $employee = Employee::with(['user', 'department', 'designation'])
                ->where('user_id', $id)
                ->first();
        }

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Fetch existing entry for requested month/date if exists
        $query = MonthlyIncentiveEntry::with('details')
            ->where('employee_id', $employee->id);

        if ($date) {
            $query->where('date', $date);
        } else {
            $query->where('month_year', $month);
        }

        $existingEntry = $query->first();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user->name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'pf_no' => $employee->pf_number,
                'esi_no' => $employee->esic_number,
                'scroll_no' => $employee->employee_id,
            ],
            'existing_entry' => $existingEntry ? [
                'id' => $existingEntry->id,
                'remark' => $existingEntry->remark,
                'date' => $existingEntry->date,
                'details' => $existingEntry->details->map(function ($d) {
                    return [
                        'type_id' => $d->type_id,
                        'name' => $d->name,
                        'type' => $d->type,
                        'mode' => $d->mode,
                        'value' => $d->value
                    ];
                })
            ] : null
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month_year' => 'required|string|size:7',
            'date' => 'nullable|date',
            'remark' => 'nullable|string',
            'details' => 'nullable|array',
            'details.*.type_id' => 'nullable|exists:incentive_deduction_types,id',
            'details.*.name' => 'nullable|string',
            'details.*.type' => 'nullable|in:earning,deduction',
            'details.*.mode' => 'nullable|in:amount,day',
            'details.*.value' => 'required|numeric',
        ]);

        if (empty($validated['details']) && empty($validated['remark'])) {
            // Check if existing record exists to clear it out
            $existing = MonthlyIncentiveEntry::where('employee_id', $validated['employee_id'])
                ->where('month_year', $validated['month_year'])
                ->when(isset($validated['date']), function ($q) use ($validated) {
                    return $q->where('date', $validated['date']);
                })->first();

            if ($existing) {
                $existing->details()->delete();
                $existing->delete();
                return redirect()->route('hr.monthly-incentives.index', ['employee_id' => $validated['employee_id']])
                    ->with('success', 'Earnings/Deductions record cleared.');
            }

            return redirect()->back()->with('error', 'Please add at least one earning/deduction component or a remark before saving.');
        }

        return DB::transaction(function () use ($validated) {
            $entry = MonthlyIncentiveEntry::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'date' => $validated['date'] ?? null,
                    'month_year' => $validated['month_year'],
                ],
                [
                    'remark' => $validated['remark'] ?? null,
                    'branch_id' => auth()->user()->branch_id ?? Employee::find($validated['employee_id'])->branch_id
                ]
            );

            // Re-sync details by deleting old ones first (cleaner for ad-hoc fields)
            $entry->details()->delete();
            if (isset($validated['details'])) {
                foreach ($validated['details'] as $detail) {
                    $entry->details()->create([
                        'type_id' => $detail['type_id'] ?? null,
                        'name' => $detail['name'] ?? null,
                        'type' => $detail['type'] ?? 'earning',
                        'mode' => $detail['mode'] ?? 'amount',
                        'value' => $detail['value'] ?? 0
                    ]);
                }
            }

            return redirect()->route('hr.monthly-incentives.index', ['employee_id' => $validated['employee_id']])
                ->with('success', 'Earnings/Deductions record saved successfully.');
        });
    }

    public function getEmployeeHistory(Request $request, $id)
    {
        $employee = Employee::with(['user', 'department', 'designation'])->find($id);

        if (!$employee) {
            $employee = Employee::with(['user', 'department', 'designation'])
                ->where('user_id', $id)
                ->first();
        }

        if (!$employee) {
            return response()->json(['entries' => []]);
        }

        $entries = MonthlyIncentiveEntry::with('details')
            ->where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->orderBy('month_year', 'desc')
            ->get()
            ->map(function ($entry) {
                $earnings = $entry->details->where('type', 'earning');
                $deductions = $entry->details->where('type', 'deduction');
                return [
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'month_year' => $entry->month_year,
                    'remark' => $entry->remark,
                    'total_earnings' => $earnings->sum('value'),
                    'total_deductions' => $deductions->sum('value'),
                    'details' => $entry->details->map(fn($d) => [
                        'type_id' => $d->type_id,
                        'name' => $d->name,
                        'type' => $d->type,
                        'mode' => $d->mode,
                        'value' => $d->value,
                    ])->values(),
                ];
            });

        return response()->json(['entries' => $entries]);
    }
}
