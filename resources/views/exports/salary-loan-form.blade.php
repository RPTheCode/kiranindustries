@php
    $isBlank = (bool) ($blank ?? false);
    $blankLine = '________________________';
    $blankShort = '________';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $isBlank ? 'Salary Loan Form (Blank)' : 'Salary Loan Form - #'.$loan->id }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #1e293b; line-height: 1.35; margin: 0; padding: 16px; background: #f1f5f9; }
        .no-print { margin-bottom: 12px; }
        .no-print button { background: #2d4a77; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .page-wrap { max-width: 210mm; margin: 0 auto; }
        .form-block { width: 100%; border: 1px solid #cbd5e1; border-left: 5px solid #2d4a77; border-radius: 4px; border-collapse: collapse; background: #fff; }
        .form-content-cell { vertical-align: top; padding: 10px 14px; }
        .form-footer-cell { vertical-align: bottom; padding: 10px 14px 8px; border-top: 1px solid #e2e8f0; }
        .form-header { background: #2d4a77; color: #fff; padding: 6px 10px; border-radius: 3px; text-align: center; margin-bottom: 4px; }
        .form-header h1 { margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 900; }
        .form-type-bar { background: #eef2ff; border: 1px solid #c7d2fe; padding: 5px 10px; margin-bottom: 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .meta-bar { font-size: 11px; color: #475569; margin-bottom: 8px; font-weight: bold; }
        .details-table { width: 100%; margin-bottom: 8px; border-collapse: collapse; background: #f8fafc; border: 1px solid #cbd5e1; }
        .details-table td { padding: 5px 8px; font-size: 12px; }
        .label { font-weight: bold; color: #475569; }
        .val-line { font-weight: 900; color: #0f172a; }
        .blank-field { border-bottom: 1px dashed #94a3b8; min-height: 18px; color: #64748b; font-weight: normal; }
        .info-strip { font-size: 11px; margin-bottom: 6px; padding: 5px 8px; border-radius: 3px; font-weight: bold; }
        .info-strip-salary { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .amount-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: 1px solid #cbd5e1; }
        .amount-table td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .amount-highlight { font-size: 14px; font-weight: 900; }
        .reason-line { border-bottom: 1px dashed #94a3b8; min-height: 18px; padding: 2px 4px; font-weight: bold; }
        .guarantor-table { width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #cbd5e1; }
        .guarantor-table th, .guarantor-table td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 11px; }
        .guarantor-table th { background: #f1f5f9; text-align: left; }
        .guarantor-table .sig-cell { height: 32px; }
        .surety-text { font-size: 10px; color: #475569; margin: 8px 0; line-height: 1.45; font-style: italic; }
        .signature-table { width: 100%; margin-top: 12px; border-collapse: collapse; }
        .signature-table td { text-align: center; font-size: 10px; font-weight: bold; color: #475569; vertical-align: bottom; padding-top: 10px; }
        .sig-line { border-bottom: 1px solid #64748b; margin: 0 auto 5px; width: 90%; display: block; height: 22px; }
        .accounts-block, .hr-block { margin-top: 10px; padding: 8px; border: 1px dashed #94a3b8; font-size: 10px; line-height: 1.5; }
        .form-footer-table { width: 100%; border-collapse: collapse; }
        .form-footer-table td { font-size: 9px; color: #64748b; font-weight: bold; }
        .form-footer-right { text-align: right; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button type="button" onclick="window.print()">Print</button></div>
    <div class="page-wrap">
        <table class="form-block">
            <tr>
                <td class="form-content-cell">
                    <div class="form-header"><h1>{{ strtoupper($companyName ?? 'KIRAN INDUSTRIES PVT LTD.') }}</h1></div>
                    <div class="form-type-bar">FORM TYPE:- LOAN FORM</div>
                    <div class="meta-bar">
                        @if($isBlank)
                            Application No: <span class="blank-field">{{ $blankShort }}</span>
                            &nbsp;|&nbsp; Date: <span class="blank-field">{{ $blankShort }}</span>
                        @else
                            Application No: <span class="val-line">#{{ $loan->id }}</span>
                            &nbsp;|&nbsp; Date: <span class="val-line">{{ $loan->application_date?->format('d/m/Y') }}</span>
                            &nbsp;|&nbsp; Status: <span class="val-line">{{ strtoupper(str_replace('_', ' ', $loan->status)) }}</span>
                        @endif
                    </div>
                    <table class="details-table">
                        <tr>
                            <td class="label">Employee Name:</td>
                            <td style="width:40%">
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $loan->employee?->name }}</span>@endif
                            </td>
                            <td class="label" style="text-align:right">Code No:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankShort }}</div>
                                @else<span class="val-line">{{ $loan->employee?->employee?->employee_id ?? $loan->employee?->employee?->emy_code }}</span>@endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Division:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $loan->branch?->name ?? $loan->employee?->employee?->branch?->name ?? '—' }}</span>@endif
                            </td>
                            <td class="label" style="text-align:right">Department:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $loan->employee?->employee?->department?->name ?? '—' }}</span>@endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Designation:</td>
                            <td colspan="3">
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $loan->employee?->employee?->designation?->name ?? '—' }}</span>@endif
                            </td>
                        </tr>
                    </table>
                    <div class="info-strip info-strip-salary">
                        @if($isBlank)
                            <strong>Present Salary:</strong> ₹ {{ $blankShort }}
                            &nbsp;|&nbsp; <strong>Installments:</strong> {{ $blankShort }}
                            &nbsp;|&nbsp; <strong>EMI:</strong> ₹ {{ $blankShort }}
                        @else
                            <strong>Present Salary:</strong> ₹ {{ number_format((float) $loan->present_salary_snapshot, 2) }}
                            &nbsp;|&nbsp; <strong>Installments:</strong> {{ $loan->installment_count }}
                            &nbsp;|&nbsp; <strong>EMI:</strong> ₹ {{ number_format((float) ($loan->installment_amount ?? 0), 2) }}
                        @endif
                    </div>
                    <table class="amount-table">
                        <tr>
                            <td class="label" style="width:22%;background:#f8fafc">Loan Amount:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="amount-highlight">₹ {{ number_format((float) ($loan->approved_amount ?? $loan->requested_amount), 2) }}</span>@endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="background:#f8fafc">Amount in Words:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else{{ $loan->amount_in_words }}@endif
                            </td>
                        </tr>
                    </table>
                    <table style="width:100%;margin-bottom:8px"><tr>
                        <td style="width:70px;font-weight:bold;color:#475569">Purpose:</td>
                        <td class="reason-line">@unless($isBlank){{ $loan->purpose }}@endunless</td>
                    </tr></table>
                    <p style="font-weight:bold;margin:12px 0 6px;text-transform:uppercase;font-size:11px">Surety</p>
                    <table class="guarantor-table">
                        <thead>
                            <tr><th>#</th><th>Guarantor Name</th><th>Code</th><th>Department</th><th>Signature</th></tr>
                        </thead>
                        <tbody>
                            @if($isBlank)
                                @for($i = 1; $i <= 3; $i++)
                                    <tr>
                                        <td>{{ $i }}</td>
                                        <td class="blank-field">{{ $blankLine }}</td>
                                        <td class="blank-field">{{ $blankShort }}</td>
                                        <td class="blank-field">{{ $blankLine }}</td>
                                        <td class="sig-cell"></td>
                                    </tr>
                                @endfor
                            @else
                                @foreach($loan->guarantors as $g)
                                    <tr>
                                        <td>{{ $g->sort_order }}</td>
                                        <td>{{ $g->name }}</td>
                                        <td>{{ $g->employee_code ?? '—' }}</td>
                                        <td>{{ $g->department ?? '—' }}</td>
                                        <td class="sig-cell"></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    <p class="surety-text">I give full assurance to pay the above loan amount. If the applicant fails to repay the loan, I/we shall clear the loan outstanding from my/our monthly wage/salary.</p>
                    <table class="signature-table">
                        <tr>
                            <td style="width:33%"><span class="sig-line"></span>Applicant Sign</td>
                            <td style="width:33%"><span class="sig-line"></span>Recommended by Dept. Head</td>
                            <td style="width:33%"><span class="sig-line"></span>Authorised Sign</td>
                        </tr>
                    </table>
                    <div class="accounts-block">
                        <strong>For Account Department,</strong><br>
                        @if($isBlank)
                            Please Pay Rs. {{ $blankShort }} ({{ $blankLine }}) as Advance / Loan and Deduction @
                            ₹ {{ $blankShort }} from the salary of the month of {{ $blankShort }}.
                        @else
                            Please Pay Rs. {{ number_format((float) ($loan->approved_amount ?? $loan->requested_amount), 2) }}
                            ({{ $loan->amount_in_words }}) as Advance / Loan and Deduction @
                            ₹ {{ number_format((float) ($loan->installment_amount ?? 0), 2) }}
                            from the salary of the month of {{ $loan->deduction_start_month ?? $loan->month_year ?? '________' }}.
                        @endif
                    </div>
                    <div class="hr-block">
                        <strong>For Human Resource Department,</strong><br>
                        @if($isBlank)
                            Advance / Loan Posted at Code {{ $blankShort }} Dated {{ $blankShort }}
                            for sanctioned loan amt. ₹ {{ $blankShort }} Only.
                        @else
                            Advance / Loan Posted at Code {{ $loan->employee?->employee?->employee_id ?? '____' }}
                            Dated {{ $loan->disbursement_date?->format('d/m/Y') ?? '____' }}
                            for sanctioned loan amt. ₹ {{ number_format((float) ($loan->approved_amount ?? $loan->requested_amount), 2) }} Only.
                        @endif
                    </div>
                    <table class="signature-table">
                        <tr><td colspan="3" style="text-align:right;padding-top:16px"><span class="sig-line" style="width:40%;margin-left:auto"></span>Director's Sign</td></tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="form-footer-cell">
                    <table class="form-footer-table">
                        <tr>
                            <td>Develop by Sridix Technology LLP</td>
                            <td class="form-footer-right">Printed on: {{ $printDate ?? now()->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
