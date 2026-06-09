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
        .summary-row.compact th, .summary-row.compact td { padding: 5px 4px; font-size: 8px; }
        .info-note {
            font-size: 8px;
            color: #64748B;
            margin: 4px 0 8px;
            line-height: 1.45;
        }
        .salary-breakdown {
            font-size: 9px;
            color: #334155;
            margin-bottom: 10px;
            padding: 8px 10px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            line-height: 1.6;
        }
        .salary-breakdown strong { color: #0F172A; }
        .employer-section { margin-top: 8px; }
        .employer-section .section-title { font-size: 9px; color: #64748B; }
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
    $fmtDays = fn ($n) => number_format((float) $n, ((float) $n) == floor((float) $n) ? 0 : 1);
    $maskAccount = function (?string $account): ?string {
        if ($account === null || $account === '') {
            return null;
        }
        $account = preg_replace('/\s+/', '', $account);
        if (strlen($account) <= 4) {
            return $account;
        }

        return str_repeat('X', max(0, strlen($account) - 4)) . substr($account, -4);
    };

    $statutoryLabels = collect([
        (float) $entry->pf_employee > 0 ? 'PF' : null,
        (float) $entry->esi_employee > 0 ? 'ESIC' : null,
        (float) $entry->pt_amount > 0 ? 'PT' : null,
    ])->filter()->values();
    $statutoryNote = $statutoryLabels->isNotEmpty()
        ? $statutoryLabels->join(', ')
        : __('statutory');
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
    @if(!empty($emp?->pf_number) || !empty($emp?->uan_number) || !empty($emp?->esic_number) || !empty($emp?->pan_card_number))
    <tr>
        @if(!empty($emp?->pf_number))
            <td class="lbl">{{ __('PF Number') }}</td>
            <td class="val">{{ $emp->pf_number }}</td>
        @elseif(!empty($emp?->uan_number))
            <td class="lbl">{{ __('UAN') }}</td>
            <td class="val">{{ $emp->uan_number }}</td>
        @else
            <td class="lbl"></td><td class="val"></td>
        @endif
        @if(!empty($emp?->uan_number) && !empty($emp?->pf_number))
            <td class="lbl">{{ __('UAN') }}</td>
            <td class="val">{{ $emp->uan_number }}</td>
        @elseif(!empty($emp?->esic_number))
            <td class="lbl">{{ __('ESIC Number') }}</td>
            <td class="val">{{ $emp->esic_number }}</td>
        @elseif(!empty($emp?->pan_card_number))
            <td class="lbl">{{ __('PAN') }}</td>
            <td class="val">{{ $emp->pan_card_number }}</td>
        @else
            <td class="lbl"></td><td class="val"></td>
        @endif
    </tr>
    @if(!empty($emp?->pan_card_number) && (!empty($emp?->pf_number) || !empty($emp?->esic_number)))
    <tr>
        <td class="lbl">{{ __('PAN') }}</td>
        <td class="val">{{ $emp->pan_card_number }}</td>
        <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @endif
    @if(!empty($emp?->date_of_joining))
    <tr>
        <td class="lbl">{{ __('Date of Joining') }}</td>
        <td class="val">{{ \Carbon\Carbon::parse($emp->date_of_joining)->format('d M Y') }}</td>
        <td class="lbl">{{ __('OT Status') }}</td>
        <td class="val">{{ ($entry->ot_enabled ?? false) ? __('Yes') : __('No') }}</td>
    </tr>
    @else
    <tr>
        <td class="lbl">{{ __('OT Status') }}</td>
        <td class="val">{{ ($entry->ot_enabled ?? false) ? __('Yes') : __('No') }}</td>
        <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @if(!empty($emp?->bank_name) || !empty($emp?->account_number) || !empty($emp?->ifsc_code))
    <tr>
        @if(!empty($emp?->bank_name))
            <td class="lbl">{{ __('Bank Name') }}</td>
            <td class="val">{{ $emp->bank_name }}</td>
        @else
            <td class="lbl"></td><td class="val"></td>
        @endif
        @if(!empty($emp?->account_number))
            <td class="lbl">{{ __('Account Number') }}</td>
            <td class="val">{{ $maskAccount($emp->account_number) }}</td>
        @else
            <td class="lbl"></td><td class="val"></td>
        @endif
    </tr>
    @if(!empty($emp?->ifsc_code))
    <tr>
        <td class="lbl">{{ __('IFSC Code') }}</td>
        <td class="val">{{ $emp->ifsc_code }}</td>
        <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @endif
</table>

@if($run?->use_attendance)
@php
    $workingDays = (float) ($entry->working_days ?? 26);
    $presentDays = (float) ($entry->present_days ?? 0);
    $paidDays = (float) ($entry->paid_days ?? 0);
    $actualPaidDays = (float) ($entry->actual_paid_days ?? $paidDays);
    $woWorked = (float) ($entry->week_off_worked_days ?? 0);
    $piDays = (float) ($entry->incentive_days ?? 0);
    $piAmount = (float) ($entry->incentive_amount ?? 0);
    $halfDays = (float) ($entry->half_days ?? 0);
    $govtPaidDays = $salaryMeta['govt_wage_paid_days'] ?? null;
    $showGovtDays = ($salaryMeta['govt_wage_applied'] ?? false)
        && $govtPaidDays !== null
        && abs($actualPaidDays - (float) $govtPaidDays) >= 0.01;
@endphp
<div class="section-title">{{ __('Attendance Summary') }}</div>
<table class="summary-row">
    <tr>
        <th>{{ __('Working Days') }}</th>
        <th>{{ __('Present Days') }}</th>
        @if($showGovtDays)
            <th>{{ __('Salary Days') }}</th>
            <th>{{ __('Statutory Days') }}</th>
        @else
            <th>{{ __('Paid Days') }}</th>
        @endif
        @if($woWorked > 0)<th>{{ __('Week-off Worked') }}</th>@endif
        @if($halfDays > 0)<th>{{ __('Half Days') }}</th>@endif
        @if($piDays > 0)<th>{{ __('PI Days') }}</th>@endif
    </tr>
    <tr>
        <td>{{ $fmtDays($workingDays) }}</td>
        <td>{{ $fmtDays($presentDays) }}</td>
        @if($showGovtDays)
            <td>{{ $fmtDays($actualPaidDays) }}</td>
            <td>{{ $fmtDays((float) $govtPaidDays) }}</td>
        @else
            <td>{{ $fmtDays($paidDays) }}</td>
        @endif
        @if($woWorked > 0)<td>{{ $fmtDays($woWorked) }}</td>@endif
        @if($halfDays > 0)<td>{{ $fmtDays($halfDays) }}</td>@endif
        @if($piDays > 0)<td>{{ $fmtDays($piDays) }}</td>@endif
    </tr>
</table>
@if($woWorked > 0 || $showGovtDays)
    <div class="info-note">
        @if($woWorked > 0)
            {{ __('Salary days = Present days + Week-off worked (:present + :wo = :total).', [
                'present' => $fmtDays($presentDays),
                'wo' => $fmtDays($woWorked),
                'total' => $fmtDays($actualPaidDays),
            ]) }}
        @endif
        @if($showGovtDays)
            {{ $woWorked > 0 ? ' ' : '' }}{{ __('Statutory days are used for PF calculation at government minimum wage rate.') }}
        @endif
    </div>
@endif
@if($piAmount > 0)
    <div style="font-size: 8px; color: #64748B; margin-bottom: 6px;">
        {{ __('Production Incentive (PI)') }}: {{ $fmt($piAmount) }} ({{ number_format($piDays, $piDays == floor($piDays) ? 0 : 1) }} {{ __('days') }})
    </div>
@endif
@endif

@php
    $isDayRate = $salaryMeta['is_day_rate'] ?? false;
    $govtApplied = $salaryMeta['govt_wage_applied'] ?? false;
    $rateAmount = (float) ($salaryMeta['rate_amount'] ?? $entry->monthly_gross);
    $salaryDays = (float) ($salaryMeta['actual_paid_days'] ?? $entry->paid_days ?? 0);
    $contractEarnings = (float) ($salaryMeta['contract_earnings'] ?? 0);
    $govtRate = (float) ($salaryMeta['govt_min_wage_per_day'] ?? 0);
    $govtDays = (float) ($salaryMeta['govt_wage_paid_days'] ?? 0);
    $govtEarnings = (float) ($salaryMeta['govt_wage_computed_earnings'] ?? $entry->total_earnings);
    $govtAdjust = (float) ($salaryMeta['govt_wage_adjustment'] ?? 0);
@endphp

@if($isDayRate && $govtApplied && $govtRate > 0)
    <div class="section-title">{{ __('How Your Salary Was Calculated') }}</div>
    <div class="salary-breakdown">
        <strong>{{ __('Contract pay') }}:</strong>
        {{ $fmt($rateAmount) }}/{{ __('day') }} × {{ $fmtDays($salaryDays) }} {{ __('salary days') }} = {{ $fmt($contractEarnings) }}<br>
        <strong>{{ __('Statutory (PF) basis') }}:</strong>
        {{ $fmt($govtRate) }}/{{ __('day') }} × {{ $fmtDays($govtDays) }} {{ __('days') }} = {{ $fmt($govtEarnings) }}<br>
        @if(($salaryMeta['govt_wage_adjustment_type'] ?? null) === 'deduction' && $govtAdjust > 0)
            <strong>{{ __('Adjustment') }}:</strong>
            −{{ $fmt($govtAdjust) }} ({{ __('deducted so your take-home matches contract pay') }})
        @endif
    </div>
@else
    <div class="section-title">{{ __('Salary Summary') }}</div>
    <table class="summary-row compact">
        <tr>
            <th>{{ __($salaryMeta['rate_label'] ?? 'Monthly Gross') }}</th>
            @if($isDayRate)
                <th>{{ __('CTC (Day × Working Days)') }}</th>
                @if($salaryDays > 0)
                    <th>{{ __('Salary Days') }}</th>
                @endif
            @endif
            @if($govtApplied)
                <th>{{ __('Contract Salary') }}</th>
            @endif
        </tr>
        <tr>
            <td>{{ $fmt($rateAmount) }}{{ $isDayRate ? '/day' : '' }}</td>
            @if($isDayRate)
                <td>{{ $fmt($salaryMeta['ctc'] ?? 0) }}</td>
                @if($salaryDays > 0)
                    <td>{{ $fmtDays($salaryDays) }}</td>
                @endif
            @endif
            @if($govtApplied)
                <td>{{ $fmt($contractEarnings) }}</td>
            @endif
        </tr>
    </table>
@endif

<div class="section-title">{{ __('Pay Summary') }}</div>
<table class="summary-row">
    <tr>
        <th>{{ __('Basic') }}</th>
        <th>{{ __('Gross Earnings') }}</th>
        <th>{{ __('Total Deductions') }}</th>
        <th>{{ __('Take Home (Net Pay)') }}</th>
    </tr>
    <tr>
        <td>{{ $fmt($entry->basic) }}</td>
        <td>{{ $fmt($entry->total_earnings) }}</td>
        <td>{{ $fmt($entry->total_deductions) }}</td>
        <td style="font-weight: bold; color: #059669; font-size: 10px;">{{ $fmt($entry->net_salary) }}</td>
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
                    @forelse($deductions as $label => $amount)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="amt">{{ $fmt($amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" style="text-align:center; color:#94A3B8;">{{ __('No deductions') }}</td>
                        </tr>
                    @endforelse
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
    <div class="employer-section">
    <div class="section-title">{{ __('Employer PF/ESI (Not Deducted From Your Salary)') }}</div>
    <table class="summary-row">
        <tr>
            @if((float) $entry->pf_wages > 0)
                <th>{{ __('PF Wages') }}</th>
            @endif
            @if((float) $entry->pf_employer > 0)
                <th>{{ __('PF (Employer)') }}</th>
            @endif
            @if((float) $entry->pf_eps_employer > 0)
                <th>{{ __('EPS (Pension)') }}</th>
            @endif
            @if((float) $entry->pf_epf_employer > 0)
                <th>{{ __('EPF (Employer Share)') }}</th>
            @endif
            @if(($salaryMeta['pf_admin_employer'] ?? 0) > 0)
                <th>{{ __('PF Admin Charges') }}</th>
            @endif
            @if((float) $entry->esi_employer > 0)
                <th>{{ __('ESI (Employer)') }}</th>
            @endif
        </tr>
        <tr>
            @if((float) $entry->pf_wages > 0)
                <td>{{ $fmt($entry->pf_wages) }}</td>
            @endif
            @if((float) $entry->pf_employer > 0)
                <td>{{ $fmt($entry->pf_employer) }}</td>
            @endif
            @if((float) $entry->pf_eps_employer > 0)
                <td>{{ $fmt($entry->pf_eps_employer) }}</td>
            @endif
            @if((float) $entry->pf_epf_employer > 0)
                <td>{{ $fmt($entry->pf_epf_employer) }}</td>
            @endif
            @if(($salaryMeta['pf_admin_employer'] ?? 0) > 0)
                <td>{{ $fmt($salaryMeta['pf_admin_employer']) }}</td>
            @endif
            @if((float) $entry->esi_employer > 0)
                <td>{{ $fmt($entry->esi_employer) }}</td>
            @endif
        </tr>
    </table>
    </div>
@endif

<div class="net-pay-box">
    <div style="font-size: 11px; font-weight: 600; color: #059669; text-transform: uppercase;">{{ __('Take Home Salary') }}</div>
    <div class="net-pay-amount">{{ $fmt($entry->net_salary) }}</div>
    <div style="font-size: 9px; color: #047857;">{{ $netPayWords }}</div>
    <div style="font-size: 8px; color: #64748B; margin-top: 6px;">
        {{ __('Gross :earnings − Deductions :deductions = Net :net. :statutory are statutory deductions.', [
            'earnings' => $fmt($entry->total_earnings),
            'deductions' => $fmt($entry->total_deductions),
            'net' => $fmt($entry->net_salary),
            'statutory' => $statutoryNote,
        ]) }}
    </div>
</div>

<div class="footer">
    {{ __('This is a system-generated payslip from Salary Payroll.') }} | {{ $companyName }}
</div>
</body>
</html>
