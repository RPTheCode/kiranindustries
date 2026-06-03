<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Imports\EmployeeAdvanceImport;
use App\Exports\EmployeeAdvanceTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Exception;

class EmployeeAdvanceController extends Controller
{
    public function index(Request $request)
    {
        // 1. Base Query: Employees who have advances created by the current context
        // Use withoutGlobalScopes() to ensure we can fetch employees from OTHER branches
        $query = User::withoutGlobalScopes()
            ->where('type', 'employee')
            ->whereHas('employeeAdvances', function ($q) use ($request) {
                $q->whereIn('created_by', getCompanyAndUsersId());

                // Apply Date Filters to the existence check
                if ($request->has('from_date') && !empty($request->from_date)) {
                    $q->whereDate('pay_date', '>=', $request->from_date);
                }
                if ($request->has('to_date') && !empty($request->to_date)) {
                    $q->whereDate('pay_date', '<=', $request->to_date);
                }

                // Apply Branch Filter (Explicit Branch Tracking)
                $activeBranchId = session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    // Filter by the branch where the advance was created (branch_id column)
                    // Fallback to Creator's branch or Employee's branch if branch_id is null (though migration populated it)
                    $q->where(function ($subQ) use ($activeBranchId) {
                        $subQ->where('branch_id', $activeBranchId)
                            ->orWhere(function ($fallbackQ) use ($activeBranchId) {
                                $fallbackQ->whereNull('branch_id')
                                    ->whereHas('creator.employee', function ($branchQ) use ($activeBranchId) {
                                        $branchQ->where('branch_id', $activeBranchId);
                                    });
                            });
                    });
                }
            });

        // 2. Search Filter (by Employee Name/Email)
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        // 3. Branch Filter (Removed implicit employee branch check)

        // 4. Eager Load Relations
        $employeesWithAdvances = $query->with([
            'employee' => function ($q) {
                $q->withoutGlobalScopes()->with('branch');
            },
            'employeeAdvances' => function ($q) use ($request) {
                $q->whereIn('created_by', getCompanyAndUsersId())
                    ->orderBy('pay_date', 'desc');

                // Apply Date Filters
                if ($request->has('from_date') && !empty($request->from_date)) {
                    $q->whereDate('pay_date', '>=', $request->from_date);
                }
                if ($request->has('to_date') && !empty($request->to_date)) {
                    $q->whereDate('pay_date', '<=', $request->to_date);
                }

                // Apply Branch Filter (Explicit Branch Tracking)
                $activeBranchId = session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $q->where(function ($subQ) use ($activeBranchId) {
                        $subQ->where('branch_id', $activeBranchId)
                            ->orWhere(function ($fallbackQ) use ($activeBranchId) {
                                $fallbackQ->whereNull('branch_id')
                                    ->whereHas('creator.employee', function ($branchQ) use ($activeBranchId) {
                                        $branchQ->where('branch_id', $activeBranchId);
                                    });
                            });
                    });
                }
            }
        ])
            ->paginate(10);

        // 5. Get List for Create Modal (All Employees)
        $employees = User::withPermissionCheck()
            ->where('type', 'employee')
            ->with([
                'employee' => function ($query) {
                    $query->withoutGlobalScopes()->select('user_id', 'employee_id');
                }
            ])
            ->get(['id', 'name'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name . ' - ' . ($user->employee->employee_id ?? ''),
                ];
            });

        return Inertia::render('hr/employee-advances/index', [
            'advances' => $employeesWithAdvances, // Renamed but kept key for compatibility or change key in frontend
            'filters' => $request->all(['search', 'from_date', 'to_date']),
            'employees' => $employees,
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->query('search');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $activeBranchId = session('active_branch_id');

        $filename = 'employee_advances';
        if ($activeBranchId && $activeBranchId !== 'all') {
            $branch = \App\Models\Branch::find($activeBranchId);
            if ($branch) {
                // Sanitize branch name for filename
                $branchName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $branch->name);
                $filename = $branchName;
            }
        }

        // Append date and time: BranchMatches_YYYY-MM-DD_His.xlsx
        $filename .= '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmployeeAdvancesExport($search, $fromDate, $toDate, $activeBranchId), $filename);
    }

    public function create()
    {
        // Get employees to select from - All employees across branches
        $employees = User::withPermissionCheck()
            ->where('type', 'employee')
            ->with([
                'employee' => function ($query) {
                    $query->withoutGlobalScopes()->select('user_id', 'employee_id');
                }
            ])
            ->get(['id', 'name'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name . ' - ' . ($user->employee->employee_id ?? ''),
                ];
            });

        return Inertia::render('hr/employee-advances/create', [
            'employees' => $employees,
            'employeeId' => request('employee_id'), // Optional pre-fill
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'pay_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        $validated['created_by'] = creatorId();
        $validated['status'] = 'paid';

        $activeBranchId = session('active_branch_id');
        if ($activeBranchId && $activeBranchId !== 'all') {
            $validated['branch_id'] = $activeBranchId;
        } else {
            // If 'all' or no branch selected (e.g. superadmin), try to get from user's own branch if applicable
            // or leave null. The user requested "jis branch me add hua", implying the context. 
            // If context is 'All', it's ambiguous, but usually admins have a context. 
            // We'll leave it null if context is 'all'.
        }

        EmployeeAdvance::create($validated);

        return redirect()->route('hr.employee-advances.index')->with('success', __('Employee advance created successfully.'));
    }

    public function edit(EmployeeAdvance $employeeAdvance)
    {
        if ($employeeAdvance->created_by != creatorId() && $employeeAdvance->created_by != Auth::user()->created_by) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $employees = User::withPermissionCheck()
            ->where('type', 'employee')
            ->with([
                'employee' => function ($query) {
                    $query->withoutGlobalScopes()->select('user_id', 'employee_id');
                }
            ])
            ->get(['id', 'name'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name . ' - ' . ($user->employee->employee_id ?? ''),
                ];
            });

        return Inertia::render('hr/employee-advances/edit', [
            'advance' => $employeeAdvance,
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, EmployeeAdvance $employeeAdvance)
    {
        if ($employeeAdvance->created_by != creatorId() && $employeeAdvance->created_by != Auth::user()->created_by) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'pay_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        $employeeAdvance->update($validated);

        return redirect()->back()->with('success', __('Employee advance updated successfully.'));
    }

    public function destroy(EmployeeAdvance $employeeAdvance)
    {
        if ($employeeAdvance->created_by != creatorId() && $employeeAdvance->created_by != Auth::user()->created_by) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $employeeAdvance->delete();

        return redirect()->back()->with('success', __('Employee advance deleted successfully.'));
    }

    /**
     * Import employee advances from Excel/CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,excel,csv,txt',
        ]);

        try {
            $import = new EmployeeAdvanceImport;
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();
            $savedCount = $import->rowsSaved;
            $failedCount = $failures->count();

            if ($failedCount > 0) {
                $msg = '<div class="space-y-1 text-sm">';
                $msg .= '<div class="font-bold text-gray-800 border-b pb-1 mb-2">Import Summary: ' . $savedCount . ' saved, ' . $failedCount . ' failed</div>';
                
                
                
                $msg .= '<div class="text-red-500 mt-2 font-semibold">✘ Failures:</div>';
                $msg .= '<ul class="list-disc pl-5 text-red-500 text-xs space-y-0.5">';
                foreach ($failures as $failure) {
                    $msg .= '<li>Row ' . $failure->row() . ': ' . implode(', ', $failure->errors()) . '</li>';
                }
                $msg .= '</ul>';
                $msg .= '</div>';

                return redirect()->back()->with('error', $msg);
            }

            return redirect()->back()->with('success', __('Employee advances imported successfully.'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('Error importing file:') . ' ' . $e->getMessage());
        }
    }

    /**
     * Download the template for importing employee advances.
     */
    public function importTemplate()
    {
        return Excel::download(new EmployeeAdvanceTemplateExport, 'employee_advances_template.xlsx');
    }
}
