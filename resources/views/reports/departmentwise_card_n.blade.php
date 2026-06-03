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
        .status-left { float: left; width: 100%; text-align: left; }
        .clearfix::after { content: ""; clear: both; display: table; }

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
        th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 3px 1px;
            border: 1px solid #cbd5e0;
            font-size: 7px;
            text-transform: uppercase;
        }
        td {
            padding: 3px 1px;
            border: 1px solid #edf2f7;
            vertical-align: middle;
            font-size: 8px;
            text-align: center;
            height: {{ ($hourlyType === 'T' || $hourlyType === 'Y') ? '35px' : '22px' }};
        }
        .col-emp {
            width: 16%;
            text-align: left !important;
            padding-left: 4px;
            font-weight: bold;
            border-right: 1px solid #cbd5e0;
            font-size: 8.5px;
            line-height: 1.0;
        }
        td.col-emp { color: #1a365d; }
        .status-p { color: #15803d; font-weight: bold; }
        .status-a { color: #dc2626; font-weight: bold; }
        .col-day { width: 2.5%; }
        .col-stat {
            width: 3.2%;
            font-weight: bold;
            background-color: #f8fafc;
            color: #1a365d;
            border-left: 1px solid #cbd5e0;
            font-size: 7.5px;
        }
        .row-alt { background-color: #fcfcfc; }

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
                        <th style="width: 4%;">No.</th>
                        <th class="col-emp">Name</th>
                        @foreach($dates as $date)
                            <th class="col-day">{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
                        @endforeach
                        <th class="col-stat">PR</th>
                        <th class="col-stat">P.I</th>
                        <th class="col-stat">TOT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $index => $row)
                        <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                            <td style="width: 4%;">{{ $row['sr'] }}</td>
                            <td class="col-emp">{{ $row['code'] ?? $row['emy_code'] ?? 'N/A' }}-{{ ucwords(strtolower($row['name'] ?? $row['user']['name'] ?? 'N/A')) }}</td>
                            @foreach($dates as $date)
                                @php
                                    $val = $row['attendance'][$date] ?? '-';
                                    $isMis = $row['attendance_mis'][$date] ?? false;
                                    $isPi = $row['attendance_pi'][$date] ?? false;
                                    $misCellStyle = $isMis ? 'color: #dc2626; font-weight: 900;' : '';
                                @endphp
                                <td style="{{ $isPi ? 'background-color: #e0f2fe; border: 1px solid #bae6fd;' : ($isMis ? 'background-color: #fff5f5;' : '') }}">
                                    @if($isMis && ($cardType === 'A' || $hourlyType === 'A'))
                                        {{-- MIS in P/A mode: show MIS in red --}}
                                        <span style="color: #dc2626; font-weight: 900; font-size: 7px;">{{ $val }}</span>
                                    @elseif((str_contains($val, 'P') || str_contains($val, 'A')) && ($cardType === 'A' || $hourlyType === 'A'))
                                        <span class="{{ str_contains($val, 'P') ? 'status-p' : 'status-a' }}">{{ $val != '-' ? $val : '' }}</span>
                                    @elseif($isMis && $hourlyType !== 'T')
                                        {{-- MIS in hourly/numeric/inout mode: red --}}
                                        <span style="{{ $misCellStyle }}">{{ $val != '-' ? $val : '' }}</span>
                                    @elseif($hourlyType === 'N' || $hourlyType === 'Y')
                                        <span style="{{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">{{ $val != '-' ? $val : '' }}</span>
                                    @elseif($hourlyType === 'T')
                                        <div style="font-size: 7px !important; line-height: 1.0; font-family: 'Arial Narrow', sans-serif; font-weight: bold; {{ $isPi ? 'color: #0369a1; font-weight: 900;' : $misCellStyle }}">
                                            {!! str_replace('/', '<br>', $val) !!}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="col-stat">{{ number_format($row['total_pr'], 1) }}</td>
                            <td class="col-stat">{{ isset($row['total_pi']) && $row['total_pi'] > 0 ? number_format($row['total_pi'], 1) : '' }}</td>
                            <td class="col-stat">{{ number_format($row['total_tot'] ?? $row['total_pr'], 1) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="{{ 2 + count($dates) + 3 }}"
                            style="text-align: right; font-weight: bold; padding-right: 10px; background-color: #f8fafc; color: #1a365d;">
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