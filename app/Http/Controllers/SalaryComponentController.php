<?php

namespace App\Http\Controllers;

use App\Models\SalaryComponent;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SalaryComponentController extends Controller
{
    use Concerns\LogsMasterCrud;
    public function index(Request $request)
    {
        $query = SalaryComponent::withPermissionCheck()
            ->with(['creator', 'branch']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle type filter
        if ($request->has('type') && !empty($request->type) && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Handle calculation type filter
        if ($request->has('calculation_type') && !empty($request->calculation_type) && $request->calculation_type !== 'all') {
            $query->where('calculation_type', $request->calculation_type);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle branch filter
        if ($request->has('branch_id') && !empty($request->branch_id) && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('type', 'asc')->orderBy('name', 'asc');
        }

        $salaryComponents = $query->paginate($request->per_page ?? 100)->withQueryString();

        // Get branches for filter
        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $branchId = session('active_branch_id');
        $activeBranchName = $branchId && $branchId !== 'all'
            ? Branch::find($branchId)?->name
            : null;

        return Inertia::render('hr/salary-components/index', [
            'salaryComponents' => $salaryComponents,
            'branches' => $branches,
            'activeBranchId' => $branchId,
            'activeBranchName' => $activeBranchName,
            'filters' => $request->all(['search', 'type', 'calculation_type', 'status', 'sort_field', 'sort_direction', 'per_page', 'branch_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:earning,deduction',
            'calculation_type' => 'required|in:percentage,percentage_of_gross',
            'percentage_of_basic' => 'required_if:calculation_type,percentage|nullable|numeric|min:0|max:100',
            'percentage_of_gross_pay' => 'required_if:calculation_type,percentage_of_gross|nullable|numeric|min:0|max:100',
            'rounding_method' => 'nullable|in:none,round,ceil,floor',
            'is_taxable' => 'boolean',
            'is_mandatory' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated = $this->normalizePercentagePayload($validated);

        $validated['branch_id'] = session('active_branch_id');
        if (!$validated['branch_id']) {
            return redirect()->back()->with('error', __('No active branch selected.'));
        }

        $validated['created_by'] = creatorId();

        $exists = SalaryComponent::where('name', $validated['name'])
            ->where('branch_id', $validated['branch_id'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', __('Salary component with this name already exists.'));
        }

        $salaryComponent = SalaryComponent::create($validated);
        $this->logMasterCreated($salaryComponent);

        return redirect()->back()->with('success', __('Salary component created successfully.'));
    }

    public function update(Request $request, $salaryComponentId)
    {
        $salaryComponent = SalaryComponent::where('id', $salaryComponentId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($salaryComponent) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'type' => 'required|in:earning,deduction',
                    'calculation_type' => 'required|in:percentage,percentage_of_gross',
                    'percentage_of_basic' => 'required_if:calculation_type,percentage|nullable|numeric|min:0|max:100',
                    'percentage_of_gross_pay' => 'required_if:calculation_type,percentage_of_gross|nullable|numeric|min:0|max:100',
                    'rounding_method' => 'nullable|in:none,round,ceil,floor',
                    'is_taxable' => 'boolean',
                    'is_mandatory' => 'boolean',
                    'status' => 'nullable|in:active,inactive',
                ]);

                $validated = $this->normalizePercentagePayload($validated);

                $validated['branch_id'] = session('active_branch_id');
                if (!$validated['branch_id']) {
                    return redirect()->back()->with('error', __('No active branch selected.'));
                }

                $exists = SalaryComponent::where('name', $validated['name'])
                    ->where('branch_id', $validated['branch_id'])
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->where('id', '!=', $salaryComponentId)
                    ->exists();

                if ($exists) {
                    return redirect()->back()->with('error', __('Salary component with this name already exists.'));
                }

                $salaryComponent->update($validated);
                $this->logMasterUpdated($salaryComponent);

                return redirect()->back()->with('success', __('Salary component updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update salary component'));
            }
        } else {
            return redirect()->back()->with('error', __('Salary component Not Found.'));
        }
    }

    private function normalizePercentagePayload(array $validated): array
    {
        $validated['default_amount'] = 0;

        if ($validated['calculation_type'] === 'percentage') {
            $validated['percentage_of_gross_pay'] = null;
        } else {
            $validated['percentage_of_basic'] = null;
        }

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['is_taxable'] = $validated['is_taxable'] ?? true;
        $validated['is_mandatory'] = $validated['is_mandatory'] ?? false;
        $validated['rounding_method'] = $validated['rounding_method'] ?? 'round';
        $validated['description'] = $this->buildDescription($validated);

        return $validated;
    }

    private function buildDescription(array $validated): string
    {
        $labels = [
            'BASIC' => 'Basic Salary',
            'HRA' => 'House Rent Allowance',
            'LTA' => 'Leave Travel Allowance',
        ];

        $label = $labels[strtoupper($validated['name'])] ?? $validated['name'];

        if ($validated['calculation_type'] === 'percentage_of_gross') {
            $pct = rtrim(rtrim(number_format((float) $validated['percentage_of_gross_pay'], 2, '.', ''), '0'), '.');

            return "{$label} — {$pct}% of gross";
        }

        $pct = rtrim(rtrim(number_format((float) $validated['percentage_of_basic'], 2, '.', ''), '0'), '.');

        return "{$label} — {$pct}% of basic";
    }

    public function destroy($salaryComponentId)
    {
        $salaryComponent = SalaryComponent::where('id', $salaryComponentId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($salaryComponent) {
            try {
                $this->logMasterDeleted($salaryComponent);
                $salaryComponent->delete();
            return redirect()->back()->with('success', __('Salary component deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete salary component'));
            }
        } else {
            return redirect()->back()->with('error', __('Salary component Not Found.'));
        }
    }

    public function toggleStatus($salaryComponentId)
    {
        $salaryComponent = SalaryComponent::where('id', $salaryComponentId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($salaryComponent) {
            try {
                $salaryComponent->status = $salaryComponent->status === 'active' ? 'inactive' : 'active';
                $salaryComponent->save();

            return redirect()->back()->with('success', __('Salary component status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update salary component status'));
            }
        } else {
            return redirect()->back()->with('error', __('Salary component Not Found.'));
        }
    }
}