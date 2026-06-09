<?php

namespace App\Services\SalaryPayroll;

use App\Models\Branch;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SalaryPayrollRunService
{
    public function __construct(
        private SalaryPayrollScopeService $scopeService
    ) {}

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function monthsForFinancialYear(string $financialYear): array
    {
        $parts = explode('-', $financialYear);
        $startYear = (int) ($parts[0] ?? now()->year);
        $endYear = (int) ($parts[1] ?? ($startYear + 1));

        $months = [];
        for ($m = 4; $m <= 12; $m++) {
            $months[] = $this->monthOption($startYear, $m);
        }
        for ($m = 1; $m <= 3; $m++) {
            $months[] = $this->monthOption($endYear, $m);
        }

        return $months;
    }

    public function periodFromMonthYear(string $monthYear): array
    {
        $start = Carbon::createFromFormat('Y-m', $monthYear)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return [
            'pay_period_start' => $start->toDateString(),
            'pay_period_end' => $end->toDateString(),
            'financial_year' => financialYearLabelForDate($end),
            'month_year' => $monthYear,
            'period_mode' => 'month',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{pay_period_start: string, pay_period_end: string, financial_year: string, month_year: string, period_mode: string}
     */
    public function resolvePeriod(array $data): array
    {
        $mode = $data['period_mode'] ?? 'month';

        if ($mode === 'custom') {
            $start = Carbon::parse($data['pay_period_start'])->startOfDay();
            $end = Carbon::parse($data['pay_period_end'])->startOfDay();

            if ($end->lt($start)) {
                throw new \InvalidArgumentException(__('End date must be on or after start date.'));
            }

            return [
                'pay_period_start' => $start->toDateString(),
                'pay_period_end' => $end->toDateString(),
                'month_year' => $end->format('Y-m'),
                'financial_year' => $data['financial_year'] ?? financialYearLabelForDate($end),
                'period_mode' => 'custom',
            ];
        }

        if (empty($data['month_year'])) {
            throw new \InvalidArgumentException(__('Select a month.'));
        }

        return $this->periodFromMonthYear($data['month_year']);
    }

    public function isFullMonthPeriod(string $payPeriodStart, string $payPeriodEnd): bool
    {
        $start = Carbon::parse($payPeriodStart)->startOfDay();
        $end = Carbon::parse($payPeriodEnd)->startOfDay();

        return $start->isSameDay($start->copy()->startOfMonth())
            && $end->isSameDay($start->copy()->endOfMonth())
            && $start->format('Y-m') === $end->format('Y-m');
    }

    /**
     * @param  array{pay_period_start: string, pay_period_end: string, period_mode?: string}  $period
     */
    public function buildTitle(array $period, ?int $branchId): string
    {
        $start = Carbon::parse($period['pay_period_start']);
        $end = Carbon::parse($period['pay_period_end']);
        $branchName = $branchId ? Branch::find($branchId)?->name : null;

        if (($period['period_mode'] ?? 'month') === 'month' || $this->isFullMonthPeriod($period['pay_period_start'], $period['pay_period_end'])) {
            $title = $start->format('F Y') . ' ' . __('Payroll');
        } else {
            $title = $start->format('d M') . ' – ' . $end->format('d M Y') . ' ' . __('Payroll');
        }

        if ($branchName) {
            $title .= ' - ' . $branchName;
        }

        return $title;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeSignature(string $scopeMode, array $filters): string
    {
        $filters = $this->scopeService->normalizeFilters($filters);
        $normalized = [
            'scope_mode' => $this->scopeService->normalizeScopeMode($scopeMode),
            'category_ids' => $filters['category_ids'],
            'shift_ids' => $filters['shift_ids'],
            'employee_ids' => $filters['employee_ids'],
            'department_ids' => $filters['department_ids'],
        ];
        sort($normalized['category_ids']);
        sort($normalized['shift_ids']);
        sort($normalized['employee_ids']);
        sort($normalized['department_ids']);

        return hash('sha256', json_encode($normalized));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function findMatchingRun(
        ?int $branchId,
        string $payPeriodStart,
        string $payPeriodEnd,
        string $scopeMode,
        array $filters,
        ?string $status = null
    ): ?SalaryPayrollRun {
        $signature = $this->scopeSignature($scopeMode, $filters);

        $query = SalaryPayrollRun::query()
            ->where('branch_id', $branchId)
            ->whereDate('pay_period_start', $payPeriodStart)
            ->whereDate('pay_period_end', $payPeriodEnd)
            ->when($status, fn ($q) => $q->where('status', $status));

        foreach ($query->get() as $run) {
            if ($this->scopeSignature($run->scope_mode, $run->scope_filters ?? []) === $signature) {
                return $run;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{run: SalaryPayrollRun, reused: bool}
     */
    public function createOrUpdateRun(array $data, ?int $branchId): array
    {
        $scopeMode = $this->scopeService->normalizeScopeMode($data['scope_mode'] ?? 'all');
        $filters = $this->scopeService->normalizeFilters($data);
        $period = $this->resolvePeriod($data);

        $locked = $this->findMatchingRun(
            $branchId,
            $period['pay_period_start'],
            $period['pay_period_end'],
            $scopeMode,
            $filters,
            'finalized'
        );
        if ($locked) {
            throw new \InvalidArgumentException(
                __('Payroll for this period and scope is already locked. Cannot create duplicate.')
            );
        }

        $existing = $this->findMatchingRun(
            $branchId,
            $period['pay_period_start'],
            $period['pay_period_end'],
            $scopeMode,
            $filters
        );
        if ($existing && ! $existing->isFinalized()) {
            return [
                'run' => $this->updateRun($existing, $data, $branchId),
                'reused' => true,
            ];
        }

        return [
            'run' => $this->createRun($data, $branchId),
            'reused' => false,
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertNoDuplicateUnlockedRun(
        ?int $branchId,
        string $payPeriodStart,
        string $payPeriodEnd,
        string $scopeMode,
        array $filters,
        ?int $excludeRunId = null
    ): void {
        $existing = $this->findMatchingRun($branchId, $payPeriodStart, $payPeriodEnd, $scopeMode, $filters);
        if ($existing && ! $existing->isFinalized() && $existing->id !== $excludeRunId) {
            throw new \InvalidArgumentException(
                __('An unlocked payroll already exists for this period and scope. Use Regenerate on the existing run instead of creating a duplicate.')
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertNoFinalizedOverlap(
        ?int $branchId,
        string $monthYear,
        string $scopeMode,
        array $filters,
        ?int $excludeRunId = null
    ): void {
        $readyIds = $this->scopeService->readyEmployees($branchId, $scopeMode, $filters)->pluck('id');
        if ($readyIds->isEmpty()) {
            return;
        }

        $existing = SalaryPayrollRun::query()
            ->where('branch_id', $branchId)
            ->where('month_year', $monthYear)
            ->where('status', 'finalized')
            ->when($excludeRunId, fn ($q) => $q->where('id', '!=', $excludeRunId))
            ->pluck('id');

        if ($existing->isEmpty()) {
            return;
        }

        $overlap = SalaryPayrollEntry::query()
            ->whereIn('salary_payroll_run_id', $existing)
            ->whereIn('employee_id', $readyIds)
            ->where('status', 'calculated')
            ->exists();

        if ($overlap) {
            throw new \InvalidArgumentException(
                __('One or more employees already have locked payroll for this month.')
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRun(array $data, ?int $branchId): SalaryPayrollRun
    {
        $scopeMode = $this->scopeService->normalizeScopeMode($data['scope_mode'] ?? 'all');
        $filters = $this->scopeService->normalizeFilters($data);
        $period = $this->resolvePeriod($data);

        $this->assertNoFinalizedOverlap($branchId, $period['month_year'], $scopeMode, $filters);

        return SalaryPayrollRun::create([
            'branch_id' => $branchId,
            'financial_year' => $data['financial_year'] ?? $period['financial_year'],
            'month_year' => $period['month_year'],
            'pay_period_start' => $period['pay_period_start'],
            'pay_period_end' => $period['pay_period_end'],
            'title' => $this->buildTitle($period, $branchId),
            'scope_mode' => $scopeMode,
            'scope_filters' => $filters,
            'use_attendance' => array_key_exists('use_attendance', $data)
                ? (bool) $data['use_attendance']
                : true,
            'apply_attendance_extra' => (bool) ($data['apply_attendance_extra'] ?? false),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);
    }

    public function finalize(SalaryPayrollRun $run): SalaryPayrollRun
    {
        if ($run->isFinalized()) {
            throw new \InvalidArgumentException(__('This payroll run is already finalized.'));
        }

        if ($run->status !== 'calculated') {
            throw new \InvalidArgumentException(__('Generate payroll before locking.'));
        }

        SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->where('is_locked', false)
            ->update([
                'is_locked' => true,
                'locked_by' => Auth::id(),
                'locked_at' => now(),
            ]);

        $run->update([
            'status' => 'finalized',
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
        ]);

        return $run->fresh();
    }

    public function lockEntry(SalaryPayrollEntry $entry): SalaryPayrollEntry
    {
        $run = $entry->run;

        if ($run->isFinalized()) {
            throw new \InvalidArgumentException(__('This payroll run is already locked.'));
        }

        if ($entry->is_locked) {
            throw new \InvalidArgumentException(__('This employee is already locked.'));
        }

        if ($run->status !== 'calculated') {
            throw new \InvalidArgumentException(__('Generate payroll before locking employees.'));
        }

        $entry->update([
            'is_locked' => true,
            'locked_by' => Auth::id(),
            'locked_at' => now(),
        ]);

        return $entry->fresh(['locker']);
    }

    public function lockedEntryCount(SalaryPayrollRun $run): int
    {
        return SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->where('is_locked', true)
            ->count();
    }

    public function hasLockedEntries(SalaryPayrollRun $run): bool
    {
        return $this->lockedEntryCount($run) > 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRun(SalaryPayrollRun $run, array $data, ?int $branchId): SalaryPayrollRun
    {
        if ($run->isFinalized()) {
            throw new \InvalidArgumentException(__('Locked payroll runs cannot be modified.'));
        }

        if ($this->hasLockedEntries($run)) {
            throw new \InvalidArgumentException(__('Cannot customize payroll while individual employees are locked. Use "Lock Payroll" to finalize, or regenerate locked employees first.'));
        }

        $scopeMode = $this->scopeService->normalizeScopeMode($data['scope_mode'] ?? 'all');
        $filters = $this->scopeService->normalizeFilters($data);
        $period = $this->resolvePeriod($data);

        $this->assertNoFinalizedOverlap(
            $branchId ?? $run->branch_id,
            $period['month_year'],
            $scopeMode,
            $filters,
            $run->id
        );

        $this->assertNoDuplicateUnlockedRun(
            $branchId ?? $run->branch_id,
            $period['pay_period_start'],
            $period['pay_period_end'],
            $scopeMode,
            $filters,
            $run->id
        );

        $run->update([
            'financial_year' => $data['financial_year'] ?? $period['financial_year'],
            'month_year' => $period['month_year'],
            'pay_period_start' => $period['pay_period_start'],
            'pay_period_end' => $period['pay_period_end'],
            'title' => $this->buildTitle($period, $branchId ?? $run->branch_id),
            'scope_mode' => $scopeMode,
            'scope_filters' => $filters,
            'use_attendance' => array_key_exists('use_attendance', $data)
                ? (bool) $data['use_attendance']
                : ($run->use_attendance ?? true),
            'apply_attendance_extra' => array_key_exists('apply_attendance_extra', $data)
                ? (bool) $data['apply_attendance_extra']
                : (bool) ($run->apply_attendance_extra ?? false),
            'status' => 'draft',
        ]);

        return $run->fresh();
    }

    public function refreshRunTotals(SalaryPayrollRun $run): void
    {
        $entries = SalaryPayrollEntry::where('salary_payroll_run_id', $run->id)->get();
        $calculated = $entries->where('status', 'calculated');

        $run->update([
            'employee_count' => $entries->count(),
            'total_gross' => round($calculated->sum('total_earnings'), 2),
            'total_net' => round($calculated->sum('net_salary'), 2),
            'total_pf_employee' => round($calculated->sum('pf_employee'), 2),
            'total_pf_employer' => round($calculated->sum('pf_employer'), 2),
            'total_esi_employee' => round($calculated->sum('esi_employee'), 2),
            'total_esi_employer' => round($calculated->sum('esi_employer'), 2),
            'status' => 'calculated',
        ]);
    }

    private function monthOption(int $year, int $month): array
    {
        $date = Carbon::create($year, $month, 1);

        return [
            'value' => $date->format('Y-m'),
            'label' => $date->format('M Y'),
        ];
    }
}
