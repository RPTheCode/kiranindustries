<?php

namespace App\Exports;

use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Services\SalaryPayroll\SalaryPayrollChallanReportBuilder;
use App\Services\SalaryPayroll\SalaryPayrollRunService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StatutoryChallanReportExport implements FromView, ShouldAutoSize, WithStyles
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private SalaryPayrollRun $run,
        private array $filters = []
    ) {}

    public function view(): View
    {
        $run = $this->run->load('branch:id,name');
        $report = app(SalaryPayrollChallanReportBuilder::class)->build($run, $this->filters);
        $summary = app(SalaryPayrollRunService::class)->statutoryChallanSummary($run);

        return view('exports.statutory-challan-report', [
            'run' => $run,
            'report' => $report,
            'summary' => $summary,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
        ];
    }
}
