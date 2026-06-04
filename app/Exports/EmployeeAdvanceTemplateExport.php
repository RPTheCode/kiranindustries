<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeAdvanceTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'sr_no' => 1,
                'employee_id' => 'KIRAN001',
                'amount' => 5000,
                'pay_date' => now()->format('Y-m-d'),
                'remarks' => 'Monthly Advance',
            ],
            [
                'sr_no' => 2,
                'employee_id' => 'KIRAN002',
                'amount' => 2000,
                'pay_date' => now()->format('Y-m-d'),
                'remarks' => 'Emergency Advance',
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'sr_no',
            'employee_id',
            'amount',
            'pay_date',
            'remarks',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
