
        @page {
            margin: 98px 0.5cm 35px 0.5cm;
        }

        /* Fixed Header Logic */
        header {
            position: fixed;
            top: -88px;
            left: 0;
            right: 0;
            height: 92px;
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
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 900;
        }

        /* Status Bar (Grey) */
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

        .status-left { float: left; width: 100%; text-align: left; }
        .status-right { float: right; width: 15%; text-align: right; }
        .clearfix::after { content: ""; clear: both; display: table; }

