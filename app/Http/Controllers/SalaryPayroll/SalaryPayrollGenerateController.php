<?php

namespace App\Http\Controllers\SalaryPayroll;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalary;
use App\Models\Category;
use App\Models\Department;
use App\Models\PayrollParameter;
use App\Models\ProfessionalTaxSlab;
use App\Models\SalaryPayroll\SalaryAdvanceRequest;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Services\SalaryPayroll\BranchPayrollSettingsService;
use App\Services\SalaryPayroll\SalaryPayrollBatchProcessor;
use App\Services\SalaryPayroll\SalaryPayrollChallanReportBuilder;
use App\Services\SalaryPayroll\SalaryPayrollChallanReportExportService;
use App\Services\SalaryPayroll\SalaryPayrollMispunchService;
use App\Services\SalaryPayroll\SalaryPayrollPayslipService;
use App\Services\SalaryPayroll\SalaryPayrollRegisterBuilder;
use App\Services\SalaryPayroll\SalaryPayrollRegisterExportService;
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
        private SalaryPayrollPayslipService $payslipService,
        private SalaryPayrollRegisterBuilder $registerBuilder,
        private SalaryPayrollRegisterExportService $registerExportService,
        private SalaryPayrollChallanReportBuilder $challanReportBuilder,
        private SalaryPayrollChallanReportExportService $challanReportExportService,
        private BranchPayrollSettingsService $branchPayrollSettings,
        private SalaryPayrollMispunchService $mispunchService
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

        $advanceMap = $this->advanceRequestsForEntries($entries->getCollection());
        $deferredAdvanceMap = $this->deferredAdvanceRecoveriesForEmployees(
            $entries->getCollection()->pluck('employee_id')->unique()->values()->all(),
            $salaryPayrollRun
        );

        $entries->getCollection()->transform(
            fn ($entry) => $this->formatEntry($entry, $salaryPayrollRun, $advanceMap, $deferredAdvanceMap)
        );

        $mispunchCount = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $salaryPayrollRun->id)
            ->where('has_mispunch', true)
            ->where('is_locked', false)
            ->count();

        $mispunchEntries = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $salaryPayrollRun->id)
            ->where('has_mispunch', true)
            ->where('is_locked', false)
            ->with(['employee:id,name', 'employee.employee:id,user_id,employee_id'])
            ->join('users', 'salary_payroll_entries.employee_id', '=', 'users.id')
            ->select('salary_payroll_entries.*')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'name' => $entry->employee?->name,
                'employee_code' => $entry->employee?->employee?->employee_id,
                'mispunch_count' => (int) ($entry->mispunch_count ?? 0),
                'mispunch_records' => $this->mispunchService->recordsPayloadForEntry($entry),
            ])
            ->values()
            ->all();

        return Inertia::render('hr/salary-payroll/payroll-generate/show', [
            'run' => $this->formatRun($salaryPayrollRun),
            'statutory_challan' => $this->runService->statutoryChallanSummary($salaryPayrollRun),
            'entries' => $entries,
            'mispunch_count' => $mispunchCount,
            'mispunch_entries' => $mispunchEntries,
            'ready_to_lock_count' => $this->runService->readyToLockCount($salaryPayrollRun),
            'filters' => $request->only(['search', 'per_page', 'category_id', 'shift_id', 'department_id', 'lock_status']),
            'categories' => $this->branchCategories($branchId),
            'departments' => $this->branchDepartments($branchId),
            'shifts' => $this->branchShifts($branchId),
        ]);
    }

    public function register(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $salaryPayrollRun->load(['branch:id,name']);

        $filters = $this->registerFilters($request);
        $register = $this->registerBuilder->build($salaryPayrollRun, $filters);

        return Inertia::render('hr/salary-payroll/payroll-generate/register', [
            'run' => $this->formatRun($salaryPayrollRun),
            'statutory_challan' => $this->runService->statutoryChallanSummary($salaryPayrollRun),
            'register' => $register,
            'filters' => $filters,
            'categories' => $this->branchCategories(session('active_branch_id')),
            'departments' => $this->branchDepartments(session('active_branch_id')),
            'shifts' => $this->branchShifts(session('active_branch_id')),
        ]);
    }

    public function downloadRegister(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $salaryPayrollRun->load('branch:id,name');

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'file_password' => ['required', 'string', 'min:4', 'max:255'],
            'file_password_confirmation' => ['required', 'same:file_password'],
        ]);

        $filters = $this->registerFilters($request);
        $filename = $this->registerExportService->buildFilename($salaryPayrollRun);
        $encrypted = $this->registerExportService->exportEncrypted(
            $salaryPayrollRun,
            $filters,
            (string) $request->input('file_password')
        );

        return response($encrypted, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function challanReport(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $salaryPayrollRun->load(['branch:id,name']);

        $filters = $this->registerFilters($request);
        $report = $this->challanReportBuilder->build($salaryPayrollRun, $filters);

        return Inertia::render('hr/salary-payroll/payroll-generate/challan-report', [
            'run' => $this->formatRun($salaryPayrollRun),
            'report' => $report,
            'summary' => $this->runService->statutoryChallanSummary($salaryPayrollRun),
            'filters' => $filters,
            'categories' => $this->branchCategories(session('active_branch_id')),
            'departments' => $this->branchDepartments(session('active_branch_id')),
            'shifts' => $this->branchShifts(session('active_branch_id')),
        ]);
    }

    public function downloadChallanReport(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $salaryPayrollRun->load('branch:id,name');

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'file_password' => ['required', 'string', 'min:4', 'max:255'],
            'file_password_confirmation' => ['required', 'same:file_password'],
        ]);

        $filters = $this->registerFilters($request);
        $filename = $this->challanReportExportService->buildFilename($salaryPayrollRun);
        $encrypted = $this->challanReportExportService->exportEncrypted(
            $salaryPayrollRun,
            $filters,
            (string) $request->input('file_password')
        );

        return response($encrypted, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function registerFilters(Request $request): array
    {
        $search = $request->filled('search') ? trim((string) $request->search) : null;
        $lockStatus = $request->string('lock_status')->toString();

        return [
            'search' => $search,
            'category_id' => $request->integer('category_id') ?: null,
            'shift_id' => $request->integer('shift_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'lock_status' => in_array($lockStatus, ['locked', 'unlocked'], true) ? $lockStatus : null,
        ];
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

    public function lockEntry(Request $request, SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        $skipMispunch = $request->boolean('skip_mispunch');

        try {
            $this->runService->lockEntry($salaryPayrollEntry, $skipMispunch);
            $salaryPayrollEntry->refresh();
            $this->payslipService->generateForEntry($salaryPayrollEntry);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('Employee locked but payslip generation failed: :message', ['message' => $e->getMessage()]));
        }

        $message = $skipMispunch && $salaryPayrollEntry->has_mispunch
            ? __('Employee locked with mispunch pending. Payslip generated.')
            : __('Employee payroll locked and payslip generated.');

        return redirect()->back()->with('success', $message);
    }

    public function unlockEntry(SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        try {
            $this->runService->unlockEntry($salaryPayrollEntry);
            $this->payslipService->removePayslipForEntry($salaryPayrollEntry);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('Employee unlocked. You can regenerate or edit before locking again.'));
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

    public function clearEntryMispunch(Request $request, SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        if ($salaryPayrollRun->isFinalized()) {
            return redirect()->back()->with('error', __('Locked payroll runs cannot be changed.'));
        }

        if ($salaryPayrollEntry->is_locked) {
            return redirect()->back()->with('error', __('This employee is locked and cannot be changed.'));
        }

        $validated = $request->validate([
            'biometric_attendance_id' => 'required|integer',
            'mode' => 'required|in:shift,manual',
            'in_time' => 'required_if:mode,manual|nullable|string',
            'out_time' => 'required_if:mode,manual|nullable|string',
        ]);

        $record = \App\Models\BiometricAttendance::query()->findOrFail($validated['biometric_attendance_id']);

        try {
            $this->mispunchService->assertRecordBelongsToEntry($record, $salaryPayrollEntry);
            if ($validated['mode'] === 'shift') {
                $this->mispunchService->clearWithShiftTimes($record);
            } else {
                $this->mispunchService->clearWithManualTimes(
                    $record,
                    (string) $validated['in_time'],
                    (string) $validated['out_time']
                );
            }
            $this->batchProcessor->processEmployee($salaryPayrollRun, (int) $salaryPayrollEntry->employee_id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __(
            'Mispunch cleared for :date. Salary recalculated.',
            ['date' => $record->attendance_date->format('d M Y')]
        ));
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

    public function toggleApplyAttendanceExtra(Request $request, SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        if ((int) $salaryPayrollEntry->salary_payroll_run_id !== (int) $salaryPayrollRun->id) {
            abort(404);
        }

        if (! auth()->user()?->can('apply-salary-payroll-attendance-extra')) {
            abort(403);
        }

        if ($salaryPayrollRun->isFinalized()) {
            return redirect()->back()->with('error', __('Locked payroll runs cannot be changed.'));
        }

        if ($salaryPayrollEntry->is_locked) {
            return redirect()->back()->with('error', __('This employee is locked and cannot be changed.'));
        }

        $otEnabled = (bool) ($salaryPayrollEntry->ot_enabled ?? $salaryPayrollEntry->employee?->employee?->ot_flag ?? false);
        if ($otEnabled) {
            return redirect()->back()->with('error', __('Adjust toggle applies to OT No employees only.'));
        }

        $extraDays = (float) ($salaryPayrollEntry->attendance_extra_days ?? 0);
        if ($extraDays <= 0) {
            return redirect()->back()->with('error', __('This employee has no extra days to adjust.'));
        }

        $apply = $request->boolean('apply');

        try {
            $salaryPayrollEntry->update(['apply_attendance_extra' => $apply]);
            $this->batchProcessor->processEmployee($salaryPayrollRun, (int) $salaryPayrollEntry->employee_id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $message = $apply
            ? __('Adjust amount added to net salary for :name.', ['name' => $salaryPayrollEntry->employee?->name ?? 'employee'])
            : __('Adjust amount removed from net salary for :name.', ['name' => $salaryPayrollEntry->employee?->name ?? 'employee']);

        return redirect()->back()->with('success', $message);
    }

    private function canToggleAttendanceExtra(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): bool
    {
        if (! auth()->user()?->can('apply-salary-payroll-attendance-extra')) {
            return false;
        }

        if ($run?->isFinalized() || $entry->isLocked()) {
            return false;
        }

        $otEnabled = (bool) ($entry->ot_enabled ?? $entry->employee?->employee?->ot_flag ?? false);

        return ! $otEnabled && (float) ($entry->attendance_extra_days ?? 0) > 0;
    }

    public function finalize(Request $request, SalaryPayrollRun $salaryPayrollRun)
    {
        $this->assertBranchAccess($salaryPayrollRun);

        $skipMispunch = $request->boolean('skip_mispunch');

        try {
            $this->runService->finalize($salaryPayrollRun, $skipMispunch);
            $generated = $this->payslipService->generateForRun($salaryPayrollRun->fresh());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __('Payroll locked but payslip generation failed: :message', ['message' => $e->getMessage()]));
        }

        $salaryPayrollRun->refresh();
        $skippedMispunch = $this->runService->unlockedMispunchEntryCount($salaryPayrollRun);

        if ($skipMispunch && $skippedMispunch > 0 && ! $salaryPayrollRun->isFinalized()) {
            $message = $generated > 0
                ? __('Locked :count ready employee(s). :skipped still open (mispunch). Payslip(s) generated.', [
                    'count' => $generated,
                    'skipped' => $skippedMispunch,
                ])
                : __('No ready employees to lock. :skipped employee(s) still have mispunch.', [
                    'skipped' => $skippedMispunch,
                ]);

            return redirect()->back()->with('success', $message);
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

        if ($this->runService->hasLockedEntries($salaryPayrollRun)) {
            $lockedCount = $this->runService->lockedEntryCount($salaryPayrollRun);

            return redirect()->back()->with('error', __('Cannot delete payroll while :count employee(s) are locked. Unlock all employees first.', [
                'count' => $lockedCount,
            ]));
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
            'apply_attendance_extra' => 'nullable|boolean',
        ]);

        if (! empty($validated['apply_attendance_extra']) && ! auth()->user()?->can('apply-salary-payroll-attendance-extra')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'apply_attendance_extra' => [__('You do not have permission to add extra days salary for OT No employees.')],
            ]);
        }

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
            'apply_attendance_extra' => (bool) ($run->apply_attendance_extra ?? false),
            'status' => $run->status,
            'is_locked' => $run->isFinalized(),
            'locked_entry_count' => $this->runService->lockedEntryCount($run),
            'unlocked_entry_count' => $this->runService->unlockedEntryCount($run),
            'ready_to_lock_count' => $this->runService->readyToLockCount($run),
            'unlocked_mispunch_count' => $this->runService->unlockedMispunchEntryCount($run),
            'payslip_count' => \App\Models\SalaryPayroll\SalaryPayrollPayslip::query()
                ->where('salary_payroll_run_id', $run->id)
                ->count(),
            'employee_count' => $run->employee_count,
            'total_gross' => (float) $run->total_gross,
            'total_net' => (float) $run->total_net,
            'total_pf_employee' => (float) $run->total_pf_employee,
            'total_pf_employer' => (float) $run->total_pf_employer,
            'total_pf_eps_employer' => (float) ($run->total_pf_eps_employer ?? 0),
            'total_pf_epf_employer' => (float) ($run->total_pf_epf_employer ?? 0),
            'total_pf_admin_employer' => (float) ($run->total_pf_admin_employer ?? 0),
            'total_pf_challan_ac1' => (float) ($run->total_pf_challan_ac1 ?? 0),
            'total_pf_challan_ac2' => (float) ($run->total_pf_challan_ac2 ?? 0),
            'total_pf_challan_ac10' => (float) ($run->total_pf_challan_ac10 ?? 0),
            'total_pf_challan_total' => (float) ($run->total_pf_challan_total ?? 0),
            'total_esi_employee' => (float) $run->total_esi_employee,
            'total_esi_employer' => (float) $run->total_esi_employer,
            'total_pt_amount' => (float) ($run->total_pt_amount ?? 0),
            'total_advance_recovery' => $this->totalAdvanceRecoveryForRun($run),
            'advance_recovery_employee_count' => $this->advanceRecoveryEmployeeCountForRun($run),
            'branch' => $run->branch ? ['id' => $run->branch->id, 'name' => $run->branch->name] : null,
            'creator' => $run->creator ? ['id' => $run->creator->id, 'name' => $run->creator->name] : null,
            'finalizer' => $run->finalizer ? ['id' => $run->finalizer->id, 'name' => $run->finalizer->name] : null,
            'finalized_at' => $run->finalized_at?->toDateTimeString(),
            'created_at' => $run->created_at?->toDateTimeString(),
        ];
    }

    private function formatEntry(
        SalaryPayrollEntry $entry,
        ?SalaryPayrollRun $run = null,
        ?\Illuminate\Support\Collection $advanceMap = null,
        ?\Illuminate\Support\Collection $deferredAdvanceMap = null
    ): array {
        $attendanceExtra = $this->attendanceExtraForEntry($entry, $run);
        $advanceAllocations = $this->advanceAllocationsPayload($entry, $advanceMap);
        $advanceRecoveryAmount = round(collect($advanceAllocations)->sum('amount'), 2);
        $advanceRecoveryDeferred = $this->advanceRecoveryDeferredPayload($entry, $run, $deferredAdvanceMap);

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
            'gross_input_mode' => $this->grossInputModeForEntry($entry),
            'daily_option' => $this->grossInputModeForEntry($entry) === 'day',
            'employee_working_days' => $this->payrollStandardWorkingDays($entry, $run),
            'working_days' => (float) ($entry->working_days ?: $this->payrollStandardWorkingDays($entry, $run)),
            'working_days_source' => $entry->working_days_source,
            'use_government_wage_rules' => $this->branchUsesGovtWageRules($entry, $run),
            'govt_min_wage_per_day' => $entry->govt_min_wage_per_day !== null ? (float) $entry->govt_min_wage_per_day : null,
            'govt_min_wage_used' => $entry->govt_min_wage_used !== null ? (float) $entry->govt_min_wage_used : null,
            'govt_wage_salary_applied' => (bool) ($entry->govt_wage_salary_applied ?? false),
            'govt_wage_rate_for_salary' => ($entry->govt_wage_salary_applied ?? false) && $entry->govt_min_wage_per_day !== null
                ? (float) round((float) $entry->govt_min_wage_per_day, 0)
                : null,
            'actual_paid_days' => $this->actualPaidDaysForEntry($entry, $run),
            'govt_wage_equiv_days_raw' => $entry->govt_wage_equiv_days_raw !== null ? (float) $entry->govt_wage_equiv_days_raw : null,
            'govt_wage_paid_days' => $entry->govt_wage_paid_days !== null ? (float) $entry->govt_wage_paid_days : null,
            'contract_regular_earnings' => $entry->contract_regular_earnings !== null ? (float) $entry->contract_regular_earnings : null,
            'govt_wage_computed_earnings' => $entry->govt_wage_computed_earnings !== null ? (float) $entry->govt_wage_computed_earnings : null,
            'govt_wage_adjustment_amount' => (float) ($entry->govt_wage_adjustment_amount ?? 0),
            'govt_wage_adjustment_type' => $entry->govt_wage_adjustment_type,
            'govt_wage_missing_reason' => $this->govtWageMissingReasonForEntry($entry, $run),
            'present_days' => (float) ($entry->present_days ?? 0),
            'half_days' => (float) ($entry->half_days ?? 0),
            'week_off_worked_days' => (float) ($entry->week_off_worked_days ?? 0),
            'paid_days' => (float) ($entry->paid_days ?? 0),
            'ot_enabled' => (bool) ($entry->ot_enabled ?? $entry->employee?->employee?->ot_flag ?? false),
            'incentive_days' => (float) ($entry->incentive_days ?? 0),
            'incentive_amount' => (float) ($entry->incentive_amount ?? 0),
            'incentive_per_day_rate' => $this->incentivePerDayRateForEntry($entry, $run),
            'regular_earnings' => $this->regularEarningsForEntry($entry),
            'attendance_extra_days' => (float) ($entry->attendance_extra_days ?? $attendanceExtra['days']),
            'attendance_extra_amount' => (float) ($entry->attendance_extra_amount ?? $attendanceExtra['amount']),
            'apply_attendance_extra' => (bool) ($entry->apply_attendance_extra ?? false),
            'attendance_extra_applied' => (bool) ($entry->attendance_extra_applied ?? false),
            'run_apply_attendance_extra' => (bool) ($run?->apply_attendance_extra ?? false),
            'can_toggle_attendance_extra' => $this->canToggleAttendanceExtra($entry, $run),
            'mispunch_count' => (int) ($entry->mispunch_count ?? 0),
            'has_mispunch' => (bool) ($entry->has_mispunch ?? false),
            'mispunch_records' => ($entry->has_mispunch ?? false)
                ? $this->mispunchService->recordsPayloadForEntry($entry)
                : [],
            'basic' => (float) $entry->basic,
            'total_earnings' => (float) $entry->total_earnings,
            'total_deductions' => (float) $entry->total_deductions,
            'net_salary' => (float) $entry->net_salary,
            'pf_employee' => (float) $entry->pf_employee,
            'pf_wages' => (float) ($entry->pf_wages ?: $entry->basic),
            'pf_employer' => (float) $entry->pf_employer,
            'pf_eps_employer' => (float) ($entry->pf_eps_employer ?? 0),
            'pf_epf_employer' => (float) ($entry->pf_epf_employer ?? 0),
            'pf_admin_employer' => (float) ($entry->pf_admin_employer ?? 0),
            'pf_challan_ac1' => (float) ($entry->pf_challan_ac1 ?? 0),
            'pf_challan_ac2' => (float) ($entry->pf_challan_ac2 ?? 0),
            'pf_challan_ac10' => (float) ($entry->pf_challan_ac10 ?? 0),
            'pf_challan_total' => (float) ($entry->pf_challan_total ?? 0),
            'pf_breakdown' => $this->pfBreakdownForEntry($entry, $run),
            'esi_employee' => (float) $entry->esi_employee,
            'esi_employer' => (float) $entry->esi_employer,
            'pt_amount' => (float) $entry->pt_amount,
            'pt_breakdown' => $this->ptBreakdownForEntry($entry, $run),
            'earnings_breakdown' => $this->componentEarningsBreakdown($entry->earnings_breakdown ?? []),
            'deductions_breakdown' => $entry->deductions_breakdown ?? [],
            'advance_allocations' => $advanceAllocations,
            'advance_recovery_amount' => $advanceRecoveryAmount,
            'advance_recovery_deferred' => $advanceRecoveryDeferred,
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

    private function payrollStandardWorkingDays(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): float
    {
        $stored = (float) ($entry->working_days ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $branchId = $run?->branch_id ?? $entry->run?->branch_id;
        if ($branchId) {
            return $this->branchPayrollSettings->resolveWorkingDays(
                (int) $branchId,
                $run?->financial_year ?? $entry->run?->financial_year
            );
        }

        return BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;
    }

    private function branchUsesGovtWageRules(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): bool
    {
        $branchId = $run?->branch_id ?? $entry->run?->branch_id;
        if (! $branchId) {
            return false;
        }

        return (bool) \App\Models\Branch::find($branchId)?->use_government_wage_rules;
    }

    private function govtWageMissingReasonForEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): ?string
    {
        if ($entry->govt_min_wage_per_day !== null || $entry->govt_min_wage_used !== null) {
            return null;
        }

        $branchId = $run?->branch_id ?? $entry->run?->branch_id;
        if (! $branchId || ! $entry->employee) {
            return null;
        }

        $branch = \App\Models\Branch::find($branchId);
        if (! $branch?->use_government_wage_rules) {
            return null;
        }

        $asOf = $run?->pay_period_end ?? $entry->run?->pay_period_end;
        $govtWage = $this->branchPayrollSettings->resolveGovtMinWageForEmployee(
            $entry->employee,
            (int) $branchId,
            $asOf ? \Carbon\Carbon::parse($asOf) : null
        );

        return $govtWage['missing_reason'] ?? null;
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

        $adminPct = PayrollParameter::pfAdminChargePct($params);
        $storedAdmin = (float) ($entry->pf_admin_employer ?? 0);
        $admin = $storedAdmin > 0
            ? $storedAdmin
            : max(0, round((float) $entry->pf_employer - $eps - $epfEmployer, 0));
        if ($admin <= 0 && $wages > 0) {
            $admin = round($wages * $adminPct / 100, 0);
        }

        $employerTotal = (float) $entry->pf_employer;
        if ($employerTotal <= 0) {
            $employerTotal = $eps + $epfEmployer + $admin;
        }

        $employeePf = (float) $entry->pf_employee;
        $storedAc1 = (float) ($entry->pf_challan_ac1 ?? 0);
        $storedAc2 = (float) ($entry->pf_challan_ac2 ?? 0);
        $storedAc10 = (float) ($entry->pf_challan_ac10 ?? 0);
        $storedChallanTotal = (float) ($entry->pf_challan_total ?? 0);

        $ac1 = $storedAc1 > 0 ? $storedAc1 : ($employeePf + $epfEmployer);
        $ac2 = $storedAc2 > 0 ? $storedAc2 : $eps;
        $ac10 = $storedAc10 > 0 ? $storedAc10 : $admin;
        $challanTotal = $storedChallanTotal > 0 ? $storedChallanTotal : ($ac1 + $ac2 + $ac10);

        return [
            'wages' => $wages,
            'employee_pct' => $employeePct,
            'employee' => $employeePf,
            'eps_pct' => $epsPct,
            'eps' => $eps,
            'epf_employer_pct' => $epfPct,
            'epf_employer' => $epfEmployer,
            'admin_pct' => $adminPct,
            'admin' => $ac10,
            'employer_total' => $employerTotal,
            'challan_ac1' => $ac1,
            'challan_ac2' => $ac2,
            'challan_ac10' => $ac10,
            'challan_total' => $challanTotal,
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

    private function incentivePerDayRateForEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): float
    {
        $incentiveDays = (float) ($entry->incentive_days ?? 0);
        if ($incentiveDays <= 0) {
            return 0.0;
        }

        $workingDays = $this->payrollStandardWorkingDays($entry, $run);
        $salary = EmployeeSalary::where('employee_id', $entry->employee_id)->first();
        $structureGross = (float) ($salary?->monthly_gross ?? $entry->monthly_gross ?? 0);

        if ($workingDays <= 0 || $structureGross <= 0) {
            return 0.0;
        }

        return round($structureGross / $workingDays, 2);
    }

    private function grossInputModeForEntry(SalaryPayrollEntry $entry): string
    {
        $salary = EmployeeSalary::where('employee_id', $entry->employee_id)->first();

        return $salary?->gross_input_mode === 'day' ? 'day' : 'month';
    }

    private function regularEarningsForEntry(SalaryPayrollEntry $entry): float
    {
        if ($entry->govt_wage_salary_applied && $entry->contract_regular_earnings !== null) {
            return round((float) $entry->contract_regular_earnings, 0);
        }

        $totalEarnings = (float) $entry->total_earnings;
        $incentiveAmount = (float) ($entry->incentive_amount ?? 0);
        $extraAmount = ($entry->attendance_extra_applied ?? false)
            ? (float) ($entry->attendance_extra_amount ?? 0)
            : 0.0;

        return round(max(0, $totalEarnings - $incentiveAmount - $extraAmount), 0);
    }

    private function actualPaidDaysForEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): float
    {
        $govtPaidDays = (float) ($entry->govt_wage_paid_days ?? $entry->paid_days ?? 0);
        $stored = $entry->actual_paid_days !== null ? (float) $entry->actual_paid_days : 0.0;

        if (! ($entry->govt_wage_salary_applied ?? false)) {
            return $stored > 0 ? $stored : $govtPaidDays;
        }

        if ($stored > $govtPaidDays + 0.009) {
            return round($stored, 2);
        }

        $contractEarnings = (float) ($entry->contract_regular_earnings ?? 0);
        $perDayRate = $this->incentivePerDayRateForEntry($entry, $run);
        if ($contractEarnings > 0 && $perDayRate > 0) {
            $derivedDays = round($contractEarnings / $perDayRate, 2);
            if ($derivedDays > $govtPaidDays + 0.009) {
                return $derivedDays;
            }
        }

        $workingDays = $this->payrollStandardWorkingDays($entry, $run);
        $presentDays = (float) ($entry->present_days ?? 0);
        $weekOffWorkedDays = (float) ($entry->week_off_worked_days ?? 0);
        $otEnabled = (bool) ($entry->ot_enabled ?? $entry->employee?->employee?->ot_flag ?? false);
        $attendancePaidDays = min($presentDays, $workingDays) + $weekOffWorkedDays;
        if ($otEnabled && $attendancePaidDays > $workingDays) {
            $attendancePaidDays = $workingDays;
        }

        if ($attendancePaidDays > $govtPaidDays + 0.009) {
            return round($attendancePaidDays, 2);
        }

        return $stored > 0 ? round($stored, 2) : $govtPaidDays;
    }

    /**
     * @return array{days: float, amount: float}
     */
    private function attendanceExtraForEntry(SalaryPayrollEntry $entry, ?SalaryPayrollRun $run = null): array
    {
        $otEnabled = (bool) ($entry->ot_enabled ?? $entry->employee?->employee?->ot_flag ?? false);
        if ($otEnabled) {
            return ['days' => 0.0, 'amount' => 0.0];
        }

        $workingDays = $this->payrollStandardWorkingDays($entry, $run);
        $paidDays = (float) ($entry->paid_days ?? 0);
        if ($paidDays <= $workingDays) {
            return ['days' => 0.0, 'amount' => 0.0];
        }

        $extraDays = $paidDays - $workingDays;
        $salary = EmployeeSalary::where('employee_id', $entry->employee_id)->first();
        $structureGross = (float) ($salary?->monthly_gross ?? $entry->monthly_gross ?? 0);
        $extraAmount = $workingDays > 0
            ? round(($structureGross / $workingDays) * $extraDays, 0)
            : 0.0;

        return ['days' => round($extraDays, 2), 'amount' => $extraAmount];
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

    /**
     * @param  \Illuminate\Support\Collection<int, SalaryPayrollEntry>  $entries
     * @return \Illuminate\Support\Collection<int, SalaryAdvanceRequest>
     */
    /**
     * @param  array<int, int>  $employeeIds
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, SalaryAdvanceRequest>>
     */
    private function deferredAdvanceRecoveriesForEmployees(array $employeeIds, SalaryPayrollRun $run): \Illuminate\Support\Collection
    {
        if ($employeeIds === [] || ! $run->pay_period_end) {
            return collect();
        }

        $periodEnd = $run->pay_period_end->format('Y-m-d');

        return SalaryAdvanceRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', [SalaryAdvanceRequest::STATUS_DISBURSED, SalaryAdvanceRequest::STATUS_RECOVERING])
            ->whereRaw('paid_amount < COALESCE(approved_amount, requested_amount)')
            ->whereDate('disbursement_date', '>', $periodEnd)
            ->orderBy('disbursement_date')
            ->get(['id', 'employee_id', 'approved_amount', 'requested_amount', 'paid_amount', 'disbursement_date', 'status'])
            ->groupBy('employee_id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, SalaryAdvanceRequest>>|null  $deferredAdvanceMap
     * @return array<int, array<string, mixed>>
     */
    private function advanceRecoveryDeferredPayload(
        SalaryPayrollEntry $entry,
        ?SalaryPayrollRun $run,
        ?\Illuminate\Support\Collection $deferredAdvanceMap
    ): array {
        if (! $deferredAdvanceMap || ! $run?->pay_period_end) {
            return [];
        }

        return $deferredAdvanceMap
            ->get($entry->employee_id, collect())
            ->map(fn (SalaryAdvanceRequest $advance) => [
                'salary_advance_request_id' => $advance->id,
                'pending_amount' => (float) $advance->pending_amount,
                'disbursement_date' => $advance->disbursement_date?->format('Y-m-d'),
                'pay_period_end' => $run->pay_period_end->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SalaryPayrollEntry>  $entries
     * @return \Illuminate\Support\Collection<int, SalaryAdvanceRequest>
     */
    private function advanceRequestsForEntries(\Illuminate\Support\Collection $entries): \Illuminate\Support\Collection
    {
        $ids = $entries
            ->flatMap(fn (SalaryPayrollEntry $entry) => collect($entry->advance_allocations ?? [])
                ->pluck('salary_advance_request_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return SalaryAdvanceRequest::query()
            ->whereIn('id', $ids)
            ->get(['id', 'approved_amount', 'requested_amount', 'paid_amount', 'disbursement_date', 'status', 'application_date'])
            ->keyBy('id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SalaryAdvanceRequest>|null  $advanceMap
     * @return array<int, array<string, mixed>>
     */
    private function advanceAllocationsPayload(SalaryPayrollEntry $entry, ?\Illuminate\Support\Collection $advanceMap = null): array
    {
        $allocations = $entry->advance_allocations ?? [];
        if ($allocations === []) {
            return [];
        }

        $ids = collect($allocations)
            ->pluck('salary_advance_request_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $advances = $advanceMap ?? SalaryAdvanceRequest::query()
            ->whereIn('id', $ids)
            ->get(['id', 'approved_amount', 'requested_amount', 'paid_amount', 'disbursement_date', 'status', 'application_date'])
            ->keyBy('id');

        return collect($allocations)
            ->map(function (array $allocation) use ($advances) {
                $requestId = (int) ($allocation['salary_advance_request_id'] ?? 0);
                $amount = round((float) ($allocation['amount'] ?? 0), 2);
                if ($requestId <= 0 || $amount <= 0) {
                    return null;
                }

                /** @var SalaryAdvanceRequest|null $advance */
                $advance = $advances->get($requestId);
                $approved = $advance
                    ? (float) ($advance->approved_amount ?? $advance->requested_amount)
                    : null;
                $paidTotal = $advance ? (float) $advance->paid_amount : null;
                $paidBefore = $paidTotal !== null ? max(0, round($paidTotal - $amount, 2)) : null;

                return [
                    'salary_advance_request_id' => $requestId,
                    'amount' => $amount,
                    'approved_amount' => $approved,
                    'paid_before' => $paidBefore,
                    'paid_total' => $paidTotal,
                    'pending_amount' => $advance ? (float) $advance->pending_amount : null,
                    'disbursement_date' => $advance?->disbursement_date?->format('Y-m-d'),
                    'application_date' => $advance?->application_date?->format('Y-m-d'),
                    'status' => $advance?->status,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function totalAdvanceRecoveryForRun(SalaryPayrollRun $run): float
    {
        return (float) SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->get(['advance_allocations'])
            ->sum(function (SalaryPayrollEntry $entry) {
                return collect($entry->advance_allocations ?? [])
                    ->sum(fn (array $allocation) => (float) ($allocation['amount'] ?? 0));
            });
    }

    private function advanceRecoveryEmployeeCountForRun(SalaryPayrollRun $run): int
    {
        return SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->get(['advance_allocations'])
            ->filter(function (SalaryPayrollEntry $entry) {
                return collect($entry->advance_allocations ?? [])
                    ->sum(fn (array $allocation) => (float) ($allocation['amount'] ?? 0)) > 0;
            })
            ->count();
    }
}
