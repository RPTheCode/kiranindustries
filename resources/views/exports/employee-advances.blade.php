<table>
    <thead>
        <tr>
            <th colspan="{{ 9 + count($dates) + 4 }}"
                style="font-weight: bold; font-size: 14px; text-align: center; background-color: #00B050; color: #000000; height: 30px; vertical-align: middle;">
                {{ $title ?? 'EMPLOYEE ADVANCES' }}
            </th>
        </tr>
        <tr>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                SR.</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                ID</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                EMPLOYEE NAME</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                Des.</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                BANK</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                ACCOUNT HOLDER NAME</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                IFSC CODE</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                ACCOUNT NUMBER</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                REMARK</th>

            @foreach($dates as $date)
                <th
                    style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle; transform: rotate(-90deg);">
                    {{ $date }}
                </th>
            @endforeach

            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                Period Advance</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                Total Advance (All)</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                Total Paid (All)</th>
            <th
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: center; vertical-align: middle;">
                Pending Amount (All)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($employees as $index => $employee)
            <tr>
                <td style="border: 1px solid #000000; text-align: center; vertical-align: middle;">{{ $index + 1 }}</td>
                <td style="border: 1px solid #000000; text-align: center; vertical-align: middle;">
                    {{ $employee['employee_id'] }}
                </td>
                <td style="border: 1px solid #000000; text-align: left; vertical-align: middle;">
                    {{ $employee['employee_name'] }}
                </td>

                <td style="border: 1px solid #000000; text-align: center; vertical-align: middle;">
                    {{ $employee['designation'] }}
                </td>
                <td style="border: 1px solid #000000; text-align: center; vertical-align: middle;">
                    {{ $employee['bank_name'] }}
                </td>
                <td style="border: 1px solid #000000; text-align: left; vertical-align: middle;">
                    {{ $employee['name_of_member'] }}
                </td>
                <td style="border: 1px solid #000000; text-align: center; vertical-align: middle;">
                    {{ $employee['ifsc_code'] }}
                </td>
                <td style="border: 1px solid #000000; text-align: center; vertical-align: middle;">
                    &nbsp;{{ $employee['account_number'] }}</td>
                <td style="border: 1px solid #000000; text-align: left; vertical-align: middle;">{{ $employee['remark'] }}
                </td>

                @foreach($dates as $date)
                    <td style="border: 1px solid #000000; text-align: right; vertical-align: middle;">
                        {{ isset($employee['advances'][$date]) ? number_format($employee['advances'][$date], 2) : '0' }}
                    </td>
                @endforeach

                <td
                    style="font-weight: bold; background-color: #E2EFDA; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                    {{ number_format($employee['period_total'], 2) }}
                </td>
                <td
                    style="font-weight: bold; background-color: #E2EFDA; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                    {{ number_format($employee['total_advance'], 2) }}
                </td>
                <td
                    style="font-weight: bold; background-color: #E2EFDA; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                    {{ number_format($employee['total_paid'], 2) }}
                </td>
                <td
                    style="font-weight: bold; border: 1px solid #000000; text-align: right; vertical-align: middle; color: {{ $employee['pending_amount'] > 0 ? 'red' : 'black' }};">
                    {{ number_format($employee['pending_amount'], 2) }}
                </td>
            </tr>
        @endforeach
        {{-- Total Row --}}
        <tr>
            <td colspan="9"
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                TOTAL
            </td>
            @foreach($dates as $date)
                <td
                    style="font-weight: bold; background-color: #E2EFDA; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                    {{ isset($totalByDate[$date]) ? number_format($totalByDate[$date], 2) : '0.00' }}
                </td>
            @endforeach
            <td
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                {{ number_format($grandPeriodTotal ?? 0, 2) }}
            </td>
            <td
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                {{ number_format($grandTotalAdvance ?? 0, 2) }}
            </td>
            <td
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                {{ number_format($grandTotalPaid ?? 0, 2) }}
            </td>
            <td
                style="font-weight: bold; background-color: #C6E0B4; border: 1px solid #000000; text-align: right; vertical-align: middle;">
                {{ number_format($grandTotalPending ?? 0, 2) }}
            </td>
        </tr>
    </tbody>
</table>