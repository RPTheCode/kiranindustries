<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BranchesTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'sr_no' => 1,
                'name' => 'Main Branch',
                'address' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'USA',
                'zip_code' => '10001',
                'phone' => '1234567890',
                'email' => 'branch@example.com',
                'branch_in_charge_name' => 'John Doe',
                'branch_in_charge_contact_number' => '9876543210',
                'status' => 'active',
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'sr_no',
            'name',
            'address',
            'city',
            'state',
            'country',
            'zip_code',
            'phone',
            'email',
            'branch_in_charge_name',
            'branch_in_charge_contact_number',
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
