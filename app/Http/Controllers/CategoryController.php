<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    use Concerns\LogsMasterCrud;
    public function index(Request $request)
    {
        $query = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->with(['branch'])
            ->whereIn('created_by', getCompanyAndUsersId());

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $search = $request->search;
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Branch-wise filtering: use request branch_id or fall back to active session branch
        $branchId = $request->input('branch_id') ?? session('active_branch_id');
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $categories = $query->paginate($request->per_page ?? 10)->withQueryString();

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (!isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string)$branchId : 'all';
        }

        return Inertia::render('hr/masters/categories/index', [
            'categories' => $categories,
            'branches' => $branches,
            'activeBranchId' => session('active_branch_id'),
            'filters' => $filters
        ]);
    }

    public function store(Request $request)
    {
        $targetBranchId = $request->input('branch_id') ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('categories')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                }),
            ],
            'status' => 'nullable|string|in:active,inactive'
        ]);

        $category = Category::create([
            'name' => $request->name,
            'code' => $request->code,
            'status' => $request->status ?? 'active',
            'created_by' => creatorId(),
            'branch_id' => $targetBranchId
        ]);
        $this->logMasterCreated($category);

            return redirect()->back()->with('success', __('Category created successfully'));
    }

    public function update(Request $request, $categoryId)
    {
        $category = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $categoryId)
            ->first();

        if (!$category) {
            return redirect()->back()->with('error', __('Category Not Found.'));
        }

        $targetBranchId = $request->input('branch_id') ?? $category->branch_id ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('categories')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })->ignore($categoryId),
            ],
            'status' => 'nullable|string|in:active,inactive'
        ]);

        $category->update([
            'name' => $request->name,
            'code' => $request->code,
            'status' => $request->status ?? 'active',
            'branch_id' => $targetBranchId
        ]);
        $this->logMasterUpdated($category);

            return redirect()->back()->with('success', __('Category updated successfully'));
    }

    public function destroy($categoryId)
    {
        $category = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $categoryId)
            ->first();

        if (!$category) {
            return redirect()->back()->with('error', __('Category Not Found.'));
        }

        if ($category->employees()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete category with assigned employees.'));
        }

        $this->logMasterDeleted($category);
        $category->delete();
            return redirect()->back()->with('success', __('Category deleted successfully'));
    }

    public function toggleStatus($categoryId)
    {
        $category = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $categoryId)
            ->first();

        if (!$category) {
            return redirect()->back()->with('error', __('Category Not Found.'));
        }

        $category->status = $category->status === 'active' ? 'inactive' : 'active';
        $category->save();

            return redirect()->back()->with('success', __('Category status updated successfully'));
    }

    public function copyToBranches(Request $request, $categoryId)
    {
        $request->validate([
            'branch_ids'   => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $source = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $categoryId)
            ->first();

        if (!$source) {
            return redirect()->back()->with('error', __('Category Not Found.'));
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($request->branch_ids as $branchId) {
                $existing = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                    ->where('branch_id', $branchId)
                    ->where('code', $source->code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch     = Branch::find($branchId);
                    $warnings[] = __("Category code ':code' already exists in Branch ':branch'. Skipped.", [
                        'code'   => $source->code,
                        'branch' => $branch ? $branch->name : '#' . $branchId,
                    ]);
                    continue;
                }

                $clone             = $source->replicate();
                $clone->branch_id  = $branchId;
                $clone->created_by = Auth::id();
                $clone->save();
                $successCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to copy category to branches.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No categories were copied.'));
        }

        $msg = __(':count category(s) successfully copied.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'category_ids'   => 'required|array|min:1',
            'category_ids.*' => 'required|integer|exists:categories,id',
            'branch_ids'       => 'required|array|min:1',
            'branch_ids.*'     => 'required|integer|exists:branches,id',
        ]);

        $sources = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('id', $request->category_ids)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', __('Categories Not Found.'));
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $source) {
                foreach ($request->branch_ids as $branchId) {
                    $existing = Category::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                        ->where('branch_id', $branchId)
                        ->where('code', $source->code)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->first();

                    if ($existing) {
                        $branch     = Branch::find($branchId);
                        $warnings[] = __("':name' (code ':code') already exists in ':branch'. Skipped.", [
                            'name'   => $source->name,
                            'code'   => $source->code,
                            'branch' => $branch ? $branch->name : '#' . $branchId,
                        ]);
                        continue;
                    }

                    $clone             = $source->replicate();
                    $clone->branch_id  = $branchId;
                    $clone->created_by = Auth::id();
                    $clone->save();
                    $successCount++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to bulk copy categories.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No categories were copied.'));
        }

        $msg = __(':count category(s) successfully copied to selected branches.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }
}
