<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DesignationsTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'name' => 'Fitter',
                'code' => 'FITTER',
                'rate' => 500,
                'department' => 'Production',
                'department_id' => '',
                'status' => 'active',
            ],
            [
                'name' => 'Helper',
                'code' => 'HELPER',
                'rate' => 300,
                'department' => 'Packing',
                'department_id' => '',
                'status' => 'active',
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'code',
            'rate',
            'department',
            'department_id',
            'status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
