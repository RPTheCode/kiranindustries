<!DOCTYPE html>
<html>

<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @include('reports.partials.header_css')
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Department Styling */
        .department-header {
            background-color: #f8fafc;
            border-left: 4px solid #1a365d;
            padding: 6px 10px;
            margin-top: 5px;
            margin-bottom: 2px;
            font-weight: 800;
            color: #1a365d;
            font-size: 9px;
            text-transform: uppercase;
            display: table;
            width: 100%;
        }

        .dept-total-badge {
            background-color: #1a365d;
            color: white;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 8px;
            float: right;
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
            background-color: #2d3748;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 4px 2px;
            border: 1px solid #4a5568;
            font-size: 7px;
            text-transform: uppercase;
        }

        td {
            padding: 3px 2px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 7.5px;
            text-align: center;
        }

        .row-alt {
            background-color: #f8fafc;
        }

        .text-red {
            color: #e53e3e;
            font-weight: bold;
        }

        .text-green {
            color: #38a169;
        }

        .col-no {
            width: 3%;
        }

        .col-date {
            width: 7%;
        }

        .col-doj {
            width: 8%;
        }

        .col-name {
            width: 22%;
            text-align: left;
            padding-left: 4px;
            font-weight: bold;
            color: #1a365d;
        }

        .col-shift {
            width: 6%;
            font-weight: bold;
            color: #2d4a77;
        }

        .col-time {
            width: 7%;
            font-weight: bold;
        }

        .col-hrs {
            width: 8%;
        }

        .col-ot {
            width: 6%;
        }

        .col-late {
            width: 8%;
        }

        .col-early {
            width: 8%;
        }

        .col-mis {
            width: 5%;
            font-weight: bold;
        }

        .col-man {
            width: 5%;
            font-weight: bold;
            color: #4338ca;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            width: 100%;
            font-size: 7px;
            color: #718096;
            text-align: center;
        }
    </style>
</head>

<body>
    @include('reports.partials.header')

    <div class="content-section">
        @php 
            $groupTypeLabel = 'DEPARTMENT';
            if (isset($reportType) && ($reportType === 'shift' || $reportType === 'dayshift' || $reportType === 'nightshift')) {
                $groupTypeLabel = 'SHIFT';
            }
        @endphp

        @foreach($reportData as $deptName => $deptRows)
            <div class="department-header clearfix">
                <span style="float: left;">{{ $groupTypeLabel }}: {{ strtoupper($deptName) }}</span>
                <span class="dept-total-badge">PRESENT: {{ count($deptRows) }}</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-date">Date</th>
                        <th class="col-name">Employee Name</th>
                        <th class="col-shift">Sft</th>
                        <th class="col-time">In</th>
                        <th class="col-time">Out</th>
                        <th class="col-hrs">Hrs</th>
                        <th class="col-ot">OT</th>
                        <th class="col-late">Late</th>
                        <th class="col-early">Early</th>
                        <th class="col-mis">Mis</th>
                        
                    </tr>
                </thead>
                <tbody>
                    @foreach($deptRows as $index => $row)
                        @php
                            // Format Work Hours: 8.30 -> 8h 30m
                            $hrs = $row['work_hours'];
                            if ($hrs && str_contains($hrs, '.')) {
                                $parts = explode('.', $hrs);
                                $hrs = $parts[0] . 'h ' . $parts[1] . 'm';
                            } elseif ($hrs) {
                                $hrs = $hrs . 'h 00m';
                            } else {
                                $hrs = '0h 0m';
                            }

                            // Format OT
                            $ot = $row['overtime'];
                            if ($ot && $ot !== '-' && str_contains($ot, '.')) {
                                $parts = explode('.', $ot);
                                $ot = $parts[0] . 'h ' . $parts[1] . 'm';
                            } elseif ($ot && $ot !== '-') {
                                $ot = $ot . 'h 0m';
                            } else {
                                $ot = '0h 0m';
                            }

                            // Late In
                            $late = $row['late_in'];
                            if ($late === '-')
                                $late = 'ON TIME';

                            // Early Out
                            $early = $row['early_out'];
                            if ($early === '-')
                                $early = '-';

                            // Mis Punch
                            $isMis = ($row['mis_punch'] === 'MISP');
                        @endphp
                        <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                            <td class="col-no">{{ $row['sr'] }}</td>
                            <td class="col-date">{{ $row['date'] ?? '-' }}</td> 
                            <td class="col-name">
                                {{ strtoupper($row['name'] ?? $row['user']['name'] ?? 'N/A') }}
                                @if(isset($row['is_manual']) && $row['is_manual'] && ($row['status'] ?? '') !== 'A')
                                    <span style="color: #4338ca; font-size: 7px; font-weight: bold;">(M)</span>
                                @endif
                            </td>
                            <td class="col-shift">{{ $row['shift'] ?? $row['shift']['name'] ?? '-' }}</td>
                            <td class="col-time">{!! str_replace(' (M)', ' <span style="color: #4338ca; font-size: 7px; font-weight: bold;">(M)</span>', $row['in_time'] ?: '---') !!}</td>
                            <td class="col-time">{!! str_replace(' (M)', ' <span style="color: #4338ca; font-size: 7px; font-weight: bold;">(M)</span>', $row['out_time'] ?: '---') !!}</td>
                            <td class="col-hrs">{{ $hrs }}</td>
                            <td class="col-ot">{{ $ot }}</td>
                            <td class="col-late {{ $late !== 'ON TIME' ? 'text-red' : '' }}">{{ $late }}</td>
                            <td class="col-early">{{ $early }}</td>
                            <td class="col-mis {{ $isMis ? 'text-red' : '' }}">{{ $isMis ? 'YES' : '-' }}</td>
                           
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>




</body>

                           
</html>