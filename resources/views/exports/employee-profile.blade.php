<table>
    <thead>
        <tr>
            <td colspan="4" style="font-weight: bold; font-size: 16px; text-align: center;">{{ $employee->name }} -
                Employee Profile</td>
        </tr>
    </thead>
    <tbody>

        {{-- Basic Information --}}
        <tr>
            <td colspan="4"
                style="font-weight: bold; background-color: #dbeafe; border: 1px solid #000000; text-align: center;">
                Basic Information</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Name</td>
            <td>{{ $employee->name }}</td>
            <td style="font-weight: bold;">Email</td>
            <td>{{ $employee->email }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Employee ID</td>
            <td>{{ $employee->employee->employee_id ?? '-' }}</td>
            <td style="font-weight: bold;">Phone</td>
            <td>{{ $employee->employee->phone ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Date of Birth</td>
            <td>{{ $employee->employee->date_of_birth ?? '-' }}</td>
            <td style="font-weight: bold;">Gender</td>
            <td>{{ ucfirst($employee->employee->gender ?? '-') }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Status</td>
            <td>{{ ucfirst($employee->status ?? '-') }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="4"></td>
        </tr>

        {{-- Loop through all employments (branches) --}}
        @foreach($relatedEmployments as $emp)
            <tr>
                <td colspan="4"
                    style="font-weight: bold; background-color: #dbeafe; border: 1px solid #000000; text-align: center;">
                    Employment Details - {{ $emp->employee->branch->name ?? 'Unknown Branch' }}
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Branch</td>
                <td>{{ $emp->employee->branch->name ?? '-' }}</td>
                <td style="font-weight: bold;">Department</td>
                <td>{{ $emp->employee->department->name ?? '-' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Designation</td>
                <td>{{ $emp->employee->designation->name ?? '-' }}</td>
                <td style="font-weight: bold;">Date of Joining</td>
                <td>{{ $emp->employee->date_of_joining ?? '-' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Employment Type</td>
                <td>{{ $emp->employee->employment_type ?? '-' }}</td>
                <td style="font-weight: bold;">Shift</td>
                <td>
                    @if($emp->employee->shift)
                        {{ $emp->employee->shift->name }} ({{ $emp->employee->shift->start_time }} -
                        {{ $emp->employee->shift->end_time }})
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Attendance Policy</td>
                <td>{{ $emp->employee->attendancePolicy->name ?? '-' }}</td>
                <td style="font-weight: bold;">Skills</td>
                <td>
                    @if($emp->employee->skills && $emp->employee->skills->isNotEmpty())
                        {{ $emp->employee->skills->pluck('name')->join(', ') }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">UAN Number</td>
                <td>{{ $emp->employee->uan_number ?? '-' }}</td>
                <td style="font-weight: bold;">ESIC Number</td>
                <td>{{ $emp->employee->esic_number ?? '-' }}</td>
            </tr>
            {{-- Merged ESIC Number into previous row --}}
            <tr>
                <td colspan="4"></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4"
                style="font-weight: bold; background-color: #dbeafe;border: 1px solid #000000; text-align: center;">
                Contact Information</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Address Line 1</td>
            <td>{{ $employee->employee->address_line_1 ?? '-' }}</td>
            <td style="font-weight: bold;">Address Line 2</td>
            <td>{{ $employee->employee->address_line_2 ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">City</td>
            <td>{{ $employee->employee->city ?? '-' }}</td>
            <td style="font-weight: bold;">State</td>
            <td>{{ $employee->employee->state ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Country</td>
            <td>{{ $employee->employee->country ?? '-' }}</td>
            <td style="font-weight: bold;">Postal Code</td>
            <td>{{ $employee->employee->postal_code ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="4" style="font-weight: bold; font-style: italic;">Emergency Contact</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Name</td>
            <td>{{ $employee->employee->emergency_contact_name ?? '-' }}</td>
            <td style="font-weight: bold;">Relationship</td>
            <td>{{ $employee->employee->emergency_contact_relationship ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Phone</td>
            <td>{{ $employee->employee->emergency_contact_number ?? '-' }}</td>
            <td style="font-weight: bold;">Address</td>
            <td>{{ $employee->employee->emergency_contact_address ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="4"></td>
        </tr>

        {{-- Banking Information --}}
        <tr>
            <td colspan="4"
                style="font-weight: bold; background-color: #dbeafe; border: 1px solid #000000; text-align: center;">
                Banking Information</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Bank Name</td>
            <td>{{ $employee->employee->bank_name ?? '-' }}</td>
            <td style="font-weight: bold;">Account Holder</td>
            <td>{{ $employee->employee->account_holder_name ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Account Number</td>
            <td>{{ $employee->employee->account_number ?? '-' }}</td> {{-- Ensure to force string if excel
            converts --}}
            <td style="font-weight: bold;">IFSC/BIC/SWIFT</td>
            <td>{{ $employee->employee->bank_identifier_code ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Bank Branch</td>
            <td>{{ $employee->employee->bank_branch ?? '-' }}</td>
            <td style="font-weight: bold;">Tax Payer ID</td>
            <td>{{ $employee->employee->tax_payer_id ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="4"></td>
        </tr>

        {{-- Documents --}}
        <tr>
            <td colspan="4"
                style="font-weight: bold; background-color: #dbeafe; border: 1px solid #000000; text-align: center;">
                Documents</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Document Type</td>
            <td style="font-weight: bold;">Expiry Date</td>
            <td style="font-weight: bold;">Status</td>
            <td></td>
        </tr>
        @if($employee->employee && $employee->employee->documents && $employee->employee->documents->isNotEmpty())
            @foreach($employee->employee->documents as $doc)
                <tr>
                    <td>{{ $doc->documentType->name ?? 'Unknown' }}</td>
                    <td>{{ $doc->expiry_date ?? '-' }}</td>
                    <td>{{ ucfirst($doc->verification_status) }}</td>
                    <td></td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="4" style="text-align: center;">-</td>
            </tr>
        @endif
        <tr>
            <td colspan="4"></td>
        </tr>

        {{-- Work History --}}
        <tr>
            <td colspan="4"
                style="font-weight: bold; background-color: #dbeafe; border: 1px solid #000000; text-align: center;">
                Work History</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Site/Company</td>
            <td style="font-weight: bold;">Start Date</td>
            <td style="font-weight: bold;">End Date</td>
            <td style="font-weight: bold;">Skills Used</td>
        </tr>
        @forelse($workHistory as $history)
            <tr>
                <td>{{ $history->site_name }}</td>
                <td>{{ $history->start_date }}</td>
                <td>{{ $history->end_date ?? 'Present' }}</td>
                <td>
                    @if($history->skills)
                        {{ $history->skills->pluck('name')->join(', ') }}
                    @else
                        No Data
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">No Data Found</td>
            </tr>
        @endforelse

    </tbody>
</table>