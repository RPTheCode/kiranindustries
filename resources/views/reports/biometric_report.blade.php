<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
    @include('reports.partials.header_css')
        
        table { width: 100%; border-collapse: collapse; background: white; table-layout: fixed; }
        th { 
            background-color: #1a365d; 
            color: white; 
            font-weight: bold; 
            text-align: center; 
            padding: 4px 1px; 
            border: 1px solid #1a365d; 
            font-size: 8px; 
            text-transform: uppercase; 
        }
        td { 
            padding: 4px 1px; 
            border: 1px solid #e2e8f0; 
            vertical-align: middle; 
            font-size: 8.5px; 
            color: #1e293b;
            text-align: center;
        }
        
        .group-header {
            background-color: #f8fafc;
            border-left: 5px solid #1a365d;
            padding: 6px 10px;
            margin-top: 10px;
            font-weight: 800;
            color: #1a365d;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
        }

        .row-alt { background-color: #f8fafc; }
        .cell-emp-name { text-align: left; padding-left: 3px; font-weight: 700; color: #1e293b; text-transform: uppercase; font-size: 8px; }
        .cell-dept { text-align: left; padding-left: 3px; font-size: 8px; }
        .cell-time { font-weight: bold; color: #1e293b; }
        .mis-punch { color: #dc2626; font-weight: 900; }
        .late { color: #d97706; font-weight: 900; }
        .early { color: #4b5563; font-weight: 900; }
        .ot { color: #059669; font-weight: 900; }
        .status-p { color: #059669; font-weight: 800; }
        .status-a { color: #dc2626; font-weight: 800; }
        .status-mis { color: #ea580c; font-weight: 800; }
        
        .grand-total {
            background-color: #1a365d;
            color: white;
            padding: 10px 15px;
            margin-top: 15px;
            font-weight: bold;
            font-size: 11px;
            text-align: right;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    @include('reports.partials.header')
    
    @foreach($reportData as $groupName => $rows)
        @php $sno = 1; @endphp
        <div class="group-header">{{ $groupName }}</div>
        
        <table>
            <thead>
                <tr>
                    <th style="width:3%;">No</th>
                    <th style="width:6%;">Date</th>
                    <th style="width:5%;">Code</th>
                    <th style="width:11%;">Name</th>
                    <th style="width:8%;">Dept</th>
                    <th style="width:7%;">Category</th>
                    <th style="width:4%;">Sft</th>
                    <th style="width:5%;">In</th>
                    <th style="width:5%;">Out</th>
                    <th style="width:5%;">Hrs</th>
                    <th style="width:4%;">OT</th>
                    <th style="width:4%;">Duty</th>
                    <th style="width:6%;">Status</th>
                    <th style="width:5%;">Late</th>
                    <th style="width:5%;">Erly</th>
                    <th style="width:4%;">Mis</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $sts = strtoupper($row['status'] ?? 'P');
                        $stsClass = match($sts) {
                            'P', 'HD', 'OD', 'CO' => 'status-p',
                            'A' => 'status-a',
                            'MIS' => 'status-mis',
                            default => '',
                        };
                    @endphp
                    <tr class="{{ $loop->index % 2 != 0 ? 'row-alt' : '' }}">
                        <td style="color: #64748b;">{{ $sno++ }}</td>
                        <td>{{ $row['date'] ?? '-' }}</td>
                        <td style="font-weight:700;">{{ $row['code'] ?? '-' }}</td>
                        <td class="cell-emp-name">
                            {{ $row['name'] ?? 'N/A' }}
                            @if(!empty($row['is_manual']) && ($row['status'] ?? '') !== 'A')
                                <span style="color:#4338ca;font-size:7px;font-weight:bold;">(M)</span>
                            @endif
                        </td>
                        <td class="cell-dept">{{ $row['department'] ?? '-' }}</td>
                        <td class="cell-dept">{{ $row['category'] ?? '-' }}</td>
                        <td>{{ $row['shift'] ?? '-' }}</td>
                        <td class="cell-time">{!! str_replace(' (M)', ' <span style="color:#4338ca;font-size:7px;font-weight:bold;">(M)</span>', $row['time_in'] ?? $row['in_time'] ?? '-') !!}</td>
                        <td class="cell-time">{!! str_replace(' (M)', ' <span style="color:#4338ca;font-size:7px;font-weight:bold;">(M)</span>', $row['time_out'] ?? $row['out_time'] ?? '-') !!}</td>
                        <td style="font-weight:bold;">{{ $row['hours'] ?? '-' }}</td>
                        <td class="ot">{{ (!empty($row['overtime']) && $row['overtime'] !== '0h 0m' && $row['overtime'] !== '-') ? $row['overtime'] : '-' }}</td>
                        <td style="font-weight:bold;">{{ $row['duty'] ?? '-' }}</td>
                        <td class="{{ $stsClass }}">{{ $row['status_label'] ?? ($row['status'] ?? '-') }}</td>
                        <td class="late">{{ ($row['late_in'] ?? 'ON TIME') !== 'ON TIME' ? $row['late_in'] : '-' }}</td>
                        <td class="early">{{ ($row['early_out'] ?? '-') !== '-' ? $row['early_out'] : '-' }}</td>
                        <td class="mis-punch">{{ ($row['mis_punch'] ?? '-') === 'YES' ? 'YES' : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if(!isset($is_chunk) || !$is_chunk || (isset($is_last_chunk) && $is_last_chunk))
    <div class="grand-total">
        GRAND TOTAL UNIQUE EMPLOYEES: {{ $global_total_employees ?? count($uniqueEmployees ?? []) }}
    </div>
    @endif
</body>
</html>
