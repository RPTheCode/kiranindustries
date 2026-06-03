<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        th {
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000000;
        }

        td {
            border: 1px solid #000000;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <table>
        <thead>
            <tr>
                <th colspan="{{ 14 + count($earningComponents) + 1 + count($deductionComponents) + 1 + 7 + 3 }}"
                    style="font-weight: bold; font-size: 16px; color: #000000; text-align: center; height: 30px; vertical-align: middle; border: 1px solid #000000;">
                    Salary Register - {{ $payrollRun->branch->name ?? 'All Branches' }}
                    ({{ $payrollRun->pay_period_start->format('d M, Y') }} To
                    {{ $payrollRun->pay_period_end->format('d M, Y') }})
                </th>
            </tr>
            <tr>
                {{-- Fixed Columns (Rowspan 2) --}}
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Sr. No.</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Sr. No. in Employee/ Workman Register</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Name of Employee/Workman</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    UAN</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Designation</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Rate Of Wage (Daily)</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Rate Of Wage (Monthly)</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Present Days</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Week Off Days</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Half Days</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    No. of Days Worked</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Overtime hours worked</th>

                {{-- Earnings Group Header --}}
                <th colspan="{{ count($earningComponents) }}"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; text-align: center; vertical-align: middle; border: 1px solid #000000;">
                    Earning</th>

                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Total (Earnings)</th>

                {{-- Deductions Group Header --}}
                @if(count($deductionComponents) > 0)
                    <th colspan="{{ count($deductionComponents) }}"
                        style="font-weight: bold; color: #000000; background-color: #e0e0e0; text-align: center; vertical-align: middle; border: 1px solid #000000;">
                        Deduction</th>
                @endif

                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Total (Deductions)</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Net Payment</th>

                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Bank Account</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    IFSC Code</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Bank Name</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Pay Date</th>
                <th rowspan="2"
                    style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                    Remarks</th>
            </tr>
            <tr>
                {{-- Earnings Sub-headers --}}
                @foreach($earningComponents as $component)
                    <th
                        style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                        {{ ucwords(str_replace('_', ' ', $component)) }}
                    </th>
                @endforeach

                {{-- Deductions Sub-headers --}}
                @foreach($deductionComponents as $component)
                    <th
                        style="font-weight: bold; color: #000000; background-color: #e0e0e0; vertical-align: middle; text-align: center; border: 1px solid #000000;">
                        {{ ucwords(str_replace('_', ' ', $component)) }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['sr_no'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['employee_id'] }}</td>
                    <td style="border: 1px solid #000000;">{{ $row['name'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['uan'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['designation'] }}</td>

                    {{-- Rate Of Wage (Daily) --}}
                    <td style="text-align: right; border: 1px solid #000000;">{{ number_format($row['per_day_salary'], 2) }}
                    </td>
                    {{-- Rate Of Wage (Monthly) --}}
                    <td style="text-align: right; border: 1px solid #000000;">{{ number_format($row['basic_salary'], 2) }}
                    </td>
                    {{-- Present Days --}}
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['present_days'] }}</td>
                    {{-- Week Off Days --}}
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['week_off_present_days'] ?? 0 }}</td>
                    {{-- Half Days --}}
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['half_days'] }}</td>
                    {{-- No. of Days Worked (Total) --}}
                    <td style="text-align: center; border: 1px solid #000000;">
                        {{ $row['present_days'] + ($row['week_off_present_days'] ?? 0) + ($row['half_days'] / 2) }}
                    </td>
                    {{-- Overtime hours worked --}}
                    <td style="text-align: center; border: 1px solid #000000;">
                        {{ \App\Models\AttendanceRecord::formatDuration($row['overtime_hours'] ?? 0) }}
                    </td>

                    @foreach($earningComponents as $component)
                        <td style="text-align: right; border: 1px solid #000000;">
                            {{ number_format($row['earnings'][$component] ?? 0, 2) }}
                        </td>
                    @endforeach
                    <td style="text-align: right; font-weight: bold; background-color: #d9ead3; border: 1px solid #000000;">
                        {{ number_format($row['total_earnings'], 2) }}
                    </td>

                    @foreach($deductionComponents as $component)
                        <td style="text-align: right; border: 1px solid #000000;">
                            {{ number_format($row['deductions'][$component] ?? 0, 2) }}
                        </td>
                    @endforeach
                    <td style="text-align: right; font-weight: bold; background-color: #f4cccc; border: 1px solid #000000;">
                        {{ number_format($row['total_deductions'], 2) }}
                    </td>

                    {{-- Net Payment --}}
                    <td style="text-align: right; font-weight: bold; background-color: #cfe2f3; border: 1px solid #000000;">
                        {{ number_format($row['net_salary'], 2) }}
                    </td>

                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['account_number'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['ifsc_code'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['bank_name'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['date_of_payment'] }}</td>
                    <td style="text-align: center; border: 1px solid #000000;">{{ $row['remarks'] }}</td>
                </tr>
            @endforeach

            {{-- Totals Row --}}
            <tr style="font-weight: bold; color: #000000; background-color: #e6e6e6;">
                <td colspan="5"
                    style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">TOTALS</td>

                <td style="border: 1px solid #000000;"></td> {{-- Daily Rate Total NA --}}
                <td style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">
                    {{ number_format($totals['basic_salary'], 2) }}
                </td>
                <td style="border: 1px solid #000000;"></td> {{-- Present Days Total NA --}}
                <td style="border: 1px solid #000000;"></td> {{-- Week Off Days Total NA --}}
                <td style="border: 1px solid #000000;"></td> {{-- Half Days Total NA --}}
                <td style="border: 1px solid #000000;"></td> {{-- Total Days Worked Total NA --}}
                <td style="text-align: center; border: 1px solid #000000; font-weight: bold; color: #000000;">
                    {{ \App\Models\AttendanceRecord::formatDuration($totals['overtime_hours'] ?? 0) }}
                </td>

                @foreach($earningComponents as $component)
                    <td style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">
                        {{ number_format($totals['earnings'][$component] ?? 0, 2) }}
                    </td>
                @endforeach
                <td style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">
                    {{ number_format($totals['total_earnings'], 2) }}
                </td>

                @foreach($deductionComponents as $component)
                    <td style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">
                        {{ number_format($totals['deductions'][$component] ?? 0, 2) }}
                    </td>
                @endforeach
                <td style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">
                    {{ number_format($totals['total_deductions'], 2) }}
                </td>

                <td style="text-align: right; border: 1px solid #000000; font-weight: bold; color: #000000;">
                    {{ number_format($totals['net_salary'], 2) }}
                </td>

                <td colspan="5" style="border: 1px solid #000000;"></td>
            </tr>
        </tbody>
    </table>
</body>

</html>