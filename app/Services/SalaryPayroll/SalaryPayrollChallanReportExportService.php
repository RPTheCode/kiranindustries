<?php

namespace App\Services\SalaryPayroll;

use App\Exports\StatutoryChallanReportExport;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Nick\SecureSpreadsheet\Encrypt;
use RuntimeException;

class SalaryPayrollChallanReportExportService
{
    public function buildFilename(SalaryPayrollRun $run): string
    {
        $period = $run->pay_period_start?->format('M_Y') ?? 'payroll';
        $branch = preg_replace('/[^A-Za-z0-9_-]+/', '_', $run->branch?->name ?? 'branch');

        return "Statutory_Challan_{$branch}_{$period}_".now()->format('Y-m-d_His').'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportEncrypted(SalaryPayrollRun $run, array $filters, string $password): string
    {
        $plain = Excel::raw(
            new StatutoryChallanReportExport($run, $filters),
            ExcelFormat::XLSX
        );

        if ($plain === '' || $plain === false) {
            throw new RuntimeException('Failed to generate statutory challan report spreadsheet.');
        }

        $tempDir = storage_path('app/temp/excel-encrypt');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('Unable to create temporary export directory.');
        }

        $encryptor = new Encrypt(true);

        return $encryptor->input($plain)
            ->setTempPathFolder($tempDir)
            ->password($password)
            ->output();
    }
}
