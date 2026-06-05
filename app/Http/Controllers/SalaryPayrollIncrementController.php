<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Services\SalaryPayroll\EmployeeSalaryRevisionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalaryPayrollIncrementController extends Controller
{
    public function __construct(
        private EmployeeSalaryRevisionService $revisionService
    ) {}

    public function index(Request $request)
    {
        $branchId = session('active_branch_id');
        $branchName = $branchId ? \App\Models\Branch::find($branchId)?->name : null;

        return Inertia::render('hr/salary-payroll/salary-increment/index', [
            'categories' => $this->branchCategories($branchId),
            'departments' => $this->branchDepartments($branchId),
            'shifts' => $this->branchShifts($branchId),
            'activeBranchId' => $branchId,
            'activeBranchName' => $branchName,
            'defaultEffectiveFrom' => now()->toDateString(),
        ]);
    }

    public function preview(Request $request)
    {
        $validated = $this->validateBulkRequest($request);
        $branchId = session('active_branch_id');
        $components = $this->branchSalaryComponents($branchId);

        if ($components->isEmpty()) {
            return response()->json(['error' => __('No active salary components for this branch.')], 422);
        }

        return response()->json(
            $this->revisionService->previewBulkIncrement($validated, $components, $branchId)
        );
    }

    public function apply(Request $request)
    {
        $validated = $this->validateBulkRequest($request, requireEffectiveFrom: true);
        $branchId = session('active_branch_id');
        $components = $this->branchSalaryComponents($branchId);

        if ($components->isEmpty()) {
            return redirect()->back()->with('error', __('No active salary components for this branch.'));
        }

        try {
            $saved = $this->revisionService->applyBulkIncrement($validated, $components, $branchId);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('Salary increment applied to :count employee(s).', ['count' => $saved]));
    }

    private function validateBulkRequest(Request $request, bool $requireEffectiveFrom = false): array
    {
        $rules = [
            'increment_mode' => 'required|in:percentage,fixed',
            'increment_value' => 'required|numeric|min:0.01',
            'category_id' => 'nullable',
            'department_id' => 'nullable',
            'shift_id' => 'nullable',
            'search' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($requireEffectiveFrom) {
            $rules['effective_from'] = 'required|date';
        }

        return $request->validate($rules);
    }

    private function branchSalaryComponents(?int $branchId)
    {
        $query = \App\Models\SalaryComponent::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereIn('created_by', getCompanyAndUsersId())
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
}
