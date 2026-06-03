<!DOCTYPE html>
<html>
<head>
    <title>Employee Report</title>
    <style>
        @page {
            margin: 0.5cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
        }
        .header {
            background-color: #1a365d; /* Dark Blue */
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .report-info {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 8px 10px;
            margin-bottom: 15px;
            font-weight: bold;
            color: #2d3748;
            display: table;
            width: 100%;
        }
        .info-left { display: table-cell; text-align: left; width: 70%; }
        .info-right { display: table-cell; text-align: right; width: 30%; font-size: 9px; }

        .department-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .department-header {
            background-color: #edf2f7;
            padding: 6px 10px;
            border-left: 4px solid #3182ce;
            margin-bottom: 5px;
            display: table;
            width: 100%;
        }
        .dept-title { 
            display: table-cell; 
            font-weight: bold; 
            font-size: 11px;
            color: #2c5282;
            text-transform: uppercase;
        }
        .dept-total { 
            display: table-cell; 
            text-align: right; 
            font-weight: bold;
            color: #2d3748;
        }
        .total-badge {
            background-color: #3182ce;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
        }

        .employees-container {
            width: 100%;
        }
        .col-half {
            width: 49%;
            float: left;
        }
        .col-spacer {
            width: 2%;
            float: left;
        }
        .employee-table {
            width: 100%;
            border-collapse: collapse;
        }
        .employee-table th {
            background-color: #f8fafc;
            color: #4a5568;
            font-weight: bold;
            text-align: left;
            padding: 4px 6px;
            border-bottom: 1px solid #cbd5e0;
            font-size: 9px;
            text-transform: uppercase;
        }
        .employee-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }
        .row-even { background-color: #ffffff; }
        .row-odd { background-color: #fdfdfd; }
        
        .cell-scroll { width: 5%; color: #718096; font-size: 9px; }
        .cell-code { width: 15%; font-weight: bold; color: #2d3748; }
        .cell-name { width: 40%; text-transform: uppercase; }
        .cell-desig { width: 25%; color: #4a5568; }
        .cell-shift { width: 15%; text-align: right; font-weight: bold; color: #38a169; }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #a0aec0;
            padding: 10px 0;
            border-top: 1px solid #edf2f7;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Main Header -->
        <div class="header">
            <h1>{{ $companyName }}</h1>
        </div>

        <!-- Report Status Bar -->
        <div class="report-info clearfix">
            <div class="info-left">
                EMPLOYEE MASTER REPORT
            </div>
            <div class="info-right">
                PRINTED ON {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>

        @php $globalScroll = 1; @endphp
        
        @foreach($departments as $department)
            @if($department->employees->count() > 0)
            <div class="department-section">
                <!-- Department Banner -->
                <div class="department-header">
                    <div class="dept-title">DEPARTMENT: {{ $department->name }}</div>
                    <div class="dept-total">
                        TOTAL EMPLOYEES: <span class="total-badge">{{ $department->employees->count() }}</span>
                    </div>
                </div>
                
                <div class="employees-container clearfix">
                    @php 
                        $employees = $department->employees;
                        $halfCount = ceil($employees->count() / 2);
                        $leftCol = $employees->take($halfCount);
                        $rightCol = $employees->slice($halfCount);
                    @endphp

                    <!-- Left Column -->
                    <div class="col-half">
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th style="width:5%">#</th>
                                    <th style="width:15%">EmpCode</th>
                                    <th style="width:40%">EMPLOYEE NAME</th>
                                    <th style="width:25%">DESIGNATION</th>
                                    <th style="width:15%; text-align:right">SHIFT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($leftCol as $index => $employee)
                                    <tr class="{{ $index % 2 == 0 ? 'row-even' : 'row-odd' }}">
                                        <td class="cell-scroll">{{ $globalScroll++ }}</td>
                                        <td class="cell-code">{{ $employee->emy_code ?? $employee->employee_id }}</td>
                                        <td class="cell-name">{{ $employee->user->name ?? 'Unknown' }}</td>
                                        <td class="cell-desig">{{ $employee->designation->name ?? '-' }}</td>
                                        <td class="cell-shift">{{ $employee->shift->short_code ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="col-spacer">&nbsp;</div>
                    
                    <!-- Right Column -->
                    <div class="col-half">
                        @if($rightCol->count() > 0)
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th style="width:5%">#</th>
                                    <th style="width:15%">EmpCode</th>
                                    <th style="width:40%">EMPLOYEE NAME</th>
                                    <th style="width:25%">DESIGNATION</th>
                                    <th style="width:15%; text-align:right">SHIFT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rightCol as $index => $employee)
                                    <tr class="{{ $index % 2 == 0 ? 'row-even' : 'row-odd' }}">
                                        <td class="cell-scroll">{{ $globalScroll++ }}</td>
                                        <td class="cell-code">{{ $employee->emy_code ?? $employee->employee_id }}</td>
                                        <td class="cell-name">{{ $employee->user->name ?? 'Unknown' }}</td>
                                        <td class="cell-desig">{{ $employee->designation->name ?? '-' }}</td>
                                        <td class="cell-shift">{{ $employee->shift->short_code ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        @endforeach

        
    </div>
</body>
</html>
