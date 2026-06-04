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
            margin-top: 10px;
            background: white;
        }
        th {
            background-color: #f1f5f9;
            color: #1a365d;
            border: 1px solid #cbd5e0;
            padding: 4px;
            text-align: center;
            text-transform: uppercase;
            font-size: 7.5px;
        }
        td {
            border: 1px solid #cbd5e0;
            padding: 4px;
            text-align: center;
            font-size: 8px;
        }
        .col-name { text-align: left !important; width: 150px; font-weight: bold; color: #1a365d; }
        .col-code { width: 60px; }
        .col-amt { text-align: right !important; width: 60px; }
        .row-alt { background-color: #f9fafb; }
        .total-row { background-color: #edf2f7; font-weight: bold; color: #1a365d; }
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
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Emp. Code</th>
                    <th>Employee Name</th>
                    <th>Gross Pay</th>
                    <th>Incent Day</th>
                    <th>Incent Amt</th>
                    <th>Prod Incent</th>
                    <th>Total Amt</th>
                    <th>Loan Advance</th>
                    <th>Net Pay</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $grandGross = 0;
                    $grandIncentDay = 0;
                    $grandIncentAmt = 0;
                    $grandProdIncent = 0;
                    $grandTotal = 0;
                    $grandLoan = 0;
                    $grandNet = 0;
                @endphp
                @foreach($reportData as $index => $row)
                    @php
                        $grandGross += $row['gross_pay'];
                        $grandIncentDay += $row['incent_day'];
                        $grandIncentAmt += $row['incent_amt'];
                        $grandProdIncent += $row['prod_incent'];
                        $grandTotal += $row['total_amt'];
                        $grandLoan += $row['loan_advance'];
                        $grandNet += $row['net_pay'];
                    @endphp
                    <tr class="{{ $loop->iteration % 2 == 0 ? 'row-alt' : '' }}">
                        <td>{{ $loop->iteration }}</td>
                        <td class="col-code">{{ $row['emy_code'] }}</td>
                        <td class="col-name">{{ strtoupper($row['name']) }}</td>
                        <td class="col-amt">{{ number_format($row['gross_pay'], 2) }}</td>
                        <td>{{ number_format($row['incent_day'], 1) }}</td>
                        <td class="col-amt">{{ number_format($row['incent_amt'], 2) }}</td>
                        <td class="col-amt">{{ number_format($row['prod_incent'], 2) }}</td>
                        <td class="col-amt">{{ number_format($row['total_amt'], 2) }}</td>
                        <td class="col-amt">{{ number_format($row['loan_advance'], 2) }}</td>
                        <td class="col-amt" style="font-weight: bold; color: #1a365d;">{{ number_format($row['net_pay'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right; padding-right: 10px;">GRAND TOTAL:</td>
                    <td class="col-amt">{{ number_format($grandGross, 2) }}</td>
                    <td>{{ number_format($grandIncentDay, 1) }}</td>
                    <td class="col-amt">{{ number_format($grandIncentAmt, 2) }}</td>
                    <td class="col-amt">{{ number_format($grandProdIncent, 2) }}</td>
                    <td class="col-amt">{{ number_format($grandTotal, 2) }}</td>
                    <td class="col-amt">{{ number_format($grandLoan, 2) }}</td>
                    <td class="col-amt">{{ number_format($grandNet, 2) }}</td>
                </tr>
            </tbody>
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
