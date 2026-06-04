<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeSampleExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            [
                '1',
                'John Doe',
                'john.doe@example.com',
                '123456',
                'KIRAN001',
                '1234567890',
                '1990-01-01',
                'male',
                'PHP, Laravel',
                'Engineering',
                'Software Engineer',
                '2023-01-01',
                'UAN1234567890',
                'ESIC123456',
                'Employee',
                'Active',
                '123 Main St',
                'Apt 4B',
                'New York',
                'NY',
                'USA',
                '10001',
                'Jane Doe',
                'Spouse',
                '0987654321',
                '123 Main St',
                'Bank of America',
                'John Doe',
                '123456789',
                'BOFAUS3N',
                'New York',
                'TAX123'
            ],
            [
                '2',
                'Jane Smith',
                'jane.smith@example.com',
                '123456',
                'KIRAN002',
                '0987654321',
                '1992-05-15',
                'female',
                'Carpentry',
                'Production',
                'Labourer',
                '2023-02-01',
                'UAN0987654321',
                'ESIC654321',
                'Labour',
                'Active',
                '456 Side St',
                '',
                'Los Angeles',
                'CA',
                'USA',
                '90001',
                'Robert Smith',
                'Brother',
                '1234567890',
                '456 Side St',
                'Chase Bank',
                'Jane Smith',
                '987654321',
                'CHASUS3L',
                'Los Angeles',
                'TAX456'
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'sr_no',
            'name',
            'email',
            'password',
            'employee_id',
            'phone',
            'date_of_birth',
            'gender',
            'skill',
            'department_name',
            'designation_name',
            'date_of_joining',
            // 'date_of_joining',
            // 'pf_number',
            // 'uan_number',
            'uan_number',
            'esic_number',
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

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
        ];
    }
}
