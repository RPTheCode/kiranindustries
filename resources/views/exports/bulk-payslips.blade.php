<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>
    @php
        $blueColor = '#007bff';
        $greenColor = '#28a745';
        $yellowColor = '#ffc107';
        $tealColor = '#17a2b8';
        $grayBg = '#f8f9fa';
        $borderSolid = 'border: 2px solid #6c757d;';
    @endphp
    @forelse ($data as $row)
        <table>
            <thead>
                <tr>
                    <th colspan="10"
                        style="background-color: {{ $blueColor }}; color: #ffffff; font-weight: bold; text-align: left; height: 35px; padding-left: 10px; font-size: 13pt; {{ $borderSolid }}">
                        Employee Information
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Employee
                        Name:</td>
                    <td colspan="3" style="{{ $borderSolid }}">{{ $row['name'] }}</td>
                    <td colspan="2" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Employee
                        ID:</td>
                    <td colspan="3" style="{{ $borderSolid }}">{{ $row['employee_id'] }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Branch:
                    </td>
                    <td colspan="3" style="{{ $borderSolid }}">{{ $row['branch'] }}</td>
                    <td colspan="2" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Basic
                        Salary:</td>
                    <td colspan="3" style="text-align: right; font-weight: bold; {{ $borderSolid }}">
                        Rs.{{ number_format($row['basic_salary'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Email:</td>
                    <td colspan="3" style="{{ $borderSolid }}">{{ $row['email'] }}</td>
                    <td colspan="2" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Pay Period:</td>
                    <td colspan="3" style="{{ $borderSolid }}">{{ $row['pay_period'] }}</td>
                </tr>
                <!-- <tr>
                    <td colspan="10" style="height: 15px; border: none;"></td>
                </tr> -->

                <!-- Attendance Summary -->
                <tr>
                    <th colspan="10"
                        style="background-color: {{ $greenColor }}; color: #ffffff; font-weight: bold; text-align: left; height: 35px; padding-left: 10px; font-size: 13pt; {{ $borderSolid }}">
                        Attendance Summary
                    </th>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: center; vertical-align: middle; height: 35px; border-left: 2px solid #6c757d; border-right: 1px solid #6c757d; border-top: 2px solid #6c757d; border-bottom: 
                        1px solid #6c757d;">
                        <strong>Working Days:&nbsp; </strong> {{ $row['working_days'] }} &nbsp; | &nbsp;
                        <strong>Present:&nbsp; </strong> {{ $row['present_days'] }}
                    </td>
                    <td colspan="5" style="text-align: center; vertical-align: middle; height: 35px; border-left: 1px solid #6c757d; border-right: 2px solid #6c757d; border-top: 2px solid #6c757d; border-bottom: 
                        1px solid #6c757d;">
                        <strong>Extra Present Days:&nbsp; </strong> {{ $row['week_off_present_days'] }} &nbsp; | &nbsp;
                        <strong>Paid Leave:&nbsp; </strong> {{ $row['paid_leave'] }}
                    </td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: center; vertical-align: middle; height: 35px; border-left: 2px solid #6c757d; border-right: 1px solid #6c757d; border-top: 1px solid #6c757d; border-bottom: 
                        2px solid #6c757d;">
                        <strong>Unpaid Leave:&nbsp; </strong> {{ $row['unpaid_leave'] }} &nbsp; | &nbsp;
                        <strong>Half Days:&nbsp; </strong> {{ $row['half_days'] }}
                    </td>
                    <td colspan="5" style="text-align: center; vertical-align: middle; height: 35px; border-left: 1px solid #6c757d; border-right: 2px solid #6c757d; border-top: 1px solid #6c757d; border-bottom: 
                        2px solid #6c757d;">
                        <strong>Absent:&nbsp; </strong> {{ $row['absent_days'] }} &nbsp; | &nbsp;
                        <strong>Overtime:&nbsp;</strong> {{ \App\Models\AttendanceRecord::formatDuration($row['overtime_hours']) }}h
                    </td>
                </tr>
                <!-- <tr>
                    <td colspan="10" style="height: 15px; border: none;"></td>
                </tr> -->

                <!-- Deduction Calculation Details -->
                <tr>
                    <th colspan="10"
                        style="background-color: {{ $yellowColor }}; color: #000000; font-weight: bold; text-align: left; height: 35px; padding-left: 10px; font-size: 13pt; {{ $borderSolid }}">
                        Deduction Calculation Details
                    </th>
                </tr>
                <tr style="background-color: {{ $grayBg }};">
                    <td colspan="3" style="font-weight: bold; {{ $borderSolid }}">Type</td>
                    <td colspan="3" style="font-weight: bold; {{ $borderSolid }}">Days/Details</td>
                    <td colspan="2" style="font-weight: bold; {{ $borderSolid }}">Rate</td>
                    <td colspan="2" style="font-weight: bold; text-align: right; {{ $borderSolid }}">Deduction Amount</td>
                </tr>
                <tr>
                    <td colspan="3" style="{{ $borderSolid }}">Per Day Salary</td>
                    <td colspan="3" style="{{ $borderSolid }}">Basic Salary / Working Days</td>
                    <td colspan="2" style="text-align: right; {{ $borderSolid }}">
                        {{ number_format($row['basic_salary'], 2) }}/{{ $row['working_days'] }}</td>
                    <td colspan="2" style="text-align: right; font-weight: bold; {{ $borderSolid }}">
                        Rs.{{ number_format($row['per_day_salary'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="3" style="{{ $borderSolid }}">Total Unpaid Leave Deduction</td>
                    <td colspan="3" style="{{ $borderSolid }}">Absent + Half Days + Unpaid Leave</td>
                    <td colspan="2" style="font-weight: bold; {{ $borderSolid }}">Total Deduction</td>
                    <td colspan="2" style="text-align: right; font-weight: bold; {{ $borderSolid }}">
                        Rs.{{ number_format($row['unpaid_leave_deduction'], 2) }}</td>
                </tr>
                <!-- <tr>
                    <td colspan="10" style="height: 15px; border: none;"></td>
                </tr> -->

                <!-- Salary Details -->
                <tr>
                    <th colspan="10"
                        style="background-color: {{ $tealColor }}; color: #ffffff; font-weight: bold; text-align: left; height: 35px; padding-left: 10px; font-size: 13pt; {{ $borderSolid }}">
                        Salary Details
                    </th>
                </tr>
                <tr style="background-color: {{ $tealColor }}; color: #ffffff;">
                    <td colspan="3" style="font-weight: bold; {{ $borderSolid }}">Earnings</td>
                    <td colspan="2" style="font-weight: bold; text-align: right; {{ $borderSolid }}">Amount (Rs.)</td>
                    <td colspan="3" style="font-weight: bold; {{ $borderSolid }}">Deductions</td>
                    <td colspan="2" style="font-weight: bold; text-align: right; {{ $borderSolid }}">Amount (Rs.)</td>
                </tr>

                @php
                    $earningsKeys = array_keys($row['earnings']);
                    $deductionsKeys = array_keys($row['deductions']);
                    $maxLines = max(count($earningsKeys), count($deductionsKeys));
                @endphp

                @for ($i = 0; $i < $maxLines; $i++)
                    <tr>
                        <td colspan="3" style="{{ $borderSolid }}">{{ $earningsKeys[$i] ?? '' }}</td>
                        <td colspan="2" style="text-align: right; {{ $borderSolid }}">
                            {{ isset($earningsKeys[$i]) ? number_format($row['earnings'][$earningsKeys[$i]], 2) : '' }}
                        </td>
                        <td colspan="3" style="{{ $borderSolid }}">{{ $deductionsKeys[$i] ?? '' }}</td>
                        <td colspan="2" style="text-align: right; {{ $borderSolid }}">
                            {{ isset($deductionsKeys[$i]) ? number_format($row['deductions'][$deductionsKeys[$i]], 2) : '' }}
                        </td>
                    </tr>
                @endfor

                <tr>
                    <td colspan="3" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Total
                        Earnings</td>
                    <td colspan="2"
                        style="text-align: right; font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">
                        {{ number_format($row['total_earnings'], 2) }}</td>
                    <td colspan="3" style="font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">Total
                        Deductions</td>
                    <td colspan="2"
                        style="text-align: right; font-weight: bold; background-color: {{ $grayBg }}; {{ $borderSolid }}">
                        {{ number_format($row['total_deductions'], 2) }}</td>
                </tr>

                <tr>
                    <td colspan="7"
                        style="height: 40px; font-size: 14pt; font-weight: bold; padding-left: 10px; background-color: {{ $blueColor }}; color: #ffffff; {{ $borderSolid }}">
                        NET SALARY (Take Home)
                    </td>
                    <td colspan="3"
                        style="text-align: right; height: 40px; font-size: 14pt; font-weight: bold; padding-right: 10px; background-color: {{ $blueColor }}; color: #ffffff; {{ $borderSolid }}">
                        Rs.{{ number_format($row['net_salary'], 2) }}
                    </td>
                </tr>
                {{-- Global separator --}}
                <tr style="border: none;">
                    <td colspan="10" style="height: 40px; border: none; background-color: #ffffff;"></td>
                </tr>
            </tbody>
        </table>
    @empty
        <table style="width: 100%; border: 3px solid {{ $blueColor }};">
            <tr>
                <td colspan="10"
                    style="height: 100px; text-align: center; vertical-align: middle; background-color: #f0f7ff; color: {{ $blueColor }}; font-weight: bold; font-size: 18pt;">
                    NO PAYSLIPS FOUND FOR THIS PERIOD.
                </td>
            </tr>
        </table>
    @endforelse
</body>

</html>