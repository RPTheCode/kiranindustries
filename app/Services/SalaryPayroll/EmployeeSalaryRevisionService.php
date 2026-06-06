<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryRevision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeSalaryRevisionService
{
    public function __construct(
        private SalaryStructureCalculator $calculator,
        private SalaryComponentAssignmentService $componentAssignment
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyForEmployee(int $employeeId): array
    {
        $joinDate = $this->joiningDateForEmployee($employeeId);

        $revisions = EmployeeSalaryRevision::where('employee_id', $employeeId)
            ->with('creator:id,name')
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get();

        return $revisions->values()->map(function (EmployeeSalaryRevision $revision, int $index) use ($revisions, $joinDate) {
            $from = $revision->effective_from->copy();
            if ($revision->revision_type === 'joining' && $joinDate->lt($revision->effective_from)) {
                $from = $joinDate->copy();
            }

            $next = $revisions->get($index + 1);
            $isActive = $revision->effective_to === null;

            // Show period end as the next revision start date (e.g. joining till 5 Jun, increment from 5 Jun)
            $displayTo = null;
            if ($next) {
                $displayTo = $next->effective_from->copy();
            } elseif ($revision->effective_to) {
                $displayTo = $revision->effective_to->copy()->addDay();
            }

            return [
                'id' => $revision->id,
                'monthly_gross' => (float) $revision->monthly_gross,
                'previous_gross' => $revision->previous_gross !== null ? (float) $revision->previous_gross : null,
                'increment_percentage' => $revision->increment_percentage !== null ? (float) $revision->increment_percentage : null,
                'increment_amount' => $revision->increment_amount !== null ? (float) $revision->increment_amount : null,
                'effective_from' => $from->format('d M Y'),
                'effective_to' => $displayTo?->format('d M Y'),
                'effective_from_raw' => $from->format('Y-m-d'),
                'effective_to_raw' => $displayTo?->format('Y-m-d'),
                'revision_type' => $revision->revision_type,
                'notes' => $revision->notes,
                'is_active' => $isActive,
                'created_by' => $revision->creator?->name,
                'created_at' => $revision->created_at?->format('Y-m-d H:i'),
            ];
        })->all();
    }

    /**
     * @param  array{
     *     revision_type?: string,
     *     effective_from?: string|null,
     *     notes?: string|null,
     *     previous_gross?: float|null,
     *     increment_percentage?: float|null,
     *     increment_amount?: float|null
     * }  $meta
     */
    public function applySalary(int $employeeId, array $split, array $meta = [], ?int $salaryId = null): EmployeeSalary
    {
        return DB::transaction(function () use ($employeeId, $split, $meta, $salaryId) {
            $effectiveFrom = Carbon::parse($meta['effective_from'] ?? now()->toDateString())->startOfDay();
            $existing = EmployeeSalary::where('employee_id', $employeeId)->first();
            $previousGross = $meta['previous_gross'] ?? ($existing ? (float) ($existing->monthly_gross ?? $existing->basic_salary ?? 0) : null);

            if ($existing && $previousGross > 0) {
                $this->validateEffectiveFrom($employeeId, $effectiveFrom);
                $this->repairJoiningRevisionDate($employeeId);

                $hasRevisions = EmployeeSalaryRevision::where('employee_id', $employeeId)->exists();
                if (! $hasRevisions) {
                    $this->archiveExistingSalary($existing, $effectiveFrom->copy()->subDay());
                }
                $this->closeActiveRevision($employeeId, $effectiveFrom);
            }

            $salary = $this->persistCurrentSalary($employeeId, $split, $salaryId);

            EmployeeSalaryRevision::create([
                'employee_id' => $employeeId,
                'monthly_gross' => $split['monthly_gross'],
                'basic_salary' => $split['basic_amount'] ?? collect($split['breakdown'])->firstWhere('name', 'BASIC')['amount'] ?? $split['monthly_gross'],
                'components' => $split['components'],
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => null,
                'revision_type' => $meta['revision_type'] ?? ($existing ? 'increment' : 'joining'),
                'previous_gross' => $previousGross > 0 ? $previousGross : null,
                'increment_percentage' => $meta['increment_percentage'] ?? null,
                'increment_amount' => $previousGross > 0
                    ? ($meta['increment_amount'] ?? round($split['monthly_gross'] - $previousGross, 2))
                    : null,
                'notes' => $meta['notes'] ?? null,
                'created_by' => creatorId(),
            ]);

            return $salary;
        });
    }

    /**
     * @param  array{
     *     increment_mode: string,
     *     increment_value: float,
     *     category_id?: int|null,
     *     department_id?: int|null,
     *     shift_id?: int|null,
     *     search?: string|null
     * }  $filters
     * @return array{employees: array<int, array<string, mixed>>, count: int}
     */
    public function previewBulkIncrement(array $filters, Collection $components, ?int $branchId): array
    {
        $employees = $this->employeesForBulk($filters, $branchId);
        $mode = $filters['increment_mode'];
        $value = (float) $filters['increment_value'];

        $preview = [];
        foreach ($employees as $user) {
            $currentGross = (float) $this->salaryRecordValue($user, 'monthly_gross', 0);
            if ($currentGross <= 0) {
                continue;
            }

            $newGross = $this->calculateNewGross($currentGross, $mode, $value);
            $options = $this->statutoryOptionsForUser($user);
            $employeeComponents = $this->componentAssignment->resolveForEmployee($components, $user->employee);
            $split = $this->calculator->splitFromGross($newGross, $employeeComponents, $options);

            $incrementAmount = round($newGross - $currentGross, 2);
            $actualPercentage = $currentGross > 0
                ? round(($incrementAmount / $currentGross) * 100, 2)
                : null;

            $preview[] = [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->employee?->employee_id,
                'category' => $user->employee?->category?->name,
                'department' => $user->employee?->department?->name,
                'shift' => $user->employee?->shift?->name,
                'current_gross' => $currentGross,
                'new_gross' => $newGross,
                'increment_amount' => $incrementAmount,
                'increment_percentage' => $mode === 'percentage'
                    ? $value
                    : null,
                'actual_increment_percentage' => $actualPercentage,
                'salary_id' => $this->salaryRecordValue($user, 'id'),
            ];
        }

        return ['employees' => $preview, 'count' => count($preview)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyBulkIncrement(array $filters, Collection $components, ?int $branchId): int
    {
        $preview = $this->previewBulkIncrement($filters, $components, $branchId);
        $effectiveFrom = Carbon::parse($filters['effective_from'])->startOfDay();
        $mode = $filters['increment_mode'];
        $value = (float) $filters['increment_value'];
        $saved = 0;

        DB::transaction(function () use ($preview, $components, $effectiveFrom, $mode, $value, $filters, &$saved) {
            foreach ($preview['employees'] as $row) {
                $user = User::with('employee')->find($row['id']);
                if (! $user) {
                    continue;
                }

                $options = $this->statutoryOptionsForUser($user);
                $employeeComponents = $this->componentAssignment->resolveForEmployee($components, $user->employee);
                $split = $this->calculator->splitFromGross((float) $row['new_gross'], $employeeComponents, $options);

                $incrementPct = $mode === 'percentage' ? $value : ($row['actual_increment_percentage'] ?? null);

                $this->applySalary((int) $row['id'], $split, [
                    'revision_type' => 'increment',
                    'effective_from' => $effectiveFrom->toDateString(),
                    'notes' => $filters['notes'] ?? __('Bulk salary increment'),
                    'previous_gross' => (float) $row['current_gross'],
                    'increment_percentage' => $incrementPct,
                    'increment_amount' => (float) $row['increment_amount'],
                ], $row['salary_id'] ?? null);

                $saved++;
            }
        });

        return $saved;
    }

    public function joiningDateForEmployee(int $employeeId): Carbon
    {
        $user = User::with('employee')->find($employeeId);
        $joining = $user?->employee?->date_of_joining;

        if ($joining) {
            return Carbon::parse($joining)->startOfDay();
        }

        $salary = EmployeeSalary::where('employee_id', $employeeId)->first();

        return Carbon::parse($salary?->created_at ?? now())->startOfDay();
    }

    public function validateEffectiveFrom(int $employeeId, Carbon $effectiveFrom): void
    {
        if ($effectiveFrom->lt(Carbon::today())) {
            throw new \InvalidArgumentException(
                __('Increment date must be today or a future date.')
            );
        }

        $active = EmployeeSalaryRevision::where('employee_id', $employeeId)
            ->whereNull('effective_to')
            ->first();

        if ($active && $effectiveFrom->lt($active->effective_from)) {
            throw new \InvalidArgumentException(
                __('Increment date cannot be before current salary start date (:date).', [
                    'date' => $active->effective_from->format('d M Y'),
                ])
            );
        }
    }

    public function calculateNewGross(float $currentGross, string $mode, float $value): float
    {
        if ($currentGross <= 0) {
            return 0;
        }

        $newGross = match ($mode) {
            'percentage' => $currentGross * (1 + ($value / 100)),
            'fixed' => $currentGross + $value,
            'set_gross' => $value,
            default => $currentGross,
        };

        return max(0, round($newGross, 0));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function employeesForBulk(array $filters, ?int $branchId): Collection
    {
        $query = User::query()
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereHas('employee', function ($q) use ($branchId, $filters) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
                if (! empty($filters['category_id']) && $filters['category_id'] !== 'all') {
                    $q->where('category_id', $filters['category_id']);
                }
                if (! empty($filters['department_id']) && $filters['department_id'] !== 'all') {
                    $q->where('department_id', $filters['department_id']);
                }
                if (! empty($filters['shift_id']) && $filters['shift_id'] !== 'all') {
                    $q->where('shift_id', $filters['shift_id']);
                }
            })
            ->with([
                'employee.category:id,name',
                'employee.department:id,name',
                'employee.shift:id,name',
            ]);

        if (! empty($filters['search'])) {
            $terms = $this->parseSearchTerms($filters['search']);
            if (! empty($terms)) {
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere(function ($sub) use ($term) {
                            if (preg_match('/^\d+$/', $term)) {
                                $sub->whereHas('employee', fn ($eq) => $eq->where('employee_id', $term));
                            } else {
                                $sub->where('name', 'like', "%{$term}%")
                                    ->orWhereHas('employee', fn ($eq) => $eq->where('employee_id', 'like', "%{$term}%"));
                            }
                        });
                    }
                });
            }
        }

        $users = $query->orderBy('name')->get();
        $salaries = EmployeeSalary::whereIn('employee_id', $users->pluck('id'))->get()->keyBy('employee_id');

        return $users->map(function ($user) use ($salaries) {
            $salary = $salaries->get($user->id);
            $user->salary_record = $salary ? [
                'id' => $salary->id,
                'monthly_gross' => (float) ($salary->monthly_gross ?? $salary->basic_salary ?? 0),
            ] : null;

            return $user;
        })->filter(fn ($user) => ($user->salary_record['monthly_gross'] ?? 0) > 0);
    }

    private function closeActiveRevision(int $employeeId, Carbon $incrementDate): void
    {
        $endDate = $incrementDate->copy()->subDay();

        EmployeeSalaryRevision::where('employee_id', $employeeId)
            ->whereNull('effective_to')
            ->each(function (EmployeeSalaryRevision $revision) use ($endDate) {
                $startDate = Carbon::parse($revision->effective_from)->startOfDay();
                $actualEnd = $endDate->lt($startDate) ? $startDate->copy() : $endDate->copy();
                $revision->update(['effective_to' => $actualEnd->toDateString()]);
            });
    }

    private function repairJoiningRevisionDate(int $employeeId): void
    {
        $joinDate = $this->joiningDateForEmployee($employeeId);

        EmployeeSalaryRevision::where('employee_id', $employeeId)
            ->where('revision_type', 'joining')
            ->update(['effective_from' => $joinDate->toDateString()]);
    }

    private function archiveExistingSalary(EmployeeSalary $salary, Carbon $proposedEndDate): void
    {
        $joinDate = $this->joiningDateForEmployee((int) $salary->employee_id);
        $endDate = $proposedEndDate->copy();
        if ($endDate->lt($joinDate)) {
            $endDate = $joinDate->copy();
        }

        EmployeeSalaryRevision::create([
            'employee_id' => $salary->employee_id,
            'monthly_gross' => (float) ($salary->monthly_gross ?? $salary->basic_salary ?? 0),
            'basic_salary' => $salary->basic_salary,
            'components' => $salary->components,
            'effective_from' => $joinDate->toDateString(),
            'effective_to' => $endDate->toDateString(),
            'revision_type' => 'joining',
            'notes' => __('Salary from joining date'),
            'created_by' => $salary->created_by ?? creatorId(),
        ]);
    }

    private function persistCurrentSalary(int $employeeId, array $split, ?int $salaryId = null): EmployeeSalary
    {
        $basicAmount = $split['basic_amount'] ?? collect($split['breakdown'])->firstWhere('name', 'BASIC')['amount'] ?? $split['monthly_gross'];

        $payload = [
            'employee_id' => $employeeId,
            'monthly_gross' => $split['monthly_gross'],
            'basic_salary' => $basicAmount,
            'components' => $split['components'],
            'is_active' => true,
            'calculation_status' => 'calculated',
        ];

        if ($salaryId) {
            $record = EmployeeSalary::where('id', $salaryId)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->firstOrFail();
            $record->update($payload);

            return $record;
        }

        $existing = EmployeeSalary::where('employee_id', $employeeId)->first();
        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return EmployeeSalary::create(array_merge($payload, ['created_by' => creatorId()]));
    }

    private function statutoryOptionsForUser(User $user): array
    {
        $profile = $user->employee;

        return [
            'apply_pf' => (bool) ($profile?->pf_flag ?? false),
            'apply_esi' => (bool) ($profile?->esic_flag ?? false),
        ];
    }

    private function salaryRecordValue(User $user, string $key, mixed $default = null): mixed
    {
        $record = $user->salary_record ?? null;

        if (is_array($record)) {
            return $record[$key] ?? $default;
        }

        if (is_object($record)) {
            return $record->{$key} ?? $default;
        }

        return $default;
    }

    /**
     * @return array<int, string>
     */
    private function parseSearchTerms(string $search): array
    {
        $parts = preg_split('/[\s,;]+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            static fn (string $term) => trim($term),
            array_filter($parts, static fn ($term) => is_string($term) && trim($term) !== '')
        )));
    }
}
