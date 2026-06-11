<?php

namespace App\Http\Controllers\SalaryPayroll;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Services\SalaryPayroll\SalaryPayrollPayslipService;
use App\Services\SalaryPayroll\SalaryPayrollRunService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SalaryPayrollPayslipController extends Controller
{
    public function __construct(
        private SalaryPayrollRunService $runService,
        private SalaryPayrollPayslipService $payslipService
    ) {}

    public function index(Request $request)
    {
        $branchId = session('active_branch_id');
        $branchName = $branchId ? Branch::find($branchId)?->name : null;

        $financialYear = $request->get('financial_year', currentFinancialYearLabel());
        $fyOptions = $this->financialYearOptions($branchId, $financialYear);

        if (! in_array($financialYear, $fyOptions, true)) {
            $financialYear = $fyOptions[0] ?? currentFinancialYearLabel();
        }

        $months = $this->formatMonths($this->runService->monthsForFinancialYear($financialYear));
        $monthValues = collect($months)->pluck('value')->all();

        $defaultMonth = $this->defaultMonthYear($branchId, $financialYear, $monthValues);
        $monthYear = $request->get('month_year', $defaultMonth);
        if (! in_array($monthYear, $monthValues, true)) {
            $monthYear = $defaultMonth;
        }

        $monthEmployeeCounts = $this->monthEmployeeCounts($branchId, $financialYear);
        $months = array_map(function (array $month) use ($monthEmployeeCounts) {
            $count = $monthEmployeeCounts[$month['value']] ?? 0;
            $month['has_payroll'] = $count > 0;
            $month['employee_count'] = $count;

            return $month;
        }, $months);

        $run = SalaryPayrollRun::query()
            ->with(['branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('financial_year', $financialYear)
            ->where('month_year', $monthYear)
            ->orderByDesc('id')
            ->first();

        $summary = [
            'employee_count' => 0,
            'generated_count' => 0,
            'downloaded_count' => 0,
            'total_net' => 0,
            'payroll_status' => null,
            'run_id' => null,
        ];

        $entries = null;

        if ($run) {
            $baseQuery = SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->when(! $run->isFinalized(), fn ($q) => $q->where('is_locked', true));

            $summaryQuery = (clone $baseQuery);
            $summary['employee_count'] = (clone $summaryQuery)->count();
            $summary['total_net'] = (float) (clone $summaryQuery)->sum('net_salary');
            $summary['generated_count'] = (clone $summaryQuery)->whereHas('payslip')->count();
            $summary['downloaded_count'] = (clone $summaryQuery)
                ->whereHas('payslip', fn ($q) => $q->whereNotNull('downloaded_at'))
                ->count();
            $summary['payroll_status'] = $run->status;
            $summary['run_id'] = $run->id;

            $query = (clone $baseQuery)
                ->with([
                    'employee:id,name,avatar',
                    'employee.employee' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'user_id', 'employee_id', 'emy_code'),
                    'payslip:id,salary_payroll_entry_id,payslip_number,generated_at,downloaded_at,file_path',
                ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('employee', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhereHas('employee', function ($emp) use ($search) {
                                $emp->withoutGlobalScopes()
                                    ->where('employee_id', 'like', "%{$search}%")
                                    ->orWhere('emy_code', 'like', "%{$search}%");
                            });
                    })->orWhereHas('payslip', fn ($p) => $p->where('payslip_number', 'like', "%{$search}%"));
                });
            }

            if ($request->filled('status') && $request->status !== 'all') {
                match ($request->status) {
                    'ready' => $query->whereDoesntHave('payslip'),
                    'generated' => $query->whereHas('payslip', fn ($q) => $q->whereNull('downloaded_at')),
                    'downloaded' => $query->whereHas('payslip', fn ($q) => $q->whereNotNull('downloaded_at')),
                    default => null,
                };
            }

            $entries = $query
                ->orderBy('id')
                ->paginate($request->integer('per_page', 10))
                ->withQueryString();

            $entries->getCollection()->transform(fn (SalaryPayrollEntry $entry) => $this->formatEntry($entry, $run));
        }

        return Inertia::render('hr/salary-payroll/payslips/index', [
            'payslips' => $entries,
            'summary' => $summary,
            'run' => $run ? [
                'id' => $run->id,
                'title' => $run->title,
                'status' => $run->status,
                'month_year' => $run->month_year,
                'financial_year' => $run->financial_year,
                'pay_period_start' => $run->pay_period_start?->format('Y-m-d'),
                'pay_period_end' => $run->pay_period_end?->format('Y-m-d'),
                'branch_name' => $run->branch?->name,
            ] : null,
            'financialYear' => $financialYear,
            'financialYearOptions' => $fyOptions,
            'monthYear' => $monthYear,
            'months' => $months,
            'activeBranchId' => $branchId,
            'activeBranchName' => $branchName,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function preview(SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry): Response
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $this->assertEntryBelongsToRun($salaryPayrollRun, $salaryPayrollEntry);

        try {
            $path = $this->payslipService->previewPathForEntry($salaryPayrollEntry);
            $payslip = $salaryPayrollEntry->fresh(['payslip'])->payslip;
            $filename = $payslip
                ? $this->payslipService->downloadFilename($payslip)
                : 'payslip.pdf';

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (\Throwable $e) {
            abort(500, __('Failed to load payslip preview.'));
        }
    }

    public function download(SalaryPayrollRun $salaryPayrollRun, SalaryPayrollEntry $salaryPayrollEntry): BinaryFileResponse|RedirectResponse
    {
        $this->assertBranchAccess($salaryPayrollRun);
        $this->assertEntryBelongsToRun($salaryPayrollRun, $salaryPayrollEntry);

        try {
            $payslip = $this->payslipService->ensurePayslip($salaryPayrollEntry);
            $path = $this->payslipService->downloadPath($payslip);

            return response()->download($path, $this->payslipService->downloadFilename($payslip));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('hr.salary-payroll.payslips.index', [
                'financial_year' => $salaryPayrollRun->financial_year,
                'month_year' => $salaryPayrollRun->month_year,
            ])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('hr.salary-payroll.payslips.index', [
                'financial_year' => $salaryPayrollRun->financial_year,
                'month_year' => $salaryPayrollRun->month_year,
            ])->with('error', __('Failed to download payslip: :message', ['message' => $e->getMessage()]));
        }
    }

    private function assertBranchAccess(SalaryPayrollRun $run): void
    {
        $branchId = session('active_branch_id');
        if ($branchId && $run->branch_id && (int) $run->branch_id !== (int) $branchId) {
            abort(403);
        }
    }

    private function assertEntryBelongsToRun(SalaryPayrollRun $run, SalaryPayrollEntry $entry): void
    {
        if ((int) $entry->salary_payroll_run_id !== (int) $run->id) {
            abort(404);
        }
    }

    /**
     * @return array<int, string>
     */
    private function financialYearOptions(?int $branchId, ?string $preferredYear = null): array
    {
        $fromDb = SalaryPayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('financial_year')
            ->distinct()
            ->orderByDesc('financial_year')
            ->pluck('financial_year')
            ->filter()
            ->values()
            ->all();

        $options = array_values(array_unique(array_filter([
            $preferredYear,
            currentFinancialYearLabel(),
            ...$fromDb,
            nextFinancialYearLabel(),
        ])));

        usort($options, fn (string $a, string $b) => (int) explode('-', $b)[0] <=> (int) explode('-', $a)[0]);

        return $options;
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $months
     * @return array<int, array{value: string, label: string, short_label: string, year: int}>
     */
    private function formatMonths(array $months): array
    {
        return array_map(function (array $month) {
            $date = Carbon::createFromFormat('Y-m', $month['value']);

            return [
                'value' => $month['value'],
                'label' => $month['label'],
                'short_label' => $date->format('M'),
                'year' => (int) $date->format('Y'),
            ];
        }, $months);
    }

    /**
     * @param  array<int, string>  $monthValues
     */
    private function defaultMonthYear(?int $branchId, string $financialYear, array $monthValues): string
    {
        $latestWithRun = SalaryPayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('financial_year', $financialYear)
            ->orderByDesc('month_year')
            ->value('month_year');

        if ($latestWithRun && in_array($latestWithRun, $monthValues, true)) {
            return $latestWithRun;
        }

        $current = now()->format('Y-m');

        return in_array($current, $monthValues, true)
            ? $current
            : ($monthValues[0] ?? $current);
    }

    /**
     * @return array<string, int>
     */
    private function monthEmployeeCounts(?int $branchId, string $financialYear): array
    {
        $runs = SalaryPayrollRun::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('financial_year', $financialYear)
            ->get(['id', 'month_year', 'status']);

        $counts = [];
        foreach ($runs as $run) {
            $count = SalaryPayrollEntry::query()
                ->where('salary_payroll_run_id', $run->id)
                ->when(! $run->isFinalized(), fn ($q) => $q->where('is_locked', true))
                ->count();

            if ($count > 0) {
                $counts[$run->month_year] = ($counts[$run->month_year] ?? 0) + $count;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEntry(SalaryPayrollEntry $entry, SalaryPayrollRun $run): array
    {
        $payslip = $entry->payslip;
        $empDetail = $entry->employee?->employee;
        $employeeCode = $empDetail?->employee_id ?: $empDetail?->emy_code;

        $status = 'ready';
        if ($payslip?->downloaded_at) {
            $status = 'downloaded';
        } elseif ($payslip?->generated_at) {
            $status = 'generated';
        }

        return [
            'id' => $entry->id,
            'run_id' => $run->id,
            'employee' => [
                'id' => $entry->employee_id,
                'name' => $entry->employee?->name,
                'avatar' => $entry->employee?->avatar,
                'employee_code' => $employeeCode,
            ],
            'pay_date' => $run->pay_period_end?->format('Y-m-d'),
            'net_pay' => (float) $entry->net_salary,
            'payslip_number' => $payslip?->payslip_number,
            'status' => $status,
            'generated_at' => $payslip?->generated_at?->format('Y-m-d H:i'),
            'downloaded_at' => $payslip?->downloaded_at?->format('Y-m-d H:i'),
            'can_download' => $entry->isLocked() || $run->isFinalized(),
        ];
    }
}
