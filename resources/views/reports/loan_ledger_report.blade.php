<!DOCTYPE html>
<html>
<head>
    <title>Loan & Advance Ledger</title>
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
        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 100px;
            text-align: center;
        }
        .main-header {
            background-color: #1a365d;
            color: white;
            padding: 8px;
            text-align: center;
        }
        .main-header h1 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 900;
        }
        .sub-header {
            background-color: #2d4a77;
            color: white;
            padding: 4px;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-bar {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e0;
            padding: 5px 10px;
            font-size: 7.5px;
            font-weight: 800;
            color: #1a365d;
        }
        .status-left  { float: left;  width: 100%; text-align: left;  }
        .status-right { float: right; width: 40%; text-align: right; }
        .clearfix::after { content: ""; clear: both; display: table; }
        footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 20px;
            font-size: 6.5px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #cbd5e0;
            background: white;
            margin-top: 10px;
        }
        th {
            background-color: #1a365d;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            padding: 5px 2px;
            border: 1px solid #cbd5e0;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        td {
            padding: 5px 3px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 7.5px;
            text-align: center;
        }
        .text-left  { text-align: left !important;  padding-left: 6px; }
        .text-right { text-align: right !important; padding-right: 6px; }
        .font-bold  { font-weight: bold; color: #1a365d; }
        .row-alt    { background-color: #f8fafc; }
        .badge-loan {
            display: inline-block;
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 6.5px;
            font-weight: bold;
        }
        .badge-advance {
            display: inline-block;
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 6.5px;
            font-weight: bold;
        }
        .badge-amount {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-outstanding {
            display: inline-block;
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 7px;
            font-weight: bold;
        }
        .total-row td {
            background-color: #1a365d;
            color: #ffffff;
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
                REPORT TYPE: {{ strtoupper($reportTitle ?? 'LOAN & ADVANCE LEDGER') }} | PERIOD: {{ $period ?? now()->format('d-M-Y') }}
            </div>
        </div>
    </header>

    <div style="margin-top:10px;">
        <table>
            <thead>
                <tr>
                    <th style="width:5%;">S.N</th>
                    <th style="width:35%;">EMPLOYEE</th>
                    <th style="width:10%;">TYPE</th>
                    <th style="width:14%;">PERIOD (MONTHS)</th>
                    <th style="width:18%;">TOTAL AMOUNT (Rs.)</th>
                    <th style="width:18%;">INSTALLMENT (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                @php $sn = 1; @endphp
                @forelse($reportData as $row)
                <tr class="{{ $sn % 2 == 0 ? 'row-alt' : '' }}">
                    <td class="text-center font-bold">{{ $sn++ }}</td>
                    <td class="text-left">
                        <div class="font-bold" style="color:#1a365d; font-size:7.5px;">{{ $row['code'] }}</div>
                        <div style="color:#334155; font-size:7px; font-weight:600; text-transform:uppercase;">{{ $row['name'] }}</div>
                    </td>
                    <td class="text-center">
                        <span style="border:1px solid #fca5a5; background-color:#fef2f2; color:#991b1b; padding:1px 4px; border-radius:2px; font-size:6.5px; font-weight:bold;">
                            {{ strtoupper($row['loan_type']) }}
                        </span>
                    </td>
                    <td class="text-center font-bold" style="color:#0369a1;">
                        {{ $row['period'] }}
                    </td>
                    <td class="text-right">
                        <span class="badge-amount">{{ $row['total_amount'] }}</span>
                    </td>
                    <td class="text-right">
                        <span style="color:#16a34a; font-weight:bold; font-size:7.5px;">{{ $row['installment'] }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding:20px; color:#64748b; font-style:italic;">
                        No loan or advance records found.
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4" class="text-right" style="padding-right:8px;">TOTAL EMPLOYEES</td>
                    <td colspan="2" class="text-center">{{ $reportData->count() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $fontBold = $fontMetrics->get_font("helvetica", "bold");
                $fontNormal = $fontMetrics->get_font("helvetica", "normal");
                
                // Top Right: PAGE X OF Y
                $pdf->text(520, 35, "PAGE " . $PAGE_NUM . " OF " . $PAGE_COUNT, $fontBold, 8, array(0.1, 0.21, 0.36));
                
                // Bottom Left: Develop by Sridix
                $pdf->text(20, 815, "Develop by Sridix Technology LLP", $fontNormal, 7, array(0.5, 0.5, 0.5));

                // Bottom Center: Continued on Page
                if ($PAGE_COUNT > 1 && $PAGE_NUM < $PAGE_COUNT) {
                    $pdf->text(240, 815, "Continued on Page No... " . ($PAGE_NUM + 1), $fontBold, 7, array(0.2, 0.2, 0.2));
                }

                // Bottom Right: Printed On
                $printedText = "Printed On : {{ now()->format('d/m/Y H:i:s') }}";
                $textWidth = $fontMetrics->getTextWidth($printedText, $fontNormal, 7);
                $pdf->text(575 - $textWidth, 815, $printedText, $fontNormal, 7, array(0.5, 0.5, 0.5));
            ');
        }
    </script>
</body>
</html>
