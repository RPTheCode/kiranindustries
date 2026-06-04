<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @page { 
            margin: 110px 0.3cm 1cm 0.3cm; 
            size: A4 portrait; 
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
            top: -100px;
            left: 0px;
            right: 0px;
            height: 100px;
            text-align: center;
        }
        
        /* Premium Blue Header Bar */
        .main-header { 
            background-color: #1a365d; 
            color: white; 
            padding: 8px; 
            text-align: center; 
            margin-bottom: 0px;
        }
        .main-header h1 { 
            margin: 0; 
            font-size: 14px; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            font-weight: 900; 
        }
        
        /* Sub Header Title */
        .sub-header {
            background-color: #2d4a77;
            color: white;
            padding: 4px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Status Bar (Grey) */
        .status-bar { 
            background-color: #f1f5f9; 
            border: 1px solid #cbd5e0; 
            padding: 5px 10px; 
            margin: 4px 0; 
            font-size: 8px; 
            font-weight: 800;
            color: #1a365d;
        }
        .status-left { float: left; width: 60%; text-align: left; }
        .status-right { float: right; width: 40%; text-align: right; }
        .clearfix::after { content: ""; clear: both; display: table; }
        
        /* Premium Department Banner */
        .dept-banner { 
            background-color: #edf2f7; 
            padding: 3px 6px; 
            border-left: 3px solid #1a365d; 
            margin-top: 6px; 
            margin-bottom: 2px; 
            font-weight: bold; 
            font-size: 9px; 
            color: #1a365d; 
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; background: white; }
        th { 
            background-color: #f8fafc; 
            color: #1a365d; 
            font-weight: bold; 
            text-align: left; 
            padding: 2px 1px; 
            border: 1px solid #cbd5e0; 
            font-size: 7px; 
            text-transform: uppercase; 
        }
        td { 
            padding: 2px 1px; 
            border: 1px solid #edf2f7; 
            vertical-align: middle; 
            font-size: 7.5px; 
            text-align: center;
        }
        
        .row-alt { background-color: #fcfcfc; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .cell-code { color: #1a365d; font-weight: bold; }
        
        .col-sr { width: 22px; }
        .col-date { width: 42px; }
        .col-code { width: 40px; }
        .col-name { width: {{ in_array($reportType, ['deptwise', 'intime', 'outtime']) ? '240px' : '150px' }}; text-align: left !important; padding-left: 3px; }
        .col-dept { width: 90px; }
        .col-m { width: 15px; }
        .col-shift { width: 30px; }
        .col-time { width: 38px; }
        .col-hours { width: 38px; }
        .col-ot { width: 35px; }
        .col-late { width: 35px; }
        .col-early { width: 35px; }
        .col-mis { width: 28px; }

        .summary-box { 
            margin-top: 10px; 
            background-color: #1a365d; 
            color: white; 
            padding: 8px 15px; 
            font-weight: bold; 
            font-size: 11px; 
            text-align: right;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }}</h1>
        </div>
        <div class="sub-header">
            {{ strtoupper($reportTitle) }}
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportType) }} | PERIOD: {{ $period }}
            </div>
            <div class="status-right">
                PRINTED ON {{ $printDate }}
            </div>
        </div>
    </header>

    <div class="content-section" style="margin-top: 10px;">
        @foreach($reportData as $deptName => $rows)
            @if(in_array($reportType, ['deptwise', 'intime', 'outtime']))
                <div class="dept-banner">DEPARTMENT: {{ strtoupper($deptName) }} (Total: {{ count($rows) }})</div>
            @endif
            <table>
                <thead>
                    <tr>
                        <th class="col-sr text-center">Sr.</th>
                        <th class="col-date">Date</th>
                        <th class="col-code">Code</th>
                        <th class="col-name text-center">Employee Name</th>
                        @if(!in_array($reportType, ['deptwise', 'intime', 'outtime']))
                            <th class="col-dept text-center">Department</th>
                        @endif
                        <th class="col-m text-center">M</th>
                        <th class="col-shift text-center">Shift</th>
                        <th class="col-time text-center">Time In</th>
                        <th class="col-time text-center">Time Out</th>
                        <th class="col-hours text-center">Hours</th>
                        <th class="col-ot text-center">OT</th>
                        <th class="col-late text-center">LateIn</th>
                        <th class="col-early text-center">EarlyOut</th>
                        <th class="col-mis text-center">MisP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $index => $row)
                        <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                            <td class="text-center">{{ $row['sr'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td class="cell-code">{{ $row['code'] }}</td>
                            <td class="col-name">{{ strtoupper($row['name']) }}</td>
                            @if(!in_array($reportType, ['deptwise', 'intime', 'outtime']))
                                <td class="text-center">{{ $row['dept'] }}</td>
                            @endif
                            <td class="text-center">{{ $row['machine_type'] }}</td>
                            <td class="text-center">{{ $row['shift'] }}</td>
                            <td class="text-center">{!! str_replace(' (M)', ' <span style="color: #4338ca; font-size: 7px; font-weight: bold;">(M)</span>', $row['in_time']) !!}</td>
                            <td class="text-center">{!! str_replace(' (M)', ' <span style="color: #4338ca; font-size: 7px; font-weight: bold;">(M)</span>', $row['out_time']) !!}</td>
                            <td class="text-center">{{ $row['work_hours'] }}</td>
                            <td class="text-center">{{ $row['overtime'] }}</td>
                            <td class="text-center">{{ $row['late_in'] }}</td>
                            <td class="text-center">{{ $row['early_out'] }}</td>
                            <td class="text-center bold">{{ $row['mis_punch'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <div class="summary-box">
            GRAND TOTAL PRESENT : {{ $totalPresent }}
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("helvetica", "bold");
            $color = array(0.1, 0.21, 0.36);
            
            // Footer "Continued on Page" logic
            $pdf->page_script('
                if ($PAGE_COUNT > 1 && $PAGE_NUM < $PAGE_COUNT) {
                    $font = $fontMetrics->get_font("helvetica", "bold");
                    $pdf->text(240, 810, "Continued on Page No... " . ($PAGE_NUM + 1), $font, 9, array(0.2, 0.2, 0.2));
                }
            ');
            
             // Page X of Y in the status bar
             $pdf->page_text(370, 75, "PAGE {PAGE_NUM} OF {PAGE_COUNT}", $font, 9, $color);
         }
    </script>
</body>
</html>
