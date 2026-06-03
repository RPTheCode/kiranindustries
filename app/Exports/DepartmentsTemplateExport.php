<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepartmentsTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'code' => 'HRD',
                'short_code' => 'HR',
                'name' => 'Human Resources',
                'status' => 'active',
            ],
            [
                'code' => 'ENG',
                'short_code' => 'EN',
                'name' => 'Engineering',
                'status' => 'active',
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'code',
            'short_code',
            'name',
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
