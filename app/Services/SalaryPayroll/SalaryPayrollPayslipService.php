<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollPayslip;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class SalaryPayrollPayslipService
{
    public function canGeneratePayslip(SalaryPayrollEntry $entry): bool
    {
        $run = $entry->run;

        return $entry->is_locked || ($run && $run->isFinalized());
    }

    public function generatePayslipNumber(SalaryPayrollEntry $entry): string
    {
        $run = $entry->run ?? SalaryPayrollRun::find($entry->salary_payroll_run_id);
        $periodEnd = $run?->pay_period_end ?? now();
        $date = Carbon::parse($periodEnd);
        $prefix = 'SP-' . $date->format('Ym') . '-';

        $user = User::query()
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()])
            ->find($entry->employee_id);

        $employeeCode = (string) ($user?->employee?->employee_id ?? $user?->employee?->emy_code ?? $entry->employee_id);
        $employeeCode = trim($employeeCode) !== '' ? $employeeCode : str_pad((string) $entry->employee_id, 4, '0', STR_PAD_LEFT);

        $baseNumber = $prefix . $employeeCode;

        if (! SalaryPayrollPayslip::where('payslip_number', $baseNumber)->exists()) {
            return $baseNumber;
        }

        $count = 1;
        while (SalaryPayrollPayslip::where('payslip_number', "{$baseNumber}-{$count}")->exists()) {
            $count++;
        }

        return "{$baseNumber}-{$count}";
    }

    public function ensurePayslip(SalaryPayrollEntry $entry, bool $forceRegenerate = false): SalaryPayrollPayslip
    {
        if (! $this->canGeneratePayslip($entry)) {
            throw new \InvalidArgumentException(__('Payslip is available only for locked or finalized payroll entries.'));
        }

        $entry->loadMissing([
            'run.branch',
            'employee.employee.category',
            'employee.employee.department',
            'employee.employee.designation',
            'employee.employee.shift',
            'employee.employee.branch',
        ]);

        $existing = SalaryPayrollPayslip::query()
            ->where('salary_payroll_entry_id', $entry->id)
            ->first();

        if ($existing && ! $forceRegenerate && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
            return $existing;
        }

        if ($existing && $forceRegenerate && $existing->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $payslip = $existing ?? new SalaryPayrollPayslip([
            'salary_payroll_entry_id' => $entry->id,
            'salary_payroll_run_id' => $entry->salary_payroll_run_id,
            'employee_id' => $entry->employee_id,
            'payslip_number' => $this->generatePayslipNumber($entry),
            'generated_by' => Auth::id(),
        ]);

        if (! $existing) {
            $payslip->save();
        }

        $filePath = $this->renderPdf($entry, $payslip);

        $payslip->update([
            'file_path' => $filePath,
            'generated_at' => now(),
            'generated_by' => Auth::id(),
        ]);

        return $payslip->fresh();
    }

    public function generateForEntry(SalaryPayrollEntry $entry): SalaryPayrollPayslip
    {
        return $this->ensurePayslip($entry, true);
    }

    public function removePayslipForEntry(SalaryPayrollEntry $entry): void
    {
        $payslip = SalaryPayrollPayslip::query()
            ->where('salary_payroll_entry_id', $entry->id)
            ->first();

        if (! $payslip) {
            return;
        }

        if ($payslip->file_path) {
            Storage::disk('public')->delete($payslip->file_path);
        }

        $payslip->delete();
    }

    public function generateForRun(SalaryPayrollRun $run): int
    {
        $entries = SalaryPayrollEntry::query()
            ->where('salary_payroll_run_id', $run->id)
            ->when(! $run->isFinalized(), fn ($q) => $q->where('is_locked', true))
            ->get();

        $count = 0;
        foreach ($entries as $entry) {
            try {
                $this->ensurePayslip($entry, true);
                $count++;
            } catch (\Throwable) {
                // skip broken entries
            }
        }

        return $count;
    }

    public function renderPdf(SalaryPayrollEntry $entry, SalaryPayrollPayslip $payslip): string
    {
        $run = $entry->run;
        $employee = $entry->employee;
        $empDetail = $employee?->employee;

        $earnings = $this->buildPayslipEarningLines($entry);

        $deductions = $this->buildPayslipDeductionLines($entry);

        $salaryMeta = $this->buildPayslipSalaryMeta($entry, $employee);

        $creatorId = $run?->created_by ?? Auth::id();

        $data = [
            'payslip' => $payslip,
            'entry' => $entry,
            'run' => $run,
            'employee' => $employee,
            'employeeDetail' => $empDetail,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'salaryMeta' => $salaryMeta,
            'themeColor' => brandThemeColor($creatorId),
            'currencySymbol' => pdfCurrencySymbol($creatorId),
            'companyName' => config('app.name', 'Kiran Industries'),
        ];

        $pdf = Pdf::loadView('salary-payroll.payslip', $data);

        $fileName = 'salary-payslip-' . $payslip->payslip_number . '.pdf';
        $filePath = 'salary-payroll-payslips/' . $fileName;

        Storage::disk('public')->put($filePath, $pdf->output());

        return $filePath;
    }

    public function downloadPath(SalaryPayrollPayslip $payslip): string
    {
        if (! $payslip->file_path || ! Storage::disk('public')->exists($payslip->file_path)) {
            $entry = $payslip->entry ?? SalaryPayrollEntry::find($payslip->salary_payroll_entry_id);
            if (! $entry) {
                throw new \InvalidArgumentException(__('Payslip file not found.'));
            }
            $payslip = $this->ensurePayslip($entry);
        }

        $payslip->update(['downloaded_at' => now()]);

        return Storage::disk('public')->path($payslip->file_path);
    }

    public function downloadFilename(SalaryPayrollPayslip $payslip): string
    {
        return 'payslip-' . $payslip->payslip_number . '.pdf';
    }

    public function createZipForRun(SalaryPayrollRun $run): string
    {
        $this->generateForRun($run);

        $payslips = SalaryPayrollPayslip::query()
            ->where('salary_payroll_run_id', $run->id)
            ->whereNotNull('file_path')
            ->get();

        if ($payslips->isEmpty()) {
            throw new \InvalidArgumentException(__('No payslips available to download.'));
        }

        $zipDir = storage_path('app/temp');
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zipPath = $zipDir . '/salary-payroll-' . $run->id . '-' . time() . '.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(__('Failed to create payslip archive.'));
        }

        foreach ($payslips as $payslip) {
            $fullPath = Storage::disk('public')->path($payslip->file_path);
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $this->downloadFilename($payslip));
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayslipSalaryMeta(SalaryPayrollEntry $entry, ?User $employee): array
    {
        $salary = $employee
            ? EmployeeSalary::where('employee_id', $employee->id)->first()
            : null;

        $grossInputMode = $salary?->gross_input_mode ?? 'month';
        $workingDays = max(1, (float) ($entry->working_days ?? 26));
        $dayRate = (float) ($salary?->per_day_salary ?? $entry->monthly_gross ?? 0);
        $isDayRate = $grossInputMode === 'day';

        $rateAmount = $isDayRate ? $dayRate : (float) $entry->monthly_gross;
        $ctc = $isDayRate ? round($dayRate * $workingDays, 0) : (float) $entry->monthly_gross;

        return [
            'is_day_rate' => $isDayRate,
            'rate_label' => $isDayRate ? 'Day Rate' : 'Monthly Gross',
            'rate_amount' => $rateAmount,
            'ctc' => $ctc,
            'contract_earnings' => $entry->contract_regular_earnings !== null
                ? (float) $entry->contract_regular_earnings
                : null,
            'govt_wage_applied' => (bool) $entry->govt_wage_salary_applied,
            'govt_wage_adjustment' => (float) ($entry->govt_wage_adjustment_amount ?? 0),
            'govt_wage_adjustment_type' => $entry->govt_wage_adjustment_type,
            'govt_wage_paid_days' => $entry->govt_wage_paid_days !== null ? (float) $entry->govt_wage_paid_days : null,
            'actual_paid_days' => $entry->actual_paid_days !== null ? (float) $entry->actual_paid_days : null,
            'govt_min_wage_per_day' => (float) ($entry->govt_min_wage_per_day ?? 0),
            'govt_wage_computed_earnings' => (float) ($entry->govt_wage_computed_earnings ?? 0),
            'pf_admin_employer' => (float) ($entry->pf_admin_employer ?? 0),
            'pf_challan_ac1' => (float) ($entry->pf_challan_ac1 ?? 0),
            'pf_challan_ac2' => (float) ($entry->pf_challan_ac2 ?? 0),
            'pf_challan_ac10' => (float) ($entry->pf_challan_ac10 ?? 0),
            'pf_challan_total' => (float) ($entry->pf_challan_total ?? 0),
        ];
    }

    /**
     * Earnings for payslip — one line per component, no duplicates.
     *
     * @return array<string, float>
     */
    public function buildPayslipEarningLines(SalaryPayrollEntry $entry): array
    {
        $lines = collect($entry->earnings_breakdown ?? [])
            ->filter(fn ($amount) => (float) $amount > 0);

        if ($lines->isNotEmpty()) {
            return $this->sortPayslipEarnings($lines)->all();
        }

        if ((float) $entry->basic > 0) {
            return ['Basic Salary' => (float) $entry->basic];
        }

        return [];
    }

    /**
     * Deductions for payslip — statutory items appear once (from breakdown only).
     *
     * @return array<string, float>
     */
    public function buildPayslipDeductionLines(SalaryPayrollEntry $entry): array
    {
        $lines = collect($entry->deductions_breakdown ?? [])
            ->filter(fn ($amount) => (float) $amount > 0);

        if ($lines->isNotEmpty()) {
            return $this->sortPayslipDeductions($lines)->all();
        }

        // Fallback for older entries without breakdown stored.
        $fallback = [];
        if ((float) $entry->pf_employee > 0) {
            $fallback['Provident Fund (PF)'] = (float) $entry->pf_employee;
        }
        if ((float) $entry->esi_employee > 0) {
            $fallback['ESIC'] = (float) $entry->esi_employee;
        }
        if ((float) $entry->pt_amount > 0) {
            $fallback['Professional Tax'] = (float) $entry->pt_amount;
        }

        return $fallback;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, float|int|string>  $lines
     * @return \Illuminate\Support\Collection<string, float>
     */
    private function sortPayslipDeductions($lines)
    {
        $priority = [
            'PROVIDENT FUND (PF)' => 1,
            'PF' => 1,
            'EPF' => 1,
            'ESIC' => 2,
            'ESI' => 2,
            'PROFESSIONAL TAX' => 3,
            'PT' => 3,
            'GOVT WAGE ADJUSTMENT' => 4,
            'TDS' => 5,
            'SALARY LOAN RECOVERY' => 6,
            'SALARY ADVANCE RECOVERY' => 7,
        ];

        return $lines->sortBy(fn ($amount, $name) => [
            $priority[strtoupper($name)] ?? 99,
            strtoupper($name),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, float|int|string>  $lines
     * @return \Illuminate\Support\Collection<string, float>
     */
    private function sortPayslipEarnings($lines)
    {
        $priority = ['BASIC' => 1, 'HRA' => 2, 'LTA' => 3, 'ALLOWANCE' => 4, 'SPECIAL ALLOWANCE' => 5];

        return $lines->sortBy(fn ($amount, $name) => [
            $priority[strtoupper($name)] ?? 99,
            strtoupper($name),
        ]);
    }
}
