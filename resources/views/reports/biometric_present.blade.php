<!DOCTYPE html>
<html>

<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 90px 0.5cm 35px 0.5cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8px;
            color: #333;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }

        /* Fixed Header Logic */
        header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 85px;
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
            font-size: 14px;
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
            font-size: 9px;
            font-weight: 800;
            color: #1a365d;
        }

        .status-left {
            float: left;
            width: 100%;
            text-align: left;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Department Styling */
        .department-header {
            background-color: #f8fafc;
            border-left: 5px solid #1a365d;
            padding: 8px 10px;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 800;
            color: #1a365d;
            font-size: 11px;
            text-transform: uppercase;
            display: table;
            width: 100%;
        }

        .dept-total-badge {
            background-color: #1a365d;
            color: white;
            padding: 2px 10px;
            border-radius: 15px;
            font-size: 10px;
            float: right;
        }

        /* Column Layout for Employees */
        .employees-row {
            width: 100%;
            margin-top: 5px;
        }

        .col-half {
            width: 49%;
            float: left;
        }

        .col-spacer {
            width: 2%;
            float: left;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0px;
            background: white;
            table-layout: fixed;
        }

        th {
            background-color: #f1f5f9;
            color: #1a365d;
            font-weight: bold;
            text-align: left;
            padding: 5px;
            border: 1px solid #cbd5e0;
            font-size: 9px;
            text-transform: uppercase;
        }

        td {
            padding: 5px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 10px;
            color: #1e293b;
        }

        .row-alt {
            background-color: #f8fafc;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .cell-code {
            color: #1a365d;
            font-weight: 700;
            width: 18%;
        }

        .cell-name {
            width: 42%;
            text-transform: uppercase;
        }

        .cell-shift {
            text-align: center;
            font-weight: bold;
            color: #1a365d;
            width: 15%;
        }

        .cell-days {
            text-align: right;
            font-weight: bold;
            color: #1a365d;
            width: 15%;
        }

        .grand-total {
            background-color: #1a365d;
            color: white;
            padding: 10px 15px;
            margin-top: 20px;
            font-weight: bold;
            font-size: 13px;
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
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportType) }} | PERIOD: {{ $period }}
            </div>

        </div>
    </header>

    <div class="summary-section">
        @php $globalScroll = 1; @endphp

        @foreach($reportData as $deptName => $deptRows)
                    <div class="department-header clearfix">
                        <span style="float: left;">DEPARTMENT: {{ strtoupper($deptName) }}</span>
                        <span class="dept-total-badge">PRESENT: {{ count($deptRows) }}</span>
                    </div>

                    <div class="employees-row clearfix">
                        @php 
                                        $total = count($deptRows);
                            $halfCount = ceil($total / 2);
                            $deptRowsArray = collect($deptRows)->toArray();
                            $leftCol = array_slice($deptRowsArray, 0, $halfCount);
                            $rightCol = array_slice($deptRowsArray, $halfCount);
                        @endphp

                        <div class="col-half">
                            <table>
                                <thead>
                                <tr>
                                        <th style="width:10%">#</th>
                                        <th class="cell-code">CODE</th>
                                        <th class="cell-name">NAME</th>
                                        <th class="cell-shift">SFT</th>
                                        @if($isMultipleDays)
                                            <th class="cell-days">DAYS</th>
                                        @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($leftCol as $index => $row)
                                    <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                                            <td class="text-center" style="font-size: 8.5px; color: #64748b;">{{ $row['sr'] }}</td>
                                            <td class="cell-code">{{ $row['code'] ?? 'N/A' }}</td>
                                                <td class="cell-name">{{ $row['name'] ?? 'N/A' }}</td>
                                                <td class="cell-shift">{{ $row['shift'] ?? '-' }}</td>
                                                @if($isMultipleDays)
                                                    <td class="cell-days">{{ $row['days_present'] ?? 0 }}</td>
                                                @endif
                                            </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="col-spacer">&nbsp;</div>

                        <div class="col-half">
                            @if(count($rightCol) > 0)
                                <table>
                                    <thead>
                                    <tr>
                                            <th style="width:10%">#</th>
                                            <th class="cell-code">CODE</th>
                                            <th class="cell-name">NAME</th>
                                            <th class="cell-shift">SFT</th>
                                            @if($isMultipleDays)
                                                <th class="cell-days">DAYS</th>
                                            @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rightCol as $index => $row)
                                        <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                                                <td class="text-center" style="font-size: 8.5px; color: #64748b;">{{ $row['sr'] }}</td>
                                                <td class="cell-code">{{ $row['code'] ?? 'N/A' }}</td>
                                                    <td class="cell-name">{{ $row['name'] ?? 'N/A' }}</td>
                                                    <td class="cell-shift">{{ $row['shift'] ?? '-' }}</td>
                                                    @if($isMultipleDays)
                                                        <td class="cell-days">{{ $row['days_present'] ?? 0 }}</td>
                                                    @endif
                                                </tr>
                                    @endforeach
                                    </tbody>
                                    </table>
                            @endif
                            </div>

             </div>
        @endforeach

    @if($isLastChunk ?? true)
    <div class="grand-total">
        {{ $isMultipleDays ? 'TOTAL UNIQUE EMPLOYEES: ' . $totalEmployees : 'GRAND TOTAL PRESENT: ' . $totalEmployees }}
    </div>
    @endif
</body>
</html>
