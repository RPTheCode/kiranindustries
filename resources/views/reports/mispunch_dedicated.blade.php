<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @include('reports.partials.header_css')

        table.main-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            table-layout: fixed;
            margin-top: 2px;
        }

        table.main-table th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 5px 2px;
            border: 1px solid #1a365d;
            font-size: 10px;
            text-transform: uppercase;
        }

        table.main-table > tbody > tr > td {
            padding: 4px 3px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 10px;
            color: #1e293b;
            text-align: center;
        }

        table.pairs-mini {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 9px;
        }

        table.pairs-mini th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 800;
            padding: 3px 2px;
            border: 1px solid #e2e8f0;
            font-size: 8px;
            text-transform: uppercase;
        }

        table.pairs-mini td {
            padding: 3px 2px;
            border: 1px solid #e2e8f0;
            text-align: center;
            vertical-align: middle;
        }

        table.pairs-mini tr.pair-ok td { background-color: #f0fdf4; }
        table.pairs-mini tr.pair-bad td { background-color: #fef2f2; }

        .group-header {
            background-color: #fef2f2;
            border-left: 5px solid #dc2626;
            padding: 6px 10px;
            margin-top: 8px;
            font-weight: 800;
            color: #991b1b;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
        }

        .group-badge {
            float: right;
            background-color: #dc2626;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
        }

        .row-alt > td { background-color: #fafafa; }
        .cell-name { text-align: left; padding-left: 4px; font-weight: 700; color: #1e293b; text-transform: uppercase; }
        .cell-code { font-weight: 700; color: #1a365d; }
        .cell-missing { color: #dc2626; font-weight: 900; }
        .cell-ok { color: #15803d; font-weight: 800; font-size: 8px; }
        .cell-bad { color: #c2410c; font-weight: 800; font-size: 8px; text-align: left; padding-left: 3px; }
        .badge-misp { background-color: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 900; border: 1px solid #fca5a5; }
        .pair-arrow { color: #94a3b8; font-weight: 700; padding: 0 2px; }

        .grand-total {
            background-color: #1a365d;
            color: white;
            padding: 8px 12px;
            margin-top: 12px;
            font-weight: bold;
            font-size: 11px;
            text-align: right;
            border-radius: 4px;
        }

        .summary-note {
            margin-top: 6px;
            padding: 6px 10px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            font-size: 9px;
            color: #9a3412;
            text-align: left;
        }

        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    @include('reports.partials.header')

    <div class="summary-note">
        <strong>How to read:</strong> Each row shows one day. The <strong>IN → OUT Pairs</strong> table lists every shift segment.
        Green rows = complete pair. Red rows = missing IN or OUT — fill the blank time in MisPunch module.
        <br><strong>Note:</strong> Today's date is excluded — employees may still be on duty.
    </div>

    @php
        $globalSr = 1;
        $groupLabel = (isset($reportType) && strtoupper($reportType) === 'DEPARTMENT') ? 'DEPARTMENT' : 'EMPLOYEE';
    @endphp

    @foreach($reportData as $groupName => $rows)
        <div class="group-header clearfix">
            <span>{{ $groupLabel }}: {{ strtoupper($groupName) }}</span>
            <span class="group-badge">RECORDS: {{ count($rows) }}</span>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 8%;">Date</th>
                    <th style="width: 7%;">Code</th>
                    <th style="width: 13%;">Employee</th>
                    <th style="width: 10%;">Dept</th>
                    <th style="width: 5%;">Sft</th>
                    <th style="width: 47%;">IN → OUT Pairs</th>
                    <th style="width: 6%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $pairs = $row['punch_pairs'] ?? [];
                        if (empty($pairs)) {
                            $inVal = $row['time_in'] ?? $row['in_time'] ?? null;
                            $outVal = $row['time_out'] ?? $row['out_time'] ?? null;
                            $pairs = [[
                                'num' => 1,
                                'in' => ($inVal && $inVal !== '---') ? $inVal : null,
                                'out' => ($outVal && $outVal !== '---') ? $outVal : null,
                                'complete' => ($inVal && $inVal !== '---' && $outVal && $outVal !== '---'),
                                'issue' => (!$inVal || $inVal === '---') ? 'Missing IN' : ((!$outVal || $outVal === '---') ? 'Missing OUT' : null),
                            ]];
                        }
                        $badCount = count(array_filter($pairs, fn ($p) => empty($p['complete'])));
                    @endphp
                    <tr class="{{ $loop->index % 2 != 0 ? 'row-alt' : '' }}">
                        <td style="color: #64748b; font-size: 9px;">{{ str_pad($globalSr++, 2, '0', STR_PAD_LEFT) }}</td>
                        <td style="font-weight: 700;">{{ $row['date'] ?? '-' }}</td>
                        <td class="cell-code">{{ $row['code'] ?? '-' }}</td>
                        <td class="cell-name">{{ $row['name'] ?? 'N/A' }}</td>
                        <td style="font-size: 9px;">{{ $row['department'] ?? '-' }}</td>
                        <td style="font-size: 9px;">{{ $row['shift'] ?? '-' }}</td>
                        <td style="padding: 3px;">
                            <table class="pairs-mini">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">#</th>
                                        <th style="width: 22%;">IN</th>
                                        <th style="width: 4%;"></th>
                                        <th style="width: 22%;">OUT</th>
                                        <th style="width: 44%;">Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pairs as $pair)
                                        <tr class="{{ !empty($pair['complete']) ? 'pair-ok' : 'pair-bad' }}">
                                            <td style="font-weight: 700; color: #64748b;">{{ $pair['num'] ?? $loop->iteration }}</td>
                                            <td class="{{ empty($pair['in']) ? 'cell-missing' : '' }}">
                                                {{ !empty($pair['in']) ? $pair['in'] : '—' }}
                                            </td>
                                            <td class="pair-arrow">→</td>
                                            <td class="{{ empty($pair['out']) ? 'cell-missing' : '' }}">
                                                {{ !empty($pair['out']) ? $pair['out'] : '—' }}
                                            </td>
                                            <td class="{{ !empty($pair['complete']) ? 'cell-ok' : 'cell-bad' }}">
                                                @if(!empty($pair['complete']))
                                                    OK
                                                @else
                                                    {{ $pair['issue'] ?? 'Incomplete' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($badCount > 0)
                                <div style="font-size: 8px; color: #c2410c; font-weight: 800; text-align: left; margin-top: 2px; padding-left: 2px;">
                                    {{ $badCount }} incomplete pair{{ $badCount > 1 ? 's' : '' }} need correction
                                </div>
                            @endif
                        </td>
                        <td><span class="badge-misp">MISP</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if(!isset($is_chunk) || !$is_chunk || (isset($is_last_chunk) && $is_last_chunk))
        <div class="grand-total">
            TOTAL MISPUNCH RECORDS: {{ $totalRecords ?? 0 }}
            &nbsp;|&nbsp;
            UNIQUE EMPLOYEES: {{ $global_total_employees ?? count($uniqueEmployees ?? []) }}
        </div>
    @endif
</body>
</html>
