<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LoanLedgerExport implements FromView, WithEvents, WithColumnWidths
{
    public function __construct(protected $employees, protected string $companyTitle) {}

    public function view(): View
    {
        return view('exports.loan_ledger_excel', [
            'employees' => $this->employees,
            'companyName' => $this->companyTitle,
            'reportTitle' => 'LOAN & ADVANCE LEDGER',
            'period' => 'AS OF ' . now()->format('d-M-Y'),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // S.N
            'B' => 12,  // CODE
            'C' => 35,  // NAME
            'D' => 20,  // LOAN TYPE
            'E' => 20,  // PERIOD (MONTHS)
            'F' => 25,  // TOTAL AMOUNT
            'G' => 25,  // INSTALLMENT
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
                $sheet->getStyle($sheet->calculateWorksheetDimension())
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                    
                $sheet->getStyle($sheet->calculateWorksheetDimension())
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('e2e8f0'));
            },
        ];
    }
}
