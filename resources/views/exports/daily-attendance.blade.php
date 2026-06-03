<table>
    <thead>
        <tr>
            <th colspan="{{ $is_range ? 11 : 10 }}" style="text-align: center; font-size: 14pt;">
                {{ ucwords($branch_name) }} - ATTENDANCE REPORT
            </th>
        </tr>
        <tr>
            <th colspan="{{ $is_range ? 11 : 10 }}" style="text-align: center; font-size: 12pt;">Date: {{ $date }}</th>
        </tr>
        <tr>
            <th>SR</th>
            <th>Employee Name</th>
            <th>Designation</th>
            @if($is_range)
                <th>Date</th>
            @endif
            <th>No. of Working Hours</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Today Salary</th>
            <th>Today OT</th>
            <th>Today Total Amount</th>
            <th>Advance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $row)
            <tr style="{{ $row['is_subtotal'] ? 'font-weight: bold; background-color: #f9f9f9;' : '' }}">
                <td>{{ $row['sr'] }}</td>
                @if($row['is_subtotal'])
                    <td colspan="{{ $is_range ? 3 : 2 }}" style="text-align: right;">{{ $row['name'] }}</td>
                @else
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['designation'] }}</td>
                @endif

                @if($is_range && !$row['is_subtotal'])
                    <td>{{ $row['date'] }}</td>
                @endif
                <td>{{ \App\Models\AttendanceRecord::formatDuration($row['working_hours']) }}</td>
                <td>{{ $row['clock_in'] }}</td>
                <td>{{ $row['clock_out'] }}</td>
                <td>{{ number_format($row['today_salary'], 2) }}</td>
                <td>{{ number_format($row['today_ot'], 2) }}</td>
                <td>{{ number_format($row['total_amount'], 2) }}</td>
                <td>{{ number_format($row['advance'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="font-weight: bold; background-color: #f2f2f2;">
            <td colspan="{{ $is_range ? 4 : 3 }}" style="text-align: right;">
                TOTAL ({{ $totals['total_days'] }} Records)
            </td>
            <td>{{ \App\Models\AttendanceRecord::formatDuration($totals['working_hours']) }}</td>
            <td></td>
            <td></td>
            <td>{{ number_format($totals['today_salary'], 2) }}</td>
            <td>{{ number_format($totals['today_ot'], 2) }}</td>
            <td>{{ number_format($totals['total_amount'], 2) }}</td>
            <td>{{ number_format($totals['advance'], 2) }}</td>
        </tr>
    </tfoot>
</table>