@php
    $isBlank = (bool) ($blank ?? false);
    $blankLine = '________________________';
    $blankShort = '________';
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $isBlank ? 'Salary Advance Form (Blank)' : 'Salary Advance Form - #'.$advance->id }}</title>
    <style>
        @page {
            margin: 10mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 16px;
            background: #f1f5f9;
        }

        .no-print {
            margin-bottom: 12px;
        }

        .no-print button {
            background: #2d4a77;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
        }

        .page-wrap {
            max-width: 210mm;
            margin: 0 auto;
        }

        .form-block {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-left: 5px solid #2d4a77;
            border-radius: 4px;
            border-collapse: collapse;
            table-layout: fixed;
            background: #ffffff;
        }

        .form-content-cell {
            vertical-align: top;
            padding: 10px 14px 10px 14px;
        }

        .form-footer-cell {
            vertical-align: bottom;
            padding: 10px 14px 8px 14px;
            border-top: 1px solid #e2e8f0;
        }

        .form-header {
            background-color: #2d4a77;
            color: white;
            padding: 6px 10px;
            border-radius: 3px;
            text-align: center;
            margin-bottom: 4px;
        }

        .form-header h1 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 900;
            line-height: 1.3;
        }

        .form-type-bar {
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
            padding: 5px 10px;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 900;
            color: #000000;
            border-radius: 3px;
            text-transform: uppercase;
            text-align: left;
        }

        .meta-bar {
            font-size: 11px;
            color: #475569;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .details-table {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }

        .details-table td {
            padding: 5px 8px;
            font-size: 12px;
            vertical-align: middle;
        }

        .label {
            font-weight: bold;
            color: #475569;
            white-space: nowrap;
        }

        .val-line {
            font-weight: 900;
            color: #0f172a;
        }

        .blank-field {
            border-bottom: 1px dashed #94a3b8;
            min-height: 18px;
            color: #64748b;
            font-weight: normal;
        }

        .info-strip {
            font-size: 11px;
            margin-bottom: 6px;
            padding: 5px 8px;
            border-radius: 3px;
            line-height: 1.4;
        }

        .info-strip-salary {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            font-weight: bold;
        }

        .info-strip-advance {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            color: #1e40af;
            font-weight: bold;
        }

        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            overflow: hidden;
        }

        .amount-table td {
            padding: 6px 8px;
            font-size: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        .amount-table tr:last-child td {
            border-bottom: none;
        }

        .amount-highlight {
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
        }

        .reason-row {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .reason-row td {
            padding: 3px 0;
            font-size: 12px;
            vertical-align: top;
        }

        .reason-line {
            border-bottom: 1px dashed #94a3b8;
            min-height: 18px;
            padding: 2px 4px;
            font-weight: bold;
            color: #0f172a;
        }

        .signature-table {
            width: 100%;
            margin-top: 16px;
            border-collapse: collapse;
        }

        .signature-table td {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            width: 33.33%;
            vertical-align: bottom;
            padding-top: 12px;
        }

        .sig-line {
            border-bottom: 1px solid #64748b;
            margin: 0 auto 5px auto;
            width: 88%;
            display: block;
            height: 24px;
        }

        .form-footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .form-footer-table td {
            font-size: 9px;
            color: #64748b;
            font-weight: bold;
            padding: 0;
            vertical-align: middle;
        }

        .form-footer-right {
            text-align: right;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="page-wrap">
        <table class="form-block">
            <tr>
                <td class="form-content-cell">
                    <div class="form-header">
                        <h1>{{ strtoupper($companyName ?? 'KIRAN INDUSTRIES PVT LTD.') }}</h1>
                    </div>

                    <div class="form-type-bar">FORM TYPE:- SALARY ADVANCE FORM</div>

                    <div class="meta-bar">
                        @if($isBlank)
                            Application No: <span class="blank-field">{{ $blankShort }}</span>
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            Application Date: <span class="blank-field">{{ $blankShort }}</span>
                        @else
                            Application No: <span class="val-line">#{{ $advance->id }}</span>
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            Application Date: <span class="val-line">{{ $advance->application_date?->format('d/m/Y') }}</span>
                            @if($advance->status)
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                Status: <span class="val-line">{{ strtoupper(str_replace('_', ' ', $advance->status)) }}</span>
                            @endif
                        @endif
                    </div>

                    <table class="details-table">
                        <tr>
                            <td class="label">Employee Name:</td>
                            <td style="width: 40%;">
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $advance->employee?->name }}</span>@endif
                            </td>
                            <td class="label" style="text-align: right;">Code No:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankShort }}</div>
                                @else<span class="val-line">{{ $advance->employee?->employee?->employee_id ?? $advance->employee?->employee?->emy_code }}</span>@endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Department:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $advance->employee?->employee?->department?->name ?? '—' }}</span>@endif
                            </td>
                            <td class="label" style="text-align: right;">Designation:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $advance->employee?->employee?->designation?->name ?? '—' }}</span>@endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Branch / Division:</td>
                            <td colspan="3">
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line">{{ $advance->branch?->name ?? $advance->employee?->employee?->branch?->name ?? '—' }}</span>@endif
                            </td>
                        </tr>
                    </table>

                    <div class="info-strip info-strip-salary">
                        @if($isBlank)
                            <strong>Present Salary:</strong> ₹ {{ $blankShort }}
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <strong>Earned Till Date:</strong> ₹ {{ $blankShort }}
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <strong>Allowed Advance:</strong> ₹ {{ $blankShort }}
                        @else
                            <strong>Present Salary:</strong> ₹ {{ number_format((float) $advance->present_salary_snapshot, 2) }}
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <strong>Earned Till Date:</strong> ₹ {{ number_format((float) $advance->earned_salary_snapshot, 2) }}
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <strong>Allowed Advance:</strong> ₹ {{ number_format((float) $advance->allowed_amount_snapshot, 2) }}
                        @endif
                    </div>

                    <table class="amount-table">
                        <tr>
                            <td class="label" style="width: 22%; background: #f8fafc;">Advance Amount:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="amount-highlight">₹ {{ number_format((float) ($advance->approved_amount ?? $advance->requested_amount), 2) }}</span>@endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="background: #f8fafc;">Amount in Words:</td>
                            <td>
                                @if($isBlank)<div class="blank-field">{{ $blankLine }}</div>
                                @else<span class="val-line" style="font-size: 11px;">{{ $advance->amount_in_words }}</span>@endif
                            </td>
                        </tr>
                    </table>

                    @unless($isBlank)
                        <div class="info-strip info-strip-advance">
                            <strong>Advance Requested:</strong> ₹ {{ number_format((float) $advance->requested_amount, 2) }}
                            @if($advance->approved_amount && (float) $advance->approved_amount !== (float) $advance->requested_amount)
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong>Approved:</strong> ₹ {{ number_format((float) $advance->approved_amount, 2) }}
                            @endif
                            @if((float) $advance->paid_amount > 0)
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong>Recovered:</strong> ₹ {{ number_format((float) $advance->paid_amount, 2) }}
                            @endif
                        </div>
                    @endunless

                    <table class="reason-row">
                        <tr>
                            <td style="width: 70px; font-weight: bold; color: #475569; white-space: nowrap;">Purpose:</td>
                            <td class="reason-line">@unless($isBlank){{ $advance->purpose }}@endunless</td>
                        </tr>
                    </table>

                    @if(!$isBlank && $advance->remarks)
                        <table class="reason-row">
                            <tr>
                                <td style="width: 70px; font-weight: bold; color: #475569; white-space: nowrap;">Remarks:</td>
                                <td class="reason-line">{{ $advance->remarks }}</td>
                            </tr>
                        </table>
                    @endif

                    @if($isBlank)
                        <table class="reason-row">
                            <tr>
                                <td style="width: 70px; font-weight: bold; color: #475569; white-space: nowrap;">Remarks:</td>
                                <td class="reason-line"></td>
                            </tr>
                        </table>
                    @endif

                    <table class="signature-table">
                        <tr>
                            <td><span class="sig-line"></span>Employee</td>
                            <td><span class="sig-line"></span>Department Head</td>
                            <td><span class="sig-line"></span>Approved By</td>
                        </tr>
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
