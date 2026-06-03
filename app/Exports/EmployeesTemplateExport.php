<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'PRODUCTION',
                'STAFF',
                '1001',
                'P',
                'G',
                '',
                'Male',
                'Full Time',
                '',
                'HR',
                'Executive',
                'Sample Employee',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'sample@example.com',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'NO',
                '',
                'NO',
                '',
                'NO',
                'NO',
                'NO',
                'NO',
                'NO',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '26',
                '',
                '',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'SECTION',
            'CATEGORY',
            'EMP.CODE',
            'O / P',
            'SHIFT',
            'UAN NO',
            'GENDER',
            'STATUS',
            'EMP.SKILL',
            'DEPARTMENT',
            'DESIGNATION',
            'EMP.NAME',
            'FATHER.NAME',
            'PLACE',
            'LOCAL ADDRESS',
            'LOCAL CITY',
            'LOCAL PINCODE',
            'STATE',
            'PERM.ADDRESS',
            'EMP.EMAILS',
            'PHONE NO 1',
            'PHONE NO 2',
            'ITAX NO.',
            'DRIVING LIC.',
            'ELEC.CARD',
            'AADHAR CARD',
            'BRITH DT.',
            'JOINING DT.',
            'CONFIRM DT.',
            'EDUCATION',
            'EXPERINCE',
            'PF.FLAG',
            'PF.NO.',
            'ESIC FLAG',
            'ESIC NO.',
            'DAILY FLAG',
            'PTAX FLAG',
            'HOD FLAG',
            'BONUS FLAG',
            'OT FLAG',
            'OT HOURS',
            'BANK NAME',
            'BANK TYPE',
            'BANK IFSC',
            'BANK A/C.',
            'GROSS AMT',
            'BASIC',
            'H.R.A.',
            'CONVEY',
            'ALLOWANCE',
            'MEDICAL',
            'EDUCATION ALLOWANCE',
            'DAYS',
            'WEEK OFF',
            'LUNCH TIME',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
