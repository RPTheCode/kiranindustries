<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeProfileExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $employeeId;

    public function __construct($employeeId)
    {
        $this->employeeId = $employeeId;
    }

    public function view(): View
    {
        $employee = User::with([
            'employee.branch',
            'employee.department',
            'employee.designation',
            'employee.shift',
            'employee.attendancePolicy',
            'employee.documents.documentType',
        ])->findOrFail($this->employeeId);

        // Fetch related employments (other branches)
        $relatedEmployments = collect();
        $employeeId = $employee->employee->employee_id ?? null;

        if ($employeeId) {
            $relatedEmployments = User::whereHas('employee', function ($query) use ($employeeId) {
                $query->withoutGlobalScopes()->where('employee_id', $employeeId);
            })->with([
                        'employee' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.branch',
                        'employee.department' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.designation' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.shift' => function ($query) {
                            $query->withoutGlobalScopes();
                        },
                        'employee.attendancePolicy' => function ($query) {
                            $query->withoutGlobalScopes();
                        }
                    ])
                ->get();
        }

        // Fetch work history
        $workHistory = \App\Models\EmployeeWorkHistory::whereIn('employee_id', $relatedEmployments->pluck('id'))
            ->with(['skills', 'creator'])
            ->orderBy('start_date', 'desc')
            ->get();

        return view('exports.employee-profile', [
            'employee' => $employee,
            'relatedEmployments' => $relatedEmployments,
            'workHistory' => $workHistory
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true, 'size' => 16]],
        ];
    }
}
