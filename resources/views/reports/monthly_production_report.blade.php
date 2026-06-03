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
            font-size: 8px; 
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

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #cbd5e0;
            background: white;
            margin-top: 10px;
        }
        th {
            background-color: #f8fafc;
            color: #1a365d;
            font-weight: bold;
            text-align: center;
            padding: 4px 2px;
            border: 1px solid #cbd5e0;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        td {
            padding: 4px 2px;
            border: 1px solid #edf2f7;
            vertical-align: middle;
            font-size: 8px;
            text-align: center;
            height: 18px;
        }
        .text-left { text-align: left !important; padding-left: 4px; }
        .text-right { text-align: right !important; padding-right: 4px; }
        .font-bold { font-weight: bold; color: #1a365d; }
        .row-alt { background-color: #fcfcfc; }
        .total-row { background-color: #edf2f7; font-weight: bold; color: #1a365d; }
        .department-header {
            background-color: #f8fafc;
            border-left: 4px solid #1a365d;
            padding: 5px 8px;
            margin-top: 15px;
            margin-bottom: 2px;
            font-weight: bold;
            color: #1a365d;
            font-size: 9px;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e0;
        }
    </style>
</head>
<body>
    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }} {{ $branchName ? '- ' . strtoupper($branchName) : '' }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportType) }} | PERIOD: {{ $period }}
            </div>
            <div class="status-right">
                &nbsp;
            </div>
        </div>
    </header>

    <div class="content-section" style="margin-top: 10px;">
        @php 
            $totalQty = 0; 
            $totalEarning = 0; 
        @endphp

        @foreach($reportData as $deptName => $rows)
            <div class="department-header">
                DEPARTMENT: {{ strtoupper($deptName) }}
            </div>
            <table>
                <colgroup>
                    <col style="width: 5%;">
                    <col style="width: 25%;">
                    <col style="width: 18%;">
                    <col style="width: 18%;">
                    <col style="width: 11%;">
                    <col style="width: 11%;">
                    <col style="width: 12%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>S.N</th>
                        <th>EMPLOYEE (CODE - NAME)</th>
                        <th>DATE</th>
                        <th>MATERIAL NAME</th>
                        <th>RATE</th>
                        <th>PRODUCTION QTY</th>
                        <th>PRODUCTION EARNING</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php 
                            $totalQty += (float)$row['qty']; 
                            $totalEarning += (float)$row['earning']; 
                        @endphp
                        <tr class="{{ $row['sr'] % 2 == 0 ? 'row-alt' : '' }}">
                            <td>{{ $row['sr'] }}</td>
                            <td class="text-left font-bold">{{ $row['code'] }} - {{ strtoupper($row['name']) }}</td>
                            <td class="text-left font-bold" style="font-size: 7.5px;">{{ $row['date'] }}</td>
                            <td class="text-left font-bold" style="font-size: 7.5px;">{{ strtoupper($row['material']) }}</td>
                            <td class="text-right font-bold">Rs. {{ number_format((float)$row['rate'], 2) }}</td>
                            <td class="text-right font-bold">{{ number_format((float)$row['qty'], 2) }}</td>
                            <td class="text-right font-bold">Rs. {{ number_format((float)$row['earning'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <table style="margin-top: 15px;">
            <colgroup>
                <col style="width: 5%;">
                <col style="width: 25%;">
                <col style="width: 18%;">
                <col style="width: 18%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 12%;">
            </colgroup>
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" class="text-right font-bold">GRAND TOTAL</td>
                    <td class="text-right font-bold">{{ number_format($totalQty, 2) }}</td>
                    <td class="text-right font-bold">Rs. {{ number_format($totalEarning, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("helvetica", "bold");
            
            // Footer "Continued on Page" logic
            $pdf->page_script('
                if ($PAGE_COUNT > 1 && $PAGE_NUM < $PAGE_COUNT) {
                    $font = $fontMetrics->get_font("helvetica", "bold");
                    $pdf->text(240, 810, "Continued on Page No... " . ($PAGE_NUM + 1), $font, 9, array(0.2, 0.2, 0.2));
                }
            ');
         }
    </script>
</body>
</html>
