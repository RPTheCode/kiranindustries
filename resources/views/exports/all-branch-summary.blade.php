<table>
    <thead>
        <tr>
            <th colspan="9" style="text-align: center; font-weight: bold; font-size: 14px;">
                {{ count($summary) === 1 ? strtoupper($summary->first()->branch_name) . ' ' : 'All Branch ' }}Monthly Payroll Summary Report
            </th>
        </tr>
        <tr>
            <th colspan="9" style="text-align: center;">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} to
                {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
            </th>
        </tr>
        <tr>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">SR</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Branch Name</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Pay Period</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">No of Employee</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Working Day</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">OT HR</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Gross Pay</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">All Deduction</th>
            <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Net Pay</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalEmployees = 0;
            $totalWorkingDays = 0;
            $totalOtHours = 0;
            $totalGrossPay = 0;
            $totalDeductions = 0;
            $totalNetPay = 0;
            $lastBranchId = null;
            $sr = 0;
        @endphp
        @foreach($summary as $row)
            @php
                $showBranch = false;
                if ($row->branch_id !== $lastBranchId) {
                    $sr++;
                    $lastBranchId = $row->branch_id;
                    $showBranch = true;
                }

                $totalEmployees += $row->employee_count;
                $totalWorkingDays += $row->total_working_days;
                $totalOtHours += $row->total_ot_hours;
                $totalGrossPay += $row->total_gross_pay;
                $totalDeductions += $row->total_deductions;
                $totalNetPay += $row->total_net_pay;
            @endphp
            <tr>
                <td style="border: 1px solid #000000; text-align: center;">{{ $showBranch ? $sr : '' }}</td>
                <td style="border: 1px solid #000000;">{{ $showBranch ? $row->branch_name : '' }}</td>
                <td style="border: 1px solid #000000; text-align: center;">
                    {{ $row->pay_month ? \Carbon\Carbon::parse($row->pay_month . '-01')->format('M Y') : '-' }}
                </td>
                <td style="border: 1px solid #000000; text-align: center;">{{ $row->employee_count }}</td>
                <td style="border: 1px solid #000000; text-align: center;">{{ number_format($row->total_working_days, 1) }}
                </td>
                <td style="border: 1px solid #000000; text-align: center;">{{ number_format($row->total_ot_hours, 1) }}</td>
                <td style="border: 1px solid #000000; text-align: right;">{{ number_format($row->total_gross_pay, 2) }}</td>
                <td style="border: 1px solid #000000; text-align: right;">{{ number_format($row->total_deductions, 2) }}
                </td>
                <td style="border: 1px solid #000000; text-align: right;">{{ number_format($row->total_net_pay, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="font-weight: bold; border: 1px solid #000000; background-color: #f3f4f6;">Total</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: center; background-color: #f3f4f6;">
                {{ $totalEmployees }}
            </td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: center; background-color: #f3f4f6;">
                {{ number_format($totalWorkingDays, 1) }}
            </td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: center; background-color: #f3f4f6;">
                {{ number_format($totalOtHours, 1) }}
            </td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right; background-color: #f3f4f6;">
                {{ number_format($totalGrossPay, 2) }}
            </td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right; background-color: #f3f4f6;">
                {{ number_format($totalDeductions, 2) }}
            </td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right; background-color: #f3f4f6;">
                {{ number_format($totalNetPay, 2) }}
            </td>
        </tr>
    </tfoot>
</table>