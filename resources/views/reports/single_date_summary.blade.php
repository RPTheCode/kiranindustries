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
            font-size: 8.5px; 
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
            border: 1px solid #cbd5e0;
        }
        th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 6px;
            border: 1px solid #cbd5e0;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            padding: 6px;
            border: 1px solid #edf2f7;
            text-align: center;
            font-size: 9px;
            height: 20px;
            vertical-align: middle;
        }
        .left-align { text-align: left !important; padding-left: 10px; }
        .right-align { text-align: right !important; padding-right: 15px; font-weight: bold; }
        .dept-col { width: 22%; font-weight: bold; border-right: 1px solid #cbd5e0; }
        td.dept-col { color: #1a365d; }
        .desig-col { width: 38%; }
        .val-col { width: 13.33%; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f8fafc; }
        .grand-total-row { background-color: #1a365d; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportTitle) }} | DATE: {{ $fromDate }}
            </div>
        </div>
    </header>

    <div class="content-section" style="margin-top: 10px;">
        <table>
            <thead>
                <tr>
                    <th class="dept-col">Section</th>
                    <th class="desig-col">Designation</th>
                    <th class="val-col">PDY</th>
                    <th class="val-col">PNT</th>
                    <th class="val-col">Worker Total</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $grandPdy = 0;
                    $grandPnt = 0;
                    $grandTotal = 0;
                @endphp

                @foreach($reportData as $deptName => $designations)
                    @php 
                        $deptPdy = 0;
                        $deptPnt = 0;
                        $deptTotal = 0;
                        $isFirst = true;
                        $rowCount = count($designations);
                    @endphp

                    @foreach($designations as $desigName => $stats)
                        <tr>
                            @if($isFirst)
                                <td class="dept-col left-align" rowspan="{{ $rowCount + 1 }}">{{ ucwords(strtolower($deptName)) }}</td>
                                @php $isFirst = false; @endphp
                            @endif
                            <td class="desig-col left-align">{{ ucwords(strtolower($desigName)) }}</td>
                            <td class="val-col right-align">{{ number_format($stats['pdy'], 2) }}</td>
                            <td class="val-col right-align">{{ number_format($stats['pnt'], 2) }}</td>
                            <td class="val-col right-align">{{ number_format($stats['total'], 2) }}</td>
                        </tr>
                        @php 
                            $deptPdy += $stats['pdy'];
                            $deptPnt += $stats['pnt'];
                            $deptTotal += $stats['total'];
                        @endphp
                    @endforeach

                    <tr class="total-row">
                        <td class="left-align" style="font-weight: bold; color: #1a365d;">Total</td>
                        <td class="right-align">{{ number_format($deptPdy, 2) }}</td>
                        <td class="right-align">{{ number_format($deptPnt, 2) }}</td>
                        <td class="right-align">{{ number_format($deptTotal, 2) }}</td>
                    </tr>

                    @php 
                        $grandPdy += $deptPdy;
                        $grandPnt += $deptPnt;
                        $grandTotal += $deptTotal;
                    @endphp
                @endforeach
            </tbody>
            <tfoot>
                <tr class="grand-total-row">
                    <td colspan="2" style="text-align: right; padding-right: 15px; font-size: 11px;">GRAND TOTAL :==>></td>
                    <td class="right-align" style="font-size: 11px;">{{ number_format($grandPdy, 2) }}</td>
                    <td class="right-align" style="font-size: 11px;">{{ number_format($grandPnt, 2) }}</td>
                    <td class="right-align" style="font-size: 11px;">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

</body>
</html>
