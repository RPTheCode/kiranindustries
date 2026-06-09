<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillWageRate;
use App\Models\Branch;
use App\Models\WageZone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    use Concerns\LogsMasterCrud;

    /**
     * Skill cannot be deleted while assigned to employees (branch) or used in work history.
     *
     * @return array{can_delete: bool, employees_count: int, employee_work_histories_count: int, block_reason: ?string}
     */
    private function skillDeletionMeta(Skill $skill, ?int $workHistoryCount = null): array
    {
        $employeesCount = $this->countEmployeesAllocatedToSkill($skill);
        $workHistoryCount = $workHistoryCount ?? (int) ($skill->employee_work_histories_count ?? $skill->employeeWorkHistories()->count());

        $blockReason = null;
        if ($employeesCount > 0) {
            $blockReason = __('Cannot delete: :count employee(s) in this branch are assigned this skill.', ['count' => $employeesCount]);
        } elseif ($workHistoryCount > 0) {
            $blockReason = __('Cannot delete: skill is used in :count work history record(s).', ['count' => $workHistoryCount]);
        }

        return [
            'can_delete' => $blockReason === null,
            'employees_count' => $employeesCount,
            'employee_work_histories_count' => $workHistoryCount,
            'block_reason' => $blockReason,
        ];
    }

    private function countEmployeesAllocatedToSkill(Skill $skill): int
    {
        return countEmployeesInBranchForSkill((int) $skill->id, (int) $skill->branch_id);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $activeBranchId = session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $baseQuery = Skill::query()
            ->with(['branch'])
            ->whereIn('created_by', $companyUserIds);

        $branchId = $request->input('branch_id') ?? $activeBranchId;

        $statsQuery = clone $baseQuery;
        if ($branchId && $branchId !== 'all') {
            $statsQuery->where('branch_id', $branchId);
        }

        $statsSkills = (clone $statsQuery)->get();
        $totalEmployeesAssigned = (int) $statsSkills->sum(
            fn (Skill $skill) => countEmployeesInBranchForSkill((int) $skill->id, (int) $skill->branch_id)
        );

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', true)->count(),
            'inactive' => (clone $statsQuery)->where('status', false)->count(),
            'total_employees' => $totalEmployeesAssigned,
            'branch_id' => ($branchId && $branchId !== 'all') ? (string) $branchId : null,
        ];

        $query = clone $baseQuery;

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $statusFilter = $request->input('status', 'all');
        if ($statusFilter === 'active') {
            $query->where('status', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', false);
        }

        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->latest();
        }

        $skills = $query->withCount('employeeWorkHistories')
            ->paginate($request->per_page ?? 10)
            ->withQueryString();

        $skills->getCollection()->transform(function (Skill $skill) {
            $meta = $this->skillDeletionMeta($skill, (int) $skill->employee_work_histories_count);
            applyMasterDeleteAttributes($skill, $meta['can_delete'], $meta['block_reason'], $meta['employees_count']);

            return $skill;
        });

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (! isset($filters['status']) || $filters['status'] === null || $filters['status'] === '') {
            $filters['status'] = 'all';
        }
        if (! isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string) $branchId : 'all';
        }

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['skills', 'zones', 'rates'], true)) {
            $tab = 'skills';
        }

        $wageZones = WageZone::query()
            ->whereIn('created_by', $companyUserIds)
            ->orderBy('state')
            ->orderBy('region')
            ->orderBy('name')
            ->get()
            ->map(fn (WageZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'code' => $zone->code,
                'state' => $zone->state,
                'region' => $zone->region,
                'country' => $zone->country,
                'working_days' => (int) $zone->working_days,
                'status' => (bool) $zone->status,
                'notes' => $zone->notes,
                'display_label' => $zone->displayLabel(),
                'branches_count' => $zone->branches()->count(),
            ]);

        $selectedWageZoneId = $request->integer('wage_zone_id')
            ?: ($wageZones->firstWhere('status', true)['id'] ?? null)
            ?: ($wageZones->first()['id'] ?? null);

        $zoneRates = [];
        $selectedWageZone = $wageZones->firstWhere('id', $selectedWageZoneId);

        if ($selectedWageZoneId) {
            $rateSkillsQuery = Skill::query()
                ->whereIn('created_by', $companyUserIds)
                ->where('status', true)
                ->orderBy('name');

            if ($activeBranchId) {
                $rateSkillsQuery->where('branch_id', $activeBranchId);
            }

            $rateSkills = $rateSkillsQuery->get(['id', 'name', 'code', 'branch_id']);
            $existingRates = SkillWageRate::query()
                ->where('wage_zone_id', $selectedWageZoneId)
                ->whereIn('skill_id', $rateSkills->pluck('id'))
                ->get()
                ->keyBy('skill_id');

            $zoneRates = $rateSkills->map(function (Skill $skill) use ($existingRates) {
                $rate = $existingRates->get($skill->id);

                return [
                    'skill_id' => $skill->id,
                    'skill_name' => $skill->name,
                    'skill_code' => $skill->code,
                    'wage_per_day' => $rate?->wage_per_day,
                    'wage_per_month' => $rate?->wage_per_month,
                    'effective_from' => $rate?->effective_from?->format('Y-m-d'),
                ];
            })->values();
        }

        return Inertia::render('hr/skills/index', [
            'skills' => $skills,
            'branches' => $branches,
            'stats' => $stats,
            'activeBranchId' => $activeBranchId,
            'activeBranchName' => $activeBranchId ? Branch::find($activeBranchId)?->name : null,
            'filters' => $filters,
            'tab' => $tab,
            'wageZones' => $wageZones,
            'selectedWageZoneId' => $selectedWageZoneId,
            'selectedWageZone' => $selectedWageZone,
            'zoneRates' => $zoneRates,
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
        $meta = $this->skillDeletionMeta($skill);
        if (! $meta['can_delete']) {
            return redirect()->back()->with('error', $meta['block_reason']);
        }

        $this->logMasterDeleted($skill);
        $skill->delete();

        return redirect()->back()->with('success', __('Skill deleted successfully.'));
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

    public function saveWageRates(Request $request)
    {
        $companyUserIds = getCompanyAndUsersId();
        $activeBranchId = session('active_branch_id');

        $validated = $request->validate([
            'wage_zone_id' => 'required|exists:wage_zones,id',
            'rates' => 'required|array|min:1',
            'rates.*.skill_id' => 'required|exists:skills,id',
            'rates.*.wage_per_day' => 'nullable|numeric|min:0',
            'rates.*.wage_per_month' => 'nullable|numeric|min:0',
            'rates.*.effective_from' => 'nullable|date',
            'copy_to_all_branches' => 'nullable|boolean',
        ]);

        $zone = WageZone::query()
            ->whereIn('created_by', $companyUserIds)
            ->findOrFail($validated['wage_zone_id']);

        $copyToAllBranches = $request->boolean('copy_to_all_branches');
        $branchesUpdated = [];

        DB::transaction(function () use ($validated, $zone, $copyToAllBranches, $companyUserIds, $activeBranchId, &$branchesUpdated) {
            foreach ($validated['rates'] as $row) {
                $wages = WageZone::syncWageFields(
                    isset($row['wage_per_day']) && $row['wage_per_day'] !== '' ? (float) $row['wage_per_day'] : null,
                    isset($row['wage_per_month']) && $row['wage_per_month'] !== '' ? (float) $row['wage_per_month'] : null,
                    (int) $zone->working_days
                );

                $skillIds = $this->skillIdsForWageRateCopy(
                    (int) $row['skill_id'],
                    $copyToAllBranches,
                    $companyUserIds,
                    $activeBranchId ? (int) $activeBranchId : null
                );

                foreach ($skillIds as $skillId) {
                    if (! $wages['wage_per_day'] && ! $wages['wage_per_month']) {
                        SkillWageRate::query()
                            ->where('skill_id', $skillId)
                            ->where('wage_zone_id', $zone->id)
                            ->delete();

                        continue;
                    }

                    SkillWageRate::updateOrCreate(
                        [
                            'skill_id' => $skillId,
                            'wage_zone_id' => $zone->id,
                        ],
                        [
                            'wage_per_day' => $wages['wage_per_day'],
                            'wage_per_month' => $wages['wage_per_month'],
                            'effective_from' => $row['effective_from'] ?? null,
                            'created_by' => creatorId(),
                        ]
                    );

                    $branchId = Skill::query()->whereKey($skillId)->value('branch_id');
                    if ($branchId) {
                        $branchesUpdated[(int) $branchId] = true;
                    }
                }
            }
        });

        $branchCount = count($branchesUpdated);
        $message = $copyToAllBranches && $branchCount > 1
            ? __('Wage rates saved and copied to :count branches (matched by skill code).', ['count' => $branchCount])
            : __('Wage rates saved successfully.');

        return redirect()->back()->with('success', $message);
    }

    /**
     * @param  array<int, int>  $companyUserIds
     * @return array<int, int>
     */
    private function skillIdsForWageRateCopy(
        int $sourceSkillId,
        bool $copyToAllBranches,
        array $companyUserIds,
        ?int $activeBranchId
    ): array {
        $sourceSkill = Skill::query()
            ->whereIn('created_by', $companyUserIds)
            ->find($sourceSkillId);

        if (! $sourceSkill) {
            return [$sourceSkillId];
        }

        if ($activeBranchId && (int) $sourceSkill->branch_id !== $activeBranchId) {
            return [$sourceSkillId];
        }

        $skillIds = [$sourceSkillId];

        if (! $copyToAllBranches || ! $sourceSkill->code) {
            return $skillIds;
        }

        $otherSkillIds = Skill::query()
            ->whereIn('created_by', $companyUserIds)
            ->where('code', $sourceSkill->code)
            ->where('branch_id', '!=', $sourceSkill->branch_id)
            ->where('status', true)
            ->pluck('id')
            ->all();

        return array_values(array_unique([...$skillIds, ...$otherSkillIds]));
    }
}
