<?php

namespace App\Exports;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\EmployeeSalary;
use App\Models\EmployeeAdvance;
use App\Models\Branch;
use App\Models\Holiday;
use App\Models\WeekOff;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class DailyAttendanceExport implements FromView, ShouldAutoSize, WithEvents
{
    protected $dateFrom;
    protected $dateTo;
    protected $branchId;
    protected $employeeId;

    public function __construct($dateFrom = null, $dateTo = null, $branchId = null, $employeeId = null)
    {
        $this->dateFrom = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::today();
        $this->dateTo = $dateTo ? Carbon::parse($dateTo)->endOfDay() : $this->dateFrom->copy()->endOfDay();
        $this->branchId = $branchId;
        $this->employeeId = $employeeId;
    }

    public function view(): View
    {
        $dateFrom = $this->dateFrom->copy()->startOfDay();
        $dateTo = $this->dateTo->copy()->startOfDay();
        $branchId = $this->branchId;
        $employeeId = $this->employeeId;

        // 1. Fetch All Relevant Attendance Records in Range for this Branch (or across branches)
        $attendanceQuery = AttendanceRecord::withoutGlobalScopes()
            ->withPermissionCheck()
            ->whereBetween('date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]);

        if ($branchId && $branchId !== 'all') {
            $attendanceQuery->where('branch_id', $branchId);
        }

        if ($employeeId && $employeeId !== 'all') {
            $attendanceQuery->where('employee_id', $employeeId);
        }

        $allRecords = $attendanceQuery->with(['employee', 'branch'])->get();
        $attendanceRecords = $allRecords->groupBy(function ($item) {
            return $item->employee_id . '_' . $item->date->format('Y-m-d');
        });

        // 2. Identify Relevant Employees (Branch residents + Visitors with records)
        $visitorEmployeeIds = $allRecords->pluck('employee_id')->unique()->toArray();

        $employeeQuery = User::withPermissionCheck()
            ->where('type', 'employee')
            ->with([
                'employee' => function ($q) {
                    $q->withoutGlobalScopes()->with(['branch', 'designation']);
                }
            ]);

        if ($branchId && $branchId !== 'all') {
            $employeeQuery->where(function ($q) use ($branchId, $visitorEmployeeIds) {
                // Strictly filter by branch_id in employees table
                $q->whereHas('employee', function ($sq) use ($branchId) {
                    $sq->withoutGlobalScopes()->where('branch_id', $branchId);
                });

                // Or if they have a record in this branch for the given period
                if (!empty($visitorEmployeeIds)) {
                    $q->orWhereIn('id', $visitorEmployeeIds);
                }
            });
        }

        if ($employeeId && $employeeId !== 'all') {
            $employeeQuery->where('id', $employeeId);
        }

        $users = $employeeQuery->get()->sortBy('name');

        // 3. Fetch All Relevant Advances in Range
        $advancesQuery = EmployeeAdvance::withPermissionCheck()
            ->whereBetween('pay_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]);

        if ($branchId && $branchId !== 'all') {
            $advancesQuery->where('branch_id', $branchId);
        }

        $advances = $advancesQuery->get()->groupBy(function ($item) {
            return $item->employee_id . '_' . $item->pay_date->format('Y-m-d');
        });

        // 4. Prepare Data by iterating through Employees and Dates
        $data = [];
        $srNo = 1;
        $workingDaysCache = [];
        $isRange = $dateFrom->format('Y-m-d') !== $dateTo->format('Y-m-d');

        foreach ($users as $user) {
            $hasDataForEmployee = false;
            $firstRecordForEmployee = true;
            $empTotalHours = 0;
            $empTotalSalary = 0;
            $empTotalOT = 0;
            $empTotalAmount = 0;
            $empTotalAdvance = 0;

            for ($d = $dateFrom->copy(); $d->lte($dateTo); $d->addDay()) {
                $currentDateStr = $d->format('Y-m-d');
                $attendanceKey = $user->id . '_' . $currentDateStr;
                $attendance = $attendanceRecords->get($attendanceKey)?->first();

                // Month context for salary divisor - use employee's specific branch
                $empBranchId = $user->employee->branch_id ?? $branchId;
                $empType = $user->employee?->employment_type ?? 'Employee';
                $monthKey = $d->format('Y-m') . '_' . $empBranchId . '_' . $empType;
                if (!isset($workingDaysCache[$monthKey])) {
                    $workingDaysCache[$monthKey] = $this->calculateWorkingDaysForMonth($d, $empBranchId, $empType);
                }
                $workingDays = $workingDaysCache[$monthKey];

                // Fetch advances for this specific employee and day
                $dayAdvances = $advances->get($attendanceKey);
                $advanceAmount = $dayAdvances ? $dayAdvances->sum('amount') : 0;

                $salary = EmployeeSalary::where('employee_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                $basicSalary = $salary ? $salary->basic_salary : 0;
                $todaySalary = round($basicSalary / $workingDays, 2);

                if ($attendance) {
                    if (!in_array($attendance->status, ['present', 'half_day', 'holiday'])) {
                        $todaySalary = 0;
                    }
                    if ($attendance->status === 'half_day') {
                        $todaySalary = round($todaySalary / 2, 2);
                    }
                    $workingHours = $attendance->total_hours;
                    $overtimeHours = $attendance->overtime_hours;
                    
                    // Use standardized amount based on what's available
                    $overtimeAmount = $attendance->overtime_amount_minimum > 0 ? $attendance->overtime_amount_minimum : $attendance->overtime_amount_basic;
                    if ($overtimeAmount <= 0) {
                        $overtimeAmount = $attendance->overtime_amount;
                    }

                    // Restore clock in/out
                    $clockIn = $attendance->getRawOriginal('clock_in') ? Carbon::parse($attendance->getRawOriginal('clock_in'))->format('h:i A') : '-';
                    $clockOut = $attendance->getRawOriginal('clock_out') ? Carbon::parse($attendance->getRawOriginal('clock_out'))->format('h:i A') : '-';
                } else {
                    $todaySalary = 0;
                    $workingHours = 0;
                    $overtimeHours = 0;
                    $overtimeAmount = 0;
                    $clockIn = '-';
                    $clockOut = '-';
                }

                $totalAmount = $todaySalary + $overtimeAmount;

                // ONLY show if there is an attendance record or an advance
                if ($attendance || $advanceAmount > 0) {
                    $data[] = [
                        'sr' => $srNo++,
                        'name' => $firstRecordForEmployee ? $user->name . ($user->employee->employee_id ? ' (' . $user->employee->employee_id . ')' : '') : '',
                        'designation' => $firstRecordForEmployee ? ($user->employee->designation->name ?? '-') : '',
                        'date' => $d->format('d-m-Y'),
                        'working_hours' => $workingHours,
                        'today_salary' => $todaySalary,
                        'today_ot' => $overtimeAmount,
                        'total_amount' => $totalAmount,
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'advance' => $advanceAmount,
                        'is_subtotal' => false,
                    ];

                    // Accumulate employee totals
                    $empTotalHours += $workingHours;
                    $empTotalSalary += $todaySalary;
                    $empTotalOT += $overtimeAmount;
                    $empTotalAmount += $totalAmount;
                    $empTotalAdvance += $advanceAmount;

                    $firstRecordForEmployee = false;
                    $hasDataForEmployee = true;
                }
            }
            // If the employee has NO active rows, add one placeholder row for them with 0s
            if (!$hasDataForEmployee) {
                $data[] = [
                    'sr' => $srNo++,
                    'name' => $user->name . ($user->employee->employee_id ? ' (' . $user->employee->employee_id . ')' : ''),
                    'designation' => $user->employee->designation->name ?? '-',
                    'date' => $isRange ? '-' : $dateFrom->format('d-m-Y'),
                    'working_hours' => 0,
                    'today_salary' => 0,
                    'today_ot' => 0,
                    'total_amount' => 0,
                    'clock_in' => '-',
                    'clock_out' => '-',
                    'advance' => 0,
                    'is_subtotal' => false,
                ];
            }


            // After all dates for this employee, add a subtotal row if it's a range AND they actually had data
            // (If they had NO data, the single row above already acts as their entry)
            if ($isRange && $hasDataForEmployee) {
                $data[] = [
                    'sr' => '',
                    'name' => 'TOTAL (' . $user->name . ')',
                    'designation' => '',
                    'date' => '',
                    'working_hours' => $empTotalHours,
                    'today_salary' => $empTotalSalary,
                    'today_ot' => $empTotalOT,
                    'total_amount' => $empTotalAmount,
                    'clock_in' => '',
                    'clock_out' => '',
                    'advance' => $empTotalAdvance,
                    'is_subtotal' => true,
                ];
            }
        }

        $totals = [
            'total_days' => array_sum(array_map(fn($item) => $item['is_subtotal'] ? 0 : 1, $data)),
            'working_hours' => array_sum(array_column(array_filter($data, fn($item) => !$item['is_subtotal']), 'working_hours')),
            'today_salary' => array_sum(array_column(array_filter($data, fn($item) => !$item['is_subtotal']), 'today_salary')),
            'today_ot' => array_sum(array_column(array_filter($data, fn($item) => !$item['is_subtotal']), 'today_ot')),
            'total_amount' => array_sum(array_column(array_filter($data, fn($item) => !$item['is_subtotal']), 'total_amount')),
            'advance' => array_sum(array_column(array_filter($data, fn($item) => !$item['is_subtotal']), 'advance')),
        ];

        $branchName = 'All Branches';
        if ($branchId && $branchId !== 'all') {
            $branch = Branch::find($branchId);
            $branchName = $branch ? $branch->name : 'Unknown Branch';
        }

        $reportDateDisplay = $this->dateFrom->format('Y-m-d') === $this->dateTo->format('Y-m-d')
            ? $this->dateFrom->format('d-m-Y')
            : $this->dateFrom->format('d-m-Y') . ' to ' . $this->dateTo->format('d-m-Y');

        return view('exports.daily-attendance', [
            'data' => $data,
            'date' => $reportDateDisplay,
            'branch_name' => $branchName,
            'is_range' => $isRange,
            'totals' => $totals
        ]);
    }

    private function calculateWorkingDaysForMonth(Carbon $date, $branchId, $employmentType = 'Employee')
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $totalDaysInMonth = $startOfMonth->daysInMonth;

        $weekOff = WeekOff::where('branch_id', $branchId)
            ->where('employment_type', $employmentType)
            ->first();

        $holidays = Holiday::where(function ($q) use ($startOfMonth, $endOfMonth) {
            $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
        })
            ->where(function ($q) use ($branchId) {
                $q->doesntHave('branches')
                    ->orWhereHas('branches', function ($q2) use ($branchId) {
                        $q2->where('branches.id', $branchId);
                    });
            })
            ->get();

        $weekOffCountInMonth = 0;
        $holidayDaysInMonth = 0;

        for ($d = $startOfMonth->copy(); $d->lte($endOfMonth); $d->addDay()) {
            $isWeekOff = $weekOff ? $weekOff->isDateWeekOff($d) : $d->isWeekend();
            $isHoliday = $holidays->filter(function ($h) use ($d) {
                return $d->between($h->start_date, $h->end_date);
            })->isNotEmpty();

            if ($isHoliday) {
                $holidayDaysInMonth++;
            } elseif ($isWeekOff) {
                $weekOffCountInMonth++;
            }
        }

        return max(1, $totalDaysInMonth - $weekOffCountInMonth - $holidayDaysInMonth);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $isRange = $this->dateFrom->format('Y-m-d') !== $this->dateTo->format('Y-m-d');
                $highestColumn = $isRange ? 'K' : 'J';

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

                // 2. Styling the Date Subtitle
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

                // 4. Borders for the entire table content
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A3:{$highestColumn}" . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Styling the Totals Row (Footer)
                $sheet->getStyle("A{$highestRow}:{$highestColumn}{$highestRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'], // Light Blue
                    ],
                ]);

                // Set row height for titles
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(20);

                // Column alignments
                $sheet->getStyle('A4:A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D4:{$highestColumn}" . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Alternate row coloring and Subtotal styling
                for ($row = 4; $row < $highestRow; $row++) {
                    $cellValue = $sheet->getCell("B{$row}")->getValue();
                    $isSubtotal = str_starts_with($cellValue, 'TOTAL (');

                    if ($isSubtotal) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'], // Light Gray
                            ],
                        ]);
                    } elseif ($row % 2 == 0) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FAFAFA'], // Very Light Gray
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
