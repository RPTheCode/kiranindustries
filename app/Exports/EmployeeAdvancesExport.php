<?php

namespace App\Exports;

use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class EmployeeAdvancesExport implements FromView, ShouldAutoSize, WithEvents
{
    protected $search;
    protected $fromDate;
    protected $toDate;
    protected $activeBranchId;

    public function __construct($search, $fromDate, $toDate, $activeBranchId)
    {
        $this->search = $search;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->activeBranchId = $activeBranchId;
    }

    public function view(): View
    {
        // 1. Identify "Relevant" Employees
        // Condition A: Employees belonging to the active branch (if selected)
        // Condition B: Employees (from any branch) who have an advance in this active branch context

        $targetEmployeeIds = [];

        // Condition B Logic: Get IDs of employees with advances in this branch
        if ($this->activeBranchId && $this->activeBranchId !== 'all') {
            $advanceUserIdQuery = EmployeeAdvance::whereIn('created_by', getCompanyAndUsersId());

            // Apply same branch filter to identify relevant advances
            $advanceUserIdQuery->where(function ($subQ) {
                $subQ->where('branch_id', $this->activeBranchId)
                    ->orWhere(function ($fallbackQ) {
                        $fallbackQ->whereNull('branch_id')
                            ->whereHas('creator.employee', function ($branchQ) {
                                $branchQ->where('branch_id', $this->activeBranchId);
                            });
                    });
            });

            if ($this->fromDate)
                $advanceUserIdQuery->whereDate('pay_date', '>=', $this->fromDate);
            if ($this->toDate)
                $advanceUserIdQuery->whereDate('pay_date', '<=', $this->toDate);

            $targetEmployeeIds = $advanceUserIdQuery->pluck('employee_id')->toArray();
        }

        // Build Employee Query
        $employeeQuery = User::withoutGlobalScopes()
            ->where('type', 'employee')
            ->with([
                'employee' => function ($q) {
                    $q->withoutGlobalScopes()->with(['branch', 'designation']);
                }
            ]);

        if ($this->activeBranchId && $this->activeBranchId !== 'all') {
            $employeeQuery->where(function ($q) use ($targetEmployeeIds) {
                // Show if in current branch
                $q->whereHas('employee', function ($branchQ) {
                    $branchQ->where('branch_id', $this->activeBranchId);
                })
                    // OR if they have a relevant advance (even if from another branch)
                    ->orWhereIn('id', $targetEmployeeIds);
            });
        }
        // If 'all', we don't filter by branch, so we get everyone naturally.

        if ($this->search) {
            $employeeQuery->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        $employees = $employeeQuery->get();

        // 2. Get Advances within date range
        $advanceQuery = EmployeeAdvance::whereIn('created_by', getCompanyAndUsersId());

        if ($this->fromDate) {
            $advanceQuery->whereDate('pay_date', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $advanceQuery->whereDate('pay_date', '<=', $this->toDate);
        }

        // Apply Branch Filter (Explicit Branch Tracking) on Advances Query too
        if ($this->activeBranchId && $this->activeBranchId !== 'all') {
            $advanceQuery->where(function ($subQ) {
                $subQ->where('branch_id', $this->activeBranchId)
                    ->orWhere(function ($fallbackQ) {
                        $fallbackQ->whereNull('branch_id')
                            ->whereHas('creator.employee', function ($branchQ) {
                                $branchQ->where('branch_id', $this->activeBranchId);
                            });
                    });
            });
        }

        // Filter by fetched employees
        $advanceQuery->whereIn('employee_id', $employees->pluck('id'));

        $advances = $advanceQuery->orderBy('pay_date', 'asc')->get();

        // 3. Process Data
        // Collect unique dates
        $uniqueDates = $advances->pluck('pay_date')->map(function ($date) {
            return $date->format('d.m.Y');
        })->unique()->sort()->values()->toArray();

        // 4. Fetch All-Time Advances for these employees to calculate overall metrics
        $allTimeAdvanceQuery = EmployeeAdvance::whereIn('created_by', getCompanyAndUsersId())
            ->whereIn('employee_id', $employees->pluck('id'));

        // Apply Branch Filter if necessary (optional - usually all-time means all-time across all branches or current context?)
        // User said "total advance abhi tak usne jitne liya wo sara ayega"
        // In this system, branch isolation is quite strict, but usually advance is employee-level.
        // However, we should probably stick to the active branch if it's set, or the company if global.
        // Let's use the same branch logic as identification if branch is set.
        if ($this->activeBranchId && $this->activeBranchId !== 'all') {
            $allTimeAdvanceQuery->where(function ($subQ) {
                $subQ->where('branch_id', $this->activeBranchId)
                    ->orWhere(function ($fallbackQ) {
                        $fallbackQ->whereNull('branch_id')
                            ->whereHas('creator.employee', function ($branchQ) {
                                $branchQ->where('branch_id', $this->activeBranchId);
                            });
                    });
            });
        }

        $allAdvances = $allTimeAdvanceQuery->get();

        // Map data by employee
        $mappedData = [];

        foreach ($employees as $user) {
            $empDetails = $user->employee;

            // Get advances for this specific employee (Current Period)
            $periodAdvances = $advances->where('employee_id', $user->id);

            // Calculate Period Totals
            $periodTotalAmount = $periodAdvances->sum('amount');

            // Get advances for this specific employee (All Time)
            $employeeAllAdvances = $allAdvances->where('employee_id', $user->id);
            $allTimeTotal = $employeeAllAdvances->sum('amount');
            $allTimePaid = $employeeAllAdvances->sum('paid_amount');
            $allTimePending = $allTimeTotal - $allTimePaid;

            // Map advances by date (Current Period)
            $advancesByDate = [];
            foreach ($periodAdvances as $advance) {
                $dateKey = $advance->pay_date->format('d.m.Y');
                if (!isset($advancesByDate[$dateKey])) {
                    $advancesByDate[$dateKey] = 0;
                }
                $advancesByDate[$dateKey] += $advance->amount;
            }

            // Get latest remark (from all-time for better context, or period?)
            // Usually remark on the latest advance is most relevant.
            $latestAdvance = $employeeAllAdvances->sortByDesc('pay_date')->first();
            $remark = $latestAdvance ? $latestAdvance->remarks : '';

            $accountHolderName = $empDetails ? $empDetails->account_holder_name : '';
            $employeeName = $user->name;

            // Name of Member logic: If account holder name exists, use it, else use employee name
            $nameOfMember = !empty($accountHolderName) ? $accountHolderName : $employeeName;

            $mappedData[] = [
                'employee_id' => $empDetails ? $empDetails->employee_id : '-',
                'designation' => ($empDetails && $empDetails->designation && $empDetails->designation->name) ? $empDetails->designation->name : '-',
                'bank_name' => ($empDetails && $empDetails->bank_name) ? $empDetails->bank_name : '-',
                'ifsc_code' => ($empDetails && $empDetails->bank_identifier_code) ? $empDetails->bank_identifier_code : '-',
                'account_number' => ($empDetails && $empDetails->account_number) ? $empDetails->account_number : '-',
                'employee_name' => $employeeName ?: '-',
                'name_of_member' => $nameOfMember ?: '-',
                'remark' => $remark ?: '-',
                'advances' => $advancesByDate,
                'period_total' => $periodTotalAmount,
                'total_advance' => $allTimeTotal,
                'total_paid' => $allTimePaid,
                'pending_amount' => $allTimePending,
            ];
        }

        // Title
        $title = 'EMPLOYEE ADVANCES';
        if ($this->activeBranchId && $this->activeBranchId !== 'all') {
            $branch = \App\Models\Branch::find($this->activeBranchId);
            if ($branch) {
                $title = strtoupper($branch->name);
            }
        }

        // Calculate Totals per Date column AND Grand Totals
        $totalByDate = [];
        $grandPeriodTotal = 0;
        $grandTotalAdvance = 0;
        $grandTotalPaid = 0;
        $grandTotalPending = 0;

        foreach ($uniqueDates as $date) {
            $sumForDate = 0;
            foreach ($mappedData as $empData) {
                if (isset($empData['advances'][$date])) {
                    $sumForDate += $empData['advances'][$date];
                }
            }
            $totalByDate[$date] = $sumForDate;
        }

        // Calculate Grand Totals for summary columns
        foreach ($mappedData as $empData) {
            $grandPeriodTotal += $empData['period_total'];
            $grandTotalAdvance += $empData['total_advance'];
            $grandTotalPaid += $empData['total_paid'];
            $grandTotalPending += $empData['pending_amount'];
        }

        return view('exports.employee-advances', [
            'employees' => $mappedData,
            'dates' => $uniqueDates,
            'title' => $title,
            'totalByDate' => $totalByDate,
            'grandPeriodTotal' => $grandPeriodTotal,
            'grandTotalAdvance' => $grandTotalAdvance,
            'grandTotalPaid' => $grandTotalPaid,
            'grandTotalPending' => $grandTotalPending,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set default font
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri');
                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11);

                // Set Column Widths (Explicitly)
                $sheet->getColumnDimension('A')->setWidth(5);  // SR
                $sheet->getColumnDimension('B')->setWidth(15); // Des
                $sheet->getColumnDimension('C')->setWidth(10); // BANK
                $sheet->getColumnDimension('D')->setWidth(15); // IFSC
                $sheet->getColumnDimension('E')->setWidth(20); // Account Number
                $sheet->getColumnDimension('F')->setWidth(25); // Employee Name
                $sheet->getColumnDimension('G')->setWidth(25); // Name of Member
                $sheet->getColumnDimension('H')->setWidth(20); // Remark
    
                // Format Account Number column (H) as Text to prevent scientific notation
                $sheet->getStyle('H')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

                // Alignments
                $sheet->getStyle('A:Z')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
