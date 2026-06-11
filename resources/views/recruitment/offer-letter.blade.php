<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle ?? 'Appointment Letter' }} - {{ $companyName }}</title>
    <style>
        @if(empty($forPreview))
            @include('reports.partials.header_css')

            footer {
                position: fixed;
                bottom: -28px;
                left: 0;
                right: 0;
                height: 24px;
            }
        @else
            html, body {
                margin: 0;
                padding: 0;
                overflow: hidden;
                height: auto;
            }

            body {
                padding: 8px 10px 6px;
            }

            header {
                position: static;
                height: auto;
                margin-bottom: 6px;
            }

            footer {
                position: static;
                margin-top: 10px;
                height: auto;
            }
        @endif

        body {
            font-family: Helvetica, Arial, DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.55;
            background: #ffffff;
        }

        .main-header {
            background-color: #1a365d;
            color: white;
            padding: 6px;
            text-align: center;
            margin-bottom: 0;
        }

        .main-header h1 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 900;
        }

        .status-bar {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e0;
            padding: 5px 8px;
            margin: 0;
            font-size: 10px;
            font-weight: 900;
            color: #000000;
            line-height: 1.35;
        }

        .status-left { text-align: left; }
        .clearfix::after { content: ""; clear: both; display: table; }

        .letter-panel {
            border: 1px solid #cbd5e1;
            border-left: 5px solid #1a365d;
            border-radius: 4px;
            background: #ffffff;
            padding: 12px 14px 14px;
            margin-top: 4px;
        }

        @if(!empty($forPreview))
        .letter-panel {
            padding: 10px 12px 12px;
            margin-top: 2px;
        }

        .letter-meta-bar {
            margin-bottom: 8px;
            padding: 4px 6px;
        }

        .signature-block {
            margin-top: 18px;
        }
        @endif

        .letter-meta-bar {
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
            padding: 5px 8px;
            margin-bottom: 12px;
            font-size: 10px;
            font-weight: 900;
            color: #000000;
            border-radius: 3px;
            text-transform: uppercase;
        }

        .letter-body {
            font-size: 11.5px;
            color: #0f172a;
            white-space: pre-wrap;
        }

        .signature-block {
            margin-top: 32px;
            width: 240px;
        }

        .sig-line {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            font-size: 9px;
            color: #475569;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @include('reports.partials.header')

    <div class="letter-panel">
        <div class="letter-meta-bar">
            DOCUMENT: {{ $reportTitle ?? 'APPOINTMENT LETTER' }}
            &nbsp;|&nbsp; DATE: {{ $letterDate ?? now()->format('d-M-Y') }}
            &nbsp;|&nbsp; REF: {{ $refNo ?? 'HR/OFFER/' . now()->format('Y') }}
        </div>

        <div class="letter-body">{!! nl2br(e($bodyContent)) !!}</div>

        <div class="signature-block">
            <div class="sig-line">
                For {{ $companyName }}<br>
                Authorised Signatory — HR Department
            </div>
        </div>
    </div>

    @include('reports.partials.footer')
</body>
</html>
