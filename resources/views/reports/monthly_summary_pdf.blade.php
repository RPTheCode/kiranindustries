<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Monthly Summary</title>
    <style>
        @page { 
            margin: 90px 15mm 35px 15mm; 
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 10px;
            color: #000;
        }
        
        /* Fixed Header Logic */
        header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 85px;
            text-align: center;
        }

        /* Premium Blue Header Bar */
        .main-header {
            background-color: #1a365d;
            color: white;
            padding: 6px;
            text-align: center;
            margin-bottom: 0px;
        }

        .main-header h1 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 900;
        }

        /* Status Bar (Grey) */
        .status-bar {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e0;
            padding: 6px 10px;
            margin: 0;
            font-size: 9px;
            font-weight: 800;
            color: #1a365d;
        }

        .status-left {
            float: left;
            text-align: left;
        }

        .status-right {
            float: right;
            text-align: right;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #000000;
            padding: 5px 3px;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
        }
        td {
            font-size: 9px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8px;
            color: #666;
            padding-top: 10px;
        }
        .pagenum:before {
            content: counter(page);
        }
        .pagecount:before {
            content: counter(pages);
        }
    </style>
</head>
<body>

    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }}{{ count($summary) === 1 ? ' - ' . strtoupper($summary[0]->branch_name) : '' }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ count($summary) === 1 ? strtoupper($summary[0]->branch_name) . ' ' : '' }}MONTHLY PAYROLL SUMMARY REPORT | PERIOD: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
            </div>
            <div class="status-right">
                PAGE <span class="pagenum"></span> OF <span class="pagecount"></span>
            </div>
        </div>
    </header>

    <main>
        <table>
            <thead>
                <tr>
                    <th>SR</th>
                    <th>Branch Name</th>
                    <th>Pay Period</th>
                    <th>No of Employee</th>
                    <th>Working Day</th>
                    <th>OT HR</th>
                    <th>Gross Pay</th>
                    <th>All Deduction</th>
                    <th>Net Pay</th>
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

                @forelse($summary as $row)
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
                        <td class="text-center">{{ $showBranch ? $sr : '' }}</td>
                        <td class="text-left">{{ $showBranch ? $row->branch_name : '' }}</td>
                        <td class="text-center">
                            {{ $row->pay_month ? \Carbon\Carbon::parse($row->pay_month . '-01')->format('M Y') : '-' }}
                        </td>
                        <td class="text-center">{{ $row->employee_count }}</td>
                        <td class="text-center">{{ number_format($row->total_working_days, 1) }}</td>
                        <td class="text-center">{{ number_format($row->total_ot_hours, 1) }}</td>
                        <td class="text-right">{{ number_format($row->total_gross_pay, 2) }}</td>
                        <td class="text-right">{{ number_format($row->total_deductions, 2) }}</td>
                        <td class="text-right">{{ number_format($row->total_net_pay, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 20px;">No payroll data found for this period.</td>
                    </tr>
                @endforelse

                @if(count($summary) > 0)
                <tr>
                    <td colspan="3" class="text-center" style="font-weight: bold; background-color: #f3f4f6;">Total</td>
                    <td class="text-center" style="font-weight: bold; background-color: #f3f4f6;">{{ $totalEmployees }}</td>
                    <td class="text-center" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format($totalWorkingDays, 1) }}</td>
                    <td class="text-center" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format($totalOtHours, 1) }}</td>
                    <td class="text-right" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format($totalGrossPay, 2) }}</td>
                    <td class="text-right" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format($totalDeductions, 2) }}</td>
                    <td class="text-right" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format($totalNetPay, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <div class="footer">
            Generated via {{ config('app.name', 'HRMS') }} on {{ now()->format('d M Y h:i A') }}
        </div>
    </main>
</body>
</html>
