<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payrollEntry->employee->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        
        @page { margin: 12mm 15mm; }
        body {
            font-family: 'Inter', 'Poppins', DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 10px;
            color: #334155;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        .wrapper { width: 100%; padding-bottom: 60px; }

        /* Header */
        .header-box {
            background: {{ $themeColor ?? '#0F172A' }};
            color: #FFFFFF;
            border-radius: 8px;
            padding: 15px 15px;
            margin-bottom: 12px;
        }
        .header-table { width: 100%; }
        .header-table td { vertical-align: top; }
        .company-logo { max-height: 45px; margin-bottom: 5px; }
        .company-name { font-size: 16px; font-weight: bold; margin: 0 0 2px 0; text-transform: uppercase; color: #FFFFFF; letter-spacing: 0.5px; }
        .company-meta { font-size: 8px; color: #CBD5E1; line-height: 1.4; }
        
        .payslip-title { font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 0 0 3px 0; color: #FFFFFF; text-align: right; letter-spacing: 1px;}
        .payslip-month { font-size: 10px; text-align: right; font-weight: bold; color: #94A3B8; margin-bottom: 10px; text-transform: uppercase;}
        .payslip-meta { font-size: 8px; text-align: right; color: #CBD5E1; line-height: 1.4; }

        /* Section Headings */
        .section-title {
            font-size: 10px;
            font-weight: 600;
            color: #1E293B;
            text-transform: uppercase;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 3px;
            margin-bottom: 6px;
            margin-top: 12px;
            letter-spacing: 0.5px;
        }

        /* Employee Info */
        .emp-info-table { width: 100%; margin-bottom: 8px; }
        .emp-info-table td { padding: 3px 0; font-size: 9px; }
        .lbl { color: #64748B; font-weight: 500; width: 18%; }
        .val { color: #0F172A; font-weight: 600; width: 32%; }
        
        /* Attendance Summary */
        .att-table { width: 100%; margin-bottom: 5px; }
        .att-table th { background: #F8FAFC; border: 1px solid #E2E8F0; padding: 6px; font-size: 9px; color: #475569; text-align: center; font-weight: 500;}
        .att-table td { border: 1px solid #E2E8F0; padding: 6px 8px; font-size: 11px; color: #0F172A; text-align: center; font-weight: 600; }

        /* Earnings & Deductions */
        .salary-container { width: 100%; margin-bottom: 5px; }
        .salary-col-left { width: 49%; float: left; }
        .salary-col-right { width: 49%; float: right; }
        .ed-table { width: 100%; border: 1px solid #E2E8F0; border-radius: 4px; overflow: hidden; }
        .ed-table th { background: {{ $themeColor ?? '#0F172A' }}; padding: 6px 8px; text-align: left; font-size: 9px; color: #FFFFFF; font-weight: 500;}
        .ed-table th.amt { text-align: right; }
        .ed-table td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #F1F5F9; }
        .ed-table td.amt { text-align: right; font-weight: 600; }
        
        /* Zebra Striping */
        .ed-table tbody tr:nth-child(even) { background-color: #F8FAFC; }
        .ed-table tbody tr:nth-child(odd) { background-color: #FFFFFF; }
        
        .ed-total { background: #E2E8F0 !important; }
        .ed-total td { border-top: 1px solid #CBD5E1; padding: 6px 8px; color: #0F172A; font-weight: bold; font-size: 10px;}
        
        .clearfix { clear: both; }

        /* Formula Note */
        .formula-note { font-size: 8px; color: #64748B; text-align: right; margin-bottom: 10px; font-style: italic; }

        /* Salary Summary Row */
        .summary-row { width: 100%; border: 1px solid #E2E8F0; margin-bottom: 10px; background: #F8FAFC; border-radius: 4px; }
        .summary-row th { padding: 6px; font-size: 8px; color: #64748B; font-weight: 500; text-align: center; border-right: 1px solid #E2E8F0; border-bottom: 1px solid #E2E8F0; text-transform: uppercase;}
        .summary-row td { padding: 8px; font-size: 11px; font-weight: bold; color: #0F172A; text-align: center; border-right: 1px solid #E2E8F0;}
        .summary-row th:last-child, .summary-row td:last-child { border-right: none; }

        /* Net Pay Highlight */
        .net-pay-box {
            background: #F0FDF4;
            border: 2px solid #10B981;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            margin-bottom: 10px;
        }
        .net-pay-title { font-size: 11px; font-weight: 600; color: #059669; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 1px; }
        .net-pay-amount { font-size: 20px; font-weight: bold; color: #059669; margin-bottom: 3px; }
        .net-pay-words { font-size: 9px; color: #047857; font-weight: 600; margin-bottom: 4px; }
        .net-pay-desc { font-size: 8px; color: #047857; opacity: 0.9; }

        /* Production Table */
        .prod-table { width: 100%; border: 1px solid #E2E8F0; }
        .prod-table th { background: {{ $themeColor ?? '#0F172A' }}; padding: 6px 8px; text-align: left; font-size: 9px; color: #FFFFFF; border-bottom: 1px solid #E2E8F0; font-weight: 500;}
        .prod-table td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #F1F5F9; }
        .prod-table tbody tr:nth-child(even) { background-color: #F8FAFC; }

        /* Signatures */
        .signatures { width: 100%; margin-top: 35px; }
        .signatures td { text-align: center; vertical-align: bottom; height: 45px; width: 50%; }
        .sig-line { border-top: 1px solid #94A3B8; width: 160px; margin: 0 auto; padding-top: 5px; font-size: 9px; color: #64748B; }
        
        .digital-stamp {
            border: 2px dashed {{ $themeColor ?? '#0F172A' }};
            color: {{ $themeColor ?? '#0F172A' }};
            padding: 4px 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            transform: rotate(-5deg);
            display: inline-block;
            margin-bottom: 5px;
            opacity: 0.6;
            letter-spacing: 1px;
        }

        /* Footer */
        .footer { 
            position: fixed; 
            bottom: 0px; 
            left: 0px; 
            right: 0px; 
            text-align: center; 
            border-top: 1px solid #E2E8F0; 
            padding-top: 8px; 
            font-size: 8px; 
            color: #94A3B8; 
            line-height: 1.4; 
        }
    </style>
</head>
<body>
@php
    $emp = $employeeDetail;
    $isDaily = (bool) ($emp->daily_option ?? false);
    $branchName = $emp->branch->name ?? '-';
    $deptName = $emp->department->name ?? '-';
    $designation = $emp->designation->name ?? '-';
    $doj = $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->format('d M Y') : '-';
    
    // Company details
    $companyAddress = function_exists('getSetting') && getSetting('companyAddress') ? getSetting('companyAddress') : 'Surat, Gujarat - 395006';
    $companyGst = function_exists('getSetting') && getSetting('companyGst') ? getSetting('companyGst') : 'XXXXXXXXXX';
    $companyEmail = function_exists('getSetting') && getSetting('companyEmail') ? getSetting('companyEmail') : 'info@company.com';
    $companyPhone = function_exists('getSetting') && getSetting('companyPhone') ? getSetting('companyPhone') : '+91 99999 99999';

    // Amount in Words
    $netPayWords = '';
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
        $netPayWords = ucwords($formatter->format($payrollEntry->net_pay)) . ' Only';
    } else {
        $netPayWords = 'Rupees ' . number_format($payrollEntry->net_pay, 2) . ' Only';
    }
@endphp

<div class="wrapper">
    <!-- Header Box -->
    <div class="header-box">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    @php
                        $logoData = null;
                        $logoKey = function_exists('getSetting') ? getSetting('logoLight') : null;
                        if ($logoKey) {
                            $path = storage_path('app/public/' . $logoKey);
                            if (file_exists($path)) {
                                $type = pathinfo($path, PATHINFO_EXTENSION);
                                $data = file_get_contents($path);
                                $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
                            }
                        }
                    @endphp
                    @if($logoData)
                        <img src="{{ $logoData }}" class="company-logo" alt="Company Logo">
                    @endif
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-meta">
                        {{ $companyAddress }}<br>
                    </div>
                </td>
                <td style="width: 40%; vertical-align: bottom;">
                    <div class="payslip-title">PAYSLIP - {{ strtoupper($payrollEntry->payrollRun->pay_period_start->format('F Y')) }}</div>
                    <div class="payslip-month">Generated via HRMS</div>
                    <div class="payslip-meta">
                        <strong>Payslip No:</strong> {{ $payslip->payslip_number }}<br>
                        <strong>Pay Date:</strong> {{ $payslip->pay_date?->format('d M Y') ?? '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Employee Information -->
    <div class="section-title">Employee Information</div>
    @php
        $leftCol = [];
        $leftCol[] = ['lbl' => 'Employee Name', 'val' => $payrollEntry->employee->name];
        if (!empty($emp->emy_code)) $leftCol[] = ['lbl' => 'Employee Code', 'val' => $emp->emy_code];
        $leftCol[] = ['lbl' => 'Department', 'val' => $deptName];
        $leftCol[] = ['lbl' => 'Designation', 'val' => $designation];
        if ($doj !== '-') $leftCol[] = ['lbl' => 'Date of Joining', 'val' => $doj];
        $leftCol[] = ['lbl' => 'Branch / Location', 'val' => $branchName];
        $leftCol[] = ['lbl' => 'Pay Type', 'val' => ($isDaily ? 'Daily Wage' : 'Monthly')];
        
        $rightCol = [];
        if (!empty($emp->uan_number)) $rightCol[] = ['lbl' => 'UAN Number', 'val' => $emp->uan_number];
        if (!empty($emp->pf_number)) $rightCol[] = ['lbl' => 'PF Number', 'val' => $emp->pf_number];
        if (!empty($emp->esic_number)) $rightCol[] = ['lbl' => 'ESIC Number', 'val' => $emp->esic_number];
        if (!empty($emp->pan_number)) $rightCol[] = ['lbl' => 'PAN Number', 'val' => $emp->pan_number];
        if (!empty($emp->bank_account_no)) $rightCol[] = ['lbl' => 'Bank Account', 'val' => $emp->bank_account_no];
        if (!empty($emp->bank_name)) $rightCol[] = ['lbl' => 'Bank Name', 'val' => $emp->bank_name];
        $rightCol[] = ['lbl' => 'Base Salary Rate', 'val' => $currencySymbol . ' ' . number_format($payrollEntry->per_day_salary ?? 0, 2) . ($isDaily ? ' / day' : ' / month')];

        $rowCount = max(count($leftCol), count($rightCol));
    @endphp
    <table class="emp-info-table">
        @for($i = 0; $i < $rowCount; $i++)
            <tr>
                @if(isset($leftCol[$i]))
                    <td class="lbl">{{ $leftCol[$i]['lbl'] }}</td>
                    <td class="val">{{ $leftCol[$i]['val'] }}</td>
                @else
                    <td class="lbl"></td><td class="val"></td>
                @endif

                @if(isset($rightCol[$i]))
                    <td class="lbl">{{ $rightCol[$i]['lbl'] }}</td>
                    <td class="val">{{ $rightCol[$i]['val'] }}</td>
                @else
                    <td class="lbl"></td><td class="val"></td>
                @endif
            </tr>
        @endfor
    </table>

    <!-- Attendance Summary -->
    <div class="section-title">Attendance Summary</div>
    <table class="att-table">
        <tr>
            <th>Total Working Days</th>
            <th>Present Days</th>
            <th>Half Days</th>
            <th>Week Offs</th>
            <th>OT Days</th>
            <th>OT Hours</th>
        </tr>
        <tr>
            <td>{{ number_format($totalWorkedDays, 1) }}</td>
            <td>{{ number_format($payrollEntry->present_days ?? 0, 1) }}</td>
            <td>{{ number_format($payrollEntry->half_days ?? 0, 1) }}</td>
            <td>{{ number_format($payrollEntry->week_off_present_days ?? 0, 1) }}</td>
            <td>{{ number_format($payrollEntry->overtime_days ?? 0, 1) }}</td>
            <td>{{ number_format($payrollEntry->overtime_hours ?? 0, 1) }}</td>
        </tr>
    </table>

    <!-- Earnings & Deductions -->
    <div class="section-title">Salary Breakup</div>
    <div class="salary-container">
        <div class="salary-col-left">
            <table class="ed-table">
                <thead>
                    <tr>
                        <th>Earnings Component</th>
                        <th class="amt">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($earnings as $name => $amount)
                        <tr>
                            <td>{{ $name }}</td>
                            <td class="amt">{{ $currencySymbol }} {{ number_format($amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" style="text-align:center; color:#94A3B8; padding: 10px;">No earnings</td>
                        </tr>
                    @endforelse
                    
                    @if(($productionEntries ?? collect())->isNotEmpty())
                        @php $productionTotal = 0; @endphp
                        @foreach($productionEntries as $prod)
                            @php $productionTotal += (float) $prod->amount; @endphp
                        @endforeach
                        <tr>
                            <td>Production Earnings</td>
                            <td class="amt">{{ $currencySymbol }} {{ number_format($productionTotal, 2) }}</td>
                        </tr>
                    @endif
                    
                    <tr class="ed-total">
                        <td>Gross Earnings</td>
                        <td class="amt">{{ $currencySymbol }} {{ number_format($payrollEntry->gross_pay, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="salary-col-right">
            <table class="ed-table">
                <thead>
                    <tr>
                        <th>Deductions Component</th>
                        <th class="amt">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deductions as $name => $amount)
                        <tr>
                            <td>{{ $name }}</td>
                            <td class="amt">{{ $currencySymbol }} {{ number_format($amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" style="text-align:center; color:#94A3B8; padding: 10px;">No deductions</td>
                        </tr>
                    @endforelse
                    <tr class="ed-total">
                        <td>Total Deductions</td>
                        <td class="amt">{{ $currencySymbol }} {{ number_format($payrollEntry->total_deductions, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="clearfix"></div>
    </div>
    
    <div class="formula-note">
        * Note: Net Salary = Gross Salary - Total Deductions
    </div>

    <!-- Salary Summary Row -->
    <table class="summary-row">
        <tr>
            <th>Gross Salary</th>
            <th>Total Deductions</th>
            <th>Net Salary</th>
        </tr>
        <tr>
            <td>{{ $currencySymbol }} {{ number_format($payrollEntry->gross_pay, 2) }}</td>
            <td>{{ $currencySymbol }} {{ number_format($payrollEntry->total_deductions, 2) }}</td>
            <td>{{ $currencySymbol }} {{ number_format($payrollEntry->net_pay, 2) }}</td>
        </tr>
    </table>

    <!-- Net Pay Highlight Section -->
    <div class="net-pay-box">
        <div class="net-pay-title">NET TAKE HOME</div>
        <div class="net-pay-amount">{{ $currencySymbol }} {{ number_format($payrollEntry->net_pay, 2) }}</div>
        <div class="net-pay-words">({{ $netPayWords }})</div>
        <div class="net-pay-desc">
            @if($emp->bank_account_no)
                Salary transferred to account ending in {{ substr($emp->bank_account_no, -4) }}
            @else
                Paid via selected payment method
            @endif
        </div>
    </div>

    <!-- Production Details (Optional) -->
    @if(($productionEntries ?? collect())->isNotEmpty())
    <div class="section-title">Production Details</div>
    <table class="prod-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Material / Operation</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Rate</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productionEntries as $prod)
                <tr>
                    <td>{{ $prod->date->format('d M Y') }}</td>
                    <td>{{ $prod->materialItem?->name ?? '—' }}</td>
                    <td style="text-align: center;">{{ number_format($prod->production_qty, 2) }}</td>
                    <td style="text-align: right;">{{ $currencySymbol }} {{ number_format($prod->rate, 2) }}</td>
                    <td style="text-align: right; font-weight: 600;">{{ $currencySymbol }} {{ number_format($prod->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Signatures -->
    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line">Employee Signature</div>
            </td>
            <td>
                <div class="sig-line">Employer Signature</div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        This is a computer-generated payslip.<br>
        No signature required if digitally generated.<br><br>
        Generated on: {{ now()->format('d M Y h:i A') }} | {{ $companyName }}<br><br>
        <span style="opacity: 0.8;">Developed by Sridix Technology | EOF</span>
    </div>
</div>
</body>
</html>
