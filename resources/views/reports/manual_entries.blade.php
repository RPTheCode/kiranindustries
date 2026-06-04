<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @include('reports.partials.header_css')

        /* Manual Entries specific styles */
        .content-section { margin-top: 10px; }
        .department-header { background-color: #1a365d; color: white; padding: 4px 10px; font-weight: bold; font-size: 10px; margin-top: 10px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { background-color: #f8fafc; color: #1a365d; font-weight: bold; text-align: left; padding: 4px; border: 1px solid #e2e8f0; text-transform: uppercase; font-size: 8px; }
        td { padding: 4px; border: 1px solid #e2e8f0; font-size: 8px; }
        .row-alt { background-color: #f8fafc; }
        .col-no { width: 3%; text-align: center; }
        .col-date { width: 8%; }
        .col-code { width: 8%; }
        .col-name { width: 25%; }
        .col-time { width: 7%; text-align: center; }
        .col-status { width: 8%; text-align: center; }
        .col-manual-by { width: 10%; }
        .col-remarks { width: 20%; }
        .text-blue { color: #2563eb; font-weight: bold; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    @include('reports.partials.header')

    <div class="content-section">
        @foreach($reportData as $groupName => $rows)
            @php
                $hasManual = collect($rows)->contains('is_manual', true);
            @endphp
            @if(!$hasManual)
                @continue
            @endif

            <div class="department-header">
                DEPARTMENT: {{ strtoupper($groupName) }}
            </div>
            <table>
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-date">Date</th>
                        <th class="col-code">Code</th>
                        <th class="col-name">Employee Name</th>
                        <th class="col-time">In</th>
                        <th class="col-time">Out</th>
                        <th class="col-status">Status</th>
                        <th class="col-manual-by">Manually By</th>
                        <th class="col-remarks">Remarks/Manual Info</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sr = 1; @endphp
                    @foreach($rows as $row)
                        @if($row['is_manual'])
                            <tr class="{{ $sr % 2 == 0 ? 'row-alt' : '' }}">
                                <td class="col-no">{{ $sr++ }}</td>
                                <td class="col-date">{{ $row['date'] }}</td>
                                <td class="col-code">{{ $row['code'] }}</td>
                                <td class="col-name">{{ $row['name'] }}</td>
                                <td class="col-time">{!! str_replace(' (M)', ' <span style="color: #4338ca; font-weight: bold;">(M)</span>', $row['in_time'] ?: '-') !!}</td>
                                <td class="col-time">{!! str_replace(' (M)', ' <span style="color: #4338ca; font-weight: bold;">(M)</span>', $row['out_time'] ?: '-') !!}</td>
                                <td class="col-status text-blue">{{ $row['status'] }} <span style="color: #4338ca; font-weight: bold;">(M)</span></td>
                                <td class="col-manual-by">{{ $row['manual_by_name'] ?? '-' }}</td>
                                <td class="col-remarks">{{ $row['manual_remarks'] ?: '-' }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
</body>
</html>
