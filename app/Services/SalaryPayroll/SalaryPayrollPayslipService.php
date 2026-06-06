<?php

namespace App\Services\SalaryPayroll;

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

        $earnings = collect($entry->earnings_breakdown ?? [])
            ->filter(fn ($amount) => (float) $amount > 0)
            ->sortKeys();

        $deductions = collect($entry->deductions_breakdown ?? [])
            ->filter(fn ($amount) => (float) $amount > 0)
            ->sortKeys();

        $creatorId = $run?->created_by ?? Auth::id();

        $data = [
            'payslip' => $payslip,
            'entry' => $entry,
            'run' => $run,
            'employee' => $employee,
            'employeeDetail' => $empDetail,
            'earnings' => $earnings,
            'deductions' => $deductions,
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
}
