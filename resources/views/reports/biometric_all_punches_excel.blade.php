<table>
    <thead>
        <tr>
            <th colspan="8" style="background-color: #1a365d; color: white; font-weight: bold; text-align: center; font-size: 16px;">
                {{ strtoupper($companyName) }}
            </th>
        </tr>
        <tr>
            <th colspan="8" style="background-color: #f1f5f9; color: #1a365d; font-weight: bold; text-align: left; font-size: 12px;">
                REPORT TYPE: ALL PUNCH LOG REPORT | PERIOD: {{ $period }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($reportData as $groupName => $rows)
            <tr>
                <td colspan="8" style="background-color: #f8fafc; font-weight: bold; font-size: 12px; color: #1a365d; text-align: left;">
                    {{ $groupName }}
                </td>
            </tr>
            <tr>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">No</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">DATE</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: left;">EMP NAME WITH CODE</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: left;">DESIGNATION</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: left;">ALL PUNCH DETAILS (IN OUT)</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">LUNCH TIME</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">TOTAL HOUR</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">STATUS</th>
            </tr>
            @php $sno = 1; @endphp
            @foreach($rows as $row)
                <tr>
                    <td style="text-align: center;">{{ $sno++ }}</td>
                    <td style="text-align: center;">{{ $row['date'] ?? '-' }}</td>
                    <td style="text-align: left;">
                        {{ $row['name'] ?? 'N/A' }}
                        (CODE: {{ $row['code'] ?? '-' }})
                    </td>
                    <td style="text-align: left;">{{ $row['designation'] ?? '---' }}</td>
                    <td style="text-align: left;">
                        {{ $row['log_details'] ?? 'No logs' }}
                    </td>
                    <td style="text-align: center;">{{ $row['lunch_time'] ?? '---' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $row['hours'] }}</td>
                    <td style="text-align: center;">
                        @php
                            $statusVal = $row['status'] ?? '-';
                            if ($statusVal === 'P') $statusVal = 'PRESENT';
                            elseif ($statusVal === 'A') $statusVal = 'ABSENT';
                        @endphp
                        {{ $statusVal }}
                    </td>
                </tr>
            @endforeach
        @endforeach
        <tr>
            <td colspan="8" style="background-color: #1a365d; color: white; font-weight: bold; text-align: right; font-size: 14px;">
                GRAND TOTAL UNIQUE EMPLOYEES: {{ count($uniqueEmployees) }}
            </td>
        </tr>
    </tbody>
</table>
