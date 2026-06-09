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
            background-color: #fde68a;
        }
        .num { text-align: right; }
        .text { text-align: left; }
        .center { text-align: center; }
        .summary td { background-color: #fef3c7; font-weight: bold; }
    </style>
</head>
<body>
@php
    $rows = $report['rows'] ?? [];
    $totals = $report['totals'] ?? [];
    $pfSummary = $summary['pf'] ?? [];
    $challan = $pfSummary['challan'] ?? [];
@endphp
<table>
    <thead>
        <tr>
            <th colspan="24" class="center" style="font-size: 14px;">
                Statutory Challan Report — {{ $run->branch->name ?? 'Branch' }}
                ({{ $run->pay_period_start?->format('d M Y') }} to {{ $run->pay_period_end?->format('d M Y') }})
            </th>
        </tr>
        <tr class="summary">
            <td colspan="12" class="text">Month PF challan (EPFO deposit heads)</td>
            <td class="num">A/C 1: {{ number_format((float) ($challan['ac1_employees_pf'] ?? 0), 0) }}</td>
            <td class="num">A/C 2: {{ number_format((float) ($challan['ac2_pension_eps'] ?? 0), 0) }}</td>
            <td class="num">A/C 10: {{ number_format((float) ($challan['ac10_admin'] ?? 0), 0) }}</td>
            <td colspan="3" class="num">Total: {{ number_format((float) ($challan['total_deposit'] ?? 0), 0) }}</td>
            <td colspan="5"></td>
        </tr>
        <tr>
            <th>Sr</th>
            <th>Emp Code</th>
            <th>Name</th>
            <th>Department</th>
            <th>Category</th>
            <th>UAN</th>
            <th>PF No</th>
            <th>ESIC No</th>
            <th>Paid Days</th>
            <th>Gross</th>
            <th>PF Wages</th>
            <th>Govt PF Base</th>
            <th>Emp PF</th>
            <th>EPS</th>
            <th>EPF Empr</th>
            <th>Admin</th>
            <th>Empr PF Total</th>
            <th>A/C 1</th>
            <th>A/C 2</th>
            <th>A/C 10</th>
            <th>Challan Total</th>
            <th>ESIC Emp</th>
            <th>ESIC Empr</th>
            <th>PT</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td class="center">{{ $row['sr'] }}</td>
                <td class="center">{{ $row['employee_code'] }}</td>
                <td class="text">{{ $row['name'] }}</td>
                <td class="text">{{ $row['department'] }}</td>
                <td class="text">{{ $row['category'] }}</td>
                <td class="center">{{ $row['uan_number'] }}</td>
                <td class="center">{{ $row['pf_number'] }}</td>
                <td class="center">{{ $row['esic_number'] }}</td>
                <td class="center">{{ number_format((float) $row['paid_days'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['total_earnings'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_wages'], 0) }}</td>
                <td class="num">{{ (float) ($row['govt_min_wage_used'] ?? 0) > 0 ? number_format((float) $row['govt_min_wage_used'], 0) : '' }}</td>
                <td class="num">{{ number_format((float) $row['pf_employee'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_eps_employer'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_epf_employer'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_admin_employer'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_employer'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_challan_ac1'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_challan_ac2'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_challan_ac10'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pf_challan_total'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['esi_employee'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['esi_employer'], 0) }}</td>
                <td class="num">{{ number_format((float) $row['pt_amount'], 0) }}</td>
            </tr>
        @endforeach
        <tr class="summary">
            <td colspan="9" class="center">TOTAL ({{ $totals['employee_count'] ?? count($rows) }} employees, {{ $totals['pf_employee_count'] ?? 0 }} PF)</td>
            <td class="num">{{ number_format((float) ($totals['total_earnings'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_wages'] ?? 0), 0) }}</td>
            <td></td>
            <td class="num">{{ number_format((float) ($totals['pf_employee'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_eps_employer'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_epf_employer'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_admin_employer'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_employer'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_challan_ac1'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_challan_ac2'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_challan_ac10'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pf_challan_total'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['esi_employee'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['esi_employer'] ?? 0), 0) }}</td>
            <td class="num">{{ number_format((float) ($totals['pt_amount'] ?? 0), 0) }}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
