<?php

namespace App\Exports;

use App\Models\Payslip;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BulkPayslipExport implements FromView, ShouldAutoSize, WithStyles, WithColumnWidths, WithColumnFormatting
{
    public function columnFormats(): array
    {
        return [
            'C' => '#,##0.00',
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }
    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 18,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 18,
            'G' => 18,
            'H' => 15,
            'I' => 15,
            'J' => 18,
        ];
    }
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function view(): View
    {
        $query = Payslip::with([
            'payrollEntry.payrollRun',
            'payrollEntry.employee.employee' => function ($q) {
                $q->withoutGlobalScopes()->with(['designation', 'branch']);
            }
        ])->where(function ($q) {
            $q->whereIn('salary_status', ['released', 'RELEASED'])
                ->orWhereNull('salary_status');
        });

        // Filter by active branches
        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->whereHas('payrollEntry.payrollRun', function ($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
            });
        }

        // Default to current month if no dates are provided
        if (empty($this->filters['date_from']) && empty($this->filters['date_to'])) {
            $startOfMonth = now()->startOfMonth()->toDateString();
            $endOfMonth = now()->endOfMonth()->toDateString();
            $query->where('pay_period_start', '>=', $startOfMonth)
                ->where('pay_period_end', '<=', $endOfMonth);
        } else {
            if (!empty($this->filters['date_from'])) {
                $dateFrom = \Carbon\Carbon::parse($this->filters['date_from'])->toDateString();
                $query->whereDate('pay_period_start', '>=', $dateFrom);
            }
            if (!empty($this->filters['date_to'])) {
                $dateTo = \Carbon\Carbon::parse($this->filters['date_to'])->toDateString();
                $query->whereDate('pay_period_end', '<=', $dateTo);
            }
        }
        if (!empty($this->filters['employee_id']) && $this->filters['employee_id'] !== 'all') {
            $query->where('employee_id', $this->filters['employee_id']);
        }
        if (!empty($this->filters['salary_calculation_type']) && $this->filters['salary_calculation_type'] !== 'all') {
            $query->whereHas('payrollEntry.payrollRun', function ($q) {
                $q->where('salary_calculation_type', $this->filters['salary_calculation_type']);
            });
        }

        $payslips = $query->get();

        // 1. Identify all unique Earning and Deduction components across all entries
        $allEarningComponents = [];
        $allDeductionComponents = [];

        foreach ($payslips as $payslip) {
            $entry = $payslip->payrollEntry;
            if (!$entry) continue;

            $earnings = $entry->earnings_breakdown ?? [];
            $deductions = $entry->deductions_breakdown ?? [];

            if (is_array($earnings)) {
                foreach (array_keys($earnings) as $key) {
                    if (!in_array($key, $allEarningComponents)) {
                        $allEarningComponents[] = $key;
                    }
                }
            }

            if (is_array($deductions)) {
                foreach (array_keys($deductions) as $key) {
                    if (!in_array($key, $allDeductionComponents)) {
                        $allDeductionComponents[] = $key;
                    }
                }
            }
        }

        // Ensure Basic Salary is at the beginning of earnings
        if (($key = array_search('Basic Salary', $allEarningComponents)) !== false) {
            unset($allEarningComponents[$key]);
            array_unshift($allEarningComponents, 'Basic Salary');
        }

        // 2. Prepare data collection
        $data = [];
        $srNo = 1;

        foreach ($payslips as $payslip) {
            $entry = $payslip->payrollEntry;
            if (!$entry) continue;

            $user = $entry->employee;
            $employeeProfile = $user->employee;

            // Use the individual's own breakdown (filtering out zeros naturally or explicitly)
            $earningsData = array_filter($entry->earnings_breakdown ?? [], fn($v) => $v != 0);
            $deductionsData = array_filter($entry->deductions_breakdown ?? [], fn($v) => $v != 0);

            // Add Overtime if it exists and wasn't already in the breakdown
            if ($entry->overtime_amount > 0 && !isset($earningsData['Overtime Amount'])) {
                $earningsData['Overtime Amount'] = $entry->overtime_amount;
            }

            // Ensure Basic Salary is at the top if it exists
            if (isset($earningsData['Basic Salary'])) {
                $val = $earningsData['Basic Salary'];
                unset($earningsData['Basic Salary']);
                $earningsData = ['Basic Salary' => $val] + $earningsData;
            }

            // Ensure Overtime Amount is at the bottom if it exists
            if (isset($earningsData['Overtime Amount'])) {
                $otVal = $earningsData['Overtime Amount'];
                unset($earningsData['Overtime Amount']);
                $earningsData['Overtime Amount'] = $otVal;
            }

            $row = [
                'sr_no' => $srNo++,
                'employee_id' => $employeeProfile?->employee_id ?? $user->id ?? '-',
                'name' => $user->name ?? '-',
                'email' => $user->email ?? '-',
                'branch' => $employeeProfile?->branch?->name ?? '-',
                'pay_period' => $payslip->pay_period_start->format('d-m-Y') . ' to ' . $payslip->pay_period_end->format('d-m-Y'),
                'basic_salary' => $entry->basic_salary,
                'working_days' => $entry->working_days,
                'present_days' => $entry->present_days,
                'paid_leave' => $entry->paid_leave_days,
                'unpaid_leave' => $entry->unpaid_leave_days,
                'week_off_present_days' => $entry->week_off_present_days,
                'half_days' => $entry->half_days,
                'absent_days' => $entry->absent_days,
                'overtime_hours' => $entry->overtime_hours,
                'per_day_salary' => $entry->per_day_salary,
                'unpaid_leave_deduction' => $entry->unpaid_leave_deduction,
                'earnings' => $earningsData,
                'total_earnings' => $entry->gross_pay,
                'deductions' => $deductionsData,
                'total_deductions' => $entry->total_deductions,
                'net_salary' => $entry->net_pay,
            ];

            $data[] = $row;
        }

        // 3. Calculate Totals
        $totals = [
            'basic_salary' => collect($data)->sum('basic_salary'),
            'total_earnings' => collect($data)->sum('total_earnings'),
            'total_deductions' => collect($data)->sum('total_deductions'),
            'net_salary' => collect($data)->sum('net_salary'),
            'earnings' => [],
            'deductions' => []
        ];

        foreach ($allEarningComponents as $component) {
            $totals['earnings'][$component] = collect($data)->sum(fn($row) => $row['earnings'][$component] ?? 0);
        }

        foreach ($allDeductionComponents as $component) {
            $totals['deductions'][$component] = collect($data)->sum(fn($row) => $row['deductions'][$component] ?? 0);
        }

        return view('exports.bulk-payslips', [
            'data' => $data,
            'earningComponents' => $allEarningComponents,
            'deductionComponents' => $allDeductionComponents,
            'totals' => $totals,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $dimension = $sheet->calculateWorksheetDimension();
        $sheet->getStyle($dimension)->getAlignment()->setWrapText(true);
        // Apply number format to every cell that looks like a number
        $sheet->getStyle($dimension)->getNumberFormat()->setFormatCode('#,##0.00');
        return [];
    }
}
