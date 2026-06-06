<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalaryPayrollScopeService
{
    public function defaultFilters(): array
    {
        return [
            'category_ids' => [],
            'shift_ids' => [],
            'employee_ids' => [],
            'department_ids' => [],
            'search' => null,
        ];
    }

    public function normalizeFilters(array $input): array
    {
        return [
            'category_ids' => $this->intArray($input['category_ids'] ?? []),
            'shift_ids' => $this->intArray($input['shift_ids'] ?? []),
            'employee_ids' => $this->intArray($input['employee_ids'] ?? []),
            'department_ids' => $this->intArray($input['department_ids'] ?? []),
            'search' => ! empty($input['search']) ? trim((string) $input['search']) : null,
        ];
    }

    public function normalizeScopeMode(string $mode): string
    {
        return in_array($mode, ['all', 'category', 'shift', 'employee'], true) ? $mode : 'all';
    }

    /**
     * @return Builder<User>
     */
    public function employeesQuery(?int $branchId, string $scopeMode, array $filters): Builder
    {
        $scopeMode = $this->normalizeScopeMode($scopeMode);
        $filters = $this->normalizeFilters($filters);

        $query = User::query()
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereHas('employee', function ($q) use ($branchId, $filters, $scopeMode) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
                if ($scopeMode === 'category' && ! empty($filters['category_ids'])) {
                    $q->whereIn('category_id', $filters['category_ids']);
                }
                if ($scopeMode === 'shift' && ! empty($filters['shift_ids'])) {
                    $q->whereIn('shift_id', $filters['shift_ids']);
                }
                if (! empty($filters['department_ids'])) {
                    $q->whereIn('department_id', $filters['department_ids']);
                }
            })
            ->with([
                'employee.department:id,name',
                'employee.category:id,name',
                'employee.shift:id,name',
            ])
            ->orderBy('name');

        if ($scopeMode === 'employee' && ! empty($filters['employee_ids'])) {
            $query->whereIn('id', $filters['employee_ids']);
        }

        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq->where('employee_id', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(?int $branchId, string $scopeMode, array $filters): array
    {
        $employees = $this->employeesQuery($branchId, $scopeMode, $filters)->get();
        $salaries = EmployeeSalary::whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        foreach ($employees as $user) {
            $salary = $salaries->get($user->id);
            $gross = (float) ($salary?->monthly_gross ?? $salary?->basic_salary ?? 0);
            $emp = $user->employee;

            $rows[] = [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $emp?->employee_id,
                'category' => $emp?->category?->name,
                'category_id' => $emp?->category_id,
                'shift' => $emp?->shift?->name,
                'shift_id' => $emp?->shift_id,
                'department' => $emp?->department?->name,
                'department_id' => $emp?->department_id,
                'monthly_gross' => $gross,
                'ready' => $gross > 0,
                'status' => $gross > 0 ? 'ready' : 'missing',
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, User>
     */
    public function readyEmployees(?int $branchId, string $scopeMode, array $filters): Collection
    {
        $rows = collect($this->previewRows($branchId, $scopeMode, $filters));
        $readyIds = $rows->where('ready', true)->pluck('id');

        return $this->employeesQuery($branchId, $scopeMode, $filters)
            ->whereIn('id', $readyIds)
            ->get();
    }

    /**
     * @return array{ready_count: int, missing_count: int, total_count: int, total_gross: float, rows: array, pagination: array}
     */
    public function previewSummary(?int $branchId, string $scopeMode, array $filters, array $previewOptions = []): array
    {
        $rows = $this->previewRows($branchId, $scopeMode, $filters);
        $ready = collect($rows)->where('ready', true);

        $filtered = $this->filterPreviewRows($rows, $previewOptions);
        $paginated = $this->paginatePreviewRows($filtered, $previewOptions);

        return [
            'ready_count' => $ready->count(),
            'missing_count' => count($rows) - $ready->count(),
            'total_count' => count($rows),
            'total_gross' => round($ready->sum('monthly_gross'), 2),
            'filtered_count' => count($filtered),
            'rows' => $paginated['data'],
            'pagination' => $paginated['meta'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterPreviewRows(array $rows, array $options): array
    {
        $collection = collect($rows);

        if (! empty($options['search'])) {
            $search = strtolower((string) $options['search']);
            $collection = $collection->filter(function (array $row) use ($search) {
                return str_contains(strtolower($row['name']), $search)
                    || str_contains(strtolower((string) ($row['employee_code'] ?? '')), $search);
            });
        }

        $status = $options['status'] ?? 'all';
        if ($status === 'ready') {
            $collection = $collection->where('ready', true);
        } elseif ($status === 'missing') {
            $collection = $collection->where('ready', false);
        }

        if (! empty($options['category_id'])) {
            $collection = $collection->where('category_id', (int) $options['category_id']);
        }

        if (! empty($options['shift_id'])) {
            $collection = $collection->where('shift_id', (int) $options['shift_id']);
        }

        if (! empty($options['department_id'])) {
            $collection = $collection->where('department_id', (int) $options['department_id']);
        }

        return $collection->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function paginatePreviewRows(array $rows, array $options): array
    {
        $page = max(1, (int) ($options['page'] ?? 1));
        $perPage = min(max((int) ($options['per_page'] ?? 50), 1), 200);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($rows, $offset, $perPage),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, int>
     */
    private function intArray(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values), fn ($id) => $id > 0)));
    }
}
