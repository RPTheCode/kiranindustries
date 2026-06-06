<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee?->name }}</title>
    <style>
        @page { margin: 12mm 15mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 10px;
            color: #334155;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        .header-box {
            background: {{ $themeColor ?? '#0F172A' }};
            color: #FFFFFF;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }
        .company-name { font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 0 0 4px 0; }
        .payslip-title { font-size: 13px; font-weight: bold; text-transform: uppercase; text-align: right; }
        .payslip-meta { font-size: 8px; text-align: right; color: #CBD5E1; line-height: 1.5; margin-top: 6px; }
        .section-title {
            font-size: 10px;
            font-weight: 600;
            color: #1E293B;
            text-transform: uppercase;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 3px;
            margin: 12px 0 6px;
        }
        .info-table td { padding: 3px 0; font-size: 9px; }
        .lbl { color: #64748B; width: 22%; }
        .val { color: #0F172A; font-weight: 600; width: 28%; }
        .ed-table { width: 100%; border: 1px solid #E2E8F0; margin-bottom: 8px; }
        .ed-table th {
            background: {{ $themeColor ?? '#0F172A' }};
            color: #fff;
            padding: 6px 8px;
            font-size: 9px;
            text-align: left;
        }
        .ed-table th.amt { text-align: right; }
        .ed-table td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #F1F5F9; }
        .ed-table td.amt { text-align: right; font-weight: 600; }
        .ed-total td { background: #E2E8F0; font-weight: bold; }
        .summary-row { width: 100%; border: 1px solid #E2E8F0; background: #F8FAFC; margin-bottom: 10px; }
        .summary-row th, .summary-row td { padding: 6px; font-size: 9px; text-align: center; border: 1px solid #E2E8F0; }
        .net-pay-box {
            background: #F0FDF4;
            border: 2px solid #10B981;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            margin: 10px 0;
        }
        .net-pay-amount { font-size: 20px; font-weight: bold; color: #059669; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            border-top: 1px solid #E2E8F0;
            padding-top: 8px;
            font-size: 8px;
            color: #94A3B8;
        }
        .two-col { width: 100%; }
        .two-col td { width: 50%; vertical-align: top; padding-right: 8px; }
    </style>
</head>
<body>
@php
    $emp = $employeeDetail;
    $branchName = $emp?->branch?->name ?? $run?->branch?->name ?? '-';
    $deptName = $emp?->department?->name ?? '-';
    $designation = $emp?->designation?->name ?? '-';
    $category = $emp?->category?->name ?? '-';
    $shift = $emp?->shift?->name ?? '-';
    $employeeCode = $emp?->employee_id ?? $emp?->emy_code ?? '-';
    $periodLabel = $run?->title ?? ($run?->pay_period_start?->format('d M Y') . ' - ' . $run?->pay_period_end?->format('d M Y'));

    $netPayWords = '';
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
        $netPayWords = ucwords($formatter->format((float) $entry->net_salary)) . ' Only';
    } else {
        $netPayWords = 'Rupees ' . number_format((float) $entry->net_salary, 2) . ' Only';
    }

    $fmt = fn ($n) => $currencySymbol . number_format((float) $n, 2);
@endphp

<div class="header-box">
    <table>
        <tr>
            <td style="width: 55%;">
                <div class="company-name">{{ $companyName }}</div>
                <div style="font-size: 8px; color: #CBD5E1;">{{ $branchName }}</div>
            </td>
            <td style="width: 45%;">
                <div class="payslip-title">{{ __('Salary Payslip') }}</div>
                <div class="payslip-meta">
                    {{ $periodLabel }}<br>
                    {{ __('Payslip No.') }}: {{ $payslip->payslip_number }}<br>
                    {{ __('Generated') }}: {{ $payslip->generated_at?->format('d M Y') ?? now()->format('d M Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="section-title">{{ __('Employee Details') }}</div>
<table class="info-table">
    <tr>
        <td class="lbl">{{ __('Employee Name') }}</td>
        <td class="val">{{ $employee?->name ?? '-' }}</td>
        <td class="lbl">{{ __('Employee Code') }}</td>
        <td class="val">{{ $employeeCode }}</td>
    </tr>
    <tr>
        <td class="lbl">{{ __('Department') }}</td>
        <td class="val">{{ $deptName }}</td>
        <td class="lbl">{{ __('Designation') }}</td>
        <td class="val">{{ $designation }}</td>
    </tr>
    <tr>
        <td class="lbl">{{ __('Category') }}</td>
        <td class="val">{{ $category }}</td>
        <td class="lbl">{{ __('Shift') }}</td>
        <td class="val">{{ $shift }}</td>
    </tr>
    <tr>
        <td class="lbl">{{ __('Pay Period') }}</td>
        <td class="val">{{ $run?->pay_period_start?->format('d M Y') }} - {{ $run?->pay_period_end?->format('d M Y') }}</td>
        <td class="lbl">{{ __('Financial Year') }}</td>
        <td class="val">{{ $run?->financial_year ?? '-' }}</td>
    </tr>
</table>

<table class="summary-row">
    <tr>
        <th>{{ __('Monthly Gross') }}</th>
        <th>{{ __('Basic') }}</th>
        <th>{{ __('Total Earnings') }}</th>
        <th>{{ __('Total Deductions') }}</th>
    </tr>
    <tr>
        <td>{{ $fmt($entry->monthly_gross) }}</td>
        <td>{{ $fmt($entry->basic) }}</td>
        <td>{{ $fmt($entry->total_earnings) }}</td>
        <td>{{ $fmt($entry->total_deductions) }}</td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="section-title">{{ __('Earnings') }}</div>
            <table class="ed-table">
                <thead>
                    <tr>
                        <th>{{ __('Component') }}</th>
                        <th class="amt">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($earnings as $label => $amount)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="amt">{{ $fmt($amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td>{{ __('Basic Salary') }}</td>
                            <td class="amt">{{ $fmt($entry->basic) }}</td>
                        </tr>
                    @endforelse
                    <tr class="ed-total">
                        <td>{{ __('Total Earnings') }}</td>
                        <td class="amt">{{ $fmt($entry->total_earnings) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
        <td>
            <div class="section-title">{{ __('Deductions') }}</div>
            <table class="ed-table">
                <thead>
                    <tr>
                        <th>{{ __('Component') }}</th>
                        <th class="amt">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if((float) $entry->pf_employee > 0)
                        <tr><td>{{ __('PF (Employee)') }}</td><td class="amt">{{ $fmt($entry->pf_employee) }}</td></tr>
                    @endif
                    @if((float) $entry->esi_employee > 0)
                        <tr><td>{{ __('ESI (Employee)') }}</td><td class="amt">{{ $fmt($entry->esi_employee) }}</td></tr>
                    @endif
                    @if((float) $entry->pt_amount > 0)
                        <tr><td>{{ __('Professional Tax') }}</td><td class="amt">{{ $fmt($entry->pt_amount) }}</td></tr>
                    @endif
                    @foreach($deductions as $label => $amount)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="amt">{{ $fmt($amount) }}</td>
                        </tr>
                    @endforeach
                    <tr class="ed-total">
                        <td>{{ __('Total Deductions') }}</td>
                        <td class="amt">{{ $fmt($entry->total_deductions) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

@if((float) $entry->pf_employer > 0 || (float) $entry->esi_employer > 0)
    <div class="section-title">{{ __('Employer Contributions (Info Only)') }}</div>
    <table class="summary-row">
        <tr>
            @if((float) $entry->pf_employer > 0)<th>{{ __('PF (Employer)') }}</th>@endif
            @if((float) $entry->esi_employer > 0)<th>{{ __('ESI (Employer)') }}</th>@endif
        </tr>
        <tr>
            @if((float) $entry->pf_employer > 0)<td>{{ $fmt($entry->pf_employer) }}</td>@endif
            @if((float) $entry->esi_employer > 0)<td>{{ $fmt($entry->esi_employer) }}</td>@endif
        </tr>
    </table>
@endif

<div class="net-pay-box">
    <div style="font-size: 11px; font-weight: 600; color: #059669; text-transform: uppercase;">{{ __('Net Pay') }}</div>
    <div class="net-pay-amount">{{ $fmt($entry->net_salary) }}</div>
    <div style="font-size: 9px; color: #047857;">{{ $netPayWords }}</div>
</div>

<div class="footer">
    {{ __('This is a system-generated payslip from Salary Payroll.') }} | {{ $companyName }}
</div>
</body>
</html>
