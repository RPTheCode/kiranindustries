<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    use Concerns\LogsMasterCrud;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Section::query()
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
            $query->latest();
        }

        $sections = $query->withCount('employees')
            ->paginate($request->per_page ?? 10)
            ->withQueryString();

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (!isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string)$branchId : 'all';
        }

        return Inertia::render('hr/masters/sections/index', [
            'sections' => $sections,
            'branches' => $branches,
            'activeBranchId' => session('active_branch_id'),
            'filters' => $filters
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $targetBranchId = $request->input('branch_id') ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sections')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })
            ],
            'branch_id' => 'required|exists:branches,id',
            'status' => 'required|string|in:active,inactive',
        ]);

        $section = Section::create([
            'name' => $request->name,
            'code' => $request->code,
            'branch_id' => $targetBranchId,
            'status' => $request->status ?? 'active',
            'created_by' => creatorId(),
        ]);
        $this->logMasterCreated($section);

            return redirect()->back()->with('success', 'Section created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        $targetBranchId = $request->input('branch_id') ?? $section->branch_id ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sections')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })->ignore($section->id)
            ],
            'branch_id' => 'required|exists:branches,id',
            'status' => 'required|string|in:active,inactive',
        ]);

        $section->update([
            'name' => $request->name,
            'code' => $request->code,
            'branch_id' => $targetBranchId,
            'status' => $request->status,
        ]);
        $this->logMasterUpdated($section);

            return redirect()->back()->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        if ($section->employees()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete section because it is used in employees.');
        }

        $this->logMasterDeleted($section);
        $section->delete();

            return redirect()->back()->with('success', 'Section deleted successfully.');
    }

    /**
     * Toggle the status of the specified resource.
     */
    public function toggleStatus(Section $section)
    {
        $newStatus = $section->status === 'active' ? 'inactive' : 'active';

        if ($section->status === 'active' && $section->employees()->exists()) {
            return redirect()->back()->with('error', 'Cannot disable section because it is used in employees.');
        }

        $section->update([
            'status' => $newStatus
        ]);

            return redirect()->back()->with('success', 'Section status updated successfully.');
    }

    /**
     * Copy a single section to one or more branches.
     */
    public function copyToBranches(Request $request, $sectionId)
    {
        $request->validate([
            'branch_ids'   => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $source = Section::whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $sectionId)
            ->first();

        if (!$source) {
            return redirect()->back()->with('error', 'Section Not Found.');
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($request->branch_ids as $branchId) {
                // Check if section with the same code already exists in that branch
                $existing = Section::where('branch_id', $branchId)
                    ->where('code', $source->code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch     = Branch::find($branchId);
                    $warnings[] = "Section code '{$source->code}' already exists in Branch '" . ($branch ? $branch->name : '#' . $branchId) . "'. Skipped.";
                    continue;
                }

                $clone            = $source->replicate();
                $clone->branch_id = $branchId;
                $clone->created_by = Auth::id();
                $clone->save();
                $successCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: 'Failed to copy section to branches.');
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : 'No sections were copied.');
        }

        $msg = "{$successCount} section(s) successfully copied.";
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk copy multiple sections to one or more branches.
     */
    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'section_ids'   => 'required|array|min:1',
            'section_ids.*' => 'required|integer|exists:sections,id',
            'branch_ids'  => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $sources = Section::whereIn('id', $request->section_ids)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', 'Sections Not Found.');
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $source) {
                foreach ($request->branch_ids as $branchId) {
                    // Check if section with the same code already exists in that branch
                    $existing = Section::where('branch_id', $branchId)
                        ->where('code', $source->code)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->first();

                    if ($existing) {
                        $branch     = Branch::find($branchId);
                        $warnings[] = "'{$source->name}' (code '{$source->code}') already exists in '" . ($branch ? $branch->name : '#' . $branchId) . "'. Skipped.";
                        continue;
                    }

                    $clone            = $source->replicate();
                    $clone->branch_id = $branchId;
                    $clone->created_by = Auth::id();
                    $clone->save();
                    $successCount++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: 'Failed to bulk copy sections.');
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : 'No sections were copied.');
        }

        $msg = "{$successCount} section(s) successfully copied to selected branches.";
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }
}
