<table>
    <tr>
        <td colspan="16" style="font-weight: bold; font-size: 14px; text-align: center; background-color: #1a365d; color: #ffffff;">
            {{ strtoupper($companyName) }}
        </td>
    </tr>
    <tr>
        <td colspan="16" style="font-weight: bold; font-size: 11px;">
            REPORT TYPE: {{ strtoupper($reportTitle) }} | PERIOD: {{ $period }}
        </td>
    </tr>
    <tr><td colspan="16"></td></tr>

    <tr>
        <th style="width:5%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">S.N</th>
        <th style="width:8%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">CODE</th>
        <th style="width:20%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">NAME</th>
        <th style="width:8%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">W.DAYS</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">BASIC</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">HRA</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">CONVEY.</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">MED.</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">BONUS</th>
        <th style="width:12%; background-color:#166534; color:#ffffff; font-weight:bold; text-align:right;">GROSS</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">PF</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">ESI</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">PT</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">IT</th>
        <th style="width:12%; background-color:#991b1b; color:#ffffff; font-weight:bold; text-align:right;">DEDUCT</th>
        <th style="width:14%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">NET PAY</th>
    </tr>

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
        <tr>
            <td style="text-align:center;">{{ $sn++ }}</td>
            <td style="text-align:center; font-weight:bold; color:#1a365d;">{{ $row['code'] }}</td>
            <td style="text-align:left; font-weight:bold; color:#334155;">{{ $row['name'] }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $row['working_days'] }}</td>
            <td style="text-align:right;">{{ number_format($row['basic'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['hra'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['conveyance'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['medical'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['bonus'], 2) }}</td>
            <td style="text-align:right; font-weight:bold; color:#166534; background-color:#f0fdf4;">{{ number_format($row['gross_earn'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['pf'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['esi'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['pt'], 2) }}</td>
            <td style="text-align:right;">{{ number_format($row['it'], 2) }}</td>
            <td style="text-align:right; font-weight:bold; color:#991b1b; background-color:#fef2f2;">{{ number_format($row['total_deduct'], 2) }}</td>
            <td style="text-align:right; font-weight:bold; color:#1a365d; background-color:#f8fafc;">{{ number_format($row['net_pay'], 2) }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="4" style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">GRAND TOTAL</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tBasic, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tHra, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tConv, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tMed, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tBonus, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#166534; color:#ffffff;">{{ number_format($tGross, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tPf, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tEsi, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tPt, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">{{ number_format($tIt, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#991b1b; color:#ffffff;">{{ number_format($tDeduct, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#1a365d; color:#ffffff;">{{ number_format($tNet, 2) }}</td>
    </tr>
</table>
