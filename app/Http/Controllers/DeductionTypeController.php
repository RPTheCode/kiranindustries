<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeductionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DeductionTypeController extends Controller
{
    use Concerns\LogsMasterCrud;

    public function index(Request $request)
    {
        $query = DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->with(['branch', 'categoryAmounts.category'])
            ->whereIn('created_by', getCompanyAndUsersId());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $branchId = $request->input('branch_id') ?? session('active_branch_id');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('sort_field')) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('sort_order')->orderBy('name');
        }

        $deductionTypes = $query->paginate($request->per_page ?? 100)->withQueryString();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (! isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string) $branchId : 'all';
        }

        return Inertia::render('hr/masters/deduction-types/index', [
            'deductionTypes' => $deductionTypes,
            'categories' => $this->branchCategories($branchId),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'activeBranchId' => session('active_branch_id'),
            'activeBranchName' => $branchId && $branchId !== 'all'
                ? Branch::find($branchId)?->name
                : null,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $targetBranchId = $request->input('branch_id') ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('deduction_types')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                }),
            ],
            'default_amount' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('amount_type', 'fixed') === 'fixed')],
            'amount_type' => 'required|in:fixed,category_wise',
            'calculation_mode' => 'required|in:day,month',
            'status' => 'nullable|string|in:active,inactive',
            'branch_id' => 'nullable|exists:branches,id',
            'category_amounts' => 'nullable|array',
            'category_amounts.*.category_id' => 'required_with:category_amounts|exists:categories,id',
            'category_amounts.*.amount' => 'nullable|numeric|min:0',
        ]);

        if ($request->input('amount_type') === 'fixed' && (float) ($validated['default_amount'] ?? 0) < 0) {
            return redirect()->back()->withErrors(['default_amount' => __('Default amount is required for fixed amount type.')]);
        }

        $nextSortOrder = (int) DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->where('branch_id', $targetBranchId)
            ->whereIn('created_by', $companyUserIds)
            ->max('sort_order');

        $deductionType = DeductionType::create([
            'name' => $validated['name'],
            'default_amount' => $validated['default_amount'] ?? 0,
            'amount_type' => $validated['amount_type'],
            'calculation_mode' => $validated['calculation_mode'],
            'sort_order' => $nextSortOrder + 1,
            'status' => $validated['status'] ?? 'active',
            'branch_id' => $targetBranchId,
            'created_by' => creatorId(),
        ]);

        $this->syncCategoryAmounts($deductionType, $request->input('category_amounts', []));

        $this->logMasterCreated($deductionType);

        return redirect()->back()->with('success', __('Deduction type created successfully.'));
    }

    public function update(Request $request, $deductionTypeId)
    {
        $deductionType = DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->findOrFail($deductionTypeId);

        $targetBranchId = $request->input('branch_id') ?? $deductionType->branch_id ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('deduction_types')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })->ignore($deductionType->id),
            ],
            'default_amount' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($request->input('amount_type', $deductionType->amount_type) === 'fixed')],
            'amount_type' => 'required|in:fixed,category_wise',
            'calculation_mode' => 'required|in:day,month',
            'status' => 'nullable|string|in:active,inactive',
            'branch_id' => 'nullable|exists:branches,id',
            'category_amounts' => 'nullable|array',
            'category_amounts.*.category_id' => 'required_with:category_amounts|exists:categories,id',
            'category_amounts.*.amount' => 'nullable|numeric|min:0',
        ]);

        $deductionType->update([
            'name' => $validated['name'],
            'default_amount' => $validated['default_amount'] ?? 0,
            'amount_type' => $validated['amount_type'],
            'calculation_mode' => $validated['calculation_mode'],
            'status' => $validated['status'] ?? $deductionType->status,
            'branch_id' => $targetBranchId,
        ]);

        $this->syncCategoryAmounts($deductionType, $request->input('category_amounts', []));

        $this->logMasterUpdated($deductionType);

        return redirect()->back()->with('success', __('Deduction type updated successfully.'));
    }

    public function destroy($deductionTypeId)
    {
        $deductionType = DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->findOrFail($deductionTypeId);

        if ($deductionType->monthlyIncentiveDetails()->exists()) {
            return redirect()->back()->with('error', __('Cannot delete deduction type that has existing entries. Deactivate it instead.'));
        }

        $this->logMasterDeleted($deductionType);
        $deductionType->delete();

        return redirect()->back()->with('success', __('Deduction type deleted successfully.'));
    }

    public function toggleStatus($deductionTypeId)
    {
        $deductionType = DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->findOrFail($deductionTypeId);

        $deductionType->update([
            'status' => $deductionType->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->back()->with('success', __('Status updated successfully.'));
    }

    /**
     * Persist drag-and-drop sort order (ids in display order).
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:deduction_types,id',
        ]);

        $companyUserIds = getCompanyAndUsersId();
        $branchId = session('active_branch_id');

        DB::transaction(function () use ($validated, $companyUserIds, $branchId) {
            foreach ($validated['ids'] as $index => $id) {
                DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                    ->whereIn('created_by', $companyUserIds)
                    ->when($branchId && $branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
                    ->where('id', $id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Active deduction types for the current branch (API for entry screens).
     */
    public function activeList(Request $request)
    {
        $branchId = $request->input('branch_id') ?? session('active_branch_id');

        $types = DeductionType::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->with(['categoryAmounts'])
            ->active()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->when($branchId && $branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'default_amount', 'amount_type', 'calculation_mode']);

        return response()->json(['deductionTypes' => $types]);
    }

    private function branchCategories($branchId)
    {
        return Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->when($branchId && $branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function syncCategoryAmounts(DeductionType $deductionType, array $rows): void
    {
        $deductionType->categoryAmounts()->delete();

        if ($deductionType->amount_type !== DeductionType::AMOUNT_CATEGORY_WISE) {
            return;
        }

        foreach ($rows as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $categoryId = $row['category_id'] ?? null;
            if (! $categoryId) {
                continue;
            }

            $deductionType->categoryAmounts()->create([
                'category_id' => $categoryId,
                'amount' => $amount,
            ]);
        }
    }
}
