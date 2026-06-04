<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @page { 
            margin: 72px 0.5cm 35px 0.5cm; 
        }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 9px; 
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
            font-size: 9px; 
            font-weight: 800;
            color: #1a365d;
        }
        .status-left { float: left; width: 60%; text-align: left; }
        .status-right { float: right; width: 40%; text-align: right; }
        .clearfix::after { content: ""; clear: both; display: table; }
        
        /* Table Styling */
        table { width: 100%; border-collapse: collapse; background: white; table-layout: fixed; }
        th { 
            background-color: #1a365d; 
            color: white; 
            font-weight: bold; 
            text-align: center; 
            padding: 5px 2px; 
            border: 1px solid #1a365d; 
            font-size: 8px; 
            text-transform: uppercase; 
        }
        td { 
            padding: 4px 2px; 
            border: 1px solid #e2e8f0; 
            vertical-align: middle; 
            font-size: 8px; 
            color: #1e293b;
            text-align: center;
        }
        
        .group-header {
            background-color: #f8fafc;
            border-left: 5px solid #1a365d;
            padding: 6px 10px;
            margin-top: 10px;
            font-weight: 800;
            color: #1a365d;
            font-size: 9px;
            text-transform: uppercase;
            text-align: left;
        }

        .row-alt { background-color: #f8fafc; }
        .text-left { text-align: left !important; padding-left: 5px; }
        .bold { font-weight: bold; }
        .cell-emp-name { text-align: left; font-weight: 700; color: #1e293b; text-transform: uppercase; }
        .cell-time { font-weight: bold; color: #1e293b; }
        .mis-punch { color: #dc2626; font-weight: 900; }
        .ot { color: #059669; font-weight: bold; }
        
        .grand-total {
            background-color: #1a365d;
            color: white;
            padding: 10px 15px;
            margin-top: 15px;
            font-weight: bold;
            font-size: 11px;
            text-align: right;
            border-radius: 4px;
        }

        .punch-badge {
            display: inline-block;
            padding: 1.5px 3px;
            margin: 1px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: 900;
            text-transform: uppercase;
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
                REPORT TYPE: ALL PUNCH LOG REPORT | PERIOD: {{ $period }}
            </div>
        </div>
    </header>

    @php 
    // snoGlobal removed
    @endphp
    
    @foreach($reportData as $groupName => $rows)
        @php $sno = 1; @endphp
        <div class="group-header">
            {{ $groupName }}
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 3.5%;">No</th>
                    <th style="width: 8.5%;">DATE</th>
                    <th style="width: 22%;">EMP NAME WITH CODE</th>
                    <th style="width: 12%;">DESIGNATION</th>
                    <th style="width: 32%;">ALL PUNCH DETAILS (IN OUT)</th>
                    <th style="width: 7.5%;">LUNCH TIME</th>
                    <th style="width: 7.5%;">TOTAL HOUR</th>
                    <th style="width: 7%;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr class="{{ $loop->index % 2 != 0 ? 'row-alt' : '' }}">
                        <td style="font-size: 7.5px; color: #64748b; white-space: nowrap;">{{ $sno++ }}</td>
                        <td style="white-space: nowrap;">{{ $row['date'] ?? '-' }}</td>
                        <td class="cell-emp-name">
                            {{ $row['name'] ?? 'N/A' }}
                            <div style="font-size: 7px; color: #64748b; font-weight: normal; margin-top: 1px;">CODE: {{ $row['code'] ?? '-' }}</div>
                        </td>
                        <td style="font-size: 7.5px; font-weight: bold; text-align: left; padding-left: 5px;">{{ $row['designation'] ?? '---' }}</td>
                        <td class="text-left" style="padding: 3px 6px;">
                            @php
                                $punches = array_filter(explode(',', $row['log_details'] ?? ''));
                                $pairs = [];
                                $activePair = null;
                                
                                foreach($punches as $punch) {
                                    $punch = trim($punch);
                                    if (empty($punch)) continue;
                                    $hasM = strpos($punch, '(M)') !== false;
                                    $cleanPunch = trim(str_replace('(M)', '', $punch));
                                    $parts = explode(' ', $cleanPunch);
                                    $time = $parts[0] ?? '';
                                    $type = strtoupper($parts[1] ?? '');
                                    
                                    if ($type === 'IN') {
                                        if ($activePair !== null) {
                                            $pairs[] = $activePair;
                                        }
                                        $activePair = ['in' => ['time' => $time, 'hasM' => $hasM], 'out' => null];
                                    } elseif ($type === 'OUT') {
                                        if ($activePair !== null && $activePair['out'] === null) {
                                            $activePair['out'] = ['time' => $time, 'hasM' => $hasM];
                                            $pairs[] = $activePair;
                                            $activePair = null;
                                        } else {
                                            if ($activePair !== null) {
                                                $pairs[] = $activePair;
                                            }
                                            $pairs[] = ['in' => null, 'out' => ['time' => $time, 'hasM' => $hasM]];
                                            $activePair = null;
                                        }
                                    }
                                }
                                if ($activePair !== null) {
                                    $pairs[] = $activePair;
                                }
                            @endphp
                            @if(empty($pairs))
                                <span style="color: #94a3b8; font-style: italic;">No logs</span>
                            @else
                                <table style="width: auto; border-collapse: collapse; margin: 0; padding: 0; background: transparent; table-layout: auto; border: none;">
                                    <tr style="background: transparent; border: none;">
                                        <td style="border: none; padding: 1px 4px 1px 0; text-align: left; font-weight: bold; color: #047857; font-size: 7.5px; width: 22px;">IN</td>
                                        @foreach($pairs as $p)
                                            @php
                                                $hasIn = isset($p['in']) && $p['in'] !== null;
                                                $val = $hasIn ? $p['in']['time'] : '-';
                                                $isM = $hasIn && $p['in']['hasM'];
                                            @endphp
                                            <td style="border: none; padding: 1px 6px; text-align: left; font-size: 8px; font-weight: bold; color: {{ $hasIn ? '#1e293b' : '#94a3b8' }};">
                                                {{ $val }}@if($isM)<span style="color: #4338ca; font-weight: 900; font-size: 7px;">(M)</span>@endif
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr style="background: transparent; border: none;">
                                        <td style="border: none; padding: 1px 4px 1px 0; text-align: left; font-weight: bold; color: #b91c1c; font-size: 7.5px; width: 22px;">OUT</td>
                                        @foreach($pairs as $p)
                                            @php
                                                $hasOut = isset($p['out']) && $p['out'] !== null;
                                                $val = $hasOut ? $p['out']['time'] : '-';
                                                $isM = $hasOut && $p['out']['hasM'];
                                            @endphp
                                            <td style="border: none; padding: 1px 6px; text-align: left; font-size: 8px; font-weight: bold; color: {{ $hasOut ? '#1e293b' : '#94a3b8' }};">
                                                {{ $val }}@if($isM)<span style="color: #4338ca; font-weight: 900; font-size: 7px;">(M)</span>@endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            @endif
                        </td>
                        <td style="white-space: nowrap;">{{ $row['lunch_time'] ?? '---' }}</td>
                        <td class="bold" style="white-space: nowrap;">{{ $row['hours'] }}</td>
                        <td style="white-space: nowrap;">
                            @php
                                $statusVal = $row['status'] ?? '-';
                                $bg = '#f1f5f9';
                                $fg = '#475569';
                                if ($statusVal === 'P') {
                                    $bg = '#dcfce7';
                                    $fg = '#15803d';
                                    $statusVal = 'PRESENT';
                                } elseif ($statusVal === 'A') {
                                    $bg = '#fee2e2';
                                    $fg = '#b91c1c';
                                    $statusVal = 'ABSENT';
                                } elseif ($statusVal === 'MIS') {
                                    $bg = '#ffedd5';
                                    $fg = '#c2410c';
                                }
                            @endphp
                            <span class="status-badge" style="background-color: {{ $bg }}; color: {{ $fg }};">
                                {{ $statusVal }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="grand-total">
        GRAND TOTAL UNIQUE EMPLOYEES: {{ count($uniqueEmployees) }}
    </div>
</body>
</html>
