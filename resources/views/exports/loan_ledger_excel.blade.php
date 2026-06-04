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
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">LOAN TYPE</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:center;">PERIOD (MONTHS)</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">TOTAL AMOUNT</th>
        <th style="width:15%; background-color:#1a365d; color:#ffffff; font-weight:bold; text-align:right;">INSTALLMENT</th>
    </tr>

    @php 
        $sn = 1; 
        $grandTotal = 0;
        $grandInstallment = 0;
    @endphp
    @foreach($employees as $emp)
        @php
            $grandTotal += $emp->loan_total_amount;
            $grandInstallment += $emp->loan_installment_amount;
        @endphp
        <tr>
            <td style="text-align:center;">{{ $sn++ }}</td>
            <td style="text-align:center; font-weight:bold; color:#1a365d;">{{ $emp->emy_code ?? $emp->employee_id }}</td>
            <td style="text-align:left; font-weight:bold; color:#334155;">{{ $emp->user->name ?? 'N/A' }}</td>
            <td style="text-align:center;">Loan</td>
            <td style="text-align:center;">{{ $emp->loan_period ?? 0 }}</td>
            <td style="text-align:right; font-weight:bold; color:#0f766e;">{{ number_format((float)$emp->loan_total_amount, 2) }}</td>
            <td style="text-align:right; font-weight:bold; color:#991b1b;">{{ number_format((float)$emp->loan_installment_amount, 2) }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="5" style="text-align:right; font-weight:bold; background-color:#e2e8f0; color:#1e293b;">GRAND TOTAL</td>
        <td style="text-align:right; font-weight:bold; background-color:#115e59; color:#ffffff;">{{ number_format($grandTotal, 2) }}</td>
        <td style="text-align:right; font-weight:bold; background-color:#991b1b; color:#ffffff;">{{ number_format($grandInstallment, 2) }}</td>
    </tr>
</table>
