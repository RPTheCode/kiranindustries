<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\User;
use App\Services\SalaryPayroll\BranchPayrollSettingsService;
use App\Services\SalaryPayroll\EmployeeSalaryRevisionService;
use App\Services\SalaryPayroll\SalaryComponentAssignmentService;
use App\Services\SalaryPayroll\SalaryStructureCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalaryPayrollEmployeeSalaryController extends Controller
{
    public function __construct(
        private SalaryStructureCalculator $calculator,
        private EmployeeSalaryRevisionService $revisionService,
        private SalaryComponentAssignmentService $componentAssignment,
        private BranchPayrollSettingsService $branchPayrollSettings
    ) {}

    public function index(Request $request)
    {
        $branchId = session('active_branch_id');
        $branchName = $branchId ? \App\Models\Branch::find($branchId)?->name : null;

        $query = User::query()
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereHas('employee', function ($q) use ($branchId, $request) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
                if ($request->filled('category_id') && $request->category_id !== 'all') {
                    $q->where('category_id', $request->category_id);
                }
                if ($request->filled('department_id') && $request->department_id !== 'all') {
                    $q->where('department_id', $request->department_id);
                }
                if ($request->filled('shift_id') && $request->shift_id !== 'all') {
                    $q->where('shift_id', $request->shift_id);
                }
            })
            ->with([
                'employee.department:id,name',
                'employee.category:id,name',
                'employee.shift:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq->where('employee_id', 'like', "%{$search}%"));
            });
        }

        $employees = $query
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        $userIds = $employees->getCollection()->pluck('id');
        $salaries = EmployeeSalary::whereIn('employee_id', $userIds)
            ->get()
            ->keyBy('employee_id');

        $employees->getCollection()->transform(function ($user) use ($salaries) {
            $salary = $salaries->get($user->id);
            $emp = $user->employee;
            $user->salary_record = $salary ? [
                'id' => $salary->id,
                'monthly_gross' => (float) ($salary->monthly_gross ?? $salary->basic_salary ?? 0),
                'gross_input_mode' => $this->grossInputModeForSalary($salary),
                'per_day_salary' => (float) ($salary->per_day_salary ?? 0),
                'gross_display_amount' => $this->grossDisplayAmount($salary, $emp, $emp?->branch_id),
                'net_salary' => $this->netFromRecord($salary),
                'is_active' => (bool) $salary->is_active,
            ] : null;
            $user->pf_applicable = (bool) ($emp?->pf_flag ?? false);
            $user->esi_applicable = (bool) ($emp?->esic_flag ?? false);
            $user->extra_salary_component_ids = $emp?->extra_salary_component_ids ?? [];

            return $user;
        });

        $salaryComponents = $this->branchSalaryComponents($branchId);
        $primaryComponents = $this->componentAssignment->primaryComponents($salaryComponents);
        $customComponents = $this->componentAssignment->customComponents($salaryComponents);

        $branchPayrollSettings = $branchId
            ? $this->branchPayrollSettings->resolve((int) $branchId)
            : null;

        return Inertia::render('hr/salary-payroll/employee-salaries/index', [
            'employees' => $employees,
            'salaryComponents' => $salaryComponents,
            'primaryComponents' => $primaryComponents,
            'customComponents' => $customComponents,
            'categories' => $this->branchCategories($branchId),
            'departments' => $this->branchDepartments($branchId),
            'shifts' => $this->branchShifts($branchId),
            'activeBranchId' => $branchId,
            'activeBranchName' => $branchName,
            'branchPayrollSettings' => $branchPayrollSettings,
            'filters' => $request->all(['search', 'category_id', 'department_id', 'shift_id', 'per_page']),
            'defaultEffectiveFrom' => now()->toDateString(),
        ]);
    }

    public function history(Request $request, $employeeId)
    {
        $employee = User::with('employee')->where('id', $employeeId)
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->firstOrFail();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee?->employee_id,
                'date_of_joining' => $employee->employee?->date_of_joining
                    ? Carbon::parse($employee->employee->date_of_joining)->format('d M Y')
                    : null,
            ],
            'history' => $this->revisionService->historyForEmployee((int) $employeeId),
        ]);
    }

    public function increment(Request $request, $employeeId)
    {
        $branchId = session('active_branch_id');

        $validated = $request->validate([
            'increment_mode' => 'required|in:percentage,fixed,set_gross',
            'increment_value' => 'required|numeric|min:0.01',
            'effective_from' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        $employee = User::with('employee')->where('id', $employeeId)
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->firstOrFail();

        if ($branchId && $employee->employee?->branch_id != $branchId) {
            return redirect()->back()->with('error', __('Employee does not belong to the active branch.'));
        }

        $components = $this->branchSalaryComponents($branchId);
        if ($components->isEmpty()) {
            return redirect()->back()->with('error', __('No active salary components for this branch.'));
        }

        try {
            $this->revisionService->validateEffectiveFrom(
                (int) $employeeId,
                Carbon::parse($validated['effective_from'])
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $existing = EmployeeSalary::where('employee_id', $employeeId)->first();
        $currentGross = (float) ($existing?->monthly_gross ?? $existing?->basic_salary ?? 0);

        if ($currentGross <= 0 && $validated['increment_mode'] !== 'set_gross') {
            return redirect()->back()->with('error', __('Employee has no existing salary. Set gross salary first.'));
        }

        $newGross = $this->revisionService->calculateNewGross(
            $currentGross,
            $validated['increment_mode'],
            (float) $validated['increment_value']
        );

        if ($newGross <= 0) {
            return redirect()->back()->with('error', __('Invalid new salary amount.'));
        }

        $split = $this->calculator->splitFromGross(
            $newGross,
            $this->componentsForEmployeeId($components, (int) $employeeId),
            $this->statutoryOptionsForEmployee((int) $employeeId)
        );

        $incrementPct = $validated['increment_mode'] === 'percentage'
            ? (float) $validated['increment_value']
            : ($currentGross > 0 ? round((($newGross - $currentGross) / $currentGross) * 100, 2) : null);

        $this->revisionService->applySalary((int) $employeeId, $split, [
            'revision_type' => 'increment',
            'effective_from' => $validated['effective_from'],
            'notes' => $validated['notes'] ?? __('Salary increment'),
            'previous_gross' => $currentGross > 0 ? $currentGross : null,
            'increment_percentage' => $incrementPct,
            'increment_amount' => $currentGross > 0 ? round($newGross - $currentGross, 2) : null,
        ], $existing?->id);

        return redirect()->back()->with('success', __('Salary increment saved successfully.'));
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'monthly_gross' => 'nullable|numeric|min:0',
            'gross_amount' => 'nullable|numeric|min:0',
            'gross_input_mode' => 'nullable|in:day,month',
            'working_days' => 'nullable|integer|min:1|max:31',
            'employee_id' => 'nullable|exists:users,id',
        ]);

        $branchId = session('active_branch_id');
        $components = $this->branchSalaryComponents($branchId);

        if ($components->isEmpty()) {
            return response()->json(['error' => __('No active salary components for this branch.')], 422);
        }

        $workingDays = $this->workingDaysForEmployee($validated['employee_id'] ?? null, $validated['working_days'] ?? null);
        $grossInputMode = $validated['gross_input_mode']
            ?? $this->grossInputModeForEmployee($validated['employee_id'] ?? null);
        $monthlyGross = $this->resolveMonthlyGross(
            (float) ($validated['gross_amount'] ?? $validated['monthly_gross'] ?? 0),
            $grossInputMode,
            $workingDays
        );

        return response()->json(
            $this->calculator->splitFromGross(
                $monthlyGross,
                $this->componentsForEmployeeId($components, $validated['employee_id'] ?? null),
                $this->statutoryOptionsForEmployee($validated['employee_id'] ?? null)
            )
        );
    }

    public function updateComponents(Request $request, $employeeId)
    {
        $branchId = session('active_branch_id');
        $employee = User::with('employee')->where('id', $employeeId)
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->firstOrFail();

        if ($branchId && $employee->employee?->branch_id != $branchId) {
            return redirect()->back()->with('error', __('Employee does not belong to the active branch.'));
        }

        $validated = $request->validate([
            'extra_salary_component_ids' => 'nullable|array',
            'extra_salary_component_ids.*' => 'integer',
        ]);

        $branchComponents = $this->branchSalaryComponents($branchId);
        $validIds = $this->componentAssignment->validateExtraComponentIds(
            $branchComponents,
            $validated['extra_salary_component_ids'] ?? []
        );

        $employee->employee?->update(['extra_salary_component_ids' => $validIds]);

        $salary = EmployeeSalary::where('employee_id', $employeeId)->first();
        if ($salary && (float) ($salary->monthly_gross ?? 0) > 0) {
            $split = $this->calculator->splitFromGross(
                (float) $salary->monthly_gross,
                $this->componentsForEmployeeId($branchComponents, (int) $employeeId),
                $this->statutoryOptionsForEmployee((int) $employeeId)
            );
            $salary->update([
                'basic_salary' => $split['basic_amount'],
                'components' => $split['components'],
                'calculation_status' => 'calculated',
            ]);
        }

        return redirect()->back()->with('success', __('Salary components updated for employee.'));
    }

    public function updateDailyOption(Request $request, $employeeId)
    {
        $branchId = session('active_branch_id');

        $validated = $request->validate([
            'daily_option' => 'required|boolean',
            'gross_input_mode' => 'nullable|in:day,month',
        ]);

        $employee = User::with('employee')->where('id', $employeeId)
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->firstOrFail();

        if ($branchId && $employee->employee?->branch_id != $branchId) {
            return redirect()->back()->with('error', __('Employee does not belong to the active branch.'));
        }

        $dailyOption = (bool) ($validated['gross_input_mode'] === 'day' || $validated['daily_option']);
        $workingDays = (int) $this->branchPayrollSettings->resolveWorkingDays((int) ($branchId ?? $employee->employee?->branch_id ?? 0));

        $salary = EmployeeSalary::where('employee_id', $employeeId)->first();
        if ($salary) {
            $salary->update([
                'gross_input_mode' => $dailyOption ? 'day' : 'month',
                'per_day_salary' => $dailyOption && (float) ($salary->monthly_gross ?? 0) > 0
                    ? round((float) $salary->monthly_gross / max(1, $workingDays), 2)
                    : ($salary->per_day_salary ?? null),
            ]);
        }

        return redirect()->back()->with('success', __('Salary entry mode updated.'));
    }

    public function store(Request $request)
    {
        return $this->saveSalary($request);
    }

    public function update(Request $request, $employeeSalaryId)
    {
        return $this->saveSalary($request, (int) $employeeSalaryId);
    }

    public function bulkStore(Request $request)
    {
        $branchId = session('active_branch_id');
        $validated = $request->validate([
            'monthly_gross' => 'required|numeric|min:0',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:users,id',
            'category_id' => 'nullable',
            'department_id' => 'nullable',
            'shift_id' => 'nullable',
        ]);

        $components = $this->branchSalaryComponents($branchId);
        if ($components->isEmpty()) {
            return redirect()->back()->with('error', __('No active salary components for this branch.'));
        }

        $employees = User::whereIn('id', $validated['employee_ids'])
            ->where('type', 'employee')
            ->whereHas('employee', function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->pluck('id');

        $saved = 0;
        DB::transaction(function () use ($employees, $validated, $components, &$saved) {
            foreach ($employees as $employeeId) {
                $split = $this->calculator->splitFromGross(
                    (float) $validated['monthly_gross'],
                    $this->componentsForEmployeeId($components, (int) $employeeId),
                    $this->statutoryOptionsForEmployee((int) $employeeId)
                );
                $this->revisionService->applySalary((int) $employeeId, $split, [
                    'revision_type' => 'joining',
                    'effective_from' => now()->toDateString(),
                    'notes' => __('Bulk salary assignment'),
                ]);
                $saved++;
            }
        });

        return redirect()->back()->with('success', __('Salary applied to :count employee(s).', ['count' => $saved]));
    }

    private function saveSalary(Request $request, ?int $salaryId = null)
    {
        $branchId = session('active_branch_id');

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'monthly_gross' => 'nullable|numeric|min:0',
            'gross_amount' => 'nullable|numeric|min:0',
            'gross_input_mode' => 'nullable|in:day,month',
            'notes' => 'nullable|string|max:1000',
        ]);

        $employee = User::with('employee')->findOrFail($validated['employee_id']);
        if ($branchId && $employee->employee?->branch_id != $branchId) {
            return redirect()->back()->with('error', __('Employee does not belong to the active branch.'));
        }

        $components = $this->branchSalaryComponents($branchId);
        if ($components->isEmpty()) {
            return redirect()->back()->with('error', __('No active salary components for this branch.'));
        }

        $grossInputMode = $validated['gross_input_mode']
            ?? $this->grossInputModeForEmployee((int) $validated['employee_id']);
        $grossAmount = (float) ($validated['gross_amount'] ?? $validated['monthly_gross'] ?? 0);
        if ($grossAmount <= 0) {
            return redirect()->back()->with('error', __('Gross salary amount is required.'));
        }

        $workingDays = (int) $this->branchPayrollSettings->resolveWorkingDays((int) ($branchId ?? $employee->employee?->branch_id ?? 0));
        $monthlyGross = $this->resolveMonthlyGross($grossAmount, $grossInputMode, $workingDays);
        $perDaySalary = $grossInputMode === 'day'
            ? round($grossAmount, 2)
            : round($monthlyGross / max(1, $workingDays), 2);

        $split = $this->calculator->splitFromGross(
            $monthlyGross,
            $this->componentsForEmployeeId($components, (int) $validated['employee_id']),
            $this->statutoryOptionsForEmployee($validated['employee_id'])
        );

        $hasExisting = EmployeeSalary::where('employee_id', $validated['employee_id'])->exists();
        $revisionType = $hasExisting ? 'correction' : 'joining';
        $effectiveFrom = $hasExisting
            ? now()->toDateString()
            : $this->revisionService->joiningDateForEmployee((int) $validated['employee_id'])->toDateString();

        $this->revisionService->applySalary((int) $validated['employee_id'], $split, [
            'revision_type' => $revisionType,
            'effective_from' => $effectiveFrom,
            'notes' => $validated['notes'] ?? ($revisionType === 'joining' ? __('Initial salary from joining date') : null),
        ], $salaryId);

        EmployeeSalary::where('employee_id', $validated['employee_id'])->update([
            'gross_input_mode' => $grossInputMode,
            'per_day_salary' => $perDaySalary,
        ]);

        return redirect()->back()->with('success', __('Employee salary saved successfully.'));
    }

    private function grossDisplayAmount(?EmployeeSalary $salary, $empProfile, ?int $branchId = null): float
    {
        if (! $salary) {
            return 0;
        }

        $monthly = (float) ($salary->monthly_gross ?? $salary->basic_salary ?? 0);
        if ($monthly <= 0) {
            return 0;
        }

        if ($this->grossInputModeForSalary($salary) === 'day') {
            if ((float) ($salary->per_day_salary ?? 0) > 0) {
                return (float) $salary->per_day_salary;
            }
            $days = $branchId
                ? (int) $this->branchPayrollSettings->resolveWorkingDays($branchId)
                : BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;

            return round($monthly / max(1, $days), 2);
        }

        return $monthly;
    }

    private function grossInputModeForSalary(?EmployeeSalary $salary): string
    {
        return $salary?->gross_input_mode === 'day' ? 'day' : 'month';
    }

    private function grossInputModeForProfile($empProfile): string
    {
        return 'month';
    }

    private function grossInputModeForEmployee(?int $userId): string
    {
        if (! $userId) {
            return 'month';
        }

        $salary = EmployeeSalary::where('employee_id', $userId)->first();

        return $this->grossInputModeForSalary($salary);
    }

    private function workingDaysForBranch(?int $branchId): int
    {
        if (! $branchId) {
            return (int) BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;
        }

        return (int) $this->branchPayrollSettings->resolveWorkingDays($branchId);
    }

    private function workingDaysForEmployee(?int $userId, ?int $override = null): int
    {
        if ($override && $override > 0) {
            return $override;
        }

        if (! $userId) {
            return (int) BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;
        }

        $employee = User::with('employee')->find($userId);

        return $this->workingDaysForBranch($employee?->employee?->branch_id);
    }

    private function resolveMonthlyGross(float $amount, string $mode, int $workingDays): float
    {
        if ($amount <= 0) {
            return 0;
        }

        if ($mode === 'day') {
            return round($amount * max(1, $workingDays), 2);
        }

        return round($amount, 2);
    }

    private function statutoryOptionsForEmployee(?int $userId): array
    {
        if (! $userId) {
            return ['apply_pf' => true, 'apply_esi' => true];
        }

        $employee = User::with('employee')->find($userId);
        $profile = $employee?->employee;

        return [
            'apply_pf' => (bool) ($profile?->pf_flag ?? false),
            'apply_esi' => (bool) ($profile?->esic_flag ?? false),
        ];
    }

    private function componentsForEmployeeId($components, ?int $userId)
    {
        if (! $userId) {
            return $this->componentAssignment->primaryComponents($components);
        }

        $user = User::with('employee')->find($userId);

        return $this->componentAssignment->resolveForEmployee($components, $user?->employee);
    }

    private function branchSalaryComponents(?int $branchId)
    {
        $query = SalaryComponent::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->orderBy('component_group')
            ->orderBy('type')
            ->orderBy('name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    private function branchCategories(?int $branchId)
    {
        if (! $branchId) {
            return Category::orderBy('name')->get(['id', 'name']);
        }

        return Category::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function branchDepartments(?int $branchId)
    {
        $query = Department::withoutGlobalScopes()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->orderBy('name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get(['id', 'name']);
    }

    private function branchShifts(?int $branchId)
    {
        $query = Shift::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->orderBy('name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get(['id', 'name']);
    }

    private function netFromRecord(EmployeeSalary $salary): float
    {
        if (! $salary->components || ! is_array($salary->components)) {
            return (float) ($salary->monthly_gross ?? $salary->basic_salary ?? 0);
        }

        $ids = array_keys($salary->components);
        $components = SalaryComponent::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get(['id', 'type']);

        $earnings = 0;
        $deductions = 0;
        foreach ($salary->components as $id => $amount) {
            $comp = $components->firstWhere('id', (int) $id);
            if (! $comp) {
                continue;
            }
            if ($comp->type === 'earning') {
                $earnings += (float) $amount;
            } else {
                $deductions += (float) $amount;
            }
        }

        return round($earnings - $deductions, 2);
    }
}
