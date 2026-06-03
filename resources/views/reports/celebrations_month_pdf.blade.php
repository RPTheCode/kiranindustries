<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @include('reports.partials.header_css')

        footer {
            position: fixed;
            bottom: -28px;
            left: 0;
            right: 0;
            height: 24px;
        }

        table.main-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            table-layout: fixed;
            margin-top: 4px;
            border: 1px solid #cbd5e0;
        }

        table.main-table th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 6px 4px;
            border: 1px solid #1a365d;
            font-size: 9px;
            text-transform: uppercase;
        }

        table.main-table td {
            padding: 5px 4px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 9px;
            color: #1e293b;
            text-align: center;
        }

        .row-alt td { background-color: #f8fafc; }
        .cell-name { text-align: left; padding-left: 6px; font-weight: 700; text-transform: uppercase; }
        .cell-code { font-weight: 800; color: #1a365d; }
        .cell-dept { text-align: left; padding-left: 4px; font-size: 8px; color: #475569; }

        .grand-total {
            background-color: #1a365d;
            color: white;
            padding: 7px 10px;
            margin-top: 10px;
            font-weight: bold;
            font-size: 10px;
            text-align: right;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    @include('reports.partials.header')

    <table class="main-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 10%;">Code</th>
                <th style="width: 32%;">Employee</th>
                <th style="width: 23%;">Department</th>
                <th style="width: 15%;">{{ $dateColumnLabel }}</th>
                @if($showYearsColumn)
                    <th style="width: 15%;">Completed</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $index => $row)
                <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td class="cell-code">{{ $row['code'] ?? '-' }}</td>
                    <td class="cell-name">{{ $row['name'] ?? '-' }}</td>
                    <td class="cell-dept">{{ $row['department'] ?? '-' }}</td>
                    <td>{{ $row['event_date'] ?? '-' }}</td>
                    @if($showYearsColumn)
                        <td><strong>{{ $row['years_label'] ?? '-' }}</strong></td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showYearsColumn ? 6 : 5 }}" style="padding: 16px; color: #64748b;">
                        No records for this month.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="grand-total">
        TOTAL RECORDS: {{ count($reportData) }}
    </div>

    @include('reports.partials.footer')
</body>
</html>
