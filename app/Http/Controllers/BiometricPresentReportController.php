<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EsslLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class BiometricPresentReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date ?? Carbon::today()->format('Y-m-d');
        $timePeriod = $request->time_period ?? 'full_day';
        $branches = \App\Models\Branch::where('status', 'active')->get(['id', 'name']);
        $branchId = $request->branch_id ?? 'all';

        return Inertia::render('hr/attendance/BiometricPresentReport', [
            'branches' => $branches,
            'filters' => [
                'date' => $date,
                'time_period' => $timePeriod,
                'branch_id' => $branchId,
            ],
        ]);
    }

    public function generatePdf(Request $request)
    {
        $date = $request->date ?? Carbon::today()->format('Y-m-d');
        $timePeriod = $request->time_period ?? 'full_day';
        $branchId = $request->branch_id ?? session('active_branch_id');

        // Determine period title
        $periodTitle = strtoupper(str_replace('_', ' ', $timePeriod));

        $presentEmployeeIds = [];

        // Direct real-time sync and then query from local logs
        try {
            // 1. Trigger real-time sync before generating report
            Artisan::call('essl:sync', ['--date' => $date]);

            // 2. Query from BiometricAttendance table
            $query = \App\Models\BiometricAttendance::whereHas('employee.user', function ($q) {
                    $q->where('status', 'active');
                })
                ->whereDate('attendance_date', $date)
                ->whereIn('status', ['P', 'MIS', 'HD']);

            // Filter by time period
            if ($timePeriod !== 'full_day') {
                switch ($timePeriod) {
                    case 'morning':
                        $query->whereTime('in_time', '<', '12:00:00');
                        break;
                    case 'after_lunch':
                        $query->whereTime('in_time', '>=', '12:00:00')->whereTime('in_time', '<', '16:00:00');
                        break;
                    case 'evening':
                        $query->whereTime('in_time', '>=', '16:00:00')->whereTime('in_time', '<', '20:00:00');
                        break;
                    case 'night':
                        $query->whereTime('in_time', '>=', '20:00:00');
                        break;
                }
            }

            $presentEmployeeIds = $query->distinct()->pluck('employee_id')->toArray();

        } catch (\Exception $e) {
            \Log::error("Biometric report data fetch failed: " . $e->getMessage());
            $presentEmployeeIds = \App\Models\BiometricAttendance::whereHas('employee.user', function ($q) {
                    $q->where('status', 'active');
                })
                ->whereDate('attendance_date', $date)
                ->whereIn('status', ['P', 'MIS', 'HD'])
                ->distinct()
                ->pluck('employee_id')
                ->toArray();
        }

        // Fetch Departments and their Employees who matched the machine's UserIds
        // We match by user_id, emy_code, or essl_id to ensure we find the correct employee
        $departmentsQuery = Department::withoutGlobalScopes()->orderBy('name', 'asc');

        if ($branchId && $branchId !== 'all') {
            $departmentsQuery->where('branch_id', $branchId);
        }

        $departments = $departmentsQuery->with([
            'employees' => function ($q) use ($presentEmployeeIds, $branchId) {
                $q->withoutGlobalScopes()
                    ->whereIn('id', $presentEmployeeIds);

                if ($branchId && $branchId !== 'all') {
                    $q->where('branch_id', $branchId);
                }

                // Sort employees alphabetically by user name
                $q->join('users', 'employees.user_id', '=', 'users.id')
                    ->orderBy('users.name', 'asc')
                    ->select('employees.*');

                $q->with(['user', 'shift']);
            }
        ])->get();

        // Only show departments that have present employees
        $departments = $departments->filter(function ($dept) {
            return $dept->employees->count() > 0;
        });

        // Get Dynamic Company/Branch Name
        $companyName = getSetting('titleText', 'KIRAN INDUSTRIES');
        $selectedBranchId = $branchId ?? session('active_branch_id');
        if ($selectedBranchId && $selectedBranchId !== 'all') {
            $branch = \App\Models\Branch::find($selectedBranchId);
            if ($branch) {
                $companyName .= ' - ' . strtoupper($branch->name);
            }
        }

        $data = [
            'reportData' => $departments,
            'reportTitle' => "BIOMETRIC PRESENT REPORT",
            'reportType' => "BIOMETRIC REPORT",
            'period' => "DATE: " . Carbon::parse($date)->format('d/m/Y') . " (" . $periodTitle . ")",
            'printDate' => now()->format('d/m/Y H:i:s'),
            'companyName' => $companyName,
            'totalEmployees' => $presentEmployeeIds ? count($presentEmployeeIds) : 0,
        ];

        $pdf = Pdf::setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'helvetica'
        ])->loadView('reports.biometric_present', $data);
        $pdf->setPaper('a4', 'portrait');

        // Generate dynamic filename: BranchName_biometricreport_date.pdf
        $branchName = 'All_Branches';
        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $branchName = str_replace(' ', '_', $branch->name);
            }
        }
        $formattedDate = Carbon::parse($date)->format('Y_m_d');
        $fileName = "{$branchName}_biometricreport_{$formattedDate}.pdf";

        $pdfOutput = $pdf->output();
        $filePath = 'reports/' . str_replace('.pdf', '', $fileName) . '_' . time() . '.pdf';
        
        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfOutput);
        
        if (!$request->get('is_chunk')) {
            \App\Models\ReportDownload::create([
                'user_id' => auth()->id() ?? 1,
                'report_name' => str_replace('.pdf', '', $fileName),
                'file_path' => $filePath,
                'status' => 'completed',
                'progress' => 100,
                'filters' => $request->all(),
            ]);
        }

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
    }
}
