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
            font-size: 8px;
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

        .dept-header {
            background-color: #f1f5f9;
            color: #1a365d;
            padding: 5px 12px;
            font-weight: 800;
            font-size: 9px;
            text-transform: uppercase;
            border-left: 5px solid #1a365d;
            margin: 10px 0 5px 0;
            border-bottom: 1px solid #cbd5e0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: white;
            border: 1px solid #cbd5e0;
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
            text-align: center;
            font-size: 7.5px;
            height: 20px;
            vertical-align: middle;
        }
        .name-cell {
            text-align: left !important;
            padding-left: 5px;
            font-weight: bold;
            width: 120px;
            border-right: 1px solid #cbd5e0;
            font-size: 8.5px;
        }
        td.name-cell { color: #1a365d; }
        .shift-total-row {
            background-color: #f8fafc;
            font-weight: bold;
            color: #1a365d;
        }
        .grand-total-row {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
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
        @php 
            $grandTotal = 0; 
            $grandDayTotals = [];
            foreach($dates as $date) { $grandDayTotals[$date] = 0; }
        @endphp

        @foreach($reportData as $shiftName => $employees)
            <div class="dept-header">SHIFT :=>> {{ $shiftName }}</div>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" class="name-cell" style="text-align: center;">DATE :==>></th>
                        @foreach($dates as $date)
                            <th>{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
                        @endforeach
                        <th rowspan="2">Total</th>
                    </tr>
                    <tr>
                        @foreach($dates as $date)
                            <th>{{ substr(\Carbon\Carbon::parse($date)->format('D'), 0, 1) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $shiftDayTotals = [];
                        foreach($dates as $date) { $shiftDayTotals[$date] = 0; }
                        $shiftEmpTotal = 0;
                    @endphp

                    @foreach($employees as $emp)
                        <tr>
                            <td class="name-cell">{{ $emp['code'] }}-{{ ucwords(strtolower($emp['name'])) }}</td>
                            @foreach($dates as $date)
                                @php 
                                    $val = $emp['attendance'][$date] ?? '-';
                                    if($val !== '-') {
                                        $shiftDayTotals[$date] += (float)$val;
                                        $grandDayTotals[$date] += (float)$val;
                                    }
                                @endphp
                                <td class="{{ $val === '1.0' || $val === '1' ? 'bold' : '' }}">
                                    {{ $val }}
                                </td>
                            @endforeach
                            <td style="font-weight: bold;">{{ number_format($emp['total_pr'], 1) }}</td>
                            @php $shiftEmpTotal += (float)$emp['total_pr']; @endphp
                        </tr>
                    @endforeach

                    <tr class="shift-total-row">
                        <td class="name-cell">SHIFT TOTAL:=></td>
                        @foreach($dates as $date)
                            <td>{{ $shiftDayTotals[$date] > 0 ? number_format($shiftDayTotals[$date], 1) : '' }}</td>
                        @endforeach
                        <td>{{ number_format($shiftEmpTotal, 1) }}</td>
                    </tr>
                </tbody>
            </table>
            @php $grandTotal += $shiftEmpTotal; @endphp
        @endforeach

        <table style="margin-top: 10px; border: 2px solid #1a365d;">
            <tr class="grand-total-row">
                <td class="name-cell" style="font-size: 8px; color: white;">GRAND SHIFT TOTAL:=</td>
                @foreach($dates as $date)
                    <td>{{ $grandDayTotals[$date] > 0 ? number_format($grandDayTotals[$date], 1) : '' }}</td>
                @endforeach
                <td style="font-size: 8px;">{{ number_format($grandTotal, 1) }}</td>
            </tr>
        </table>
    </div>

</body>
</html>
