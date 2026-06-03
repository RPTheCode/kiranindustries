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
            font-size: 10px;
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

        /* Table Design */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #cbd5e0;
            background: white;
        }

        th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 4px 0;
            border: 1px solid #cbd5e0;
            font-size: 7px;
            text-transform: uppercase;
        }

        td {
            padding: 4px 0;
            border: 1px solid #edf2f7;
            vertical-align: middle;
            font-size: 8.5px;
            text-align: center;
            height: 26px;
        }

        .status-p {
            color: #15803d;
            font-weight: bold;
        }

        .status-a {
            color: #dc2626;
            font-weight: bold;
        }

        .col-emp {
            width: 15%;
            text-align: left !important;
            padding-left: 3px;
            font-weight: bold;
            border-right: 1px solid #cbd5e0;
            font-size: 8.5px;
            line-height: 1.0;
        }

        td.col-emp {
            color: #1a365d;
        }

        .col-day {
            width: 2.6%;
        }

        .col-stat {
            width: 3.0%;
            font-weight: bold;
            border-left: 1px solid #cbd5e0;
            font-size: 7.5px;
        }

        td.col-stat {
            background-color: #f8fafc;
        }

        .total-row td {
            font-weight: bold;
            background-color: #edf2f7;
            color: #2d3748;
            border-top: 1px solid #cbd5e0;
            font-size: 8.5px;
        }

        .row-alt {
            background-color: #fcfcfc;
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
        <table>
            <thead>
                <tr>
                    <th style="width: 4%;">No.</th>
                    <th class="col-emp" style="text-align: right !important;">DATE :==>></th>
                    @foreach($dates as $date)
                        <th class="col-day">{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
                    @endforeach
                    <th class="col-stat">Pr</th>
                    <th class="col-stat">P.I</th>
                    <th class="col-stat">TOT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reportData as $index => $row)
                    <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                        <td style="width: 4%;">{{ $row['sr'] }}</td>
                        <td class="col-emp">
                            {{ $row['code'] ?? $row['emy_code'] ?? 'N/A' }}-{{ ucwords(strtolower($row['name'] ?? $row['user']['name'] ?? 'N/A')) }}
                        </td>
                        @foreach($dates as $date)
                            @php
                                $val = $row['attendance'][$date] ?? '-';
                                $isMis = $row['attendance_mis'][$date] ?? false;
                                $isPi = $row['attendance_pi'][$date] ?? false;
                                $misCellStyle = $isMis ? 'color: #dc2626; font-weight: 900;' : '';
                            @endphp
                            <td style="{{ $isPi ? 'background-color: #e0f2fe; border: 1px solid #bae6fd;' : ($isMis ? 'background-color: #fff5f5;' : '') }}">
                                @if($val !== '-')
                                    @if($isMis && ($cardType === 'A' || $hourlyType === 'A'))
                                        {{-- MIS in P/A mode: show MIS in red --}}
                                        <span style="color: #dc2626; font-weight: 900; font-size: 7px;">{{ $val }}</span>
                                    @elseif((str_starts_with($val, 'P') || str_starts_with($val, 'A')) && ($cardType === 'A' || $hourlyType === 'A'))
                                        <span class="{{ str_starts_with($val, 'P') ? 'status-p' : 'status-a' }}">{{ $val }}</span>
                                    @elseif($hourlyType === 'T')
                                        <div style="font-size: 7px !important; line-height: 1.0; font-family: 'Arial Narrow', sans-serif; font-weight: bold; {{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">
                                            {!! str_replace('/', '<br>', $val) !!}
                                        </div>
                                    @else
                                        <span style="{{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">{{ $val }}</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                        <td class="col-stat">{{ number_format($row['total_pr'], 1) }}</td>
                        <td class="col-stat">{{ isset($row['total_pi']) && $row['total_pi'] > 0 ? number_format($row['total_pi'], 1) : '' }}</td>
                        <td class="col-stat">{{ number_format($row['total_tot'] ?? $row['total_pr'], 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="col-emp" style="text-align: right !important;">TOTAL :==>></td>
                    @foreach($dates as $date)
                        <td>{{ $dayTotals[$date] > 0 ? $dayTotals[$date] : '' }}</td>
                    @endforeach
                    <td class="col-stat"></td>
                    <td class="col-stat"></td>
                    <td class="col-stat"></td>
                </tr>
            </tfoot>
        </table>
    </div>

</body>

</html>