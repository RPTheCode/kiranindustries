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
    </style>
</head>
<body>
    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }} {{ $branchName ? '- ' . strtoupper($branchName) : '' }}</h1>
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
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">NO</th>
                    <th style="width: 25%;">EMPLOYEE NAME</th>
                    <th style="width: 8%;">INC.AMT</th>
                    <th style="width: 8%;">ADVANCE</th>
                    <th style="width: 8%;">CANTEEN</th>
                    <th style="width: 8%;">COLONY</th>
                    <th style="width: 8%;">MOBILE</th>
                    <th style="width: 8%;">I.TAX</th>
                    <th style="width: 7%;">LT.C</th>
                    <th style="width: 8%;">INSTAL</th>
                    <th style="width: 7%;">SLP-D</th>
                    <th style="width: 10%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $cInc = 0; $cAdv = 0; $cCan = 0; $cCol = 0; $cMob = 0; $cTax = 0; $cLate = 0; $cInst = 0; $cSleep = 0; $cTotal = 0;
                    $totalEmployees = count($reportData);
                @endphp
                @foreach($reportData as $index => $row)
                    @php
                        if($row['inc_amt'] > 0) $cInc++;
                        if($row['advance'] > 0) $cAdv++;
                        if($row['canteen'] > 0) $cCan++;
                        if($row['colony'] > 0) $cCol++;
                        if($row['mobile'] > 0) $cMob++;
                        if($row['itax'] > 0) $cTax++;
                        if($row['late_come'] > 0) $cLate++;
                        if($row['instal'] > 0) $cInst++;
                        if($row['sleep_d'] > 0) $cSleep++;
                        if($row['total'] > 0) $cTotal++;
                    @endphp
                    <tr class="{{ $loop->iteration % 2 == 0 ? 'row-alt' : '' }}">
                        <td>{{ $loop->iteration }}</td>
                        <td class="text-left font-bold">{{ strtoupper($row['name']) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['inc_amt'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['advance'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['canteen'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['colony'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['mobile'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['itax'], 2) }}</td>
                        <td>{{ $row['late_come'] }}</td>
                        <td class="text-right">Rs. {{ number_format($row['instal'], 2) }}</td>
                        <td>{{ $row['sleep_d'] }}</td>
                        <td class="text-right font-bold">Rs. {{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="text-right font-bold">TOTAL WORKERS : {{ $totalEmployees }} | TOTAL :==>></td>
                    <td class="text-center font-bold">{{ $cInc }}</td>
                    <td class="text-center font-bold">{{ $cAdv }}</td>
                    <td class="text-center font-bold">{{ $cCan }}</td>
                    <td class="text-center font-bold">{{ $cCol }}</td>
                    <td class="text-center font-bold">{{ $cMob }}</td>
                    <td class="text-center font-bold">{{ $cTax }}</td>
                    <td class="text-center font-bold">{{ $cLate }}</td>
                    <td class="text-center font-bold">{{ $cInst }}</td>
                    <td class="text-center font-bold">{{ $cSleep }}</td>
                    <td class="text-center font-bold">{{ $cTotal }}</td>
                </tr>
            </tfoot>
        </table>
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
