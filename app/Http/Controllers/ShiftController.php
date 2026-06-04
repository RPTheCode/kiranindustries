<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Services\ActivityLogger;
use App\Services\ShiftDutyRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ShiftController extends Controller
{
    use Concerns\LogsMasterCrud;

    /**
     * Shift cannot be deleted while allocated to employees or referenced in attendance.
     *
     * @return array{can_delete: bool, employees_count: int, attendance_records_count: int, production_entries_count: int, block_reason: ?string}
     */
    private function shiftDeletionMeta(Shift $shift): array
    {
        $employeesCount = countEmployeesInBranchForMaster('shift_id', (int) $shift->id, (int) $shift->branch_id);
        $attendanceCount = (int) DB::table('attendance_records')->where('shift_id', $shift->id)->count();
        $productionCount = (int) DB::table('daily_production_attendance_entries')->where('shift_id', $shift->id)->count();

        $blockReason = null;
        if ($employeesCount > 0) {
            $blockReason = __('Cannot delete: :count employee(s) in this branch use this shift.', ['count' => $employeesCount]);
        } elseif ($attendanceCount > 0) {
            $blockReason = __('Cannot delete: :count attendance record(s) use this shift.', ['count' => $attendanceCount]);
        } elseif ($productionCount > 0) {
            $blockReason = __('Cannot delete: :count production attendance entries use this shift.', ['count' => $productionCount]);
        }

        return [
            'can_delete' => $blockReason === null,
            'employees_count' => $employeesCount,
            'attendance_records_count' => $attendanceCount,
            'production_entries_count' => $productionCount,
            'block_reason' => $blockReason,
        ];
    }

    private function calculateShiftDurationMinutes(string $startTime, string $endTime): int
    {
        return ShiftDutyRuleService::calculateDurationMinutes($startTime, $endTime);
    }

    private function buildDynamicDutyRules(string $startTime, string $endTime): array
    {
        return ShiftDutyRuleService::buildDynamicDutyRules($startTime, $endTime);
    }

    private function normalizeSlots(array $slots): array
    {
        return collect($slots)->map(function ($slotData, $slotIndex) {
            $slotData['priority'] = $slotData['priority'] ?? ($slotIndex + 1);
            $slotData['grace_before_in'] = isset($slotData['grace_before_in']) ? (int) $slotData['grace_before_in'] : 0;
            $slotData['grace_after_out'] = isset($slotData['grace_after_out']) ? (int) $slotData['grace_after_out'] : 0;

            $startTime = $slotData['start_time'] ?? '09:00';
            $endTime = $slotData['end_time'] ?? '18:00';
            $dutyRules = $slotData['duty_rules'] ?? [];

            if (empty($dutyRules)) {
                $dutyRules = ShiftDutyRuleService::buildDynamicDutyRules($startTime, $endTime);
            } else {
                $dutyRules = ShiftDutyRuleService::finalizeDutyRules($dutyRules, $startTime, $endTime);
            }

            $slotData['duty_rules'] = collect($dutyRules)->map(function ($ruleData, $ruleIndex) {
                unset($ruleData['id'], $ruleData['shift_slot_id'], $ruleData['created_at'], $ruleData['updated_at']);
                $ruleData['priority'] = $ruleData['priority'] ?? ($ruleIndex + 1);

                return $ruleData;
            })->values()->all();

            return $slotData;
        })->all();
    }

    private function validateDutyRules(array $slots): ?string
    {
        foreach ($slots as $slotData) {
            $slotName = $slotData['slot_name'] ?? 'Slot';
            $halfDayRule = collect($slotData['duty_rules'] ?? [])->first(fn($rule) => (float) ($rule['duty_value'] ?? -1) === 0.5);
            $fullDayRule = collect($slotData['duty_rules'] ?? [])->first(fn($rule) => (float) ($rule['duty_value'] ?? -1) === 1.0);

            if (!$halfDayRule || !$fullDayRule) {
                return __("Duty rules are incomplete for slot ':slot'.", ['slot' => $slotName]);
            }

            if ((int) $halfDayRule['min_minutes'] >= (int) $fullDayRule['min_minutes']) {
                return __("Half day min hours must be less than full day min hours for slot ':slot'.", ['slot' => $slotName]);
            }
        }

        return null;
    }

    private function persistShiftSlots(Shift $shift, array $slots): void
    {
        ActivityLogger::withoutLogging(function () use ($shift, $slots) {
            $shift->slots()->delete();

            foreach ($slots as $slotData) {
                $slot = $shift->slots()->create(collect($slotData)->except('duty_rules')->toArray());

                foreach ($slotData['duty_rules'] as $ruleData) {
                    $slot->dutyRules()->create($ruleData);
                }
            }
        });
    }

    public function index(Request $request)
    {
        $activeBranchId = session('active_branch_id');

        $baseQuery = Shift::withPermissionCheck()
            ->withoutGlobalScope(BranchScope::class);

        $branchId = $request->input('branch_id') ?? $activeBranchId;

        $statsQuery = clone $baseQuery;
        if ($branchId && $branchId !== 'all') {
            $statsQuery->where('branch_id', $branchId);
        }

        $statsShiftIds = (clone $statsQuery)->pluck('id');

        $employeeStatsQuery = Employee::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereNotNull('shift_id')
            ->whereIn('shift_id', $statsShiftIds->isEmpty() ? [-1] : $statsShiftIds);

        if ($branchId && $branchId !== 'all') {
            $employeeStatsQuery->where('branch_id', $branchId);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', 'active')->count(),
            'inactive' => (clone $statsQuery)->where('status', 'inactive')->count(),
            'total_employees' => (int) $employeeStatsQuery->count(),
            'branch_id' => ($branchId && $branchId !== 'all') ? (string) $branchId : null,
        ];

        $query = (clone $baseQuery)->with(['creator', 'branch', 'slots.dutyRules']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $statusFilter = $request->input('status', 'active');
        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $shiftType = $request->input('shift_type', 'all');
        if ($shiftType !== 'all' && $shiftType !== '') {
            match ($shiftType) {
                'multi' => $query->where('is_multi', true),
                'fixed' => $query->where('is_multi', false),
                'night' => $query->where('is_night_shift', true),
                'day' => $query->where(function ($q) {
                    $q->where('is_night_shift', false)->orWhereNull('is_night_shift');
                }),
                default => null,
            };
        }

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $shifts = $query->withCount(['employees', 'attendanceRecords'])->paginate($request->per_page ?? 10);

        $shiftIds = $shifts->getCollection()->pluck('id');
        $productionCounts = $shiftIds->isEmpty()
            ? collect()
            : DB::table('daily_production_attendance_entries')
                ->whereIn('shift_id', $shiftIds)
                ->selectRaw('shift_id, COUNT(*) as aggregate')
                ->groupBy('shift_id')
                ->pluck('aggregate', 'shift_id');

        $shifts->getCollection()->transform(function (Shift $shift) use ($productionCounts) {
            $productionCount = (int) ($productionCounts[$shift->id] ?? 0);
            $employeesCount = countEmployeesInBranchForMaster('shift_id', (int) $shift->id, (int) $shift->branch_id);
            $attendanceCount = (int) $shift->attendance_records_count;

            $canDelete = $employeesCount === 0 && $attendanceCount === 0 && $productionCount === 0;
            $blockReason = null;
            if ($employeesCount > 0) {
                $blockReason = __('Assigned to :count employee(s) — reassign before delete.', ['count' => $employeesCount]);
            } elseif ($attendanceCount > 0) {
                $blockReason = __('Used in :count attendance record(s) — cannot delete.', ['count' => $attendanceCount]);
            } elseif ($productionCount > 0) {
                $blockReason = __('Used in :count production entries — cannot delete.', ['count' => $productionCount]);
            }

            applyMasterDeleteAttributes($shift, $canDelete, $blockReason, $employeesCount);
            $shift->setAttribute('production_entries_count', $productionCount);
            $shift->setAttribute('attendance_records_count', $attendanceCount);

            return $shift;
        });

        $branches = Branch::all();

        $filters = $request->all(['search', 'status', 'shift_type', 'branch_id', 'sort_field', 'sort_direction', 'per_page']);
        if (! isset($filters['status']) || $filters['status'] === null || $filters['status'] === '') {
            $filters['status'] = 'active';
        }
        if (! isset($filters['branch_id'])) {
            $filters['branch_id'] = $branchId ? (string) $branchId : 'all';
        }

        return Inertia::render('hr/shifts/index', [
            'shifts' => $shifts,
            'branches' => $branches,
            'stats' => $stats,
            'activeBranchId' => $activeBranchId,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('Shift Store Request Raw:', $request->all());

        // Determine branch for this shift (request takes priority, fall back to session)
        $targetBranchId = $request->input('branch_id') ?? session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('shifts')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                    return $query->where('branch_id', $targetBranchId)
                        ->whereIn('created_by', $companyUserIds);
                })
            ],
            'description' => 'nullable|string',
            'is_multi' => 'boolean',
            'status' => 'nullable|in:active,inactive',
            'slots' => 'required|array|min:1',
            'slots.*.slot_name' => 'required|string|max:255',
            'slots.*.start_time' => 'required|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'slots.*.end_time' => 'required|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'slots.*.grace_before_in' => 'nullable|integer|min:0',
            'slots.*.grace_after_out' => 'nullable|integer|min:0',
            'slots.*.priority' => 'nullable|integer',
            'slots.*.duty_rules' => 'required|array|min:1',
            'slots.*.duty_rules.*.rule_name' => 'required|string|max:255',
            'slots.*.duty_rules.*.min_minutes' => 'required|integer|min:0',
            'slots.*.duty_rules.*.max_minutes' => 'required|integer|min:0',
            'slots.*.duty_rules.*.status' => 'required|string|max:20',
            'slots.*.duty_rules.*.duty_value' => 'required|numeric|min:0',
            'slots.*.duty_rules.*.color' => 'nullable|string|max:50',
            'slots.*.duty_rules.*.priority' => 'nullable|integer',
        ]);

        $validated['branch_id'] = session('active_branch_id');
        if (!$validated['branch_id']) {
            return redirect()->back()->with('error', __('No active branch selected.'));
        }

        $validated['created_by'] = creatorId();
        $validated['status'] = $validated['status'] ?? 'active';

        \Log::info('Shift Store Data:', [
            'request' => $request->all(),
            'validated' => $validated
        ]);

        // Check for duplicate slot timings and names
        if ($request->is_multi && $request->has('slots')) {
            $slots = collect($request->slots);

            // Check for duplicate timings
            $timings = $slots->map(fn($s) => trim($s['start_time']) . '-' . trim($s['end_time']));
            if ($timings->count() !== $timings->unique()->count()) {
                return redirect()->back()->withErrors(['slots' => __('Duplicate slot timings are not allowed within the same shift.')])->withInput();
            }

            // Check for duplicate slot names
            $names = $slots->map(fn($s) => strtolower(trim($s['slot_name'])));
            if ($names->count() !== $names->unique()->count()) {
                return redirect()->back()->withErrors(['slots' => __('Duplicate slot names are not allowed within the same shift.')])->withInput();
            }
        }

        $normalizedSlots = $this->normalizeSlots($request->input('slots', []));
        if ($dutyRuleError = $this->validateDutyRules($normalizedSlots)) {
            return redirect()->back()->withErrors(['slots' => $dutyRuleError])->withInput();
        }

        DB::beginTransaction();
        try {
            $shift = Shift::create(collect($validated)->except('slots')->toArray());

            $this->persistShiftSlots($shift, $normalizedSlots);
            
            DB::commit();
            $this->logMasterCreated($shift);
            return redirect()->back()->with('success', __('Shift created successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to create shift'));
        }
    }

    public function update(Request $request, $shiftId)
    {
        \Log::info('Shift Update Request Raw:', array_merge(['shift_id' => $shiftId], $request->all()));

        $shift = Shift::where('id', $shiftId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($shift) {
            try {
                // Determine branch for this shift (use the shift's own branch_id for uniqueness scoping)
                $targetBranchId = $request->input('branch_id') ?? $shift->branch_id ?? session('active_branch_id');
                $companyUserIds = getCompanyAndUsersId();

                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'short_code' => [
                        'required',
                        'string',
                        'max:50',
                        Rule::unique('shifts')->where(function ($query) use ($targetBranchId, $companyUserIds) {
                            return $query->where('branch_id', $targetBranchId)
                                ->whereIn('created_by', $companyUserIds);
                        })->ignore($shiftId)
                    ],
                    'description' => 'nullable|string',
                    'is_multi' => 'boolean',
                    'status' => 'nullable|in:active,inactive',
                    'slots' => 'required|array|min:1',
                    'slots.*.slot_name' => 'required|string|max:255',
                    'slots.*.start_time' => 'required|regex:/^\d{2}:\d{2}(:\d{2})?$/',
                    'slots.*.end_time' => 'required|regex:/^\d{2}:\d{2}(:\d{2})?$/',
                    'slots.*.grace_before_in' => 'nullable|integer|min:0',
                    'slots.*.grace_after_out' => 'nullable|integer|min:0',
                    'slots.*.priority' => 'nullable|integer',
                    'slots.*.duty_rules' => 'required|array|min:1',
                    'slots.*.duty_rules.*.rule_name' => 'required|string|max:255',
                    'slots.*.duty_rules.*.min_minutes' => 'required|integer|min:0',
                    'slots.*.duty_rules.*.max_minutes' => 'required|integer|min:0',
                    'slots.*.duty_rules.*.status' => 'required|string|max:20',
                    'slots.*.duty_rules.*.duty_value' => 'required|numeric|min:0',
                    'slots.*.duty_rules.*.color' => 'nullable|string|max:50',
                    'slots.*.duty_rules.*.priority' => 'nullable|integer',
                ]);

                $validated['branch_id'] = session('active_branch_id');
                if (!$validated['branch_id']) {
                    return redirect()->back()->with('error', __('No active branch selected.'));
                }

                \Log::info('Shift Update Data:', [
                    'shift_id' => $shiftId,
                    'request' => $request->all(),
                    'validated' => $validated
                ]);

                // Check for duplicate slot timings and names
                if ($request->is_multi && $request->has('slots')) {
                    $slots = collect($request->slots);

                    // Check for duplicate timings
                    $timings = $slots->map(fn($s) => trim($s['start_time']) . '-' . trim($s['end_time']));
                    if ($timings->count() !== $timings->unique()->count()) {
                        return redirect()->back()->withErrors(['slots' => __('Duplicate slot timings are not allowed within the same shift.')])->withInput();
                    }

                    // Check for duplicate slot names
                    $names = $slots->map(fn($s) => strtolower(trim($s['slot_name'])));
                    if ($names->count() !== $names->unique()->count()) {
                        return redirect()->back()->withErrors(['slots' => __('Duplicate slot names are not allowed within the same shift.')])->withInput();
                    }
                }

                $normalizedSlots = $this->normalizeSlots($request->input('slots', []));
                if ($dutyRuleError = $this->validateDutyRules($normalizedSlots)) {
                    return redirect()->back()->withErrors(['slots' => $dutyRuleError])->withInput();
                }

                DB::beginTransaction();
                try {
                    $shift->update(collect($validated)->except('slots')->toArray());

                    $this->persistShiftSlots($shift, $normalizedSlots);

                    DB::commit();
                    $this->logMasterUpdated($shift);
                    return redirect()->back()->with('success', __('Shift updated successfully'));
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update shift'));
            }
        } else {
            return redirect()->back()->with('error', __('Shift Not Found.'));
        }
    }

    public function destroy($shiftId)
    {
        $shift = Shift::where('id', $shiftId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($shift) {
            $meta = $this->shiftDeletionMeta($shift);
            if (!$meta['can_delete']) {
                return redirect()->back()->with('error', $meta['block_reason']);
            }

            try {
                $this->logMasterDeleted($shift);
                ActivityLogger::withoutLogging(fn () => $shift->delete());

                return redirect()->back()->with('success', __('Shift deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete shift'));
            }
        } else {
            return redirect()->back()->with('error', __('Shift Not Found.'));
        }
    }

    public function toggleStatus($shiftId)
    {
        $shift = Shift::where('id', $shiftId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if ($shift) {
            try {
                $shift->status = $shift->status === 'active' ? 'inactive' : 'active';
                $shift->save();
                $this->logMasterUpdated($shift);

                return redirect()->back()->with('success', __('Shift status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update shift status'));
            }
        } else {
            return redirect()->back()->with('error', __('Shift Not Found.'));
        }
    }

    public function copyToBranches(Request $request, $shiftId)
    {
        $request->validate([
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $sourceShift = Shift::with(['slots.dutyRules'])->where('id', $shiftId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$sourceShift) {
            return redirect()->back()->with('error', __('Shift Not Found.'));
        }

        $branchIds = $request->branch_ids;
        $successCount = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            foreach ($branchIds as $branchId) {
                // Check if shift with same short code already exists in that branch
                $existing = Shift::where('branch_id', $branchId)
                    ->where('short_code', $sourceShift->short_code)
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->first();

                if ($existing) {
                    $branch = Branch::find($branchId);
                    $warnings[] = __("Shift code ':code' already exists in Branch ':branch'. Skipped.", [
                        'code' => $sourceShift->short_code,
                        'branch' => $branch ? $branch->name : '#' . $branchId
                    ]);
                    continue;
                }

                // Clone the shift record
                $clonedShift = $sourceShift->replicate();
                $clonedShift->branch_id = $branchId;
                $clonedShift->created_by = Auth::id(); // Assign current logged in user
                $clonedShift->save();

                // Clone associated slots and duty rules
                foreach ($sourceShift->slots as $slot) {
                    $clonedSlot = $slot->replicate();
                    $clonedSlot->shift_id = $clonedShift->id;
                    $clonedSlot->save();

                    foreach ($slot->dutyRules as $rule) {
                        $clonedRule = $rule->replicate();
                        $clonedRule->shift_slot_id = $clonedSlot->id;
                        $clonedRule->save();
                    }
                }

                $successCount++;
            }

            DB::commit();

            if ($successCount === 0) {
                $errorMessage = count($warnings) > 0 ? implode(' ', $warnings) : __('No shifts were copied.');
                return redirect()->back()->with('error', $errorMessage);
            }

            $successMessage = __(':count shifts successfully copied to selected branches.', ['count' => $successCount]);
            if (count($warnings) > 0) {
                $successMessage .= ' ' . implode(' ', $warnings);
            }

            return redirect()->back()->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to copy shift to branches.'));
        }
    }

    public function bulkCopyToBranches(Request $request)
    {
        $request->validate([
            'shift_ids' => 'required|array|min:1',
            'shift_ids.*' => 'required|integer|exists:shifts,id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'required|integer|exists:branches,id',
        ]);

        $shiftIds = $request->shift_ids;
        $branchIds = $request->branch_ids;
        $successCount = 0;
        $warnings = [];

        $sourceShifts = Shift::with(['slots.dutyRules'])->whereIn('id', $shiftIds)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        if ($sourceShifts->isEmpty()) {
            return redirect()->back()->with('error', __('Shifts Not Found.'));
        }

        DB::beginTransaction();
        try {
            foreach ($sourceShifts as $sourceShift) {
                foreach ($branchIds as $branchId) {
                    // Check if shift with same short code already exists in that branch
                    $existing = Shift::where('branch_id', $branchId)
                        ->where('short_code', $sourceShift->short_code)
                        ->whereIn('created_by', getCompanyAndUsersId())
                        ->first();

                    if ($existing) {
                        $branch = Branch::find($branchId);
                        $warnings[] = __("Shift ':name' with code ':code' already exists in Branch ':branch'. Skipped.", [
                            'name' => $sourceShift->name,
                            'code' => $sourceShift->short_code,
                            'branch' => $branch ? $branch->name : '#' . $branchId
                        ]);
                        continue;
                    }

                    // Clone the shift record
                    $clonedShift = $sourceShift->replicate();
                    $clonedShift->branch_id = $branchId;
                    $clonedShift->created_by = Auth::id(); // Assign current logged in user
                    $clonedShift->save();

                    // Clone associated slots and duty rules
                    foreach ($sourceShift->slots as $slot) {
                        $clonedSlot = $slot->replicate();
                        $clonedSlot->shift_id = $clonedShift->id;
                        $clonedSlot->save();

                        foreach ($slot->dutyRules as $rule) {
                            $clonedRule = $rule->replicate();
                            $clonedRule->shift_slot_id = $clonedSlot->id;
                            $clonedRule->save();
                        }
                    }

                    $successCount++;
                }
            }

            DB::commit();

            if ($successCount === 0) {
                $errorMessage = count($warnings) > 0 ? implode(' ', $warnings) : __('No shifts were copied.');
                return redirect()->back()->with('error', $errorMessage);
            }

            $successMessage = __(':count shifts successfully copied to selected branches.', ['count' => $successCount]);
            if (count($warnings) > 0) {
                $successMessage .= ' ' . implode(' ', $warnings);
            }

            return redirect()->back()->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to copy shifts to branches.'));
        }
    }
}

