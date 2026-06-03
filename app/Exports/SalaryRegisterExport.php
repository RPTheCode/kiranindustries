<?php

namespace App\Exports;

use App\Models\PayrollRun;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalaryRegisterExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $payrollRunId;

    public function __construct($payrollRunId)
    {
        $this->payrollRunId = $payrollRunId;
    }

    public function view(): View
    {
        $payrollRun = PayrollRun::with([
            'payrollEntries.payslip',
            'payrollEntries.employee.employee' => function ($q) {
                $q->withoutGlobalScopes()->with(['designation', 'branch']);
            }
        ])
            ->findOrFail($this->payrollRunId);

        $entries = $payrollRun->payrollEntries;

        // 1. Identify all unique Earning and Deduction components across all entries
        $allEarningComponents = [];
        $allDeductionComponents = [];

        foreach ($entries as $entry) {
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

        // Use generic labels as requested
        $overtimeLabel = 'Overtime Amount';
        $shortfallLabel = 'Attendance Shortfall';

        if (!in_array($overtimeLabel, $allEarningComponents)) {
            $allEarningComponents[] = $overtimeLabel;
        }
        if (!in_array($shortfallLabel, $allDeductionComponents)) {
            $allDeductionComponents[] = $shortfallLabel;
        }

        // Ensure at least one deduction column exists
        if (empty($allDeductionComponents)) {
            $allDeductionComponents[] = 'other_deduction';
        }

        // 2. Prepare data collection
        $data = [];
        $srNo = 1;

        foreach ($entries as $entry) {
            $user = $entry->employee;
            $employeeProfile = $user->employee;

            // Format Earnings
            $earningsData = [];
            foreach ($allEarningComponents as $component) {
                $earningsData[$component] = $entry->earnings_breakdown[$component] ?? 0;
            }
            // Format Deductions
            $deductionsData = [];
            foreach ($allDeductionComponents as $component) {
                $deductionsData[$component] = $entry->deductions_breakdown[$component] ?? 0;
            }

            $row = [
                'sr_no' => $srNo++,
                'employee_id' => $employeeProfile?->employee_id ?? $user->id ?? '-',
                'name' => $user->name ?? '-',
                'uan' => $employeeProfile?->uan_number ?? '-',
                'designation' => $employeeProfile?->designation?->name ?? '-',
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
                'account_number' => $employeeProfile?->account_number ?? '-',
                'ifsc_code' => $employeeProfile?->bank_identifier_code ?? '-',
                'bank_name' => $employeeProfile?->bank_name ?? '-',
                'date_of_payment' => ($entry->payslip?->salary_status !== 'hold' && $entry->payslip?->pay_date) ? $entry->payslip->pay_date->format('d-m-Y') : '-',
                'remarks' => $entry->notes ?? '-',
            ];

            $data[] = $row;
        }

        // 3. Calculate Totals
        $totals = [
            'basic_salary' => collect($data)->sum('basic_salary'),
            'overtime_hours' => collect($data)->sum('overtime_hours'),
            'unpaid_leave_deduction' => collect($data)->sum('unpaid_leave_deduction'),
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

        return view('exports.salary-register', [
            'payrollRun' => $payrollRun,
            'data' => $data,
            'earningComponents' => $allEarningComponents,
            'deductionComponents' => $allDeductionComponents,
            'totals' => $totals,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        // Add more styling as needed in the view or here
        return [];
    }
}
