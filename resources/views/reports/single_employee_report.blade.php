<!DOCTYPE html>
<html>
<head>
    <title>Employee Profile</title>
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

        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-header {
            background-color: #edf2f7;
            padding: 6px 10px;
            border-left: 4px solid #3182ce;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 12px;
            color: #2c5282;
            text-transform: uppercase;
        }
        
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .detail-table th {
            text-align: left;
            padding: 6px 8px;
            color: #4a5568;
            font-size: 10px;
            width: 25%;
            background-color: #f8fafc;
            border-bottom: 1px solid #edf2f7;
            border-right: 1px solid #edf2f7;
        }
        .detail-table td {
            padding: 6px 8px;
            color: #2d3748;
            font-size: 11px;
            font-weight: bold;
            width: 25%;
            border-bottom: 1px solid #edf2f7;
        }

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
        .profile-header {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .profile-info {
            display: table-cell;
            vertical-align: top;
        }
        .profile-name {
            font-size: 20px;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 5px;
        }
        .profile-desig {
            font-size: 12px;
            color: #4a5568;
            text-transform: uppercase;
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
                EMPLOYEE PROFILE REPORT
            </div>

        </div>

        @if($employee)
            <div class="profile-header">
                <div class="profile-info">
                    <div class="profile-name">{{ $user->name }}</div>
                    <div class="profile-desig">{{ $employee->designation->name ?? 'No Designation' }} | Emp Code: {{ $employee->emy_code ?? $employee->employee_id }}</div>
                </div>
            </div>

            <!-- Personal Info -->
            <div class="section">
                <div class="section-header">Personal Information</div>
                <table class="detail-table">
                    <tr>
                        <th>Father's Name</th>
                        <td>{{ $employee->father_name ?? '-' }}</td>
                        <th>Date of Birth</th>
                        <td>{{ $employee->date_of_birth ? \Carbon\Carbon::parse($employee->date_of_birth)->format('d/m/Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <th>Gender</th>
                        <td style="text-transform: capitalize;">{{ $employee->gender ?? '-' }}</td>
                        <th>Marital Status</th>
                        <td style="text-transform: capitalize;">{{ $employee->marital_status ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Blood Group</th>
                        <td>{{ $employee->blood_group ?? '-' }}</td>
                        <th>Phone</th>
                        <td>{{ $employee->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $user->email ?? '-' }}</td>
                        <th>Driving Licence</th>
                        <td>{{ $employee->driving_license ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Permanent Address</th>
                        <td colspan="3">{{ $employee->address_line_1 ?? $employee->permanent_address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Local Address</th>
                        <td colspan="3">{{ $employee->address_line_2 ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>City / State</th>
                        <td>{{ $employee->city ?? '-' }} / {{ $employee->state ?? '-' }}</td>
                        <th>Pincode</th>
                        <td>{{ $employee->postal_code ?? '-' }}</td>
                    </tr>
                </table>
            </div>

            <!-- Organization Info -->
            <div class="section">
                <div class="section-header">Organization & Employment</div>
                <table class="detail-table">
                    <tr>
                        <th>Department</th>
                        <td>{{ $employee->department->name ?? '-' }}</td>
                        <th>Branch</th>
                        <td>{{ $employee->branch->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td>{{ $employee->category->name ?? '-' }}</td>
                        <th>Section</th>
                        <td>{{ $employee->section->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Shift</th>
                        <td>{{ $employee->shift->name ?? '-' }}</td>
                        <th>Date of Joining</th>
                        <td>{{ $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining)->format('d/m/Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <th>Confirm Date</th>
                        <td>{{ $employee->confirm_date ? \Carbon\Carbon::parse($employee->confirm_date)->format('d/m/Y') : '-' }}</td>
                        <th>Probation Period</th>
                        <td>{{ $employee->probation_period ? $employee->probation_period . ' Months' : '-' }}</td>
                    </tr>
                    <tr>
                        <th>Education</th>
                        <td>{{ $employee->education ?? '-' }}</td>
                        <th>Experience</th>
                        <td>{{ $employee->experience ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Daily Option</th>
                        <td>{{ $employee->daily_option ? 'YES' : 'NO' }}</td>
                        <th>Working Days</th>
                        <td>{{ $employee->working_days ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>HOD Status</th>
                        <td>{{ $employee->hod_flag ? 'HOD' : 'Standard' }}</td>
                        <th>Resign Date</th>
                        <td>{{ $employee->resign_date ? \Carbon\Carbon::parse($employee->resign_date)->format('d/m/Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <th>Resign Reason</th>
                        <td colspan="3">{{ $employee->resignReason->name ?? '-' }}</td>
                    </tr>
                </table>
            </div>

            <!-- Statutory Info -->
            <div class="section">
                <div class="section-header">Statutory & Bank Details</div>
                <table class="detail-table">
                    <tr>
                        <th>PF Number</th>
                        <td>{{ $employee->pf_number ?? '-' }}</td>
                        <th>UAN Number</th>
                        <td>{{ $employee->uan_number ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>ESIC Number</th>
                        <td>{{ $employee->esic_number ?? '-' }}</td>
                        <th>Aadhar Number</th>
                        <td>{{ $employee->aadhar_card_number ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>PAN Number</th>
                        <td>{{ $employee->pan_card_number ?? '-' }}</td>
                        <th>Bank Name</th>
                        <td>{{ $employee->bank_name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Account Number</th>
                        <td>{{ $employee->account_number ?? '-' }}</td>
                        <th>IFSC Code</th>
                        <td>{{ $employee->ifsc_code ?? '-' }}</td>
                    </tr>
                </table>
            </div>

            <!-- Nominees -->
            @if($employee->nominees && $employee->nominees->count() > 0)
            <div class="section">
                <div class="section-header">Nominee Details</div>
                <table class="detail-table" style="border: 1px solid #edf2f7;">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th style="width: 40%;">Name</th>
                            <th style="width: 20%;">Relation</th>
                            <th style="width: 15%;">Share %</th>
                            <th style="width: 25%;">Aadhar No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employee->nominees as $nominee)
                        <tr>
                            <td style="width: 40%; border-bottom: 1px solid #edf2f7;">{{ $nominee->name }}</td>
                            <td style="width: 20%; border-bottom: 1px solid #edf2f7;">{{ $nominee->relation ?? '-' }}</td>
                            <td style="width: 15%; border-bottom: 1px solid #edf2f7;">{{ $nominee->percentage }}%</td>
                            <td style="width: 25%; border-bottom: 1px solid #edf2f7;">{{ $nominee->aadhar_number ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @elseif($employee->nominee_name)
            <div class="section">
                <div class="section-header">Nominee Details</div>
                <table class="detail-table">
                    <tr>
                        <th>Nominee Name</th>
                        <td>{{ $employee->nominee_name }}</td>
                        <th>Nominee Aadhar</th>
                        <td>{{ $employee->nominee_aadhar ?? '-' }}</td>
                    </tr>
                </table>
            </div>
            @endif
            
            <!-- Salary Info -->
            <div class="section">
                <div class="section-header">Salary Breakdown</div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                            <div style="background-color: #f0fff4; padding: 5px; font-weight: bold; color: #2f855a; border-bottom: 1px solid #c6f6d5; margin-bottom: 5px;">EARNINGS</div>
                            <table class="detail-table">
                                @foreach($salaryComponents->filter(fn($c) => $c->type === 'earning' && !in_array(strtoupper($c->name), ['LTA', 'PF BASIC'])) as $comp)
                                <tr>
                                    <th style="width: 60%;">{{ $comp->name }}</th>
                                    <td style="width: 40%;">Rs.{{ number_format($employeeSalary->components[$comp->id] ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                                <tr style="background-color: #c6f6d5;">
                                    <th style="width: 60%; color: #22543d;">GROSS SALARY</th>
                                    <td style="width: 40%; color: #22543d; font-size: 12px;">Rs.{{ number_format($employee->gross_salary ?? 0, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                            <div style="background-color: #fff5f5; padding: 5px; font-weight: bold; color: #c53030; border-bottom: 1px solid #fed7d7; margin-bottom: 5px;">DEDUCTIONS</div>
                            <table class="detail-table">
                                @foreach($salaryComponents->filter(fn($c) => $c->type === 'deduction') as $comp)
                                <tr>
                                    <th style="width: 60%;">{{ $comp->name }}</th>
                                    <td style="width: 40%;">Rs.{{ number_format($employeeSalary->components[$comp->id] ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                                <tr>
                                    <th style="width: 60%;">I.T. AMOUNT</th>
                                    <td style="width: 40%;">Rs.{{ number_format($employee->it_amount ?? 0, 2) }}</td>
                                </tr>
                                @php
                                    $totalDeductions = ($employee->it_amount ?? 0) + $salaryComponents->filter(fn($c) => $c->type === 'deduction')->sum(fn($c) => $employeeSalary->components[$c->id] ?? 0);
                                    $netPay = ($employee->gross_salary ?? 0) - $totalDeductions;
                                @endphp
                                <tr style="background-color: #fff5f5;">
                                    <th style="width: 60%; color: #c53030;">TOTAL DEDUCTIONS</th>
                                    <td style="width: 40%; color: #c53030;">Rs.{{ number_format($totalDeductions, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table style="width: 100%; border-collapse: collapse; margin-top: 5px;">
                    <tr style="background-color: #2d3748; color: white;">
                        <th style="width: 70%; text-align: right; padding: 8px; color: white; font-size: 12px; border-right: 1px solid #4a5568;">NET PAYABLE</th>
                        <td style="width: 30%; text-align: center; padding: 8px; color: white; font-size: 14px; font-weight: bold;">
                            Rs.{{ number_format($netPay, 2) }}
                        </td>
                    </tr>
                </table>
            </div>

        @else
            <div style="text-align: center; padding: 50px; color: #a0aec0;">
                Employee data not found.
            </div>
        @endif


    </div>
</body>
</html>
