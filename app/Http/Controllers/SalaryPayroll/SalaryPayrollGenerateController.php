<?php

namespace App\Http\Controllers\SalaryPayroll;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\PayrollParameter;
use App\Models\ProfessionalTaxSlab;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Services\SalaryPayroll\SalaryPayrollBatchProcessor;
use App\Services\SalaryPayroll\SalaryPayrollPayslipService;
use App\Services\SalaryPayroll\SalaryPayrollRunService;
use App\Services\SalaryPayroll\SalaryPayrollScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalaryPayrollGenerateController extends Controller
{
    public function __construct(
        private SalaryPayrollScopeService $scopeService,
        private SalaryPayrollRunService $runService,
        private SalaryPayrollBatchProcessor $batchProcessor,
        private SalaryPayrollPayslipService $payslipService
    ) {}

    public function index(Request $request)
    {
        $branchId = session('active_branch_id');
        $branchName = $branchId ? \App\Models\Branch::find($branchId)?->name : null;

        $runs = SalaryPayrollRun::query()
            ->with(['creator:id,name', 'branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('month_year')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $runs->getCollection()->transform(fn (SalaryPayrollRun $run) => $this->formatRun($run));

        return Inertia::render('hr/salary-payroll/payroll-generate/index', [
            'runs' => $runs,
            'activeBranchId' => $branchId,
            'activeBranchName' => $branchName,
            'filters' => $request->all(['status', 'month_year', 'per_page']),
        ]);
    }

    public function create()
    {
        $branchId = session('active_branch_id');
        $financialYear = currentFinancialYearLabel();

        return Inertia::render('hr/salary-payroll/payroll-generate/create', [
            ...$this->formOptions($branchId, $financialYear),
            'mode' => 'create',
            'existingRun' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?int $branchId, ?string $preferredFinancialYear = null): array
    {
        $branchName = $branchId ? \App\Models\Branch::find($branchId)?->name : null;
        $financialYear = $preferredFinancialYear ?: currentFinancialYearLabel();
        $fyOptions = financialYearSelectOptions();
        if ($preferredFinancialYear && ! in_array($preferredFinancialYear, $fyOptions, true)) {
            $fyOptions = array_values(array_unique([$preferredFinancialYear, ...$fyOptions]));
        }

        $monthsByFinancialYear = [];
        foreach ($fyOptions as $fy) {
            $monthsByFinancialYear[$fy] = $this->runService->monthsForFinancialYear($fy);
        }

        $months = $monthsByFinancialYear[$financialYear] ?? [];
        $defaultMonth = collect($months)->first(
            fn ($m) => $m['value'] === now()->format('Y-m')
        )['value'] ?? ($months[0]['value'] ?? now()->format('Y-m'));

        return [
            'financialYearOptions' => $fyOptions,
            'defaultFinancialYear' => $financialYear,
            'monthsByFinancialYear' => $monthsByFinancialYear,
            'defaultMonthYear' => $defaultMonth,
            'categories' => $this->branchCategories($branchId),
            'departments' => $this->branchDepartments($branchId),
            'shifts' => $this->branchShifts($branchId),
            'employees' => $this->branchEmployees($branchId),
            'activeBranchId' => $branchId,
            'activeBranchName' => $branchName,
        ];
    }

    public function preview(Request $request)
    {
        $validated = $this->validateScopeRequest($request);
        $branchId = session('active_branch_id');

        $previewOptions = [
            'search' => $request->filled('preview_search') ? trim((string) $request->input('preview_search')) : null,
            'status' => $request->input('preview_status', 'all'),
            'category_id' => $request->integer('preview_category_id') ?: null,
            'shift_id' => $request->integer('preview_shift_id') ?: null,
            'department_id' => $request->integer('preview_department_id') ?: null,
            'page' => max(1, $request->integer('page', 1)),
            'per_page' => min(max($request->integer('per_page', 50), 10), 200),
        ];

        $summary = $this->scopeService->previewSummary(
            $branchId,
            $validated['scope_mode'],
            $validated,
            $previewOptions
        );

        $existing = $this->runService->findMatchingRun(
            $branchId,
            $validated['pay_period_start'],
            $validated['pay_period_end'],
            $validated['scope_mode'],
            $validated
        );

        if ($existing) {
            $summary['existing_run'] = [
                'id' => $existing->id,
                'title' => $existing->title,
                'status' => $existing->status,
                'is_locked' => $existing->isFinalized(),
            ];
        }

        return response()->json($summary);
    }

    public function store(Request $request)
    {
        $validated = $this->validateScopeRequest($request);
        $branchId = session('active_branch_id');

        try {
            $result = $this->runService->createOrUpdateRun($validated, $branchId);
            $run = $result['run'];
            $this->batchProcessor->process($run);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $message = $result['reused'] ?? false
            ? __('Existing payroll updated and regenerated — no duplicate created.')
            : __('Payroll generated successfully.');

        return redirect()
            ->route('hr.salary-payroll.generate.show', $run->id)
            ->with('success', $message);
    }

    public function show(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $salaryPayrollRun->load([
            'creator:id,name',
            'finalizer:id,name',
            'branch:id,name',
        ]);

        $search = $request->filled('search') ? trim((string) $request->search) : null;
        $categoryId = $request->integer('category_id') ?: null;
        $shiftId = $request->integer('shift_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $lockStatus = $request->string('lock_status')->toString();
        $lockStatus = in_array($lockStatus, ['locked', 'unlocked'], true) ? $lockStatus : null;
        $perPage = min(max($request->integer('per_page', 50), 10), 200);
        $branchId = session('active_branch_id');

        $entriesQuery = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $salaryPayrollRun->id)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('employee', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('employee.employee', fn ($eq) => $eq->where('employee_id', 'like', "%{$search}%"));
                });
            })
            ->when($categoryId, fn ($q) => $q->whereHas('employee.employee', fn ($eq) => $eq->where('category_id', $categoryId)))
            ->when($shiftId, fn ($q) => $q->whereHas('employee.employee', fn ($eq) => $eq->where('shift_id', $shiftId)))
            ->when($departmentId, fn ($q) => $q->whereHas('employee.employee', fn ($eq) => $eq->where('department_id', $departmentId)))
            ->when($lockStatus === 'locked', fn ($q) => $q->where('salary_payroll_entries.is_locked', true))
            ->when($lockStatus === 'unlocked', fn ($q) => $q->where('salary_payroll_entries.is_locked', false))
            ->join('users', 'salary_payroll_entries.employee_id', '=', 'users.id')
            ->select('salary_payroll_entries.*')
            ->orderBy('users.name');

        $entries = $entriesQuery
            ->with([
                'employee.employee.category:id,name',
                'employee.employee.shift:id,name',
                'employee.employee.department:id,name',
                'locker:id,name',
                'payslip:id,salary_payroll_entry_id,payslip_number,generated_at',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $entries->getCollection()->transform(fn ($entry) => $this->formatEntry($entry, $salaryPayrollRun));

        $mispunchCount = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $salaryPayrollRun->id)
            ->where('has_mispunch', true)
            ->count();

        return Inertia::render('hr/salary-payroll/payroll-generate/show', [
            'run' => $this->formatRun($salaryPayrollRun),
            'entries' => $entries,
            'mispunch_count' => $mispunchCount,
            'filters' => $request->only(['search', 'per_page', 'category_id', 'shift_id', 'department_id', 'lock_status']),
            'categories' => $this->branchCategories($branchId),
            'departments' => $this->branchDepartments($branchId),
            'shifts' => $this->branchShifts($branchId),
        ]);
    }

    public function edit(SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ($salaryPayrollRun->isFinalized()) {
            return redirect()
                ->route('hr.salary-payroll.generate.show', $salaryPayrollRun->id)
                ->with('error', __('Locked payroll runs cannot be customized.'));
        }

        if ($this->runService->hasLockedEntries($salaryPayrollRun)) {
            return redirect()
                ->route('hr.salary-payroll.generate.show', $salaryPayrollRun->id)
                ->with('error', __('Cannot customize payroll while individual employees are locked.'));
        }

        $branchId = session('active_branch_id');

        return Inertia::render('hr/salary-payroll/payroll-generate/create', [
            ...$this->formOptions($branchId, $salaryPayrollRun->financial_year),
            'mode' => 'edit',
            'existingRun' => $this->formatRun($salaryPayrollRun),
        ]);
    }

    public function update(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $validated = $this->validateScopeRequest($request);
        $branchId = session('active_branch_id');

        try {
            $this->runService->updateRun($salaryPayrollRun, $validated, $branchId);
            $this->batchProcessor->process($salaryPayrollRun);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hr.salary-payroll.generate.show', $salaryPayrollRun->id)
            ->with('success', __('Payroll updated and regenerated successfully.'));
    }

    public function regenerate(SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ($salaryPayrollRun->isFinalized()) {
            return redirect()->back()->with('error', __('Locked payroll runs cannot be regenerated.'));
        }

        try {
            $lockedCount = $this->runService->lockedEntryCount($salaryPayrollRun);
            $this->batchProcessor->process($salaryPayrollRun);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $message = $lockedCount > 0
            ? __('Unlocked employees regenerated. :count locked employee(s) were skipped.', ['count' => $lockedCount])
            : __('Payroll regenerated with latest salary data.');

        return redirect()->back()->with('success', $message);
    }

    public function lockEntry(SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        try {
            $this->runService->lockEntry($salaryPayrollEntry);
            $salaryPayrollEntry->refresh();
            $this->payslipService->generateForEntry($salaryPayrollEntry);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('Employee locked but payslip generation failed: :message', ['message' => $e->getMessage()]));
        }

        return redirect()->back()->with('success', __('Employee payroll locked and payslip generated.'));
    }

    public function downloadPayslip(SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        try {
            $payslip = $this->payslipService->ensurePayslip($salaryPayrollEntry);
            $path = $this->payslipService->downloadPath($payslip);

            return response()->download($path, $this->payslipService->downloadFilename($payslip));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('Failed to download payslip: :message', ['message' => $e->getMessage()]));
        }
    }

    public function downloadAllPayslips(SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        try {
            $zipPath = $this->payslipService->createZipForRun($salaryPayrollRun);
            $filename = 'payslips-' . ($salaryPayrollRun->month_year ?? $salaryPayrollRun->id) . '.zip';

            return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('Failed to download payslips: :message', ['message' => $e->getMessage()]));
        }
    }

    public function regenerateEntry(SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        if ($salaryPayrollRun->isFinalized()) {
            return redirect()->back()->with('error', __('Locked payroll runs cannot be regenerated.'));
        }

        if ($salaryPayrollEntry->is_locked) {
            return redirect()->back()->with('error', __('This employee is locked and cannot be regenerated.'));
        }

        try {
            $this->batchProcessor->processEmployee($salaryPayrollRun, (int) $salaryPayrollEntry->employee_id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('Employee payroll recalculated with latest salary data.'));
    }

    public function finalize(SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        try {
            $this->runService->finalize($salaryPayrollRun);
            $generated = $this->payslipService->generateForRun($salaryPayrollRun->fresh());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('Payroll locked but payslip generation failed: :message', ['message' => $e->getMessage()]));
        }

        $message = $generated > 0
            ? __('Payroll locked successfully. :count payslip(s) generated.', ['count' => $generated])
            : __('Payroll locked successfully.');

        return redirect()->back()->with('success', $message);
    }

    public function destroy(SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ($salaryPayrollRun->isFinalized()) {
            return redirect()->back()->with('error', __('Locked payroll runs cannot be deleted.'));
        }

        $salaryPayrollRun->entries()->delete();
        $salaryPayrollRun->delete();

        return redirect()
            ->route('hr.salary-payroll.generate.index')
            ->with('success', __('Payroll run deleted.'));
    }

    private function validateScopeRequest(Request $request): array
    {
        $validated = $request->validate([
            'period_mode' => 'required|in:month,custom',
            'financial_year' => 'required|string',
            'month_year' => 'required_if:period_mode,month|nullable|date_format:Y-m',
            'pay_period_start' => 'required_if:period_mode,custom|nullable|date',
            'pay_period_end' => 'required_if:period_mode,custom|nullable|date|after_or_equal:pay_period_start',
            'scope_mode' => 'required|in:all,category,shift,employee',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer',
            'shift_ids' => 'nullable|array',
            'shift_ids.*' => 'integer',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer',
            'search' => 'nullable|string|max:100',
            'use_attendance' => 'nullable|boolean',
        ]);

        if ($validated['scope_mode'] === 'category' && empty($validated['category_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'category_ids' => [__('Select at least one category.')],
            ]);
        }
        if ($validated['scope_mode'] === 'shift' && empty($validated['shift_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shift_ids' => [__('Select at least one shift.')],
            ]);
        }
        if ($validated['scope_mode'] === 'employee' && empty($validated['employee_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'employee_ids' => [__('Select at least one employee.')],
            ]);
        }

        try {
            $period = $this->runService->resolvePeriod($validated);
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pay_period_end' => [$e->getMessage()],
            ]);
        }

        return array_merge($validated, $period);
    }

    private function assertBranchAccess(SalaryPayrollRun $run): void
    {
        $branchId = session('active_branch_id');
        if ($branchId && $run->branch_id && (int) $run->branch_id !== (int) $branchId) {
            abort(403);
        }
    }

    private function formatRun(SalaryPayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'title' => $run->title,
            'financial_year' => $run->financial_year,
            'month_year' => $run->month_year,
            'period_mode' => $run->pay_period_start && $run->pay_period_end && ! $this->runService->isFullMonthPeriod(
                $run->pay_period_start->format('Y-m-d'),
                $run->pay_period_end->format('Y-m-d')
            ) ? 'custom' : 'month',
            'pay_period_start' => $run->pay_period_start?->format('Y-m-d'),
            'pay_period_end' => $run->pay_period_end?->format('Y-m-d'),
            'scope_mode' => $run->scope_mode,
            'scope_label' => $run->scopeLabel(),
            'scope_filters' => $run->scope_filters,
            'use_attendance' => (bool) ($run->use_attendance ?? true),
            'status' => $run->status,
            'is_locked' => $run->isFinalized(),
            'locked_entry_count' => $this->runService->lockedEntryCount($run),
            'unlocked_entry_count' => max(0, (int) $run->employee_count - $this->runService->lockedEntryCount($run)),
            'payslip_count' => \App\Models\SalaryPayroll\SalaryPayrollPayslip::query()
                ->where('salary_payroll_run_id', $run->id)
                ->count(),
            'employee_count' => $run->employee_count,
            'total_gross' => (float) $run->total_gross,
            'total_net' => (float) $run->total_net,
            'total_pf_employee' => (float) $run->total_pf_employee,
            'total_pf_employer' => (float) $run->total_pf_employer,
            'total_esi_employee' => (float) $run->total_esi_employee,
            'total_esi_employer' => (float) $run->total_esi_employer,
            'branch' => $run->branch ? ['id' => $run->branch->id, 'name' => $run->branch->name] : null,
            'creator' => $run->creator ? ['id' => $run->creator->id, 'name' => $run->creator->name] : null,
            'finalizer' => $run->finalizer ? ['id' => $run->finalizer->id, 'name' => $run->finalizer->name] : null,
            'finalized_at' => $run->finalized_at?->toDateTimeString(),
            'created_at' => $run->created_at?->toDateTimeString(),
        ];
    }

    private function formatEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): array
    {
        return [
            'id' => $entry->id,
            'employee_id' => $entry->employee_id,
            'name' => $entry->employee?->name,
            'employee_code' => $entry->employee?->employee?->employee_id,
            'category' => $entry->employee?->employee?->category?->name,
            'shift' => $entry->employee?->employee?->shift?->name,
            'department' => $entry->employee?->employee?->department?->name,
            'pf_enabled' => (bool) ($entry->employee?->employee?->pf_flag ?? false),
            'esi_enabled' => (bool) ($entry->employee?->employee?->esic_flag ?? false),
            'pf_basic_salary' => (float) ($entry->employee?->employee?->pf_basic_salary ?? 0),
            'monthly_gross' => (float) $entry->monthly_gross,
            'daily_option' => (bool) ($entry->employee?->employee?->daily_option ?? false),
            'employee_working_days' => (float) ($entry->employee?->employee?->working_days ?? 0),
            'working_days' => $this->payrollStandardWorkingDays($entry),
            'present_days' => (float) ($entry->present_days ?? 0),
            'half_days' => (float) ($entry->half_days ?? 0),
            'week_off_worked_days' => (float) ($entry->week_off_worked_days ?? 0),
            'paid_days' => (float) ($entry->paid_days ?? 0),
            'ot_enabled' => (bool) ($entry->ot_enabled ?? $entry->employee?->employee?->ot_flag ?? false),
            'incentive_days' => (float) ($entry->incentive_days ?? 0),
            'incentive_amount' => (float) ($entry->incentive_amount ?? 0),
            'incentive_per_day_rate' => $this->incentivePerDayRateForEntry($entry),
            'regular_earnings' => $this->regularEarningsForEntry($entry),
            'mispunch_count' => (int) ($entry->mispunch_count ?? 0),
            'has_mispunch' => (bool) ($entry->has_mispunch ?? false),
            'basic' => (float) $entry->basic,
            'total_earnings' => (float) $entry->total_earnings,
            'total_deductions' => (float) $entry->total_deductions,
            'net_salary' => (float) $entry->net_salary,
            'pf_employee' => (float) $entry->pf_employee,
            'pf_wages' => (float) ($entry->pf_wages ?: $entry->basic),
            'pf_employer' => (float) $entry->pf_employer,
            'pf_eps_employer' => (float) ($entry->pf_eps_employer ?? 0),
            'pf_epf_employer' => (float) ($entry->pf_epf_employer ?? 0),
            'pf_breakdown' => $this->pfBreakdownForEntry($entry, $run),
            'esi_employee' => (float) $entry->esi_employee,
            'esi_employer' => (float) $entry->esi_employer,
            'pt_amount' => (float) $entry->pt_amount,
            'pt_breakdown' => $this->ptBreakdownForEntry($entry, $run),
            'earnings_breakdown' => $this->componentEarningsBreakdown($entry->earnings_breakdown ?? []),
            'deductions_breakdown' => $entry->deductions_breakdown ?? [],
            'status' => $entry->status,
            'error_message' => $entry->error_message,
            'is_locked' => $entry->isLocked(),
            'locked_at' => $entry->locked_at?->toDateTimeString(),
            'locker' => $entry->locker ? ['id' => $entry->locker->id, 'name' => $entry->locker->name] : null,
            'payslip_number' => $entry->payslip?->payslip_number,
            'has_payslip' => (bool) $entry->payslip,
            'can_download_payslip' => $entry->isLocked(),
        ];
    }

    private function payrollStandardWorkingDays(SalaryPayrollEntry $entry): float
    {
        $stored = (float) ($entry->working_days ?? 26);

        // Salary payroll always uses 26-day month; legacy rows may have stored emp working_days (e.g. 1).
        return $stored >= 26 ? $stored : 26.0;
    }

    /**
     * @return array<string, float|int>|null
     */
    private function pfBreakdownForEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): ?array
    {
        if (! ($entry->employee?->employee?->pf_flag ?? false) || (float) $entry->pf_employee <= 0) {
            return null;
        }

        $financialYear = $run?->financial_year ?? $entry->run?->financial_year;
        $params = PayrollParameter::forFinancialYear($financialYear);
        $wages = (float) ($entry->pf_wages ?: $entry->basic);
        $employeePct = PayrollParameter::pfEmployeePct($params);
        $epsPct = PayrollParameter::pfEpsPct($params);
        $epfPct = PayrollParameter::pfEpEmployerSharePct($params);

        $eps = (float) $entry->pf_eps_employer;
        $epfEmployer = (float) $entry->pf_epf_employer;
        if ($eps <= 0 && $epfEmployer <= 0 && $wages > 0) {
            $eps = round($wages * $epsPct / 100, 0);
            $epfEmployer = round($wages * $epfPct / 100, 0);
        }

        return [
            'wages' => $wages,
            'employee_pct' => $employeePct,
            'employee' => (float) $entry->pf_employee,
            'eps_pct' => $epsPct,
            'eps' => $eps,
            'epf_employer_pct' => $epfPct,
            'epf_employer' => $epfEmployer,
        ];
    }

    /**
     * @return array<string, float|int|string|null>|null
     */
    private function ptBreakdownForEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): ?array
    {
        $ptAmount = (float) $entry->pt_amount;
        if ($ptAmount <= 0) {
            return null;
        }

        $gross = (float) $entry->total_earnings;
        $financialYear = $run?->financial_year ?? $entry->run?->financial_year;
        $slab = $this->matchingPtSlab($gross, $financialYear);

        if (! $slab) {
            return [
                'gross' => $gross,
                'min_amt' => null,
                'max_amt' => null,
                'pt_amount' => $ptAmount,
            ];
        }

        return [
            'gross' => $gross,
            'min_amt' => (float) $slab->min_amt,
            'max_amt' => $slab->max_amt !== null ? (float) $slab->max_amt : null,
            'pt_amount' => $ptAmount,
        ];
    }

    private function matchingPtSlab(float $gross, ?string $financialYear): ?ProfessionalTaxSlab
    {
        $slabs = ProfessionalTaxSlab::where('financial_year', $financialYear)
            ->orderBy('min_amt')
            ->get();

        if ($slabs->isEmpty()) {
            $slabs = ProfessionalTaxSlab::orderBy('min_amt')->get();
        }

        foreach ($slabs as $slab) {
            $min = (float) $slab->min_amt;
            $max = $slab->max_amt !== null ? (float) $slab->max_amt : null;

            if ($gross >= $min && ($max === null || $gross <= $max)) {
                return $slab;
            }
        }

        return null;
    }

    /**
     * @param  array<string, float|int>  $breakdown
     * @return array<string, float>
     */
    private function componentEarningsBreakdown(array $breakdown): array
    {
        $filtered = [];
        foreach ($breakdown as $name => $amount) {
            if ($this->isIncentiveEarningLine((string) $name)) {
                continue;
            }
            if ((float) $amount > 0) {
                $filtered[(string) $name] = (float) $amount;
            }
        }

        return $filtered;
    }

    private function isIncentiveEarningLine(string $name): bool
    {
        $upper = strtoupper($name);

        return str_contains($upper, 'INCENTIVE')
            || str_contains($upper, 'PI)')
            || str_contains($upper, 'OVERTIME SALARY')
            || str_contains($upper, 'EXTRA DAYS');
    }

    private function incentivePerDayRateForEntry(SalaryPayrollEntry $entry): float
    {
        $incentiveDays = (float) ($entry->incentive_days ?? 0);
        if ($incentiveDays <= 0) {
            return 0.0;
        }

        $workingDays = $this->payrollStandardWorkingDays($entry);
        $monthlyGross = (float) $entry->monthly_gross;
        $dailyOption = (bool) ($entry->daily_option ?? $entry->employee?->employee?->daily_option ?? false);
        $empWorkingDays = (float) ($entry->employee_working_days ?? $entry->employee?->employee?->working_days ?? 26);
        $structureGross = ($dailyOption && $empWorkingDays <= 1) ? ($monthlyGross * $workingDays) : $monthlyGross;

        if ($workingDays <= 0 || $structureGross <= 0) {
            return 0.0;
        }

        return round($structureGross / $workingDays, 2);
    }

    private function regularEarningsForEntry(SalaryPayrollEntry $entry): float
    {
        $incentiveAmount = (float) ($entry->incentive_amount ?? 0);
        $totalEarnings = (float) $entry->total_earnings;

        if ($incentiveAmount <= 0) {
            return $totalEarnings;
        }

        return round(max(0, $totalEarnings - $incentiveAmount), 0);
    }

    private function branchCategories(?int $branchId)
    {
        if (! $branchId) {
            return Category::orderBy('name')->get(['id', 'name']);
        }

        return Category::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function branchDepartments(?int $branchId)
    {
        if (! $branchId) {
            return Department::orderBy('name')->get(['id', 'name']);
        }

        return Department::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function branchShifts(?int $branchId)
    {
        if (! $branchId) {
            return Shift::orderBy('name')->get(['id', 'name']);
        }

        return Shift::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function branchEmployees(?int $branchId)
    {
        return User::query()
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($eq) => $eq->where('branch_id', $branchId)))
            ->with('employee:id,user_id,employee_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'employee_code' => $u->employee?->employee_id,
            ]);
    }
}
