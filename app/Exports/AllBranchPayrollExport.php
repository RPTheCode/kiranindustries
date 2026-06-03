<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class AllBranchPayrollExport implements FromView, ShouldAutoSize, WithEvents
{
    protected $dateFrom;
    protected $dateTo;

    public function __construct($dateFrom, $dateTo)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function view(): View
    {
        $user = auth()->user();
        $allowedBranchIds = $user ? $user->branches()->pluck('branches.id')->toArray() : [];

        if (session('active_branch_id')) {
            $allowedBranchIds = array_intersect($allowedBranchIds, [session('active_branch_id')]);
        }

        $summary = DB::table('branches')
            ->whereIn('branches.id', $allowedBranchIds)
            ->leftJoin('payroll_runs', function ($join) {
                $join->on('branches.id', '=', 'payroll_runs.branch_id')
                    ->whereIn('payroll_runs.created_by', getCompanyAndUsersId())
                    ->where('payroll_runs.salary_calculation_type', 'basic_pay')
                    ->where('payroll_runs.pay_period_start', '>=', $this->dateFrom)
                    ->where('payroll_runs.pay_period_end', '<=', $this->dateTo);
            })
            ->leftJoin('payroll_entries', 'payroll_runs.id', '=', 'payroll_entries.payroll_run_id')
            ->select(
                'branches.id as branch_id',
                'branches.name as branch_name',
                DB::raw("DATE_FORMAT(payroll_runs.pay_period_start, '%Y-%m') as pay_month"),
                DB::raw('COUNT(DISTINCT payroll_entries.employee_id) as employee_count'),
                DB::raw('COALESCE(SUM(payroll_entries.present_days + payroll_entries.week_off_present_days + (payroll_entries.half_days / 2)), 0) as total_working_days'),
                DB::raw('COALESCE(SUM(payroll_entries.overtime_hours), 0) as total_ot_hours'),
                DB::raw('COALESCE(SUM(payroll_entries.gross_pay), 0) as total_gross_pay'),
                DB::raw('COALESCE(SUM(payroll_entries.total_deductions), 0) as total_deductions'),
                DB::raw('COALESCE(SUM(payroll_entries.net_pay), 0) as total_net_pay')
            )
            ->groupBy('branches.id', 'branches.name', 'pay_month')
            ->orderBy('branches.name')
            ->orderBy('pay_month')
            ->get();

        return view('exports.all-branch-summary', [
            'summary' => $summary,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = 'I';
                $highestRow = $sheet->getHighestRow();

                // 1. Styling the Main Title
                $sheet->mergeCells("A1:{$highestColumn}1");
                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'], // Nice Blue
                    ],
                ]);

                // 2. Styling the Period Subtitle
                $sheet->mergeCells("A2:{$highestColumn}2");
                $sheet->getStyle("A2:{$highestColumn}2")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'], // Light Blue
                    ],
                ]);

                // 3. Styling the Table Header
                $sheet->getStyle("A3:{$highestColumn}3")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2F5597'], // Dark Blue
                    ],
                ]);

                // 4. Borders for the entire table
                $sheet->getStyle("A3:{$highestColumn}" . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // 5. Styling the Totals Row (Footer)
                $sheet->getStyle("A{$highestRow}:{$highestColumn}{$highestRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'], // Light Blue
                    ],
                ]);

                // Column alignments
                $sheet->getStyle('A4:A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C4:{$highestColumn}" . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Row heights
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(20);

                // Alternate row coloring
                for ($row = 4; $row < $highestRow; $row++) {
                    if ($row % 2 == 0) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'], // Light Gray
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
