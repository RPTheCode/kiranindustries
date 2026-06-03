<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    use Concerns\LogsMasterCrud;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Skill::query()
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
            $query->where('status', $request->status === 'active');
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->latest();
        }

        $skills = $query->withCount('employeeWorkHistories')
            ->paginate($request->per_page ?? 10)
            ->withQueryString();

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (!isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string)$branchId : 'all';
        }

        return Inertia::render('hr/skills/index', [
            'skills' => $skills,
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
                Rule::unique('skills')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })
            ],
            'branch_id' => 'required|exists:branches,id',
            'status' => 'boolean',
        ]);

        $skill = Skill::create([
            'name' => $request->name,
            'code' => $request->code,
            'branch_id' => $targetBranchId,
            'status' => $request->status ?? true,
            'created_by' => creatorId(),
        ]);
        $this->logMasterCreated($skill);

            return redirect()->back()->with('success', 'Skill created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Skill $skill)
    {
        $targetBranchId = $request->input('branch_id') ?? $skill->branch_id ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('skills')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })->ignore($skill->id)
            ],
            'branch_id' => 'required|exists:branches,id',
            'status' => 'boolean',
        ]);

        $skill->update([
            'name' => $request->name,
            'code' => $request->code,
            'branch_id' => $targetBranchId,
            'status' => $request->status,
        ]);
        $this->logMasterUpdated($skill);

            return redirect()->back()->with('success', 'Skill updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Skill $skill)
    {
        if ($skill->employeeWorkHistories()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete skill because it is used in employee work history.');
        }

        $this->logMasterDeleted($skill);
        $skill->delete();

            return redirect()->back()->with('success', 'Skill deleted successfully.');
    }

    /**
     * Toggle the status of the specified resource.
     */
    public function toggleStatus(Skill $skill)
    {
        if ($skill->status && $skill->employeeWorkHistories()->exists()) {
            return redirect()->back()->with('error', 'Cannot disable skill because it is used in employee work history.');
        }

        $skill->update([
            'status' => !$skill->status
        ]);

            return redirect()->back()->with('success', 'Skill status updated successfully.');
    }

    /**
     * Copy a single skill to one or more branches.
     */
    public function copyToBranches(Request $request, $skillId)
    {
        $request->validate([
            'branch_ids'   => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $source = Skill::whereIn('created_by', getCompanyAndUsersId())
            ->where('id', $skillId)
            ->first();

        if (!$source) {
            return redirect()->back()->with('error', 'Skill Not Found.');
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($request->branch_ids as $branchId) {
                // Check if skill with the same code already exists in that branch
                $existing = Skill::where('branch_id', $branchId)
                    ->where('code', $source->code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch     = Branch::find($branchId);
                    $warnings[] = "Skill code '{$source->code}' already exists in Branch '" . ($branch ? $branch->name : '#' . $branchId) . "'. Skipped.";
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
            return redirect()->back()->with('error', $e->getMessage() ?: 'Failed to copy skill to branches.');
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : 'No skills were copied.');
        }

        $msg = "{$successCount} skill(s) successfully copied.";
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk copy multiple skills to one or more branches.
     */
    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'skill_ids'   => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:skills,id',
            'branch_ids'  => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $sources = Skill::whereIn('id', $request->skill_ids)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sources->isEmpty()) {
            return redirect()->back()->with('error', 'Skills Not Found.');
        }

        $successCount = 0;
        $warnings     = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $source) {
                foreach ($request->branch_ids as $branchId) {
                    // Check if skill with the same code already exists in that branch
                    $existing = Skill::where('branch_id', $branchId)
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
            return redirect()->back()->with('error', $e->getMessage() ?: 'Failed to bulk copy skills.');
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', count($warnings) > 0 ? implode(' ', $warnings) : 'No skills were copied.');
        }

        $msg = "{$successCount} skill(s) successfully copied to selected branches.";
        if (count($warnings) > 0) {
            $msg .= ' ' . implode(' ', $warnings);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Import skills from Excel/CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,excel,csv,txt',
        ]);

        try {
            $import = new \App\Imports\SkillsImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

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

            return redirect()->back()->with('success', 'Skills imported successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    /**
     * Download the template for importing skills.
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\SkillsTemplateExport, 'skills_template.xlsx');
    }
}
