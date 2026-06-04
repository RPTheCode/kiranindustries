<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Designation;
use App\Models\PayrollRun;
use App\Models\PayrollEntry;
use App\Models\Shift;
use App\Models\Skill;
use App\Services\PayrollScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollRunController extends Controller
{
    protected function payrollFilterOptions(?int $branchId = null): array
    {
        $companyIds = getCompanyAndUsersId();

        $branches = \App\Models\Branch::whereIn('created_by', $companyIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return array_merge(
            ['branches' => $branches],
            $this->scopeFilterOptionsForBranch($branchId)
        );
    }

    /**
     * Master data for payroll scope filters, optionally limited to one branch.
     */
    protected function scopeFilterOptionsForBranch(?int $branchId = null): array
    {
        $companyIds = getCompanyAndUsersId();

        $applyBranch = function ($query) use ($branchId) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return $query;
        };

        return [
            'departments' => $applyBranch(
                Department::whereIn('created_by', $companyIds)->where('status', 'active')
            )->orderBy('name')->get(['id', 'name']),
            'shifts' => $applyBranch(
                Shift::whereIn('created_by', $companyIds)->where('status', 'active')
            )->orderBy('name')->get(['id', 'name']),
            'categories' => $applyBranch(
                Category::whereIn('created_by', $companyIds)->where('status', 'active')
            )->orderBy('name')->get(['id', 'name']),
            'designations' => $applyBranch(
                Designation::whereIn('created_by', $companyIds)->where('status', 'active')
            )->orderBy('name')->get(['id', 'name']),
            'skills' => $applyBranch(
                Skill::whereIn('created_by', $companyIds)->where('status', true)
            )->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function scopeFilterOptions(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|integer',
        ]);

        $branchId = $request->input('branch_id');
        if (empty($branchId) && session('active_branch_id')) {
            $branchId = (int) session('active_branch_id');
        }

        return response()->json($this->scopeFilterOptionsForBranch($branchId ? (int) $branchId : null));
    }

    protected function validateScopeFilters(Request $request): array
    {
        $request->validate([
            'scope_filters' => 'nullable|array',
            'scope_filters.branch_id' => 'nullable|integer',
            'scope_filters.department_ids' => 'nullable|array',
            'scope_filters.department_ids.*' => 'integer',
            'scope_filters.shift_ids' => 'nullable|array',
            'scope_filters.shift_ids.*' => 'integer',
            'scope_filters.category_ids' => 'nullable|array',
            'scope_filters.category_ids.*' => 'integer',
            'scope_filters.designation_ids' => 'nullable|array',
            'scope_filters.designation_ids.*' => 'integer',
            'scope_filters.skill_ids' => 'nullable|array',
            'scope_filters.skill_ids.*' => 'integer',
            'scope_filters.employee_mode' => 'nullable|in:all,selected',
            'scope_filters.selected_employee_ids' => 'nullable|array',
            'scope_filters.selected_employee_ids.*' => 'integer',
        ]);

        $scope = PayrollScopeService::normalizeScopeFilters(array_merge(
            $request->input('scope_filters', []),
            [
                'branch_id' => $request->input('branch_id'),
                'department_id' => $request->input('department_id'),
            ]
        ));

        return $scope;
    }

    public function previewEmployees(Request $request)
    {
        $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'salary_calculation_type' => 'nullable|in:basic_pay,minimum_wages',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'department_ids' => 'nullable|array',
            'shift_ids' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'designation_ids' => 'nullable|array',
            'skill_ids' => 'nullable|array',
            'employee_mode' => 'nullable|in:all,selected',
            'selected_employee_ids' => 'nullable|array',
        ]);

        $scope = PayrollScopeService::normalizeScopeFilters(array_merge(
            $request->only([
                'branch_id',
                'department_id',
                'department_ids',
                'shift_ids',
                'category_ids',
                'designation_ids',
                'skill_ids',
                'employee_mode',
                'selected_employee_ids',
            ]),
            $request->input('scope_filters', [])
        ));

        if (empty($scope['branch_id'])) {
            $scope['branch_id'] = session('active_branch_id') ? (int) session('active_branch_id') : null;
        }

        $preview = PayrollScopeService::previewEmployees(
            $scope,
            $request->pay_period_start,
            $request->pay_period_end,
            $request->input('salary_calculation_type', 'basic_pay'),
            $scope['branch_id']
        );

        return response()->json($preview);
    }

    public function checkOverlapping(Request $request)
    {
        $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'branch_id' => 'nullable|integer',
            'exclude_run_id' => 'nullable|integer',
        ]);

        $branchId = $request->input('branch_id') ?: (session('active_branch_id') ? (int) session('active_branch_id') : null);

        $runs = PayrollScopeService::findOverlappingRuns(
            $request->pay_period_start,
            $request->pay_period_end,
            $branchId,
            $request->input('exclude_run_id') ? (int) $request->exclude_run_id : null
        );

        return response()->json([
            'overlapping' => $runs->map(function ($run) {
                $days = \Carbon\Carbon::parse($run->pay_period_start)
                    ->diffInDays(\Carbon\Carbon::parse($run->pay_period_end)) + 1;

                return [
                    'id' => $run->id,
                    'title' => $run->title,
                    'pay_period_start' => $run->pay_period_start,
                    'pay_period_end' => $run->pay_period_end,
                    'pay_date' => $run->pay_date,
                    'status' => $run->status,
                    'period_days' => $days,
                    'scope_summary' => PayrollScopeService::scopeSummaryShort(
                        PayrollScopeService::resolvedScope($run)
                    ),
                ];
            })->values(),
        ]);
    }

    public function index(Request $request)
    {
        $query = PayrollRun::withPermissionCheck()
            ->with(['creator']);

        // Check active branch ID from session
        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->where('branch_id', $activeBranchId);
        }

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('notes', 'like', '%' . $request->search . '%');
            });
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('pay_period_start', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('pay_period_end', '<=', $request->date_to);
        }

        if ($request->filled('month_year') && preg_match('/^\d{4}-\d{2}$/', $request->month_year)) {
            [$year, $month] = explode('-', $request->month_year);
            $monthStart = \Carbon\Carbon::create((int) $year, (int) $month, 1)->startOfMonth()->toDateString();
            $monthEnd = \Carbon\Carbon::create((int) $year, (int) $month, 1)->endOfMonth()->toDateString();
            $query->where('pay_period_start', '<=', $monthEnd)
                ->where('pay_period_end', '>=', $monthStart);
        }

        if ($request->filled('filter_branch_id')) {
            $query->where('branch_id', (int) $request->filter_branch_id);
        }

        if ($request->filled('filter_department_id')) {
            $query->where('department_id', (int) $request->filter_department_id);
        }

        if ($request->filled('salary_calculation_type') && in_array($request->salary_calculation_type, ['basic_pay', 'minimum_wages'], true)) {
            $query->where('salary_calculation_type', $request->salary_calculation_type);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('pay_period_start', 'desc');
        }

        $payrollRuns = $query->paginate($request->per_page ?? 10);

        // Batch-load held salary stats for the current page
        $runIds = $payrollRuns->pluck('id');
        if ($runIds->isNotEmpty()) {
            $heldStats = \App\Models\Payslip::join('payroll_entries', 'payslips.payroll_entry_id', '=', 'payroll_entries.id')
                ->whereIn('payroll_entries.payroll_run_id', $runIds)
                ->where('payslips.salary_status', 'hold')
                ->groupBy('payroll_entries.payroll_run_id')
                ->selectRaw('payroll_entries.payroll_run_id, COUNT(*) as held_count, SUM(payroll_entries.net_pay) as held_amount')
                ->get()
                ->keyBy('payroll_run_id');

            $payrollRuns->getCollection()->transform(function ($run) use ($heldStats) {
                $stat = $heldStats->get($run->id);
                $run->held_count = $stat->held_count ?? 0;
                $run->held_amount = $stat->held_amount ?? 0;

                // Detailed employee stats for the table
                $run->processed_count = $run->payrollEntries()->count();
                $run->eligible_count = $run->getEmployeesQuery()->count();

                // Mispunch count (unique employees)
                $mispunches = $run->getMispunches();
                $run->mispunch_count = $mispunches->pluck('employee_code')->unique()->count();
                $run->period_days = \Carbon\Carbon::parse($run->pay_period_start)
                    ->diffInDays(\Carbon\Carbon::parse($run->pay_period_end)) + 1;
                $run->scope_summary = PayrollScopeService::scopeSummaryShort(
                    PayrollScopeService::resolvedScope($run)
                );

                return $run;
            });
        }

        $activeBranchId = session('active_branch_id') ? (int) session('active_branch_id') : null;
        $filterOptions = $this->payrollFilterOptions($activeBranchId);

        return Inertia::render('hr/payroll-runs/index', [
            'payrollRuns' => $payrollRuns,
            'filters' => $request->all([
                'search',
                'status',
                'date_from',
                'date_to',
                'month_year',
                'filter_branch_id',
                'filter_department_id',
                'salary_calculation_type',
                'sort_field',
                'sort_direction',
                'per_page',
            ]),
            'branches' => $filterOptions['branches'],
            'departments' => $filterOptions['departments'],
            'shifts' => $filterOptions['shifts'],
            'categories' => $filterOptions['categories'],
            'designations' => $filterOptions['designations'],
            'skills' => $filterOptions['skills'],
            'activeBranchId' => session('active_branch_id'),
        ]);

    }

    public function show($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->with([
                'payrollEntries.employee' => function ($query) {
                    $query->with([
                        'employeeAdvances',
                        'employee' => function ($q) {
                            $q->withoutGlobalScopes()->with('branch');
                        }
                    ]);
                },
                'payrollEntries.payslip'
            ])
            ->first();

        if (!$payrollRun) {
            return redirect()->back()->with('error', __('Payroll run not found.'));
        }

        // Held salary summary
        $heldPayslips = \App\Models\Payslip::whereIn('payroll_entry_id', $payrollRun->payrollEntries->pluck('id'))
            ->where('salary_status', 'hold')
            ->get();

        $heldCount = $heldPayslips->count();
        $heldAmount = $heldPayslips->sum(function ($payslip) {
            return $payslip->payrollEntry->net_pay ?? 0;
        });

        return Inertia::render('hr/payroll-runs/show', [
            'payrollRun' => $payrollRun,
            'heldCount' => $heldCount,
            'heldAmount' => $heldAmount,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'payroll_frequency' => 'required|in:weekly,biweekly,monthly',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'pay_date' => 'required|date|after_or_equal:pay_period_end',
            'notes' => 'nullable|string',
            'salary_calculation_type' => 'nullable|in:basic_pay,minimum_wages',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'scope_filters' => 'nullable|array',
        ]);

        if (empty($validated['salary_calculation_type'])) {
            $validated['salary_calculation_type'] = 'basic_pay';
        }

        $scope = $this->validateScopeFilters($request);
        if (empty($scope['branch_id'])) {
            $scope['branch_id'] = session('active_branch_id') ? (int) session('active_branch_id') : null;
        }
        PayrollScopeService::syncRunColumnsFromScope($validated, $scope);

        $validated['created_by'] = creatorId();
        $validated['status'] = 'draft';

        $duplicate = PayrollRun::where('pay_period_start', $validated['pay_period_start'])
            ->where('pay_period_end', $validated['pay_period_end'])
            ->where('salary_calculation_type', $validated['salary_calculation_type'])
            ->where('scope_filters_hash', $validated['scope_filters_hash'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->exists();

        if ($duplicate) {
            return redirect()->back()->with('error', __('Payroll run already exists for this period with same scope.'));
        }

        $run = PayrollRun::create($validated);

        $overlap = PayrollScopeService::findOverlappingRuns(
            $validated['pay_period_start'],
            $validated['pay_period_end'],
            $scope['branch_id'] ?? null,
            $run->id
        );

        if ($overlap->isNotEmpty()) {
            return redirect()->back()->with([
                'success' => __('Payroll run created successfully.'),
                'warning' => __('Another payroll run overlaps this period. Each run generates its own payslips — an employee processed in both runs will receive two payslips.'),
            ]);
        }

        return redirect()->back()->with('success', __('Payroll run created successfully.'));
    }

    public function update(Request $request, $payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($payrollRun) {
            try {
                $validated = $request->validate([
                    'title' => 'required|string|max:255',
                    'payroll_frequency' => 'required|in:weekly,biweekly,monthly',
                    'pay_period_start' => 'required|date',
                    'pay_period_end' => 'required|date|after:pay_period_start',
                    'pay_date' => 'required|date|after_or_equal:pay_period_end',
                    'notes' => 'nullable|string',
                    'salary_calculation_type' => 'nullable|in:basic_pay,minimum_wages',
                    'branch_id' => 'nullable|integer',
                    'department_id' => 'nullable|integer',
                    'scope_filters' => 'nullable|array',
                ]);

                if (empty($validated['salary_calculation_type'])) {
                    $validated['salary_calculation_type'] = 'basic_pay';
                }

                // Only allow updates if status is draft
                if ($payrollRun->status !== 'draft') {
                    return redirect()->back()->with('error', __('Cannot update processed payroll run.'));
                }

                $scope = $this->validateScopeFilters($request);
                PayrollScopeService::syncRunColumnsFromScope($validated, $scope);

                $payrollRun->update($validated);

                return redirect()->back()->with('success', __('Payroll run updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update payroll run'));
            }
        } else {
            return redirect()->back()->with('error', __('Payroll run Not Found.'));
        }
    }

    public function destroy($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($payrollRun) {
            try {
                // Only allow deletion if status is draft
                if ($payrollRun->status !== 'draft') {
                    return redirect()->back()->with('error', __('Cannot delete processed payroll run. Regenerate the run first to move it back to draft.'));
                }

                \Illuminate\Support\Facades\DB::transaction(function () use ($payrollRun) {
                    $payrollRun->purgeAllPayrollEntries();
                    $payrollRun->delete();
                });

                return redirect()->back()->with('success', __('Payroll run deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete payroll run'));
            }
        } else {
            return redirect()->back()->with('error', __('Payroll run Not Found.'));
        }
    }

    public function process($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($payrollRun) {
            try {
                if ($payrollRun->status !== 'draft') {
                    return redirect()->back()->with('error', __('Payroll run is not in draft status.'));
                }

                // Check for mispunches first
                $mispunches = $payrollRun->getMispunches();
                if ($mispunches->isNotEmpty()) {
                    return redirect()->back()->with('error', __('Cannot process payroll. Mispunch entries found. Please clear them first.'));
                }

                $success = $payrollRun->processPayroll();

                if ($success) {
                    // Auto-generate payslips
                    \App\Http\Controllers\PayslipController::bulkGenerateForPayrollRun($payrollRun);

                    return redirect()->back()->with('success', __('Payroll run processed successfully'));
                } else {
                    return redirect()->back()->with('error', __('Failed to process payroll run'));
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to process payroll run'));
            }
        } else {
            return redirect()->back()->with('error', __('Payroll run Not Found.'));
        }
    }

    public function checkMispunches($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return response()->json(['error' => 'Payroll run not found'], 404);
        }

        $mispunches = $payrollRun->getMispunches();

        return response()->json([
            'count' => $mispunches->count(),
            'mispunches' => $mispunches->map(function ($m) {
                return [
                    'date' => $m->attendance_date->format('Y-m-d'),
                    'employee_name' => $m->employee?->user?->name ?? 'Unknown',
                    'employee_code' => $m->employee_code,
                    'in_time' => $m->in_time ? $m->in_time->format('H:i') : '-',
                    'out_time' => $m->out_time ? $m->out_time->format('H:i') : '-',
                ];
            })
        ]);
    }

    public function mispunchReport($payrollRunId)
    {
        $payrollRun = PayrollRun::findOrFail($payrollRunId);

        $mispunches = $payrollRun->getMispunches();

        $records = $mispunches->map(function ($record) {
            return [
                'employee_code' => $record->employee_code,
                'name' => $record->employee?->user?->name ?? 'N/A',
                'department' => $record->employee?->department?->name ?? 'Unassigned',
                'date' => $record->attendance_date ? $record->attendance_date->format('d-m-Y') : '-',
                'shift' => $record->shift?->name ?? '-',
                'doj' => $record->employee?->join_date ? \Carbon\Carbon::parse($record->employee->join_date)->format('d-m-Y') : '-',
                'in_time' => $record->in_time ? $record->in_time->format('H:i') : '---',
                'out_time' => $record->out_time ? $record->out_time->format('H:i') : '---',
                'work_hours' => $record->total_work_hours ?? '-',
                'overtime' => $record->overtime_hours ?? '-',
                'late_in' => $record->late_in_minutes ?? '-',
                'early_out' => $record->early_out_minutes ?? '-',
                'mis_punch' => 'MISP',
            ];
        });

        $groupedRecords = $records->groupBy('department')->sortBy(function ($items, $key) {
            return $key;
        });

        $period = \Carbon\Carbon::parse($payrollRun->start_date)->format('d M') . ' - ' . \Carbon\Carbon::parse($payrollRun->end_date)->format('d M, Y');
        $reportTitle = 'Mispunch Report - ' . $period;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.mispunch_dedicated', [
            'reportData' => $groupedRecords,
            'period' => $period,
            'reportTitle' => $reportTitle,
            'companyName' => config('app.name', 'COMPANY NAME')
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('mispunch_exceptions_' . strtolower(str_replace(' ', '_', $payrollRun->month_year)) . '.pdf');
    }

    public function initiateProcess($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return response()->json(['error' => 'Payroll run not found'], 404);
        }

        if ($payrollRun->status !== 'draft' && $payrollRun->status !== 'processing' && $payrollRun->status !== 'in_review') {
            return response()->json(['error' => 'Payroll run is not in a processable status'], 400);
        }

        $preview = $payrollRun->previewEmployeeScope();

        $mispunches = $payrollRun->getMispunches();

        return response()->json([
            'valid_employee_ids' => $preview['valid_employee_ids'],
            'ready_employees' => $preview['ready_employees'],
            'mispunch_employees' => array_map(function ($row) {
                return [
                    'employee_name' => $row['name'],
                    'employee_code' => $row['code'],
                    'date' => '-',
                    'in_time' => '-',
                    'out_time' => '-',
                ];
            }, $preview['mispunch_employees']),
            'skipped_employees' => $preview['skipped_employees'],
            'counts' => $preview['counts'],
            'total_count' => $preview['counts']['eligible'],
            'valid_count' => $preview['counts']['ready'],
            'mispunch_count' => $preview['counts']['mispunch'],
            'skipped_count' => $preview['counts']['no_salary'] + $preview['counts']['no_branch'],
        ]);
    }

    public function processBatch(Request $request, $payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return response()->json(['error' => 'Payroll run not found'], 404);
        }

        $employeeIds = array_values(array_filter(array_map('intval', $request->input('employee_ids', []))));

        if (empty($employeeIds)) {
            return response()->json(['error' => 'No employees selected'], 422);
        }

        try {
            $success = $payrollRun->processBatch($employeeIds);
            if (!$success) {
                return response()->json(['error' => 'No employees in payroll scope or run cannot be processed'], 422);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function finalize($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return response()->json(['error' => 'Payroll run not found'], 404);
        }

        try {
            // Safety: Purge any entries that might have been created for mispunch employees
            $mispunches = $payrollRun->getMispunches();
            $mispunchCodes = $mispunches->pluck('employee_code')->unique()->toArray();

            if (!empty($mispunchCodes)) {
                $entriesToDelete = $payrollRun->payrollEntries()
                    ->whereHas('employee.employee', function ($q) use ($mispunchCodes) {
                        $q->whereIn('emy_code', $mispunchCodes);
                    })->get();

                foreach ($entriesToDelete as $entry) {
                    $entry->payslips()->delete();
                    $entry->delete();
                }
            }

            $payrollRun->calculateTotals();
            $payrollRun->status = 'in_review';
            $payrollRun->save();

            // Auto-generate payslips
            \App\Http\Controllers\PayslipController::bulkGenerateForPayrollRun($payrollRun);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function confirm(Request $request, $payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->with('payrollEntries.payslip')
            ->first();

        if (!$payrollRun) {
            return redirect()->back()->with('error', __('Payroll run Not Found.'));
        }

        if ($payrollRun->status !== 'in_review') {
            return redirect()->back()->with('error', __('Payroll run must be in review to confirm.'));
        }

        $releaseMode = $request->input('release_mode', 'all'); // 'all' or 'non_hold'
        $now = Carbon::now();

        foreach ($payrollRun->payrollEntries as $entry) {
            $payslip = $entry->payslip;
            if (!$payslip) {
                continue;
            }

            if ($releaseMode === 'all' || ($releaseMode === 'non_hold' && $payslip->salary_status !== 'hold')) {
                // If it was on hold, pay_date will be null. Set it to now (release/confirm date).
                // If it was already released (never held), pay_date is already set to the default. Leave it.
                if ($payslip->pay_date === null) {
                    $payslip->pay_date = $now;
                }

                $payslip->salary_status = 'released';
                $payslip->released_at = $payslip->released_at ?? $now;
                $payslip->save();
                $payslip->generatePDF();
            }
        }

        $payrollRun->status = 'completed';
        $payrollRun->save();

        return redirect()->back()->with('success', __('Payroll run confirmed successfully.'));
    }

    public function regenerateEmployee(Request $request, $payrollRunId)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
        ]);

        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return redirect()->back()->with('error', __('Payroll run Not Found.'));
        }

        if (!in_array($payrollRun->status, ['draft', 'processing', 'in_review', 'completed'], true)) {
            return redirect()->back()->with('error', __('Payroll run cannot be updated in this status.'));
        }

        try {
            if (!$payrollRun->regenerateEmployeePayroll((int) $validated['employee_id'])) {
                return redirect()->back()->with('error', __('Employee is not in this payroll scope or could not be recalculated.'));
            }

            return redirect()->back()->with('success', __('Employee payroll recalculated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to regenerate employee payroll'));
        }
    }

    public function regenerate($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return redirect()->back()->with('error', __('Payroll run Not Found.'));
        }

        if (!in_array($payrollRun->status, ['in_review', 'completed'], true)) {
            return redirect()->back()->with('error', __('Payroll run must be in review or completed to regenerate.'));
        }

        try {
            if (!$payrollRun->revertPayroll()) {
                return redirect()->back()->with('error', __('Failed to reset payroll run for regeneration.'));
            }

            return redirect()->back()->with(
                'success',
                __('Payroll run reset. Re-process employees to recalculate salaries and payslips.')
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to regenerate payroll run'));
        }
    }

    public function exportAdvances($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return redirect()->back()->with('error', __('Payroll run not found.'));
        }

        $branchId = $payrollRun->branch_id ?? 'all';
        $fromDate = $payrollRun->pay_period_start;
        $toDate = $payrollRun->pay_period_end;

        $filename = 'employee_advances';
        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $branchName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $branch->name);
                $filename = $branchName;
            }
        }

        $fromDateStr = \Carbon\Carbon::parse($fromDate)->format('Y-m-d');
        $toDateStr = \Carbon\Carbon::parse($toDate)->format('Y-m-d');

        $filename .= '_' . $fromDateStr . '_to_' . $toDateStr . '_Advances_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\EmployeeAdvancesExport(null, $fromDate, $toDate, $branchId),
            $filename
        );
    }

    public function exportSalaryRegister($payrollRunId)
    {
        $payrollRun = PayrollRun::where('id', $payrollRunId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payrollRun) {
            return redirect()->back()->with('error', __('Payroll run not found.'));
        }

        $payPeriod = $payrollRun->pay_period_start->format('M_Y');
        $filename = 'Salary_Register_' . $payPeriod . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        try {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SalaryRegisterExport($payrollRunId),
                $filename
            );
        } catch (\Throwable $e) {
            \Log::error('Salary Register Export Failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Export Failed: ' . $e->getMessage());
        }
    }

    public function exportSummary(Request $request)
    {
        $dateFrom = $request->date_from ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->date_to ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        $format = $request->input('format', 'excel');

        try {
            if ($format === 'pdf') {
                $filename = 'All_Branch_Payroll_Summary_' . $dateFrom . '_to_' . $dateTo . '.pdf';

                // Fetch the data just like the Excel export does
                $user = auth()->user();
                $allowedBranchIds = $user ? $user->branches()->pluck('branches.id')->toArray() : [];

                if (session('active_branch_id')) {
                    $allowedBranchIds = array_intersect($allowedBranchIds, [session('active_branch_id')]);
                }

                $summary = \Illuminate\Support\Facades\DB::table('branches')
                    ->whereIn('branches.id', $allowedBranchIds)
                    ->leftJoin('payroll_runs', function ($join) use ($dateFrom, $dateTo) {
                        $join->on('branches.id', '=', 'payroll_runs.branch_id')
                            ->whereIn('payroll_runs.created_by', getCompanyAndUsersId())
                            ->where('payroll_runs.salary_calculation_type', 'basic_pay')
                            ->where('payroll_runs.pay_period_start', '>=', $dateFrom)
                            ->where('payroll_runs.pay_period_end', '<=', $dateTo);
                    })
                    ->leftJoin('payroll_entries', 'payroll_runs.id', '=', 'payroll_entries.payroll_run_id')
                    ->select(
                        'branches.id as branch_id',
                        'branches.name as branch_name',
                        \Illuminate\Support\Facades\DB::raw("DATE_FORMAT(payroll_runs.pay_period_start, '%Y-%m') as pay_month"),
                        \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT payroll_entries.employee_id) as employee_count'),
                        \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(payroll_entries.present_days + payroll_entries.week_off_present_days + (payroll_entries.half_days / 2)), 0) as total_working_days'),
                        \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(payroll_entries.overtime_hours), 0) as total_ot_hours'),
                        \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(payroll_entries.gross_pay), 0) as total_gross_pay'),
                        \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(payroll_entries.total_deductions), 0) as total_deductions'),
                        \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(payroll_entries.net_pay), 0) as total_net_pay')
                    )
                    ->groupBy('branches.id', 'branches.name', 'pay_month')
                    ->orderBy('branches.name')
                    ->orderBy('pay_month')
                    ->get();

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.monthly_summary_pdf', [
                    'summary' => $summary,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'companyName' => config('app.name', 'Kiran Industries')
                ])->setPaper('A4', 'portrait');

                return $pdf->download($filename);
            }

            // Default: Excel
            $filename = 'All_Branch_Payroll_Summary_' . $dateFrom . '_to_' . $dateTo . '.xlsx';
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\AllBranchPayrollExport($dateFrom, $dateTo),
                $filename
            );
        } catch (\Throwable $e) {
            \Log::error('Payroll Summary Export Failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Export Failed: ' . $e->getMessage());
        }
    }
}
