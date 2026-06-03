<!DOCTYPE html>
<html>

<head>
    <title>MISSED PUNCH FORM</title>
    <style>
        @page {
            margin: 8mm 10mm;
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
            padding: 0;
            background: #ffffff;
        }

        /* A4 usable height ≈ 281mm — split equally for 2 forms */
        .page-table {
            width: 100%;
            height: 281mm;
            border-collapse: collapse;
            page-break-after: always;
            table-layout: fixed;
        }

        .page-table:last-child {
            page-break-after: auto;
        }

        .form-row {
            height: 50%;
            vertical-align: top;
        }

        .form-row-gap td {
            height: 2mm;
            padding: 0;
            line-height: 0;
            font-size: 0;
        }

        .form-cell {
            vertical-align: top;
            padding: 0;
            height: 139mm;
        }

        .form-block {
            width: 100%;
            height: 139mm;
            border: 1px solid #cbd5e1;
            border-left: 5px solid #2d4a77;
            border-radius: 4px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .form-content-cell {
            vertical-align: top;
            padding: 7px 12px 8px 12px;
        }

        .form-footer-cell {
            vertical-align: bottom;
            padding: 12px 12px 7px 12px;
            border-top: 1px solid #e2e8f0;
            height: 1%;
        }

        .form-header {
            background-color: #2d4a77;
            color: white;
            padding: 5px 8px;
            border-radius: 3px;
            text-align: center;
            margin-bottom: 3px;
        }

        .form-header h1 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 900;
            line-height: 1.3;
        }

        .form-type-bar {
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
            padding: 4px 8px;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 900;
            color: #000000;
            border-radius: 3px;
            text-transform: uppercase;
            text-align: left;
        }

        .details-table {
            width: 100%;
            margin-bottom: 6px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }

        .details-table td {
            padding: 4px 7px;
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

        .info-strip {
            font-size: 11px;
            margin-bottom: 4px;
            padding: 3px 7px;
            border-radius: 3px;
            line-height: 1.35;
        }

        .info-strip-issues {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            font-weight: bold;
        }

        .info-strip-punches {
            background: #f1f5f9;
            color: #334155;
        }

        .pairs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .pairs-table th {
            background: #e2e8f0;
            font-size: 11px;
            text-transform: uppercase;
            padding: 4px 7px;
            text-align: left;
            color: #475569;
        }

        .pairs-table td {
            padding: 4px 7px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            vertical-align: middle;
        }

        .punch-val {
            display: inline-block;
            min-width: 52px;
            min-height: 14px;
            border-bottom: 1px dashed #94a3b8;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            color: #0f172a;
            padding: 1px 4px;
        }

        .punch-val-filled {
            border-bottom: none;
            color: #2563eb;
        }

        .reason-row {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }

        .reason-row td {
            padding: 2px 0;
            font-size: 12px;
            vertical-align: bottom;
        }

        .reason-line {
            border-bottom: 1px dashed #94a3b8;
            height: 16px;
        }

        .signature-table {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
        }

        .signature-table td {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            width: 25%;
            vertical-align: bottom;
            padding-top: 10px;
        }

        .sig-line {
            border-bottom: 1px solid #64748b;
            margin: 0 auto 5px auto;
            width: 88%;
            display: block;
            height: 22px;
        }

        .form-footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
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
    </style>
</head>

<body>

    @foreach(collect($reportData)->chunk(2) as $pageForms)
        <table class="page-table">
            @foreach($pageForms as $rowIndex => $row)
                @if($rowIndex === 1)
                    <tr class="form-row-gap"><td colspan="1"></td></tr>
                @endif
                <tr class="form-row">
                    <td class="form-cell">
                        <table class="form-block">
                            <tr>
                                <td class="form-content-cell">
                                    <div class="form-header">
                                        <h1>{{ strtoupper($companyName ?? 'KIRAN INDUSTRIES PVT LTD.') }}</h1>
                                    </div>

                                    <div class="form-type-bar">FORM TYPE:- MISPUNCH FORM</div>

                                    <table class="details-table">
                                        <tr>
                                            <td class="label">Employee Name:</td>
                                            <td class="val-line" style="width: 42%;">{{ $row['name'] }}</td>
                                            <td class="label" style="text-align: right;">Code No:</td>
                                            <td class="val-line">{{ $row['code'] }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">Department:</td>
                                            <td class="val-line">{{ $row['dept'] }}</td>
                                            <td class="label" style="text-align: right;">Designation:</td>
                                            <td class="val-line">{{ $row['designation'] }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">Date of Mispunch:</td>
                                            <td class="val-line" colspan="3">{{ $row['date'] }}</td>
                                        </tr>
                                    </table>

                                    @if(!empty($row['issues_text']))
                                        <div class="info-strip info-strip-issues">
                                            Fix required: {{ $row['issues_text'] }}
                                        </div>
                                    @endif

                                    <div class="info-strip info-strip-punches">
                                        <strong>Device punches:</strong> {{ $row['punches_text'] }}
                                    </div>

                                    @if($row['is_multiple'])
                                        <table class="pairs-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 7%;">#</th>
                                                    <th style="width: 20%;">Clock IN</th>
                                                    <th style="width: 20%;">Clock OUT</th>
                                                    <th style="width: 53%;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($row['punch_pairs'] as $pairIdx => $pair)
                                                    @php
                                                        $pairNum = $pairIdx + 1;
                                                        $missingIn = empty($pair['in']);
                                                        $missingOut = empty($pair['out']);
                                                        $outNextDay = !empty($pair['out_next_day']);
                                                        $sameTime = !$missingIn && !$missingOut && $pair['in'] === $pair['out'];
                                                        $statusText = 'OK';
                                                        if ($missingIn && $missingOut) {
                                                            $statusText = 'Empty pair';
                                                        } elseif ($missingIn) {
                                                            $statusText = 'MISSING IN — fill below';
                                                        } elseif ($missingOut && !empty($pair['pending_next_day'])) {
                                                            $statusText = 'OUT pending — next day duty not started';
                                                        } elseif ($missingOut) {
                                                            $statusText = 'MISSING OUT — fill below';
                                                        } elseif ($sameTime) {
                                                            $statusText = 'Same IN/OUT time — correct OUT';
                                                        } elseif ($outNextDay) {
                                                            $statusText = 'OK (OUT next day)';
                                                        }
                                                    @endphp
                                                    <tr>
                                                        <td><strong>{{ $pairNum }}</strong></td>
                                                        <td>
                                                            <span class="punch-val {{ $missingIn ? '' : 'punch-val-filled' }}">
                                                                {{ $missingIn ? '' : $pair['in'] }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="punch-val {{ $missingOut ? '' : 'punch-val-filled' }}">
                                                                @if(!$missingOut)
                                                                    {{ $pair['out'] }}@if($outNextDay) <span style="font-size:9px;color:#64748b;">(+1 day)</span>@endif
                                                                @endif
                                                            </span>
                                                        </td>
                                                        <td style="font-size: 11px; color: {{ ($missingIn || ($missingOut && empty($pair['pending_next_day'])) || $sameTime) ? '#dc2626' : (!empty($pair['pending_next_day']) ? '#b45309' : '#059669') }};">
                                                            {{ $statusText }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <table class="pairs-table">
                                            <tr>
                                                <td style="width: 18%; font-weight: bold; color: #475569; font-size: 12px;">From In Time:</td>
                                                <td style="width: 22%;">
                                                    <span class="punch-val {{ ($row['in_time'] && $row['in_time'] !== '---') ? 'punch-val-filled' : '' }}">
                                                        {{ ($row['in_time'] && $row['in_time'] !== '---') ? $row['in_time'] : '' }}
                                                    </span>
                                                </td>
                                                <td style="width: 18%; font-weight: bold; color: #475569; text-align: right; font-size: 12px;">To Out Time:</td>
                                                <td>
                                                    <span class="punch-val {{ ($row['out_time'] && $row['out_time'] !== '---') ? 'punch-val-filled' : '' }}">
                                                        {{ ($row['out_time'] && $row['out_time'] !== '---') ? $row['out_time'] : '' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    @endif

                                    <table class="reason-row">
                                        <tr>
                                            <td style="width: 115px; font-weight: bold; color: #475569; white-space: nowrap;">Reason for Mispunch:</td>
                                            <td class="reason-line"></td>
                                        </tr>
                                    </table>

                                    <table class="signature-table">
                                        <tr>
                                            <td><span class="sig-line"></span>Employee Sign</td>
                                            <td><span class="sig-line"></span>Signature of HOD</td>
                                            <td><span class="sig-line"></span>Signature of HRD</td>
                                            <td><span class="sig-line"></span>Approved BY</td>
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
                    </td>
                </tr>
            @endforeach
        </table>
    @endforeach

</body>

</html>
