<?php

namespace App\Services\SalaryPayroll;

use App\Models\Employee;
use App\Models\SalaryComponent;
use Illuminate\Support\Collection;

class SalaryComponentAssignmentService
{
    public function isPrimary(SalaryComponent $component): bool
    {
        return $component->assign_to_all || $component->component_group === 'primary';
    }

    public function isCustom(SalaryComponent $component): bool
    {
        return ! $this->isPrimary($component);
    }

    /**
     * Empty assignment → primary group only.
     * Non-empty → primary (always) + selected custom components.
     *
     * @return Collection<int, SalaryComponent>
     */
    public function resolveForEmployee(Collection $branchComponents, ?Employee $employee): Collection
    {
        $assignedIds = collect($employee?->extra_salary_component_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        $primary = $this->primaryComponents($branchComponents);

        if ($assignedIds->isEmpty()) {
            return $primary;
        }

        $customSelected = $branchComponents->filter(
            fn (SalaryComponent $component) => $assignedIds->contains($component->id) && $this->isCustom($component)
        );

        return $primary->merge($customSelected)->unique('id')->values();
    }

    /**
     * @return Collection<int, SalaryComponent>
     */
    public function customComponents(Collection $branchComponents): Collection
    {
        return $branchComponents
            ->filter(fn (SalaryComponent $component) => $this->isCustom($component))
            ->values();
    }

    /**
     * @return Collection<int, SalaryComponent>
     */
    public function primaryComponents(Collection $branchComponents): Collection
    {
        return $branchComponents
            ->filter(fn (SalaryComponent $component) => $this->isPrimary($component))
            ->values();
    }

    /**
     * @param  array<int, int>  $componentIds
     * @return array<int, int>
     */
    public function validateExtraComponentIds(Collection $branchComponents, array $componentIds): array
    {
        $validCustomIds = $branchComponents
            ->filter(fn (SalaryComponent $component) => $this->isCustom($component))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return collect($componentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $validCustomIds->contains($id))
            ->unique()
            ->values()
            ->all();
    }
}
