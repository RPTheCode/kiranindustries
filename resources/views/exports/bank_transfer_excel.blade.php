<table>
    <tr>
        <td colspan="8" style="font-weight: bold; font-size: 14px; text-align: center; background-color: #1a365d; color: #ffffff;">
            {{ strtoupper($companyName) }}
        </td>
    </tr>
    <tr>
        <td colspan="8" style="font-weight: bold; font-size: 11px;">
            REPORT TYPE: {{ strtoupper($reportTitle) }} | PERIOD: {{ $period }}
        </td>
    </tr>
    <tr><td colspan="8"></td></tr>

    <tr>
        <th style="width:5%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">S.N</th>
        <th style="width:10%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">CODE</th>
        <th style="width:20%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">NAME</th>
        <th style="width:20%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">A/C HOLDER NAME</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">BANK NAME</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:left;">BANK BRANCH</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">A/C NUMBER</th>
        <th style="width:12%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">IFSC CODE</th>
    </tr>

    @php $sn = 1; @endphp
    @foreach($employees as $emp)
        <tr>
            <td style="text-align:center;">{{ $sn++ }}</td>
            <td style="text-align:center; font-weight:bold; color:#1a365d;">{{ $emp->emy_code ?? $emp->employee_id }}</td>
            <td style="text-align:left; font-weight:bold; color:#334155;">{{ $emp->user->name ?? 'N/A' }}</td>
            <td style="text-align:left;">{{ $emp->account_holder_name ?? $emp->user->name ?? 'N/A' }}</td>
            <td style="text-align:left;">{{ $emp->bank_name ?? '—' }}</td>
            <td style="text-align:left;">{{ $emp->bank_branch ?? '—' }}</td>
            <td style="text-align:center;">{{ $emp->account_number ?? '—' }}</td>
            <td style="text-align:center;">{{ $emp->ifsc_code ?? '—' }}</td>
        </tr>
    @endforeach
</table>
