    <header>
        <div class="main-header">
            <h1>{{ strtoupper($companyName ?? 'KIRAN INDUSTRIES') }}</h1>
        </div>
        <div class="status-bar clearfix">
            <div class="status-left">
                REPORT TYPE: {{ strtoupper($reportTitle ?? $reportType ?? 'REPORT') }} | PERIOD: {{ $period ?? '' }}
                | DEPT: {{ $departmentName ?? 'ALL' }}
                | SEC: {{ $sectionName ?? 'ALL' }}
                | CATEGORY: {{ $categoryName ?? 'ALL' }}
                | STATUS: {{ $statusName ?? 'ALL' }}
            </div>
        </div>
    </header>
