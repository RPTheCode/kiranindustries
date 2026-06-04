<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class EmployeesSampleExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return collect([
            [
                'John Doe',
                'john@example.com',
                '12345678',
                'EMP001',
                '1234567890',
                '1990-01-01',
                'male',
                'Human Resources', // department_name
                'HR Manager', // designation_name
                '2023-01-01',
                'Full Time',
                'Active', // employment_status
                '123 Main St',
                'Apt 4B',
                'New York',
                'NY',
                'USA',
                '10001',
                'Jane Doe',
                'Wife',
                '0987654321',
                'Same as above',
                'Bank of America',
                'John Doe',
                '123456789012',
                'BOFAUS3N',
                'New York Branch',
                'TAX123'
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'name',
            'email',
            'password',
            'employee_id',
            'phone',
            'date_of_birth',
            'gender',
            'department_name',
            'designation_name',
            'date_of_joining',
            'employment_type',
            'employment_status',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'country',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_number',
            'emergency_contact_address',
            'bank_name',
            'account_holder_name',
            'account_number',
            'bank_identifier_code',
            'bank_branch',
            'tax_payer_id'
        ];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
