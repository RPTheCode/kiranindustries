<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        @page { 
            margin: 72px 0.3cm 35px 0.3cm; 
        }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 9px; 
            color: #333; 
            line-height: 1.1; 
            margin: 0; 
            padding: 0; 
        }
        
        /* Fixed Header Logic */
        header {
            position: fixed;
            top: -62px;
            left: 0;
            right: 0;
            height: 60px;
            text-align: center;
        }
        
        /* Premium Blue Header Bar */
        .main-header { 
            background-color: #1a365d; 
            color: white; 
            padding: 6px; 
            text-align: center; 
            margin-bottom: 0px;
        }
        .main-header h1 { 
            margin: 0; 
            font-size: 16px; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            font-weight: 900; 
        }

        /* Status Bar (Grey) */
        .status-bar { 
            background-color: #f1f5f9; 
            border: 1px solid #cbd5e0; 
            padding: 6px 10px; 
            margin: 0; 
            font-size: 8px; 
            font-weight: 800;
            color: #1a365d;
        }
        .status-left { float: left; width: 70%; text-align: left; }
        .status-right { float: right; width: 30%; text-align: right; }
        .clearfix::after { content: ""; clear: both; display: table; }
        
        /* Table Styling */
        table { width: 100%; border-collapse: collapse; background: white; table-layout: fixed; margin-top: 10px; border: 1px solid #cbd5e0; }
        th { 
            background-color: #1a365d; 
            color: white; 
            font-weight: bold; 
            text-align: center; 
            padding: 8px 5px; 
            border: 1px solid #cbd5e0; 
            font-size: 9px; 
            text-transform: uppercase; 
        }
        td { 
            padding: 6px 5px; 
            border: 1px solid #edf2f7; 
            vertical-align: middle; 
            font-size: 9.5px; 
            color: #1a365d;
        }
        
        .dept-group-header {
            background-color: #f8fafc;
            font-weight: 900;
            padding: 8px;
            border: 1px solid #cbd5e1;
            color: #1a365d;
            text-transform: uppercase;
            font-size: 10px;
        }
        
        .row-alt { background-color: #fcfcfc; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName) }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportTitle) }}
            </div>
            <div class="status-right">
                {{-- Reserved for Page Numbering --}}
            </div>
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">NO.</th>
                <th style="width: 35%;">NAME OF WORKER/STAFF</th>
                <th style="width: 8%;">DLY</th>
                <th style="width: 12%;">DOJ</th>
                @if($listType === 'Department Wise')
                    <th style="width: 20%;">DESIGNATION</th>
                    <th style="width: 15%;">AMOUNTS</th>
                @else
                    <th style="width: 18%;">DEPARTMENT</th>
                    <th style="width: 17%;">DESIGNATION</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @if($listType === 'Department Wise')
                @php $currentDept = null; @endphp
                @foreach($reportData as $index => $item)
                    @if($currentDept !== $item['dept_name'])
                        <tr>
                            <td colspan="6" class="dept-group-header">DEPARTMENT: {{ strtoupper($item['dept_name']) }}</td>
                        </tr>
                        @php $currentDept = $item['dept_name']; @endphp
                    @endif
                    <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                        <td class="text-center">{{ $item['no'] }}</td>
                        <td class="bold">{{ ucwords(strtolower($item['name'])) }}</td>
                        <td class="text-center">{{ $item['dly'] }}</td>
                        <td class="text-center">{{ $item['doj'] }}</td>
                        <td>{{ ucwords(strtolower($item['designation'])) }}</td>
                        <td class="text-right">{{ $item['amount'] }}</td>
                    </tr>
                @endforeach
            @else
                @foreach($reportData as $index => $item)
                    <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                        <td class="text-center">{{ $item['no'] }}</td>
                        <td class="bold">{{ ucwords(strtolower($item['name'])) }}</td>
                        <td class="text-center">{{ $item['dly'] }}</td>
                        <td class="text-center">{{ $item['doj'] }}</td>
                        <td>{{ ucwords(strtolower($item['department'])) }}</td>
                        <td>{{ ucwords(strtolower($item['designation'])) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

</body>
</html>
