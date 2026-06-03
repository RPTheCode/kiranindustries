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
            font-size: 7px; 
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
            font-size: 13px; 
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
            font-size: 9px;
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
            font-size: 7.5px; 
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
            padding: 5px 1px;
            border: 1px solid #cbd5e0;
            font-size: 8px;
            text-transform: uppercase;
        }
        td {
            padding: 5px 3px;
            border: 1px solid #edf2f7;
            vertical-align: middle;
            font-size: 7.5px;
            text-align: center;
        }
        .text-left { text-align: left !important; padding-left: 6px; }
        .text-right { text-align: right !important; padding-right: 6px; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold; color: #1a365d; }
        .row-alt { background-color: #fcfcfc; }
        .total-row { background-color: #edf2f7; font-weight: bold; color: #1a365d; }
        .earning-section { background-color: #f0fdf4; color: #166534; font-weight: bold; }
        .deduction-section { background-color: #fef2f2; color: #991b1b; font-weight: bold; }
        .net-pay-col { background-color: #e0f2fe; color: #0369a1; font-weight: bold; }
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
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">S.N</th>
                    <th style="width: 35%;">EMPLOYEE DETAILS</th>
                    <th class="earning-section" style="width: 20%;">TOTAL EARNINGS</th>
                    <th class="deduction-section" style="width: 20%;">TOTAL DEDUCTIONS</th>
                    <th class="net-pay-col" style="width: 20%;">NET PAY</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $colTotals = [
                        'total_earning' => 0,
                        'total_deduction' => 0,
                        'net_pay' => 0,
                    ];
                @endphp

                @foreach($reportData as $deptName => $rows)
                    @php $sr = 1; @endphp
                    <tr class="department-row">
                        <td colspan="5" class="text-left font-bold" style="background-color: #f1f5f9; padding: 6px 8px; border-left: 4px solid #1a365d; font-size: 8px;">
                            DEPARTMENT: {{ strtoupper($deptName) }}
                        </td>
                    </tr>
                    @foreach($rows as $row)
                        @php 
                            $colTotals['total_earning'] += (float)$row['total_earning']; 
                            $colTotals['total_deduction'] += (float)$row['total_deduction']; 
                            $colTotals['net_pay'] += (float)$row['net_pay']; 
                        @endphp
                        <tr class="{{ $sr % 2 == 0 ? 'row-alt' : '' }}">
                            <td class="text-center" style="font-weight: bold;">{{ $sr++ }}</td>
                            
                            <!-- Employee Details Column -->
                            <td class="text-left">
                                <div style="line-height: 1.2; padding: 2px 0;">
                                    <div class="font-bold" style="color: #1a365d; font-size: 8px;">{{ $row['code'] }}</div>
                                    <div class="font-bold" style="color: #334155; font-size: 8px; text-transform: uppercase;">{{ $row['name'] }}</div>
                                    <div style="color: #64748b; font-size: 7px; font-weight: 500;">Desig: {{ $row['designation'] }}</div>
                                </div>
                            </td>

                            <td class="text-right earning-section">Rs. {{ number_format((float)$row['total_earning'], 2) }}</td>
                            <td class="text-right deduction-section">Rs. {{ number_format((float)$row['total_deduction'], 2) }}</td>
                            <td class="text-right net-pay-col">Rs. {{ number_format((float)$row['net_pay'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row" style="background-color: #f1f5f9;">
                    <td colspan="2" class="text-right font-bold" style="padding-right: 8px; vertical-align: middle;">GRAND TOTAL</td>
                    <td class="text-right font-bold earning-section">Rs. {{ number_format($colTotals['total_earning'], 2) }}</td>
                    <td class="text-right font-bold deduction-section">Rs. {{ number_format($colTotals['total_deduction'], 2) }}</td>
                    <td class="text-right font-bold net-pay-col">Rs. {{ number_format($colTotals['net_pay'], 2) }}</td>
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
                    $pdf->text(240, 815, "Continued on Page No... " . ($PAGE_NUM + 1), $font, 8, array(0.2, 0.2, 0.2));
                }
            ');
         }
    </script>
</body>
</html>
