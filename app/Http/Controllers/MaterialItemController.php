<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MaterialItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MaterialItemController extends Controller
{
    use Concerns\LogsMasterCrud;
    public function index(Request $request)
    {
        $query = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
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

        $materialItems = $query->paginate($request->per_page ?? 10)->withQueryString();

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (!isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string)$branchId : 'all';
        }

        return Inertia::render('hr/masters/material-items/index', [
            'materialItems' => $materialItems,
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
                Rule::unique('material_items')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                }),
            ],
            'rate' => 'required|numeric|min:0',
            'status' => 'nullable|string|in:active,inactive'
        ]);

        $materialItem = MaterialItem::create([
            'name' => $request->name,
            'code' => $request->code,
            'rate' => $request->rate,
            'status' => $request->status ?? 'active',
            'created_by' => creatorId(),
            'branch_id' => $targetBranchId
        ]);
        $this->logMasterCreated($materialItem);

            return redirect()->back()->with('success', __('Material item created successfully'));
    }

    public function update(Request $request, $itemId)
    {
        $materialItem = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $itemId)
            ->first();

        if (!$materialItem) {
            return redirect()->back()->with('error', __('Material Item Not Found.'));
        }

        $targetBranchId = $request->input('branch_id') ?? $materialItem->branch_id ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('material_items')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })->ignore($itemId),
            ],
            'rate' => 'required|numeric|min:0',
            'status' => 'nullable|string|in:active,inactive'
        ]);

        $materialItem->update([
            'name' => $request->name,
            'code' => $request->code,
            'rate' => $request->rate,
            'status' => $request->status ?? 'active',
            'branch_id' => $targetBranchId
        ]);
        $this->logMasterUpdated($materialItem);

            return redirect()->back()->with('success', __('Material item updated successfully'));
    }

    public function destroy($itemId)
    {
        $materialItem = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $itemId)
            ->first();

        if (!$materialItem) {
            return redirect()->back()->with('error', __('Material Item Not Found.'));
        }

        // We can check if the material item is used in any DailyProductionAttendanceEntry
        $attendanceCount = \App\Models\DailyProductionAttendanceEntry::where('material_item_id', $itemId)->count();
        if ($attendanceCount > 0) {
            return redirect()->back()->with('error', __('Cannot delete material item as it has existing production entries.'));
        }

        $this->logMasterDeleted($materialItem);
        $materialItem->delete();
            return redirect()->back()->with('success', __('Material item deleted successfully'));
    }

    public function toggleStatus($itemId)
    {
        $materialItem = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $itemId)
            ->first();

        if (!$materialItem) {
            return redirect()->back()->with('error', __('Material Item Not Found.'));
        }

        $materialItem->status = $materialItem->status === 'active' ? 'inactive' : 'active';
        $materialItem->save();

            return redirect()->back()->with('success', __('Material item status updated successfully'));
    }

    public function copyToBranches(Request $request, $itemId)
    {
        $request->validate([
            'branch_ids'   => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $source = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $itemId)
            ->first();

        if (!$source) {
            return redirect()->back()->with('error', __('Material Item Not Found.'));
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($request->branch_ids as $branchId) {
                $existing = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                    ->where('branch_id', $branchId)
                    ->where('code', $source->code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch     = Branch::find($branchId);
                    $warnings[] = __("Material item code ':code' already exists in Branch ':branch'. Skipped.", [
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
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to copy material item to branches.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No material items were copied.'));
        }

        $msg = __(':count material item(s) successfully copied.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'item_ids'     => 'required|array|min:1',
            'item_ids.*'   => 'required|integer|exists:material_items,id',
            'branch_ids'   => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $sources = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->whereIn('id', $request->item_ids)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', __('Material Items Not Found.'));
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $source) {
                foreach ($request->branch_ids as $branchId) {
                    $existing = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
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
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to bulk copy material items.'));
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : __('No material items were copied.'));
        }

        $msg = __(':count material item(s) successfully copied to selected branches.', ['count' => $successCount]);
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }
}
