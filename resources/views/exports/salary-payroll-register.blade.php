<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        th, td {
            border: 1px solid #000000;
            vertical-align: middle;
            padding: 2px 4px;
            font-size: 11px;
        }
        th {
            font-weight: bold;
            text-align: center;
            background-color: #e8e8e8;
        }
        .num { text-align: right; }
        .text { text-align: left; }
        .center { text-align: center; }
    </style>
</head>
<body>
@php
    $earningColumns = $register['earning_columns'] ?? [];
    $deductionColumns = $register['deduction_columns'] ?? [];
    $rows = $register['rows'] ?? [];
    $totals = $register['totals'] ?? [];
    $fixedCount = 18;
    $totalCols = $fixedCount + count($earningColumns) + 4 + count($deductionColumns) + 5;
@endphp
<table>
    <thead>
        <tr>
            <th colspan="{{ $totalCols }}" class="center" style="font-size: 14px;">
                Salary Register — {{ $run->branch->name ?? 'Branch' }}
                ({{ $run->pay_period_start?->format('d M Y') }} to {{ $run->pay_period_end?->format('d M Y') }})
            </th>
        </tr>
        <tr>
            <th>Sr</th>
            <th>Emp Code</th>
            <th>Name</th>
            <th>Category</th>
            <th>Department</th>
            <th>Shift</th>
            <th>Day Rate</th>
            <th>CTC / Month</th>
            <th>OT</th>
            <th>Working</th>
            <th>Present</th>
            <th>Paid</th>
            <th>WO Worked</th>
            <th>Half Days</th>
            <th>PI Days</th>
            <th>PI Amount</th>
            <th>Adj Days</th>
            <th>Adjust</th>
            @foreach ($earningColumns as $col)
                <th>{{ $col }}</th>
            @endforeach
            <th>Regular Salary</th>
            <th>Total Salary</th>
            @foreach ($deductionColumns as $col)
                <th>{{ $col }}</th>
            @endforeach
            <th>Total Deductions</th>
            <th>Net Salary</th>
            <th>PF Wages</th>
            <th>Bank</th>
            <th>Account No</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td class="center">{{ $row['sr'] }}</td>
                <td class="center">{{ $row['employee_code'] }}</td>
                <td class="text">{{ $row['name'] }}</td>
                <td class="text">{{ $row['category'] }}</td>
                <td class="text">{{ $row['department'] }}</td>
                <td class="text">{{ $row['shift'] }}</td>
                <td class="num">{{ $row['day_rate'] }}</td>
                <td class="num">{{ $row['monthly_gross'] }}</td>
                <td class="center">{{ $row['ot_enabled'] ? 'Yes' : 'No' }}</td>
                <td class="num">{{ $row['working_days'] }}</td>
                <td class="num">{{ $row['present_days'] }}</td>
                <td class="num">{{ $row['paid_days'] }}</td>
                <td class="num">{{ $row['week_off_worked_days'] }}</td>
                <td class="num">{{ $row['half_days'] }}</td>
                <td class="num">{{ $row['incentive_days'] }}</td>
                <td class="num">{{ $row['incentive_amount'] }}</td>
                <td class="num">{{ $row['attendance_extra_days'] ?? 0 }}</td>
                <td class="num">{{ $row['attendance_extra_amount'] ?? 0 }}</td>
                @foreach ($earningColumns as $col)
                    <td class="num">{{ $row['earnings'][$col] ?? 0 }}</td>
                @endforeach
                <td class="num">{{ $row['regular_earnings'] }}</td>
                <td class="num">{{ $row['total_earnings'] }}</td>
                @foreach ($deductionColumns as $col)
                    <td class="num">{{ $row['deductions'][$col] ?? 0 }}</td>
                @endforeach
                <td class="num">{{ $row['total_deductions'] }}</td>
                <td class="num">{{ $row['net_salary'] }}</td>
                <td class="num">{{ $row['pf_wages'] }}</td>
                <td class="text">{{ $row['bank_name'] }}</td>
                <td class="text">{{ $row['account_number'] }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="15" class="center">TOTAL</th>
            <th class="num">{{ $totals['incentive_amount'] ?? 0 }}</th>
            <th></th>
            <th class="num">{{ $totals['attendance_extra_amount'] ?? 0 }}</th>
            @foreach ($earningColumns as $col)
                <th class="num">{{ $totals['earnings'][$col] ?? 0 }}</th>
            @endforeach
            <th></th>
            <th class="num">{{ $totals['total_earnings'] ?? 0 }}</th>
            @foreach ($deductionColumns as $col)
                <th class="num">{{ $totals['deductions'][$col] ?? 0 }}</th>
            @endforeach
            <th class="num">{{ $totals['total_deductions'] ?? 0 }}</th>
            <th class="num">{{ $totals['net_salary'] ?? 0 }}</th>
            <th colspan="3"></th>
        </tr>
    </tbody>
</table>
</body>
</html>
