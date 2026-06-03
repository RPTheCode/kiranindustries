<table>
    <thead>
        <tr>
            <th colspan="16" style="background-color: #1a365d; color: white; font-weight: bold; text-align: center; font-size: 16px;">
                {{ strtoupper($companyName) }}
            </th>
        </tr>
        <tr>
            <th colspan="16" style="background-color: #f1f5f9; color: #1a365d; font-weight: bold; text-align: left; font-size: 11px;">
                REPORT TYPE: {{ strtoupper($reportTitle) }} | PERIOD: {{ $period }}
                | DEPT: {{ $departmentName ?? 'ALL' }}
                | SEC: {{ $sectionName ?? 'ALL' }}
                | CATEGORY: {{ $categoryName ?? 'ALL' }}
                | STATUS: {{ $statusName ?? 'ALL' }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($reportData as $groupName => $rows)
            <tr>
                <td colspan="16" style="background-color: #f8fafc; font-weight: bold; font-size: 11px; color: #1a365d; text-align: left;">
                    {{ $groupName }}
                </td>
            </tr>
            <tr>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">No</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">DATE</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">CODE</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: left;">EMPLOYEE NAME</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: left;">DEPT</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: left;">CATEGORY</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">SFT</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">IN</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">OUT</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">HRS</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">OT</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">DUTY</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">STATUS</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">LATE</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">ERLY</th>
                <th style="font-weight: bold; background-color: #1a365d; color: white; text-align: center;">MIS</th>
            </tr>
            @php $sno = 1; @endphp
            @foreach($rows as $row)
                <tr>
                    <td style="text-align: center;">{{ $sno++ }}</td>
                    <td style="text-align: center;">{{ $row['date'] ?? '-' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $row['code'] ?? '-' }}</td>
                    <td style="text-align: left;">
                        {{ $row['name'] ?? $row['user']['name'] ?? 'N/A' }}
                        @if(!empty($row['is_manual']) && ($row['status'] ?? '') !== 'A')
                            (M)
                        @endif
                    </td>
                    <td style="text-align: left;">{{ $row['department'] ?? '-' }}</td>
                    <td style="text-align: left;">{{ $row['category'] ?? '-' }}</td>
                    <td style="text-align: center;">{{ $row['shift'] ?? '-' }}</td>
                    <td style="text-align: center;">{{ strip_tags(str_replace(' (M)', ' (M)', $row['time_in'] ?? $row['in_time'] ?? '-')) }}</td>
                    <td style="text-align: center;">{{ strip_tags(str_replace(' (M)', ' (M)', $row['time_out'] ?? $row['out_time'] ?? '-')) }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $row['hours'] ?? '-' }}</td>
                    <td style="text-align: center; color: #059669; font-weight: bold;">{{ (!empty($row['overtime']) && $row['overtime'] !== '0h 0m' && $row['overtime'] !== '-') ? $row['overtime'] : '-' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $row['duty'] ?? '-' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $row['status_label'] ?? ($row['status'] ?? '-') }}</td>
                    <td style="text-align: center; color: #d97706;">{{ ($row['late_in'] ?? 'ON TIME') !== 'ON TIME' ? $row['late_in'] : '-' }}</td>
                    <td style="text-align: center; color: #4b5563;">{{ ($row['early_out'] ?? '-') !== '-' ? $row['early_out'] : '-' }}</td>
                    <td style="text-align: center; color: #dc2626; font-weight: bold;">{{ ($row['mis_punch'] ?? '-') === 'YES' ? 'YES' : '-' }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr>
            <td colspan="16" style="background-color: #1a365d; color: white; font-weight: bold; text-align: right; font-size: 12px;">
                GRAND TOTAL UNIQUE EMPLOYEES: {{ $global_total_employees ?? count($uniqueEmployees ?? []) }}
            </td>
        </tr>
    </tbody>
</table>
