<!DOCTYPE html>
<html>
<head>
    <title>Salary Structure (CTC) Report</title>
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

        /* ── Fixed Header ── */
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
            letter-spacing: 1px;
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

        /* ── Table ── */
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
            letter-spacing: 0.5px;
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
        .text-center{ text-align: center !important; }
        .font-bold  { font-weight: bold; color: #1a365d; }
        .row-alt    { background-color: #f8fafc; }

        /* ── Section separator rows ── */
        .emp-header-row td {
            background-color: #e8f0fe;
            color: #1a365d;
            font-weight: bold;
            font-size: 8px;
            border-left: 4px solid #1a365d;
            padding: 5px 8px;
        }

        /* ── Badges ── */
        .badge-earn {
            display: inline-block;
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 3px;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 7px;
        }
        .badge-deduct {
            display: inline-block;
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 3px;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 7px;
        }
        .badge-type-earn {
            display: inline-block;
            background-color: #d1fae5;
            color: #065f46;
            border-radius: 3px;
            padding: 1px 5px;
            font-size: 6.5px;
            font-weight: bold;
        }
        .badge-type-deduct {
            display: inline-block;
            background-color: #ffe4e6;
            color: #9f1239;
            border-radius: 3px;
            padding: 1px 5px;
            font-size: 6.5px;
            font-weight: bold;
        }

        /* ── Footer totals ── */
        .total-row td {
            background-color: #1a365d;
            color: #ffffff;
            font-weight: bold;
            font-size: 8px;
        }
        .total-earn   { background-color: #166534 !important; color: #ffffff !important; }
        .total-deduct { background-color: #991b1b !important; color: #ffffff !important; }

        /* ── Page footer ── */
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
    </style>
</head>
<body>

    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportTitle ?? 'SALARY CTC BREAKUP') }} | PERIOD: {{ $period ?? now()->format('d-M-Y') }}
            </div>
        </div>
    </header>

    <!-- ── Content ── -->
    <div style="margin-top:10px;">
        <table>
            <thead>
                <tr>
                    <th style="width:3%;">S.N</th>
                    <th style="width:5%;">CODE</th>
                    <th style="width:13%;">NAME</th>
                    <th style="width:5%;">W.DAYS</th>
                    <th style="width:6%;">BASIC</th>
                    <th style="width:6%;">HRA</th>
                    <th style="width:6%;">CONVEY.</th>
                    <th style="width:6%;">MED.</th>
                    <th style="width:6%;">BONUS</th>
                    <th style="width:8%; background-color:#166534;">GROSS</th>
                    <th style="width:6%;">PF</th>
                    <th style="width:5%;">ESI</th>
                    <th style="width:5%;">PT</th>
                    <th style="width:4%;">IT</th>
                    <th style="width:7%; background-color:#991b1b;">DEDUCT</th>
                    <th style="width:9%; background-color:#1a365d;">NET PAY</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $sn = 1; 
                    $tBasic=0; $tHra=0; $tConv=0; $tMed=0; $tBonus=0; $tGross=0;
                    $tPf=0; $tEsi=0; $tPt=0; $tIt=0; $tDeduct=0; $tNet=0;
                @endphp
                @foreach($reportData as $row)
                    @php
                        $tBasic += $row['basic'];
                        $tHra += $row['hra'];
                        $tConv += $row['conveyance'];
                        $tMed += $row['medical'];
                        $tBonus += $row['bonus'];
                        $tGross += $row['gross_earn'];
                        $tPf += $row['pf'];
                        $tEsi += $row['esi'];
                        $tPt += $row['pt'];
                        $tIt += $row['it'];
                        $tDeduct += $row['total_deduct'];
                        $tNet += $row['net_pay'];
                    @endphp
                    <tr class="{{ $loop->iteration % 2 == 0 ? 'row-alt' : '' }}">
                        <td class="text-center font-bold">{{ $sn++ }}</td>
                        <td class="text-center font-bold" style="color:#1a365d;">{{ $row['code'] }}</td>
                        <td class="text-left" style="font-weight:600; color:#334155;">{{ $row['name'] }}</td>
                        <td class="text-center font-bold">{{ $row['working_days'] }}</td>
                        <td class="text-right">{{ number_format($row['basic'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['hra'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['conveyance'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['medical'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['bonus'], 2) }}</td>
                        <td class="text-right font-bold" style="color:#166534; background-color:#f0fdf4;">{{ number_format($row['gross_earn'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['pf'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['esi'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['pt'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['it'], 2) }}</td>
                        <td class="text-right font-bold" style="color:#991b1b; background-color:#fef2f2;">{{ number_format($row['total_deduct'], 2) }}</td>
                        <td class="text-right font-bold" style="color:#1a365d; background-color:#f8fafc;">{{ number_format($row['net_pay'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4" class="text-right" style="padding-right:8px;">GRAND TOTAL</td>
                    <td class="text-right">{{ number_format($tBasic, 2) }}</td>
                    <td class="text-right">{{ number_format($tHra, 2) }}</td>
                    <td class="text-right">{{ number_format($tConv, 2) }}</td>
                    <td class="text-right">{{ number_format($tMed, 2) }}</td>
                    <td class="text-right">{{ number_format($tBonus, 2) }}</td>
                    <td class="text-right" style="background-color:#166534; color:white;">{{ number_format($tGross, 2) }}</td>
                    <td class="text-right">{{ number_format($tPf, 2) }}</td>
                    <td class="text-right">{{ number_format($tEsi, 2) }}</td>
                    <td class="text-right">{{ number_format($tPt, 2) }}</td>
                    <td class="text-right">{{ number_format($tIt, 2) }}</td>
                    <td class="text-right" style="background-color:#991b1b; color:white;">{{ number_format($tDeduct, 2) }}</td>
                    <td class="text-right" style="background-color:#1a365d; color:white;">{{ number_format($tNet, 2) }}</td>
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
