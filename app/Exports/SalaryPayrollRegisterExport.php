<?php

namespace App\Exports;

use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Services\SalaryPayroll\SalaryPayrollRegisterBuilder;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalaryPayrollRegisterExport implements FromView, ShouldAutoSize, WithStyles
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
        $register = app(SalaryPayrollRegisterBuilder::class)->build($run, $this->filters);

        return view('exports.salary-payroll-register', [
            'run' => $run,
            'register' => $register,
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
