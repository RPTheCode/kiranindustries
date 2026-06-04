<table>
    <tr>
        <td colspan="7" style="font-weight: bold; font-size: 14px; text-align: center; background-color: #1a365d; color: #ffffff;">
            {{ strtoupper($companyName) }}
        </td>
    </tr>
    <tr>
        <td colspan="7" style="font-weight: bold; font-size: 11px;">
            REPORT TYPE: {{ strtoupper($reportTitle) }} | PERIOD: {{ $period }}
        </td>
    </tr>
    <tr><td colspan="7"></td></tr>

    <tr>
        <th style="width:5%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">S.N</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">CODE</th>
        <th style="width:25%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">NAME</th>
        <th style="width:20%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">NOMINEE NAME</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">RELATION</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">SHARE (%)</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">AADHAR NO.</th>
    </tr>

    @php 
        $sn = 1;
        $totalEmployees = 0;
    @endphp
    @foreach($employees as $emp)
        @php
            $nomCount = $emp->nominees->count();
            if ($nomCount > 0) {
                $totalEmployees++;
            }
        @endphp
        @foreach($emp->nominees as $index => $nominee)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ $nomCount }}" style="text-align:center; vertical-align:middle;">{{ $sn++ }}</td>
                    <td rowspan="{{ $nomCount }}" style="text-align:center; font-weight:bold; color:#1a365d; vertical-align:middle;">{{ $emp->emy_code ?? $emp->employee_id }}</td>
                    <td rowspan="{{ $nomCount }}" style="text-align:left; font-weight:bold; color:#334155; vertical-align:middle;">{{ $emp->user->name ?? 'N/A' }}</td>
                @endif
                <td style="text-align:left;">{{ $nominee->name }}</td>
                <td style="text-align:left;">{{ $nominee->relation }}</td>
                <td style="text-align:center;">{{ $nominee->percentage }}%</td>
                <td style="text-align:center;">{{ $nominee->aadhar_number ?? '' }}</td>
            </tr>
        @endforeach
    @endforeach

    <tr>
        <td colspan="7" style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b; padding-right:10px;">
            TOTAL EMPLOYEES: {{ $totalEmployees }}
        </td>
    </tr>
</table>
