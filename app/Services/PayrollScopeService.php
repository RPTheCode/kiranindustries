<?php

namespace App\Services;

use App\Models\BiometricAttendance;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\Carbon;

class PayrollScopeService
{
    public static function defaultScope(): array
    {
        return [
            'branch_id' => null,
            'department_ids' => [],
            'shift_ids' => [],
            'category_ids' => [],
            'designation_ids' => [],
            'skill_ids' => [],
            'employee_mode' => 'all',
            'selected_employee_ids' => [],
        ];
    }

    public static function normalizeScopeFilters(array $input): array
    {
        $departmentIds = $input['department_ids'] ?? [];
        if (empty($departmentIds) && ! empty($input['department_id'])) {
            $departmentIds = [(int) $input['department_id']];
        }

        $selectedIds = $input['selected_employee_ids'] ?? [];
        $employeeMode = $input['employee_mode'] ?? 'all';
        if ($employeeMode === 'selected' && empty($selectedIds)) {
            $employeeMode = 'all';
        }

        return [
            'branch_id' => ! empty($input['branch_id']) ? (int) $input['branch_id'] : null,
            'department_ids' => array_values(array_unique(array_filter(array_map('intval', (array) $departmentIds)))),
            'shift_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($input['shift_ids'] ?? []))))),
            'category_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($input['category_ids'] ?? []))))),
            'designation_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($input['designation_ids'] ?? []))))),
            'skill_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($input['skill_ids'] ?? []))))),
            'employee_mode' => in_array($employeeMode, ['all', 'selected'], true) ? $employeeMode : 'all',
            'selected_employee_ids' => array_values(array_unique(array_filter(array_map('intval', (array) $selectedIds)))),
        ];
    }

    public static function hashScopeFilters(array $scope): string
    {
        $normalized = self::normalizeScopeFilters($scope);
        ksort($normalized);
        foreach (['department_ids', 'shift_ids', 'category_ids', 'designation_ids', 'skill_ids', 'selected_employee_ids'] as $key) {
            sort($normalized[$key]);
        }

        return hash('sha256', json_encode($normalized));
    }

    public static function syncRunColumnsFromScope(array &$data, array $scope): void
    {
        $scope = self::normalizeScopeFilters($scope);
        $data['scope_filters'] = $scope;
        $data['scope_filters_hash'] = self::hashScopeFilters($scope);
        $data['branch_id'] = $scope['branch_id'] ?? ($data['branch_id'] ?? null);
        $data['department_id'] = ! empty($scope['department_ids']) ? $scope['department_ids'][0] : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\User>
     */
    public static function employeesQueryForScope(
        array $scope,
        $periodStart,
        $periodEnd,
        ?string $salaryCalculationType = 'basic_pay'
    ) {
        $scope = self::normalizeScopeFilters($scope);
        $startDate = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->endOfDay();
        $branchId = $scope['branch_id'];

        $query = User::withoutGlobalScopes()
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId());

        if ($branchId) {
            $query->where(function ($outer) use ($branchId, $startDate, $endDate) {
                $outer->whereHas('employee', function ($q) use ($branchId) {
                    $q->withoutGlobalScopes()->where('branch_id', $branchId);
                })
                    ->orWhereHas('employee', function ($q) use ($branchId, $startDate, $endDate) {
                        $q->withoutGlobalScopes()
                            ->whereHas('biometricAttendances', function ($bq) use ($branchId, $startDate, $endDate) {
                                $bq->where('branch_id', $branchId)
                                    ->whereBetween('attendance_date', [$startDate, $endDate]);
                            });
                    });
            });
        }

        if (! empty($scope['department_ids'])) {
            $query->whereHas('employee', function ($q) use ($scope) {
                $q->withoutGlobalScopes()->whereIn('department_id', $scope['department_ids']);
            });
        }

        if (! empty($scope['shift_ids'])) {
            $query->whereHas('employee', function ($q) use ($scope) {
                $q->withoutGlobalScopes()->whereIn('shift_id', $scope['shift_ids']);
            });
        }

        if (! empty($scope['category_ids'])) {
            $query->whereHas('employee', function ($q) use ($scope) {
                $q->withoutGlobalScopes()->whereIn('category_id', $scope['category_ids']);
            });
        }

        if (! empty($scope['designation_ids'])) {
            $query->whereHas('employee', function ($q) use ($scope) {
                $q->withoutGlobalScopes()->whereIn('designation_id', $scope['designation_ids']);
            });
        }

        if (! empty($scope['skill_ids'])) {
            $query->whereHas('employee', function ($q) use ($scope) {
                $q->withoutGlobalScopes()->where(function ($sq) use ($scope) {
                    foreach ($scope['skill_ids'] as $skillId) {
                        $sq->orWhereJsonContains('skill_id', (int) $skillId);
                    }
                });
            });
        }

        if ($scope['employee_mode'] === 'selected' && ! empty($scope['selected_employee_ids'])) {
            $query->whereIn('id', $scope['selected_employee_ids']);
        }

        if ($salaryCalculationType === 'minimum_wages') {
            $query->whereHas('employeeSalary', function ($q) {
                $q->where('minimum_wages', '>', 0)->where('is_active', true);
            });
        }

        return $query;
    }

    public static function previewEmployees(
        array $scope,
        $periodStart,
        $periodEnd,
        ?string $salaryCalculationType = 'basic_pay',
        ?int $runBranchId = null
    ): array {
        if (empty($scope['branch_id']) && $runBranchId) {
            $scope['branch_id'] = $runBranchId;
        }

        $employees = self::employeesQueryForScope($scope, $periodStart, $periodEnd, $salaryCalculationType)
            ->with([
                'employee' => fn ($q) => $q->withoutGlobalScopes()->with(['department', 'shift']),
                'employeeSalary' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('name')
            ->get();

        $startDate = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->endOfDay();
        $codes = $employees->pluck('employee.emy_code')->filter()->values()->all();

        $mispunchCodes = [];
        if (! empty($codes)) {
            $mispunchCodes = BiometricAttendance::whereIn('employee_code', $codes)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->where('status', 'MIS')
                ->pluck('employee_code')
                ->unique()
                ->all();
        }

        $mispunchCodeSet = array_flip($mispunchCodes);
        $branchId = $scope['branch_id'] ?? $runBranchId;

        $rows = [];
        $counts = [
            'eligible' => 0,
            'ready' => 0,
            'mispunch' => 0,
            'no_salary' => 0,
            'no_branch' => 0,
            'selected' => 0,
        ];

        foreach ($employees as $user) {
            $emp = $user->employee;
            $code = $emp?->emy_code ?? '';
            $hasSalary = $user->employeeSalary()->where('is_active', true)->exists();
            $targetBranch = $branchId ?: ($emp?->branch_id);
            $hasMispunch = $code !== '' && isset($mispunchCodeSet[$code]);

            $status = 'ready';
            if (! $hasSalary) {
                $status = 'no_salary';
                $counts['no_salary']++;
            } elseif (! $targetBranch) {
                $status = 'no_branch';
                $counts['no_branch']++;
            } elseif ($hasMispunch) {
                $status = 'mispunch';
                $counts['mispunch']++;
            } else {
                $counts['ready']++;
            }

            $counts['eligible']++;
            if ($scope['employee_mode'] === 'selected' && in_array($user->id, $scope['selected_employee_ids'], true)) {
                $counts['selected']++;
            }

            $rows[] = [
                'id' => $user->id,
                'code' => $code ?: '-',
                'name' => $user->name,
                'department' => $emp?->department?->name ?? '-',
                'shift' => $emp?->shift?->name ?? '-',
                'status' => $status,
            ];
        }

        $mispunchEmployees = array_values(array_filter($rows, fn ($r) => $r['status'] === 'mispunch'));
        $readyEmployees = array_values(array_filter($rows, fn ($r) => $r['status'] === 'ready'));

        return [
            'counts' => $counts,
            'employees' => $rows,
            'ready_employees' => $readyEmployees,
            'mispunch_employees' => $mispunchEmployees,
            'skipped_employees' => array_values(array_filter($rows, fn ($r) => in_array($r['status'], ['no_salary', 'no_branch'], true))),
            'valid_employee_ids' => array_column($readyEmployees, 'id'),
        ];
    }

    public static function resolvedScope(PayrollRun $run): array
    {
        $stored = is_array($run->scope_filters) ? $run->scope_filters : [];
        if (! empty($stored)) {
            return self::normalizeScopeFilters($stored);
        }

        return self::normalizeScopeFilters([
            'branch_id' => $run->branch_id,
            'department_ids' => $run->department_id ? [$run->department_id] : [],
        ]);
    }

    /**
     * Runs whose pay period overlaps the given range (same company).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PayrollRun>
     */
    public static function findOverlappingRuns(
        $periodStart,
        $periodEnd,
        ?int $branchId = null,
        ?int $excludeRunId = null,
        ?array $companyIds = null
    ) {
        $start = $periodStart instanceof Carbon ? $periodStart : Carbon::parse($periodStart);
        $end = $periodEnd instanceof Carbon ? $periodEnd : Carbon::parse($periodEnd);
        $companyIds = $companyIds ?? getCompanyAndUsersId();

        $query = PayrollRun::query()
            ->whereIn('created_by', $companyIds)
            ->where('pay_period_start', '<=', $end->toDateString())
            ->where('pay_period_end', '>=', $start->toDateString());

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($excludeRunId) {
            $query->where('id', '!=', $excludeRunId);
        }

        return $query->orderBy('pay_period_start')->get();
    }

    public static function scopeSummaryShort(array $scope): string
    {
        $scope = self::normalizeScopeFilters($scope);
        $parts = [];

        if (! empty($scope['department_ids'])) {
            $parts[] = count($scope['department_ids']) . ' ' . __('dept(s)');
        }
        if (! empty($scope['shift_ids'])) {
            $parts[] = count($scope['shift_ids']) . ' ' . __('shift(s)');
        }
        if (! empty($scope['category_ids'])) {
            $parts[] = count($scope['category_ids']) . ' ' . __('category(s)');
        }
        if (! empty($scope['designation_ids'])) {
            $parts[] = count($scope['designation_ids']) . ' ' . __('designation(s)');
        }
        if ($scope['employee_mode'] === 'selected' && ! empty($scope['selected_employee_ids'])) {
            $parts[] = count($scope['selected_employee_ids']) . ' ' . __('selected');
        }

        return $parts ? implode(', ', $parts) : __('All employees');
    }
}
