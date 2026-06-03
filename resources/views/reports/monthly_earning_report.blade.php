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
            padding: 4px 2px;
            border: 1px solid #edf2f7;
            vertical-align: top;
            font-size: 7.5px;
            text-align: center;
        }
        .text-left { text-align: left !important; padding-left: 4px; }
        .text-right { text-align: right !important; padding-right: 4px; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold; color: #1a365d; }
        .row-alt { background-color: #fcfcfc; }
        .total-row { background-color: #edf2f7; font-weight: bold; color: #1a365d; }
        .earning-section { background-color: #f0fdf4; color: #166534; }
        
        /* Particulars Inner Table Styles */
        .particulars-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            background: transparent;
            margin: 0;
        }
        .particulars-table td {
            border: none !important;
            padding: 1.5px 2px !important;
            font-size: 7px !important;
            height: auto !important;
            vertical-align: middle !important;
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
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">S.N</th>
                    <th style="width: 30%;">EMPLOYEE DETAILS</th>
                    <th class="earning-section" style="width: 45%;">EARNING PARTICULARS</th>
                    <th class="earning-section" style="width: 20%;">TOTAL EARNING</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $colTotals = [
                        'total_earning' => 0,
                    ];
                    foreach($earningColumns as $col) { $colTotals['earnings'][$col] = 0; }
                @endphp

                @foreach($reportData as $deptName => $rows)
                    @php $sr = 1; @endphp
                    <tr class="department-row">
                        <td colspan="4" class="text-left font-bold" style="background-color: #f1f5f9; padding: 6px 8px; border-left: 4px solid #1a365d; font-size: 8px;">
                            DEPARTMENT: {{ strtoupper($deptName) }}
                        </td>
                    </tr>
                    @foreach($rows as $row)
                        @php 
                            $colTotals['total_earning'] += (float)$row['total_earning']; 
                        @endphp
                        <tr class="{{ $sr % 2 == 0 ? 'row-alt' : '' }}">
                            <td class="text-center" style="vertical-align: middle; font-weight: bold;">{{ $sr++ }}</td>
                            
                            <!-- Employee Details Column -->
                            <td class="text-left">
                                <div style="line-height: 1.2; padding: 2px 0;">
                                    <div class="font-bold" style="color: #1a365d; font-size: 8px;">{{ $row['code'] }}</div>
                                    <div class="font-bold" style="color: #334155; font-size: 8px; text-transform: uppercase;">{{ $row['name'] }}</div>
                                    <div style="color: #64748b; font-size: 7px; font-weight: 500;">Desig: {{ $row['designation'] }}</div>
                                </div>
                            </td>
                            
                            <!-- Dynamic Earnings Column -->
                            <td class="text-left">
                                @php 
                                    $hasEarnings = false;
                                    $activeEarnings = [];
                                    foreach($earningColumns as $col) {
                                        $info = $row['earnings'][$col] ?? ['amount' => 0, 'mode' => 'amount', 'qty' => 0];
                                        $val = (float)($info['amount'] ?? 0);
                                        $colTotals['earnings'][$col] += $val;
                                        if ($val > 0) {
                                            $activeEarnings[$col] = $info;
                                            $hasEarnings = true;
                                        }
                                    }
                                @endphp
                                @if($hasEarnings)
                                    <table class="particulars-table">
                                        @foreach($activeEarnings as $col => $info)
                                            <tr>
                                                <td class="text-left font-bold" style="color: #166534; width: 45%;">{{ $col }}</td>
                                                <td class="text-center" style="width: 25%;">
                                                    @if($info['mode'] === 'day')
                                                        <span style="background-color: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; padding: 1px 3px; font-size: 5.5px; font-weight: bold; border-radius: 3px; display: inline-block; white-space: nowrap;">DAYS: {{ round($info['qty'], 1) }}</span>
                                                    @else
                                                        <span style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 1px 3px; font-size: 5.5px; font-weight: bold; border-radius: 3px; display: inline-block; white-space: nowrap;">AMOUNT</span>
                                                    @endif
                                                </td>
                                                <td class="text-right font-bold" style="color: #166534; width: 30%;">{{ number_format($info['amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @else
                                    <span style="display: block; text-align: center; color: #a0aec0; padding-top: 10px;">-</span>
                                @endif
                            </td>

                            <td class="text-right font-bold earning-section" style="vertical-align: middle;">Rs. {{ number_format((float)$row['total_earning'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row" style="background-color: #f1f5f9;">
                    <td colspan="2" class="text-right font-bold" style="padding-right: 8px; vertical-align: middle;">GRAND TOTAL</td>
                    
                    <!-- Dynamic Earnings Grand Totals -->
                    <td class="text-left">
                        @php 
                            $hasGrandEarnings = false;
                            $activeGrandEarnings = [];
                            foreach($earningColumns as $col) {
                                $sum = $colTotals['earnings'][$col] ?? 0;
                                if ($sum > 0) {
                                    $activeGrandEarnings[$col] = $sum;
                                    $hasGrandEarnings = true;
                                }
                            }
                        @endphp
                        @if($hasGrandEarnings)
                            <table class="particulars-table">
                                @foreach($activeGrandEarnings as $col => $sum)
                                    <tr>
                                        <td class="text-left font-bold" style="color: #166534; width: 50%;">{{ $col }}</td>
                                        <td class="text-center" style="width: 20%">&nbsp;</td>
                                        <td class="text-right font-bold" style="color: #166534; width: 30%;">{{ number_format($sum, 2) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <span style="display: block; text-align: center; color: #a0aec0;">-</span>
                        @endif
                    </td>

                    <td class="text-right font-bold earning-section" style="vertical-align: middle;">Rs. {{ number_format($colTotals['total_earning'], 2) }}</td>
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
