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
            font-size: 9.5px; 
            text-transform: uppercase; 
        }
        td { 
            padding: 8px 5px; 
            border: 1px solid #edf2f7; 
            vertical-align: middle; 
            font-size: 10px; 
            color: #1a365d;
        }
        
        .row-alt { background-color: #fcfcfc; }
        .text-center { text-align: center !important; }
        .text-left { text-align: left !important; padding-left: 10px; }
        .bold { font-weight: bold; }
        .cell-code { font-weight: 900; }
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
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">{{ strtoupper($col1) }}</th>
                @if(isset($col3))
                    <th style="width: 25%;">{{ strtoupper($col2) }}</th>
                    <th style="width: {{ isset($col4) ? '25%' : '55%' }};">{{ strtoupper($col3) }}</th>
                @else
                    <th style="width: 80%;">{{ strtoupper($col2) }}</th>
                @endif
                @if(isset($col4))
                    <th style="width: 30%;">{{ strtoupper($col4) }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $index => $item)
                <tr class="{{ $index % 2 != 0 ? 'row-alt' : '' }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center bold cell-code">{{ $item['col1'] ?? '' }}</td>
                    @if(isset($col3))
                        <td class="text-left">{{ ucwords(strtolower($item['col2'] ?? '')) }}</td>
                        <td class="text-left {{ isset($col4) ? '' : 'bold' }}">{{ ucwords(strtolower($item['col3'] ?? '')) }}</td>
                    @else
                        <td class="text-left">{{ ucwords(strtolower($item['col2'] ?? '')) }}</td>
                    @endif
                    @if(isset($col4))
                        <td class="text-left bold">{{ $item['col4'] ?? '' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
