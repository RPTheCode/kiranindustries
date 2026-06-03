<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class HolidaysTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return collect([
            [
                '1',
                'New Year',
                '2026-01-01',
                '2026-01-01',
                'national',
                'Happy New Year',
                'yes',
                'yes',
                '-'
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'sr_no',
            'name',
            'start_date',
            'end_date',
            'category',
            'description',
            'is_recurring',
            'is_paid',
            'is_half_day'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
