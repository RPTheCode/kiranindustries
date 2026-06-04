<table>
    <tr>
        <td colspan="{{ 2 + count($dates) + 3 }}" style="font-weight: bold; font-size: 14px; text-align: center; background-color: #1a365d; color: #ffffff;">
            {{ strtoupper($companyName) }}
        </td>
    </tr>
    <tr>
        <td colspan="{{ 2 + count($dates) + 3 }}" style="font-weight: bold; font-size: 11px;">
            REPORT TYPE: {{ strtoupper($reportTitle) }} | PERIOD: {{ $period }}
        </td>
    </tr>
    <tr><td colspan="{{ 2 + count($dates) + 3 }}"></td></tr>
</table>

@php $grandTotal = 0; @endphp
@foreach($reportData as $deptName => $rows)
    @php $grandTotal += count($rows); @endphp
    <table>
        <tr>
            <td colspan="{{ 2 + count($dates) + 3 }}" style="font-weight: bold; font-size: 11px; background-color: #f1f5f9; color: #1a365d;">
                DEPARTMENT: {{ strtoupper($deptName) }}
            </td>
        </tr>
        <tr>
            <th style="background-color: #1a365d; color: #ffffff; font-weight: bold;">No.</th>
            <th style="background-color: #1a365d; color: #ffffff; font-weight: bold; text-align: left;">Name</th>
            @foreach($dates as $date)
                <th style="background-color: #1a365d; color: #ffffff; font-weight: bold; text-align: center;">{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
            @endforeach
            <th style="background-color: #1a365d; color: #ffffff; font-weight: bold;">PR</th>
            <th style="background-color: #1a365d; color: #ffffff; font-weight: bold;">P.I</th>
            <th style="background-color: #1a365d; color: #ffffff; font-weight: bold;">TOT</th>
        </tr>
        @foreach($rows as $index => $row)
            <tr>
                <td style="text-align: center;">{{ $row['sr'] }}</td>
                <td style="font-weight: bold; color: #1a365d;">
                    {{ $row['code'] ?? 'N/A' }}-{{ ucwords(strtolower($row['name'] ?? 'N/A')) }}
                </td>
                @foreach($dates as $date)
                    @php
                        $val = $row['attendance'][$date] ?? '-';
                        $isMis = $row['attendance_mis'][$date] ?? false;
                        $isPi = $row['attendance_pi'][$date] ?? false;
                        $cellStyle = 'text-align: center;';
                        if ($isPi) {
                            $cellStyle .= 'background-color: #e0f2fe; color: #0369a1; font-weight: bold;';
                        } elseif ($isMis) {
                            $cellStyle .= 'background-color: #fff5f5; color: #dc2626; font-weight: bold;';
                        } elseif ($val === '0.0' || $val === 'A') {
                            $cellStyle .= 'background-color: #fff5f5; color: #dc2626;';
                        }
                    @endphp
                    <td style="{{ $cellStyle }}">{{ $val !== '-' ? $val : '' }}</td>
                @endforeach
                <td style="text-align: center; font-weight: bold; background-color: #f8fafc;">{{ number_format($row['total_pr'], 1) }}</td>
                <td style="text-align: center; font-weight: bold; background-color: #f8fafc;">{{ isset($row['total_pi']) && $row['total_pi'] > 0 ? number_format($row['total_pi'], 1) : '' }}</td>
                <td style="text-align: center; font-weight: bold; background-color: #f8fafc;">{{ number_format($row['total_tot'] ?? $row['total_pr'], 1) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="{{ 2 + count($dates) + 3 }}" style="text-align: right; font-weight: bold; background-color: #f8fafc; color: #1a365d;">
                TOTAL IN {{ strtoupper($deptName) }}: {{ count($rows) }}
            </td>
        </tr>
    </table>
    <br>
@endforeach

<table>
    <tr>
        <td style="text-align: right; font-weight: bold; font-size: 12px; background-color: #1a365d; color: #ffffff;">
            GRAND TOTAL : {{ $grandTotal }}
        </td>
    </tr>
</table>
