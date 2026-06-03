<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SalaryCtcExport implements FromView, WithEvents, WithColumnWidths
{
    public function __construct(protected $reportData, protected string $companyTitle) {}

    public function view(): View
    {
        return view('exports.salary_ctc_excel', [
            'reportData' => $this->reportData,
            'companyName' => $this->companyTitle,
            'reportTitle' => 'SALARY CTC BREAKUP',
            'period' => 'AS OF ' . now()->format('d-M-Y'),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // S.N
            'B' => 10,  // CODE
            'C' => 30,  // NAME
            'D' => 10,  // W.DAYS
            'E' => 13,  // BASIC
            'F' => 13,  // HRA
            'G' => 13,  // CONVEY.
            'H' => 13,  // MED.
            'I' => 13,  // BONUS
            'J' => 15,  // GROSS
            'K' => 13,  // PF
            'L' => 13,  // ESI
            'M' => 13,  // PT
            'N' => 13,  // IT
            'O' => 15,  // DEDUCT
            'P' => 15,  // NET PAY
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
                    
                // Apply borders to all cells
                $sheet->getStyle($sheet->calculateWorksheetDimension())
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('e2e8f0'));
            },
        ];
    }
}
