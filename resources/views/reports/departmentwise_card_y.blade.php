<!DOCTYPE html>
<html>

<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 72px 0.3cm 35px 0.3cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7.5px;
            color: #333;
            line-height: 1.1;
            margin: 0;
            padding: 0;
        }

        /* Fixed Header Logic */
        header {
            position: fixed;
            top: -62px;
            left: 0;
            right: 0;
            height: 60px;
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
            font-size: 16px;
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
            font-size: 8px;
            font-weight: 800;
            color: #1a365d;
        }

        .status-left {
            float: left;
            width: 100%;
            text-align: left;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Department Header Style */
        .dept-header {
            background-color: #f1f5f9;
            color: #1a365d;
            padding: 6px 12px;
            font-weight: 800;
            font-size: 9px;
            text-transform: uppercase;
            border-left: 5px solid #1a365d;
            margin: 10px 0 5px 0;
            border-bottom: 1px solid #cbd5e0;
        }

        /* Table Design */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #cbd5e0;
            background: white;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #cbd5e0;
            padding: 4px 2px;
            text-align: center;
            font-size: 7.5px;
        }

        th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7px;
        }

        .col-sr {
            width: 20px;
        }

        .col-code {
            width: 35px;
        }

        .col-name {
            width: 110px;
            text-align: left !important;
            padding-left: 4px;
            font-size: 8.5px;
        }

        td.col-name {
            font-weight: bold;
            color: #1a365d;
        }

        .col-father {
            width: 90px;
            text-align: left !important;
            padding-left: 4px;
            font-size: 8px;
        }

        .col-desg {
            width: 85px;
            text-align: left !important;
            padding-left: 4px;
            font-size: 8px;
        }

        .col-date {
            width: 18px;
        }

        .col-total {
            width: 35px;
            font-weight: bold;
        }

        td.col-total {
            background-color: #f8fafc;
            color: #1a365d;
        }

        .col-lwkd {
            width: 30px;
            font-weight: bold;
        }

        td.col-lwkd {
            background-color: #f8fafc;
            color: #1a365d;
        }

        .row-alt {
            background-color: #fcfcfc;
        }

        .status-p {
            color: #15803d;
            font-weight: bold;
        }

        .status-a {
            color: #dc2626;
            font-weight: bold;
        }

        .dept-total-row td {
            background-color: #f8fafc;
            color: #1a365d;
            font-weight: bold;
            text-align: right !important;
            padding-right: 15px;
            font-size: 9px;
        }

        .grand-total-box {
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #1a365d;
            color: white;
            text-align: right;
            font-size: 12px;
            font-weight: 900;
            border-radius: 2px;
            letter-spacing: 1px;
        }
    </style>
</head>

<body>
    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportTitle) }} | PERIOD: {{ $period }}
            </div>
        </div>
    </header>

    <div class="content-section" style="margin-top: 10px;">
        @php $grandTotal = 0; @endphp
        @foreach($reportData as $deptName => $rows)
            @php $grandTotal += count($rows); @endphp
            <div class="dept-header">DEPARTMENT: {{ strtoupper($deptName) }}</div>
            <table>
                <thead>
                    <tr>
                        <th class="col-sr">No.</th>
                        <th class="col-code">Code</th>
                        <th class="col-name">Name</th>
                        <th class="col-father">Father's Name</th>
                        <th class="col-desg">Designation</th>
                        @foreach($visibleDates as $date)
                            <th class="col-date">{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
                        @endforeach
                        <th class="col-total">Total</th>
                        <th class="col-lwkd">L.Wkd</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $index => $row)
                        <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                            <td>{{ $row['sr'] }}</td>
                            <td>{{ $row['code'] ?? $row['emy_code'] ?? 'N/A' }}</td>
                            <td class="col-name">{{ ucwords(strtolower($row['name'] ?? $row['user']['name'] ?? 'N/A')) }}</td>
                            <td class="col-father">{{ ucwords(strtolower($row['father_name'] ?? '-')) }}</td>
                            <td class="col-desg">{{ ucwords(strtolower($row['designation'] ?? '-')) }}</td>
                            @foreach($visibleDates as $vd)
                                @php
                                    $val = $row['attendance'][$vd] ?? '-';
                                    $isMis = $row['attendance_mis'][$vd] ?? false;
                                    $isPi = $row['attendance_pi'][$vd] ?? false;
                                    $misCellStyle = $isMis ? 'color: #dc2626; font-weight: 900;' : '';
                                @endphp
                                <td style="{{ $isPi ? 'background-color: #e0f2fe; border: 1px solid #bae6fd;' : '' }}">
                                    @if($val !== '-')
                                        @if(str_contains($val, 'MIS'))
                                            <span style="color: #dc2626; font-weight: 900; font-size: 7px;">{{ $val }}</span>
                                        @elseif(str_contains($val, 'P') || str_contains($val, 'A'))
                                            <span class="{{ str_contains($val, 'P') ? 'status-p' : 'status-a' }}">{{ $val }}</span>
                                        @elseif($hourlyType === 'T')
                                            <div style="font-size: 7px !important; line-height: 1.0; font-family: 'Arial Narrow', sans-serif; font-weight: bold; {{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">
                                                {!! str_replace('/', '<br>', $val) !!}
                                            </div>
                                        @elseif($hourlyType === 'Y')
                                            <div style="font-size: 6px !important; line-height: 1.1; font-weight: bold; {{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">
                                                {!! str_replace('/', '<br>', $val) !!}
                                            </div>
                                        @else
                                            <span style="{{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">{{ $val }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td class="col-total">{{ number_format($row['total_tot'], 2) }}</td>
                            <td class="col-lwkd">{{ $row['l_wkd'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="dept-total-row">
                        <td colspan="{{ 7 + count($visibleDates) }}">
                            TOTAL IN {{ strtoupper($deptName) }}: {{ count($rows) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <div class="grand-total-box">
            GRAND TOTAL : {{ $grandTotal }}
        </div>
    </div>

</body>

</html>