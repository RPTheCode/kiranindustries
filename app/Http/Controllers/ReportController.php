<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Department;
use App\Models\Section;
use App\Models\BiometricAttendance;
use App\Models\Category;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\PayrollParameter;
use App\Models\DailyProductionAttendanceEntry;
use App\Models\MaterialItem;
use App\Exports\WorkerwiseAttendanceExport;
use App\Services\ActivityLogger;
use App\Services\AttendanceMatrixReportBuilder;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    use \App\Traits\LogsActivity;

    /**
     * Display the reports landing page.
     */
    public function index(Request $request)
    {
        $branchId = decryptId($request->get('branch_id') ?? session('active_branch_id') ?? (auth()->user()->branch_id ?? 1));

        $deptQuery = Department::withoutGlobalScopes()->where('status', 'active');
        $sectQuery = Section::withoutGlobalScopes()->where('status', 'active');
        $catQuery = \App\Models\Category::withoutGlobalScopes()->where('status', 'active');

        if ($branchId && $branchId !== 'all') {
            $deptQuery->where('branch_id', $branchId);
            $sectQuery->where('branch_id', $branchId);
            $catQuery->where('branch_id', $branchId);
        }

        $empQuery = \App\Models\Employee::with('user')->whereHas('user', function ($q) {
            $q->where('status', 'active');
        });

        if ($branchId && $branchId !== 'all') {
            $empQuery->where('branch_id', $branchId);
        }

        // Build branches with encrypted IDs; track active branch encrypted value
        $encryptedUserBranchId = null;
        $branches = \App\Models\Branch::where('status', 'active')->select('id', 'name')->get()->map(function ($branch) use ($branchId, &$encryptedUserBranchId) {
            $encId = encryptId($branch->id);
            if ($branch->id == $branchId) {
                $encryptedUserBranchId = $encId;
            }
            return [
                'id' => $encId,
                'name' => $branch->name,
            ];
        });

        return Inertia::render('hr/reports/index', [
            'departments' => $deptQuery->select('id', 'name')->get()->map(function ($dept) {
                return [
                    'id' => encryptId($dept->id),
                    'name' => $dept->name,
                ];
            }),
            'sections' => $sectQuery->select('id', 'name')->get()->map(function ($sect) {
                return [
                    'id' => encryptId($sect->id),
                    'name' => $sect->name,
                ];
            }),
            'categories' => $catQuery->select('id', 'name')->get()->map(function ($cat) {
                return [
                    'id' => encryptId($cat->id),
                    'name' => $cat->name,
                ];
            }),
            'branches' => $branches,
            'employees' => $empQuery->get()->map(function ($emp) {
                return [
                    'id' => encryptId($emp->id),
                    'code' => $emp->emy_code ?? $emp->employee_id,
                    'name' => $emp->user->name ?? 'N/A'
                ];
            })->sortBy('name')->values(),
            'userBranchId' => $encryptedUserBranchId,
        ]);
    }

    public function dailyReports(Request $request)
    {
        return $this->renderReportModule($request, 'Attendance Reports', 'daily');
    }

    public function monthlyReports(Request $request)
    {
        return $this->renderReportModule($request, 'Monthly Reports', 'monthly');
    }

    public function masterReports(Request $request)
    {
        return $this->renderReportModule($request, 'Master Reports', 'master');
    }

    public function previewData(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'section' => decryptId($request->get('section')),
            'category' => decryptId($request->get('category')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);

        $branchId = $request->get('branch_id');
        $reportId = $request->get('report_id');

        $fromDate = $request->get('from_date') ? Carbon::parse($request->get('from_date')) : now();
        $toDate = $request->get('to_date') ? Carbon::parse($request->get('to_date')) : now();

        $query = \App\Models\BiometricAttendance::with([
            'employee' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'employee.user',
            'employee.department',
            'employee.section',
            'employee.designation',
            'employee.branch',
            'logs'
        ])
            ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        $status = $request->get('status');
        if ($reportId === 'mispunch_dedicated') {
            $status = 'MIS';
            applyMispunchReportDateScope($query);
        } elseif (!$status || $status === 'all') {
            if (str_starts_with($reportId, 'att_')) {
                $status = 'P';
            } else {
                $status = 'all';
            }
        }

        // Daily single report: only employees who punched IN on selected date(s)
        if ($reportId === 'biometric_single') {
            $query->whereNotNull('in_time');
        }

        if ($status && $status !== 'all') {
            if ($status === 'P') {
                $query->where(function ($q) {
                    $q->whereIn('status', ['P', 'HD', 'OD', 'CO'])
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'MIS')->whereNotNull('in_time');
                        });
                });
            } elseif ($status === 'overtime') {
                $query->where('ot_minutes', '>', 0);
            } elseif ($status === 'latein') {
                $query->where('late_in', '!=', '0m')->whereNotNull('late_in');
            } elseif ($status === 'earlyout') {
                $query->where('early_out', '!=', '0m')->whereNotNull('early_out');
            } else {
                $query->where('status', $status);
            }
        }

        // Apply filters through employee relationship
        // Use withoutGlobalScopes for employee check to match PDF logic
        \Log::info("Preview Filters:", [
            'report_id' => $request->get('report_id'),
            'branch_id' => $branchId,
            'dept' => $request->get('department'),
            'employee_id' => $request->get('employee_id'),
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]);

        $query->whereHas('employee', function ($q) use ($request, $branchId) {
            $q->withoutGlobalScopes();
            if ($branchId && $branchId !== 'all') {
                $q->where('branch_id', $branchId);
            }
            if ($request->get('department') && $request->get('department') !== 'all') {
                $q->where('department_id', $request->get('department'));
            }
            if ($request->get('section') && $request->get('section') !== 'all') {
                $q->where('section_id', $request->get('section'));
            }
            if ($request->get('category') && $request->get('category') !== 'all') {
                $q->where('category_id', $request->get('category'));
            }
            if ($request->get('employee_id') && $request->get('employee_id') !== 'all') {
                $q->where('id', $request->get('employee_id'));
            } else {
                $q->whereHas('user', function ($sq) {
                    $sq->where('status', 'active');
                });
            }
        });

        \Log::info("Preview Data Query SQL: " . $query->toSql());
        \Log::info("Preview Data Query Bindings: " . json_encode($query->getBindings()));
        \Log::info("Preview Data Total Records: " . $query->count());

        $statusMinutes = max(0, (int) $request->get('status_minutes', 0));
        $needsMinutesFilter = $statusMinutes > 0 && in_array($status, ['latein', 'earlyout'], true);
        $perPage = 15;
        $page = max(1, (int) $request->get('page', 1));

        if ($needsMinutesFilter) {
            $filtered = $query->orderBy('attendance_date', 'desc')
                ->get()
                ->filter(fn($record) => passesStatusMinutesThreshold($status, $record, $statusMinutes))
                ->values();
            $total = $filtered->count();
            $slice = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
            $attendance = new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $attendance = $query->orderBy('attendance_date', 'desc')->paginate($perPage, ['*'], 'page', $page);
        }

        $items = collect($attendance->items())->map(function ($record) {
            $isInManual = false;
            $isOutManual = false;
            if ($record->is_manual) {
                if ($record->logs && $record->logs->isNotEmpty()) {
                    $isInManual = $record->logs->where('punch_type', 'IN')->where('is_manual', true)->isNotEmpty();
                    $isOutManual = $record->logs->where('punch_type', 'OUT')->where('is_manual', true)->isNotEmpty();
                } else {
                    if ($record->status === 'MIS') {
                        if ($record->in_time && $record->in_count > 0) {
                            $isInManual = true;
                        } elseif ($record->out_time && $record->out_count > 0) {
                            $isOutManual = true;
                        }
                    } else {
                        $isInManual = $record->in_time ? true : false;
                        $isOutManual = $record->out_time ? true : false;
                    }
                }
            }

            $inTime = $record->in_time ? Carbon::parse($record->in_time)->format('H:i') : '-';
            $outTime = $record->out_time ? Carbon::parse($record->out_time)->format('H:i') : '-';

            if ($isInManual && $inTime !== '-') {
                $inTime .= ' (M)';
            }
            if ($isOutManual && $outTime !== '-') {
                $outTime .= ' (M)';
            }

            $date = Carbon::parse($record->attendance_date)->format('d/m/Y');

            $issue = '-';
            if ($record->status === 'MIS') {
                $issues = [];
                if (!$record->in_time) {
                    $issues[] = 'Missing IN';
                }
                if (!$record->out_time) {
                    $issues[] = 'Missing OUT';
                }
                $issue = !empty($issues) ? implode(', ', $issues) : 'Incomplete punch';
            }

            $punchPairs = $record->status === 'MIS' ? buildMispunchPairsForReport($record) : [];

            return [
                'code' => $record->employee?->emy_code ?? ($record->employee?->employee_id ?? $record->employee_code),
                'name' => $record->employee?->user?->name ?? 'N/A',
                'department' => $record->employee?->department?->name ?? 'N/A',
                'section' => $record->employee?->section?->name ?? 'N/A',
                'branch' => $record->employee?->branch?->name ?? 'N/A',
                'shift' => $record->shift_code ?: ($record->employee?->shift?->name ?? '-'),
                'date' => $date,
                'in_time' => $inTime,
                'out_time' => $outTime,
                'log_details' => $record->log_details,
                'late_in' => ($record->late_in && $record->late_in !== '0m') ? $record->late_in : '-',
                'early_out' => ($record->early_out && $record->early_out !== '0m') ? $record->early_out : '-',
                'status' => $record->status,
                'mis_punch' => ($record->status === 'MIS') ? 'YES' : 'NO',
                'issue' => $issue,
                'punch_pairs' => $punchPairs,
                'total_hours' => $record->total_minutes ? floor($record->total_minutes / 60) . 'h ' . ($record->total_minutes % 60) . 'm' : '-',
                'over_time' => ($record->ot_minutes > 0) ? floor($record->ot_minutes / 60) . 'h ' . ($record->ot_minutes % 60) . 'm' : '-',
                'duty_value' => $record->duty_value ?? '0.0',
                'designation' => $record->employee?->designation?->name ?? '---',
                'lunch_time' => $record->employee?->lunch_time ? $record->employee->lunch_time . ' Min' : '---',
            ];
        });

        \Log::info("Preview Mapped Items Count: " . $items->count());
        if ($items->count() > 0) {
            \Log::info("First Item Sample:", (array) $items->first());
        }

        return response()->json([
            'data' => $items,
            'total' => $attendance->total(),
            'current_page' => $attendance->currentPage(),
            'last_page' => $attendance->lastPage()
        ]);
    }

    /**
     * Export workerwise attendance matrix to Excel (same layout/data as PDF).
     */
    public function exportExcel(Request $request): Response
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'section' => decryptId($request->get('section')),
            'category' => decryptId($request->get('category')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);

        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $reportId = $request->get('report_id');
        
        $customPosterReports = ['bank_transfer', 'loan_ledger', 'nominee_register', 'salary_ctc'];
        if (in_array($reportId, $customPosterReports)) {
            return $this->exportCustomPosterExcel($request);
        }

        $hourlyByReport = [
            'att_worker' => 'N',
            'att_worker_h' => 'Y',
            'att_worker_t' => 'T',
            'att_worker_pa' => 'A',
        ];

        if (!array_key_exists($reportId, $hourlyByReport)) {
            abort(400, 'Excel export is only available for workerwise attendance and custom poster reports.');
        }

        $hourlyType = $request->get('hourly_type');
        if (!in_array($hourlyType, ['N', 'Y', 'T', 'A'], true)) {
            $request->merge(['hourly_type' => $hourlyByReport[$reportId]]);
        }

        $data = AttendanceMatrixReportBuilder::build($request, 'att_worker');

        if ($data === null) {
            abort(404, 'No employees found for the selected filters.');
        }

        if (empty($data['reportData'])) {
            abort(404, 'No attendance data found for the selected period.');
        }

        $hourlyType = $data['hourlyType'] ?? $request->get('hourly_type', 'N');
        $typeSlug = match ($hourlyType) {
            'Y' => 'Hourly',
            'T' => 'Time',
            'A' => 'PA_Status',
            default => 'Numeric',
        };

        $fromDate = $request->get('from_date') ? Carbon::parse($request->get('from_date')) : now();
        $toDate = $request->get('to_date') ? Carbon::parse($request->get('to_date')) : $fromDate;
        $fileName = sprintf(
            'Workerwise_Attendance_%s_%s_to_%s.xlsx',
            $typeSlug,
            $fromDate->format('d_m_Y'),
            $toDate->format('d_m_Y')
            
        );

        return Excel::download(new WorkerwiseAttendanceExport($data), $fileName);
    }

    private function exportCustomPosterExcel(Request $request)
    {
        $request->merge(['is_excel_export' => true]);
        $reportId = $request->get('report_id');
        $fileName = strtoupper($reportId) . '_' . now()->format('d_m_Y') . '.xlsx';
        
        switch ($reportId) {
            case 'bank_transfer':
                $result = $this->generateBankTransferReport($request);
                return Excel::download(new \App\Exports\BankTransferExport($result['data'], $result['companyTitle']), $fileName);
            case 'loan_ledger':
                $result = $this->generateLoanLedgerReport($request);
                return Excel::download(new \App\Exports\LoanLedgerExport($result['data'], $result['companyTitle']), $fileName);
            case 'nominee_register':
                $result = $this->generateNomineeDeclarationReport($request);
                return Excel::download(new \App\Exports\NomineeRegisterExport($result['data'], $result['companyTitle']), $fileName);
            case 'salary_ctc':
                $result = $this->generateSalaryStructureReport($request);
                return Excel::download(new \App\Exports\SalaryCtcExport($result['data'], $result['companyTitle']), $fileName);
            default:
                abort(400, 'Invalid custom report_id');
        }
    }

    public function biometricDedicated(Request $request)
    {
        if ($request->has('report_id') && $request->has('from_date')) {
            return $this->generate($request);
        }
        return $this->renderReportModule('Biometric Dedicated Report', 'daily');
    }

    public function mispunchReport(Request $request)
    {
        // Force the report_id to mispunch_dedicated and status to MIS
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'section' => decryptId($request->get('section')),
            'category' => decryptId($request->get('category')),
            'employee_id' => decryptId($request->get('employee_id')),
            'report_id' => 'mispunch_dedicated',
            'status' => 'MIS'
        ]);

        return $this->generateBiometricReport($request);
    }

    public function mispunchFormPdf(Request $request)
    {
        $idsStr = $request->get('ids');
        if (!$idsStr) {
            abort(400, 'No records selected for printing.');
        }

        $ids = explode(',', $idsStr);
        
        $records = \App\Models\BiometricAttendance::with(['employee.user', 'employee.department', 'employee.designation', 'employee.shift.slots'])
            ->whereIn('id', $ids)
            ->orderBy('employee_code')
            ->get();

        $reportData = [];
        foreach ($records as $record) {
            $employee = resolveEmployeeForBiometricRecord($record);
            if (!$employee) {
                continue;
            }

            $reportData[] = buildMispunchReportRowFromRecord($record, $employee);
        }

        usort($reportData, fn ($a, $b) => strnatcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')));

        // Get logo
        $logoDark = getSetting('logoDark');
        $logo = null;
        if ($logoDark) {
            $possiblePaths = [
                public_path('storage/media/' . $logoDark),
                public_path('storage/' . $logoDark),
                storage_path('app/public/media/' . $logoDark),
                storage_path('app/public/' . $logoDark)
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    break;
                }
            }
        }
        
        if (!$logo) {
            $path = public_path('assets/images/logo.png');
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES PVT LTD.');
        
        $branchId = session('active_branch_id');
        $branchName = '';
        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $branchName = strtoupper($branch->name);
            }
        }
        
        if ($branchName) {
            $companyTitle .= ' - ' . $branchName;
        }

        $data = [
            'reportData' => $reportData,
            'logo' => $logo,
            'companyName' => $companyTitle,
            'printDate' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('reports.mispunch_form_pdf', $data)->setPaper('a4', 'portrait');

        $branchId = session('active_branch_id');
        $branchIdForLog = ($branchId && $branchId !== 'all') ? (int) $branchId : null;
        ActivityLogger::log(
            'Report',
            'generated',
            'Generated MisPunch correction form PDF for '.count($reportData).' employee(s)',
            $branchIdForLog
        );

        return $pdf->stream('MISSED_PUNCH_FORM.pdf');
    }

    public function download24hMispunchForms(Request $request)
    {
        $branchId = session('active_branch_id');
        $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
        
        $query = BiometricAttendance::with(['employee.user', 'employee.department', 'employee.designation', 'employee.shift.slots'])
            ->where('attendance_date', $yesterday)
            ->where('status', 'MIS')
            ->orderBy('employee_code');
            
        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            if ($request->expectsJson() || $request->boolean('inline')) {
                return response('No mispunch records found for yesterday ('.$yesterday.').', 404)
                    ->header('Content-Type', 'text/plain');
            }

            return redirect()->back()->with('error', 'No mispunch records found for yesterday ('.$yesterday.').');
        }

        $reportData = $records->map(function ($record) {
            $employee = resolveEmployeeForBiometricRecord($record);
            if (!$employee) {
                return null;
            }

            return buildMispunchReportRowFromRecord($record, $employee);
        })->filter(function ($row) {
            return $row && !empty($row['has_incomplete']);
        })->values()->all();

        usort($reportData, fn ($a, $b) => strnatcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')));

        $logo = null;
        $settingLogo = getSetting('app_logo');
        
        if ($settingLogo) {
            $possiblePaths = [
                public_path('storage/' . $settingLogo),
                public_path($settingLogo),
                storage_path('app/public/' . $settingLogo)
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    break;
                }
            }
        }
        
        if (!$logo) {
            $path = public_path('assets/images/logo.png');
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES PVT LTD.');
        
        $branchName = '';
        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $branchName = strtoupper($branch->name);
            }
        }
        
        if ($branchName) {
            $companyTitle .= ' - ' . $branchName;
        }

        $data = [
            'reportData' => $reportData,
            'logo' => $logo,
            'companyName' => $companyTitle,
            'printDate' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('reports.mispunch_form_pdf', $data)->setPaper('a4', 'portrait');

        $branchIdForLog = ($branchId && $branchId !== 'all') ? (int) $branchId : null;
        ActivityLogger::log(
            'Report',
            'downloaded',
            'Downloaded 24h MisPunch forms PDF for '.\Carbon\Carbon::parse($yesterday)->format('d M Y')
            .' ('.$records->count().' employee'.($records->count() === 1 ? '' : 's').')',
            $branchIdForLog
        );

        $filename = '24H_MISPUNCH_FORMS_'.$yesterday.'.pdf';

        // Open in new tab (inline) — attachment only when explicitly requested
        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function downloadBirthdaysMonthPdf(Request $request)
    {
        return $this->streamCelebrationsMonthPdf($request, 'birthdays');
    }

    public function downloadAnniversariesMonthPdf(Request $request)
    {
        return $this->streamCelebrationsMonthPdf($request, 'anniversaries');
    }

    private function streamCelebrationsMonthPdf(Request $request, string $type)
    {
        $branchId = session('active_branch_id');
        $companyUserIds = $this->resolveDashboardCompanyUserIds();
        $period = now()->format('F Y');

        $query = Employee::with(['user', 'department']);

        if ($type === 'birthdays') {
            $query->whereMonth('date_of_birth', now()->month);
            $reportTitle = 'BIRTHDAYS THIS MONTH';
            $dateColumnLabel = 'Birthday';
            $showYearsColumn = false;
            $orderColumn = 'date_of_birth';
            $filename = 'BIRTHDAYS_'.now()->format('M_Y').'.pdf';
        } else {
            $query->whereNotNull('date_of_joining')
                ->whereMonth('date_of_joining', now()->month)
                ->whereYear('date_of_joining', '<', now()->year);
            $reportTitle = 'WORK ANNIVERSARIES THIS MONTH';
            $dateColumnLabel = 'Joining Date';
            $showYearsColumn = true;
            $orderColumn = 'date_of_joining';
            $filename = 'ANNIVERSARIES_'.now()->format('M_Y').'.pdf';
        }

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        } elseif (! empty($companyUserIds)) {
            $query->whereIn('created_by', $companyUserIds);
        }

        $employees = $query->orderByRaw('DAY('.$orderColumn.') ASC')->get();

        $reportData = $employees->map(function ($emp) use ($type) {
            $name = $emp->user->name ?? 'Employee';
            $dept = $emp->department->name ?? 'N/A';
            $code = $emp->emy_code ?? $emp->essl_id ?? '-';

            if ($type === 'birthdays') {
                $dob = $emp->date_of_birth ? Carbon::parse($emp->date_of_birth) : null;

                return [
                    'code' => $code,
                    'name' => $name,
                    'department' => $dept,
                    'event_date' => $dob ? $dob->format('d M Y') : '-',
                ];
            }

            $doj = Carbon::parse($emp->date_of_joining);
            $years = now()->year - $doj->year;

            return [
                'code' => $code,
                'name' => $name,
                'department' => $dept,
                'event_date' => $doj->format('d M Y'),
                'years_label' => $years.' '.($years === 1 ? 'Year' : 'Years'),
            ];
        })->values()->toArray();

        $branchLabel = 'ALL';
        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $branchLabel = strtoupper($branch->name);
            }
        }

        $pdf = Pdf::loadView('reports.celebrations_month_pdf', [
            'reportTitle' => $reportTitle,
            'reportType' => $reportTitle,
            'period' => $period,
            'departmentName' => $branchLabel,
            'sectionName' => 'ALL',
            'categoryName' => 'ALL',
            'statusName' => 'ALL',
            'companyName' => $this->resolveReportCompanyTitle($branchId),
            'dateColumnLabel' => $dateColumnLabel,
            'showYearsColumn' => $showYearsColumn,
            'reportData' => $reportData,
        ])->setPaper('a4', 'portrait');

        $branchIdForLog = ($branchId && $branchId !== 'all') ? (int) $branchId : null;
        ActivityLogger::log(
            'Report',
            'downloaded',
            'Downloaded '.$reportTitle.' PDF for '.$period.' ('.count($reportData).' record'.(count($reportData) === 1 ? '' : 's').')',
            $branchIdForLog
        );

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    private function resolveDashboardCompanyUserIds(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if ($user->type === 'company') {
            $ids = \App\Models\User::where('created_by', $user->id)->pluck('id')->toArray();
            $ids[] = $user->id;

            return $ids;
        }

        $userCreatedBy = \App\Models\User::where('id', $user->created_by)->value('id');
        $ids = \App\Models\User::where('created_by', $userCreatedBy)->pluck('id')->toArray();
        $ids[] = $userCreatedBy;

        return $ids;
    }

    private function resolveReportCompanyTitle($branchId): string
    {
        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES PVT LTD.');

        if ($branchId && $branchId !== 'all') {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $companyTitle .= ' - '.strtoupper($branch->name);
            }
        }

        return $companyTitle;
    }

    private function renderReportModule(Request $request, $title, $type)
    {
        $branchId = $request->get('branch_id')
            ? decryptId($request->get('branch_id'))
            : (session('active_branch_id') ?? (auth()->user()->branch_id ?? 1));

        $employeeQuery = \App\Models\Employee::withoutGlobalScopes()
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->with('user:id,name');

        if ($branchId && $branchId !== 'all') {
            $employeeQuery->where('branch_id', $branchId);
        }

        $employees = $employeeQuery
            ->select('id', 'emy_code', 'employee_id', 'user_id', 'department_id', 'section_id', 'branch_id')
            ->orderBy('emy_code')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => encryptId($emp->id),
                    'code' => $emp->emy_code ?? $emp->employee_id,
                    'name' => $emp->user->name ?? 'N/A',
                    'department_id' => $emp->department_id ? encryptId($emp->department_id) : null,
                    'section_id' => $emp->section_id ? encryptId($emp->section_id) : null,
                ];
            })->values();

        $deptQuery = Department::where('status', 'active');
        $sectQuery = Section::withoutGlobalScopes()->where('status', 'active');
        $catQuery = \App\Models\Category::withoutGlobalScopes()->where('status', 'active');
        $desigQuery = \App\Models\Designation::withoutGlobalScopes()->where('status', 'active');
        $shiftQuery = \App\Models\Shift::withoutGlobalScopes()->where('status', 'active');

        if ($branchId && $branchId !== 'all') {
            $deptQuery->where('branch_id', $branchId);
            $sectQuery->where('branch_id', $branchId);
            $catQuery->where('branch_id', $branchId);
            $desigQuery->where('branch_id', $branchId);
            $shiftQuery->where('branch_id', $branchId);
        }

        // Build branches with encrypted IDs; track active branch encrypted value
        $encryptedUserBranchId = null;
        $branches = \App\Models\Branch::where('status', 'active')
            ->get()
            ->map(function ($branch) use ($branchId, &$encryptedUserBranchId) {
                $encId = encryptId($branch->id);
                if ($branch->id == $branchId) {
                    $encryptedUserBranchId = $encId;
                }
                return [
                    'id' => $encId,
                    'name' => $branch->name,
                ];
            });

        return Inertia::render('hr/reports/ReportModule', [
            'title' => $title,
            'reportType' => $type,
            'departments' => $deptQuery->select('id', 'name')->get()->map(function ($dept) {
                return [
                    'id' => encryptId($dept->id),
                    'name' => $dept->name,
                ];
            }),
            'sections' => $sectQuery->select('id', 'name')->get()->map(function ($sect) {
                return [
                    'id' => encryptId($sect->id),
                    'name' => $sect->name,
                ];
            }),
            'categories' => $catQuery->select('id', 'name')->get()->map(function ($cat) {
                return [
                    'id' => encryptId($cat->id),
                    'name' => $cat->name,
                ];
            }),
            'designations' => $desigQuery->select('id', 'name')->get()->map(function ($desig) {
                return [
                    'id' => encryptId($desig->id),
                    'name' => $desig->name,
                ];
            }),
            'shifts' => $shiftQuery->select('id', 'name')->get()->map(function ($shift) {
                return [
                    'id' => encryptId($shift->id),
                    'name' => $shift->name,
                ];
            }),
            'branches' => $branches,
            'employees' => $employees,
            'userBranchId' => $encryptedUserBranchId,
        ]);
    }

    /**
     * Generate dynamic PDF reports.
     */
    public function generate(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'section' => decryptId($request->get('section')),
            'category' => decryptId($request->get('category')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        if ($request->get('report_id') === 'monthly_production') {
            return $this->generateMonthlyProductionReport($request);
        }

        if (
            $request->get('report_id') === 'monthly_earning_deduction' ||
            $request->get('report_id') === 'monthly_earning' ||
            $request->get('report_id') === 'monthly_deduction_payroll' ||
            $request->get('report_id') === 'monthly_deductionyroll' ||
            $request->get('report_id') === 'monthly_payroll_summary' ||
            $request->get('report_id') === 'monthlyyroll_summary'
        ) {
            return $this->generateMonthlyEarningDeductionReport($request);
        }

        if ($request->get('report_id') === 'deduction') {
            return $this->generateMonthlyDeductionReport($request);
        }

        if ($request->get('report_id') === 'incentive') {
            return $this->generateIncentiveReport($request);
        }

        if ($request->get('report_id') === 'production') {
            return $this->generateProductionReport($request);
        }

        if (in_array($request->get('report_id'), ['bank_transfer', 'loan_ledger', 'nominee_register', 'salary_ctc'])) {
            return $this->generateCustomPosterReport($request);
        }

        $reportId = $request->get('report_id');
        if (in_array($reportId, ['att_worker', 'att_dept', 'att_shift', 'att_summary', 'emp_monthly'])) {
            return $this->generateAttendanceReport($request, $reportId);
        }

        return $this->generateBiometricReport($request);
    }

    // Custom poster report dispatcher
    private function generateCustomPosterReport(Request $request)
    {
        $reportId = $request->get('report_id');
        switch ($reportId) {
            case 'bank_transfer':
                return $this->generateBankTransferReport($request);
            case 'loan_ledger':
                return $this->generateLoanLedgerReport($request);
            case 'nominee_register':
                return $this->generateNomineeDeclarationReport($request);
            case 'salary_ctc':
                return $this->generateSalaryStructureReport($request);
            default:
                abort(400, 'Invalid custom report_id');
        }
    }

    // Helper to save poster reports to DB so they appear in Download History
    private function outputAndSavePdf($pdf, $fileName, Request $request)
    {
        $pdfOutput = $pdf->output();
        $cleanFileName = str_replace(['(', ')', ' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', strtoupper($fileName));
        $filePath = 'reports/' . $cleanFileName . '_' . time() . '.pdf';

        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfOutput);

        \App\Models\ReportDownload::create([
            'user_id' => auth()->id() ?? 1,
            'report_name' => \App\Models\ReportDownload::generateReadableName($request),
            'file_path' => $filePath,
            'status' => 'completed',
            'progress' => 100,
            'filters' => $request->all(),
        ]);

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $cleanFileName . '.pdf"');
    }

    // Generate Bank Transfer Register
    private function generateBankTransferReport(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'category' => decryptId($request->get('category')),
            'section' => decryptId($request->get('section')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);
        $branchId = $request->get('branch_id');
        $categoryId = $request->get('category');
        $empId = $request->get('employee_id');
        $branchName = ($branchId && $branchId !== 'all') ? (\App\Models\Branch::find($branchId)?->name ?? '') : '';

        $employees = Employee::withoutGlobalScopes()
            ->when($branchId && $branchId !== 'all', fn($q) => $q->where('branch_id', $branchId))
            ->when($categoryId && $categoryId !== 'all', fn($q) => $q->where('category_id', $categoryId))
            ->when($empId && $empId !== 'all', fn($q) => $q->where('id', $empId))
            ->with('user')
            ->get();

        $reportData = $employees->map(function ($emp) {
            return [
                'code' => $emp->emy_code ?? $emp->employee_id,
                'name' => $emp->user->name ?? 'N/A',
                'account_holder_name' => $emp->account_holder_name ?? $emp->user->name ?? 'N/A',
                'bank_name' => $emp->bank_name ?? '—',
                'bank_branch' => $emp->bank_branch ?? '—',
                'account_number' => $emp->account_number ?? '—',
                'ifsc_code' => $emp->ifsc_code ?? '—',
            ];
        });

        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES') . ($branchName ? " - " . $branchName : "");

        if ($request->get('is_excel_export')) {
            return ['data' => $employees, 'companyTitle' => $companyTitle];
        }

        $data = [
            'reportData'  => $reportData,
            'companyName' => $companyTitle,
            'reportTitle' => 'BANK DETAILS REPORT',
            'period'      => 'AS OF ' . now()->format('d-M-Y'),
        ];
        $pdf = Pdf::loadView('reports.bank_transfer_report', $data)
            ->setPaper('a4', 'portrait');
        return $this->outputAndSavePdf($pdf, 'BANK_TRANSFER', $request);
    }

    // Generate Loan & Advance Ledger
    private function generateLoanLedgerReport(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'category' => decryptId($request->get('category')),
            'section' => decryptId($request->get('section')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);

        $branchId = $request->get('branch_id');
        $categoryId = $request->get('category');
        $empId = $request->get('employee_id');
        $branchName = ($branchId && $branchId !== 'all') ? (\App\Models\Branch::find($branchId)?->name ?? '') : '';

        $loans = \App\Models\SalaryPayroll\SalaryLoanRequest::query()
            ->whereIn('status', [
                \App\Models\SalaryPayroll\SalaryLoanRequest::STATUS_DISBURSED,
                \App\Models\SalaryPayroll\SalaryLoanRequest::STATUS_RECOVERING,
            ])
            ->whereRaw('paid_amount < COALESCE(approved_amount, requested_amount)')
            ->when($branchId && $branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
            ->when($categoryId && $categoryId !== 'all', fn ($q) => $q->whereHas(
                'employee.employee',
                fn ($eq) => $eq->where('category_id', $categoryId)
            ))
            ->when($empId && $empId !== 'all', fn ($q) => $q->where('employee_id', $empId))
            ->with(['employee.employee'])
            ->orderBy('employee_id')
            ->get();

        $reportData = $loans->map(function ($loan) {
            $emp = $loan->employee?->employee;

            return [
                'code' => $emp->emy_code ?? $emp->employee_id ?? '—',
                'name' => $loan->employee?->name ?? 'N/A',
                'loan_type' => 'Salary Loan',
                'period' => $loan->installment_count ?? 0,
                'total_amount' => number_format((float) ($loan->approved_amount ?? $loan->requested_amount), 2),
                'installment' => number_format((float) ($loan->installment_amount ?? 0), 2),
                'outstanding' => number_format((float) $loan->pending_amount, 2),
            ];
        })->values();

        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES') . ($branchName ? " - " . $branchName : "");

        if ($request->get('is_excel_export')) {
            return ['data' => $reportData, 'companyTitle' => $companyTitle];
        }

        $data = [
            'reportData'  => $reportData,
            'companyName' => $companyTitle,
            'reportTitle' => 'LOAN & ADVANCE LEDGER',
            'period'      => 'AS OF ' . now()->format('d-M-Y'),
        ];
        $pdf = Pdf::loadView('reports.loan_ledger_report', $data)
            ->setPaper('a4', 'portrait');
        return $this->outputAndSavePdf($pdf, 'LOAN_LEDGER', $request);
    }

    // Generate Nominee Declaration Register
    private function generateNomineeDeclarationReport(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'category' => decryptId($request->get('category')),
            'section' => decryptId($request->get('section')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);
        $branchId = $request->get('branch_id');
        $categoryId = $request->get('category');
        $branchName = ($branchId && $branchId !== 'all') ? (\App\Models\Branch::find($branchId)?->name ?? '') : '';

        $employees = Employee::withoutGlobalScopes()
            ->when($branchId && $branchId !== 'all', fn($q) => $q->where('branch_id', $branchId))
            ->when($categoryId && $categoryId !== 'all', fn($q) => $q->where('category_id', $categoryId))
            ->with(['nominees'])
            ->get();

        $reportData = $employees->map(function ($emp) {
            return $emp->nominees->map(function ($nom) use ($emp) {
                return [
                    'code' => $emp->emy_code ?? $emp->employee_id,
                    'name' => $emp->user->name ?? 'N/A',
                    'nominee_name' => $nom->name,
                    'relation' => $nom->relation,
                    'share' => $nom->percentage . '%',
                    'aadhar' => $nom->aadhar_number ?? '',
                ];
            });
        })->flatten(1);

        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES') . ($branchName ? " - " . $branchName : "");

        if ($request->get('is_excel_export')) {
            return ['data' => $employees, 'companyTitle' => $companyTitle];
        }

        $data = [
            'reportData'  => $reportData,
            'companyName' => $companyTitle,
            'reportTitle' => 'NOMINEE DECLARATION REGISTER',
            'period'      => 'AS OF ' . now()->format('d-M-Y'),
        ];
        $pdf = Pdf::loadView('reports.nominee_declaration_report', $data)
            ->setPaper('a4', 'portrait');
        return $this->outputAndSavePdf($pdf, 'NOMINEE_REGISTER', $request);
    }

    // Generate Salary Structure (CTC) Break‑up Register
    private function generateSalaryStructureReport(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'department' => decryptId($request->get('department')),
            'category' => decryptId($request->get('category')),
            'section' => decryptId($request->get('section')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);
        $branchId = $request->get('branch_id');
        $categoryId = $request->get('category');
        $branchName = ($branchId && $branchId !== 'all') ? (\App\Models\Branch::find($branchId)?->name ?? '') : '';

        $employees = Employee::withoutGlobalScopes()
            ->when($branchId && $branchId !== 'all', fn($q) => $q->where('branch_id', $branchId))
            ->when($categoryId && $categoryId !== 'all', fn($q) => $q->where('category_id', $categoryId))
            ->get();

        $payrollParams = \App\Models\PayrollParameter::forDate(now());
        $financialYear = financialYearLabelForDate(now());
        $ptSlabs = \App\Models\ProfessionalTaxSlab::where('financial_year', $financialYear)->orderBy('min_amt')->get();
        if ($ptSlabs->isEmpty()) {
            $ptSlabs = \App\Models\ProfessionalTaxSlab::orderBy('min_amt')->get();
        }

        $reportData = $employees->map(function ($emp) use ($payrollParams, $ptSlabs) {
            $multiplier = ($emp->daily_option == 1) ? 26 : 1;
            $workingDays = $emp->working_days > 0 ? $emp->working_days : 26;
            
            $basic = (float)$emp->basic_salary * $multiplier;
            $hra = (float)$emp->hra_allowance * $multiplier;
            $conveyance = (float)$emp->conveyance_allowance * $multiplier;
            $medical = (float)$emp->medical_allowance * $multiplier;
            
            $bonus = 0;
            if ($emp->bonus_flag == 1 && $payrollParams) {
                $bonusPct = (float)($payrollParams->bonus_pct ?? 0);
                $bonusMax = (float)($payrollParams->bonus_max_limit ?? 0);
                if ($bonusPct > 0) {
                    $bonusBase = $basic;
                    $bonusAmount = round(($bonusBase * $bonusPct) / 100, 2);
                    if ($bonusMax > 0) {
                        $bonusAmount = min($bonusAmount, $bonusMax);
                    }
                    $bonus = $bonusAmount;
                }
            }

            $grossEarn = $basic + $hra + $conveyance + $medical + $bonus;

            $pf = 0;
            if ($emp->pf_flag == 1 && $payrollParams) {
                $pfPercentage = PayrollParameter::pfEmployeePct($payrollParams);
                $maxPfAmount = (float)($payrollParams->max_pf_amount ?? 15000);
                $pfBasic = ($emp->pf_basic_salary > 0) ? (float)$emp->pf_basic_salary * $multiplier : $basic;
                $pfBase = min($pfBasic, $maxPfAmount);
                $pf = round(($pfBase * $pfPercentage) / 100, 2);
            }

            $esi = 0;
            if ($emp->esic_flag == 1 && $payrollParams) {
                $esiPct = ($emp->esiMaster && (float)$emp->esiMaster->percentage_employee > 0)
                    ? (float)$emp->esiMaster->percentage_employee
                    : PayrollParameter::esicEmployeePct($payrollParams);
                $esiCeiling = ($emp->esiMaster && (float)$emp->esiMaster->limit > 0)
                    ? (float)$emp->esiMaster->limit
                    : PayrollParameter::esicWageLimit($payrollParams);
                $esiBase = min($grossEarn, $esiCeiling);
                $esi = round(($esiBase * $esiPct) / 100, 2);
            }

            $pt = 0;
            if ($emp->ptax_flag == 1) {
                foreach ($ptSlabs as $slab) {
                    $min = (float) $slab->min_amt;
                    $max = $slab->max_amt !== null ? (float) $slab->max_amt : null;
                    if ($grossEarn >= $min && ($max === null || $grossEarn <= $max)) {
                        $pt = round((float) $slab->pt_amt, 2);
                        break;
                    }
                }
            }

            $it = (float)$emp->it_amount;
            $totalDeduct = $pf + $esi + $pt + $it;
            $netPay = $grossEarn - $totalDeduct;

            return [
                'code' => $emp->emy_code ?? $emp->employee_id,
                'name' => $emp->user->name ?? 'N/A',
                'working_days' => $workingDays,
                'basic' => $basic,
                'hra' => $hra,
                'conveyance' => $conveyance,
                'medical' => $medical,
                'bonus' => $bonus,
                'gross_earn' => $grossEarn,
                'pf' => $pf,
                'esi' => $esi,
                'pt' => $pt,
                'it' => $it,
                'total_deduct' => $totalDeduct,
                'net_pay' => $netPay,
            ];
        });

        $companyTitle = getSetting('titleText', 'KIRAN INDUSTRIES') . ($branchName ? " - " . $branchName : "");

        if ($request->get('is_excel_export')) {
            return ['data' => $reportData, 'companyTitle' => $companyTitle];
        }

        $data = [
            'reportData'  => $reportData,
            'companyName' => $companyTitle,
            'reportTitle' => 'SALARY CTC BREAKUP',
            'period'      => 'AS OF ' . now()->format('d-M-Y'),
        ];
        $pdf = Pdf::loadView('reports.salary_structure_report', $data)
            ->setPaper('a4', 'portrait');
        return $this->outputAndSavePdf($pdf, 'SALARY_CTC', $request);
    }


    public function downloadStatus($id)
    {
        $download = \App\Models\ReportDownload::where('user_id', auth()->id())->findOrFail($id);

        return response()->json([
            'id' => $download->id,
            'status' => $download->status,
            'progress' => (int) ($download->progress ?? 0),
            'error_message' => $download->error_message,
            'report_name' => $download->report_name,
            'is_no_records' => str_contains((string) ($download->file_path ?? ''), '_no_records_'),
        ]);
    }

    public function downloadsJson(Request $request)
    {
        $query = \App\Models\ReportDownload::where('user_id', auth()->id() ?? 1);

        if ($request->has('search') && $request->search != '') {
            $query->where('report_name', 'like', '%' . $request->search . '%');
        }

        $downloads = $query->orderBy('id', 'desc')->paginate(10);

        $downloads->getCollection()->transform(function ($download) {
            $branchName = 'All Branches';
            if (isset($download->filters['branch_id'])) {
                try {
                    $branchId = decryptId($download->filters['branch_id']);
                } catch (\Exception $e) {
                    $branchId = $download->filters['branch_id'];
                }
                
                if ($branchId && $branchId !== 'all') {
                    $branch = \App\Models\Branch::find($branchId);
                    if ($branch) {
                        $branchName = $branch->name;
                    }
                }
            }
            $download->branch_name = $branchName;
            return $download;
        });

        return response()->json($downloads);
    }

    public function generateBackground(Request $request)
    {
        $reportId = $request->get('report_id');
        $reportName = \App\Models\ReportDownload::generateReadableName($request);

        $download = \App\Models\ReportDownload::create([
            'user_id' => auth()->id(),
            'report_name' => $reportName,
            'status' => 'pending',
            'filters' => $request->all()
        ]);

        \App\Jobs\GenerateBiometricReportJob::dispatch($request->all(), $download->id);

        return response()->json([
            'success' => true,
            'message' => 'Report is generating in the background. You can download it from the Downloads section soon.',
            'download_id' => $download->id
        ]);
    }

    public function downloads()
    {
        $downloads = \App\Models\ReportDownload::where('user_id', auth()->id())
            ->orderBy('id', 'desc')
            ->get();

        return inertia('hr/reports/Downloads', [
            'downloads' => $downloads
        ]);
    }

    public function downloadFile($id)
    {
        $download = \App\Models\ReportDownload::where('user_id', auth()->id())->findOrFail($id);

        if ($download->status !== 'completed' || !$download->file_path) {
            abort(404, 'File not ready or not found');
        }

        $safeName = str_replace([' ', ':', '/'], ['_', '-', '-'], $download->report_name) . '.pdf';

        if (str_starts_with($download->file_path, 'reports/')) {
            $fullPath = storage_path('app/public/' . $download->file_path);
            if (file_exists($fullPath)) {
                ActivityLogger::logReportDownload($download, 'downloaded');

                return response()->download($fullPath, $safeName);
            }
        }

        abort(404, 'File not found on server');
    }

    public function deleteDownload($id)
    {
        $download = \App\Models\ReportDownload::where('user_id', auth()->id())->findOrFail($id);

        // Delete the file if it exists
        if ($download->file_path && str_starts_with($download->file_path, 'reports/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($download->file_path);
        }

        $download->delete();

        return response()->json(['success' => true, 'message' => 'Report deleted successfully']);
    }

    public function deleteMultipleDownloads(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No reports selected.']);
        }

        $downloads = \App\Models\ReportDownload::where('user_id', auth()->id())->whereIn('id', $ids)->get();

        foreach ($downloads as $download) {
            if ($download->file_path && str_starts_with($download->file_path, 'reports/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($download->file_path);
            }
            $download->delete();
        }

        return response()->json(['success' => true, 'message' => count($downloads) . ' reports deleted successfully.']);
    }

    public function generateAttendanceReport(Request $request, $reportId)
    {
        $branchId = $request->get('branch_id') ?? session('active_branch_id') ?? (auth()->user()->branch_id ?? 1);
        $reportType = $request->get('report_type', 'codewise');
        $fromDate = $request->get('from_date') ? Carbon::parse($request->get('from_date')) : now();
        $toDate = $request->get('to_date') ? Carbon::parse($request->get('to_date')) : now();
        $sectionId = $request->get('section');
        $deptId = $request->get('department');
        $catId = $request->get('category');
        $poStatus = $request->get('po_status');
        $status = $request->get('status', 'all');

        \Log::info("Attendance Report Parameters: $reportId", $request->all());

        \Log::info("Generating Attendance Report: $reportId", [
            'filters' => $request->all(),
            'branch_id' => $branchId,
            'department_id' => $deptId,
        ]);

        $query = \App\Models\Employee::withoutGlobalScopes()
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['user', 'department', 'section', 'shift', 'category', 'designation']);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($reportType === 'dayshift') {
            $query->whereHas('shift', fn($q) => $q->dayShifts());
        } elseif ($reportType === 'nightshift') {
            $query->whereHas('shift', fn($q) => $q->nightShifts());
        }

        // Build Dynamic Header Title Parts
        $titleParts = [];

        // Add Report Type to title by default
        if ($request->get('report_id') === 'biometric_single') {
            $titleParts[] = "DAILY";
        } else {
            $titleParts[] = strtoupper($reportType);
        }

        if ($poStatus && $poStatus !== 'all') {
            $val = ($poStatus === 'P') ? 'Permanent' : 'Other';
            $query->where('po_status', $val);
            $titleParts[] = strtoupper($val);
        }

        if ($catId && $catId !== 'all') {
            $query->where('category_id', $catId);
            $cat = \App\Models\Category::find($catId);
            if ($cat)
                $titleParts[] = strtoupper($cat->name);
        }

        if ($deptId && $deptId !== 'all') {
            $query->where('department_id', $deptId);
            $dept = Department::find($deptId);
            if ($dept)
                $titleParts[] = strtoupper($dept->name);
        }

        if ($sectionId && $sectionId !== 'all') {
            $query->where('section_id', $sectionId);
            $sect = Section::find($sectionId);
            if ($sect)
                $titleParts[] = strtoupper($sect->name);
        }

        $employeeId = $request->get('employee_id');
        $employeeIdsArray = $request->get('employee_ids'); // For chunking jobs

        if ($employeeIdsArray && is_array($employeeIdsArray) && count($employeeIdsArray) > 0) {
            $query->whereIn('id', $employeeIdsArray);
        } elseif ($employeeId && $employeeId !== 'all') {
            $query->where('id', $employeeId);
        }

        $employees = $query->orderBy('emy_code')->get();
        if ($employees->isEmpty()) {
            return response()->json(['error' => 'No employees found for the selected filters.'], 404);
        }
        \Log::info("Generating Attendance Report: $reportId", ['employee_count' => count($employees)]);

        $dates = [];
        $curr = $fromDate->copy();
        while ($curr->lte($toDate)) {
            $dates[] = $curr->format('Y-m-d');
            $curr->addDay();
        }

        // SPECIAL CASE: Workerwise / Departmentwise / Shiftwise / Summary / Single Employee Attendance Report
        if (in_array($reportId, ['att_worker', 'att_dept', 'att_shift', 'att_summary', 'emp_monthly'])) {
            ini_set('memory_limit', '1024M');
            set_time_limit(300);

            $hourlyType = $request->get('hourly_type', 'N');
            $cardType = $request->get('card_type', 'N');

            // Fetch all processed attendance records for the selected employees and date range
            $attendanceRecords = \App\Models\BiometricAttendance::whereIn('employee_id', $employees->pluck('id'))
                ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

            if ($status && $status !== 'all') {
                if ($status === 'overtime') {
                    $attendanceRecords->where('ot_minutes', '>', 0);
                } elseif ($status === 'latein') {
                    $attendanceRecords->where('late_in', '!=', '0m')->whereNotNull('late_in');
                } elseif ($status === 'earlyout') {
                    $attendanceRecords->where('early_out', '!=', '0m')->whereNotNull('early_out');
                } elseif ($status === 'P') {
                    $attendanceRecords->whereIn('status', ['P', 'MIS', 'HD', 'OD', 'CO']);
                } else {
                    $attendanceRecords->where('status', $status);
                }
            }

            $attendanceRecords = $attendanceRecords->get();

            \Log::info("Attendance Records Fetched", ['count' => count($attendanceRecords)]);

            $attendanceRecords = $attendanceRecords->groupBy([
                'employee_id',
                function ($item) {
                    return \Carbon\Carbon::parse($item->attendance_date)->format('Y-m-d');
                }
            ]);

            $matrixData = [];
            foreach ($employees as $employee) {
                $totalPr = 0;
                $totalPi = 0;
                $attendance = [];
                $attendanceMis = [];
                $attendancePi = [];

                $totalHours = 0;
                foreach ($dates as $date) {
                    $records = $attendanceRecords->get($employee->id)?->get($date);

                    if ($records && $records->count() > 0) {
                        $totalMin = $records->sum('total_minutes');
                        $totalHours += $totalMin;
                        $val = (float) $records->sum('duty_value');

                        $isAnyManual = $records->contains('is_manual', true);
                        $isAnyMis = $records->contains('status', 'MIS');

                        if ($cardType === 'A' || $hourlyType === 'A') {
                            if ($isAnyMis) {
                                $attendance[$date] = 'MIS' . ($isAnyManual ? '*' : '');
                            } else {
                                $attendance[$date] = ($val > 0) ? 'P' . ($isAnyManual ? '*' : '') : 'A';
                            }
                        } elseif ($hourlyType === 'Y') {
                            // Total Hours H.MM format (e.g. 8.30 for 8h 30m)
                            if ($totalMin > 0) {
                                $hours = floor($totalMin / 60);
                                $minutes = $totalMin % 60;
                                $attendance[$date] = sprintf("%d.%02d", $hours, $minutes) . ($isAnyManual ? '*' : '');
                            } elseif ($isAnyMis) {
                                // MIS punch: has record but missing in or out — show MIS label
                                $attendance[$date] = 'MIS';
                            } else {
                                $attendance[$date] = '-';
                            }
                        } elseif ($hourlyType === 'T') {
                            // Time Format: IN / OUT (First in, Last out) + Total Hours
                            $firstRec = $records->whereNotNull('in_time')->sortBy('in_time')->first();
                            $lastRec = $records->whereNotNull('out_time')->sortByDesc('out_time')->first();

                            $first = '--:--';
                            if ($firstRec && $firstRec->in_time) {
                                $first = ($firstRec->in_time instanceof \Carbon\Carbon) ? $firstRec->in_time->format('H:i') : \Carbon\Carbon::parse($firstRec->in_time)->format('H:i');
                            }

                            $last = '--:--';
                            if ($lastRec && $lastRec->out_time) {
                                $last = ($lastRec->out_time instanceof \Carbon\Carbon) ? $lastRec->out_time->format('H:i') : \Carbon\Carbon::parse($lastRec->out_time)->format('H:i');
                            }

                            $hrsDec = $totalMin / 60;
                            $hours = ($totalMin > 0) ? number_format($hrsDec, 2) . 'h' : '0h';

                            if ($first === '--:--' && $last === '--:--') {
                                $attendance[$date] = '-';
                            } else {
                                $attendance[$date] = (($first === $last) ? "{$first}" : "{$first}/{$last}") . ($isAnyManual ? '*' : '');
                            }
                        } else {
                            $attendance[$date] = number_format($val, 1) . ($isAnyManual ? '*' : '');
                        }

                        // Track mispunch for red highlighting in view
                        $attendanceMis[$date] = $isAnyMis;
                        $attendancePi[$date] = ($val > 1.0);

                        $totalPr += min(1.0, $val);
                        $totalPi += max(0.0, $val - 1.0);
                    } else {
                        $today = \Carbon\Carbon::today()->format('Y-m-d');
                        if ($cardType === 'A' || $hourlyType === 'A') {
                            $attendance[$date] = ($date > $today) ? '' : 'A';
                        } else {
                            $attendance[$date] = '-';
                        }
                        $attendanceMis[$date] = false;
                        $attendancePi[$date] = false;
                    }
                }


                $row = [
                    'id' => $employee->id,
                    'code' => $employee->emy_code,
                    'name' => optional($employee->user)->name ?? 'N/A',
                    'father_name' => $employee->father_name ?? '-',
                    'designation' => optional($employee->designation)->name ?? '-',
                    'dept' => $employee->department->name ?? 'N/A',
                    'section' => $employee->section->name ?? 'N/A',
                    'shift' => $employee->shift->name ?? 'N/A',
                    'shift_code' => $employee->shift->short_code ?? '',
                    'is_night' => $employee->shift ? ($employee->shift->isNightShift() ? 1 : 0) : 0,
                    'attendance' => $attendance,
                    'attendance_mis' => $attendanceMis,
                    'attendance_pi' => $attendancePi,
                    'total_pr' => $totalPr,
                    'total_pi' => $totalPi,
                    'total_tot' => $totalPr + $totalPi,
                    'total_hours' => number_format($totalHours / 60, 2) . 'h'
                ];

                if (in_array($request->get('report_id'), ['att_worker', 'att_dept', 'att_shift', 'att_summary', 'emp_monthly']) && $cardType === 'Y') {
                    // Calculate L.Wkd (Present count in the last week of the range)
                    $visibleDates = array_slice($dates, -9); // Match image showing 9 days (23-31)
                    $lwkd = 0;
                    foreach ($visibleDates as $vd) {
                        if (isset($attendance[$vd]) && $attendance[$vd] !== '-') {
                            $lwkd++;
                        }
                    }
                    $row['l_wkd'] = $lwkd;
                    $row['visible_dates'] = $visibleDates;
                }

                // Include employee if they have any visible cell (MIS shows as 'MIS', present shows hours)
                $hasLogs = collect($attendance)->some(fn($v) => $v !== '-');
                if ($hasLogs) {
                    $matrixData[] = $row;
                }
            }

            // Calculate Day-wise totals
            $dayTotals = [];
            foreach ($dates as $date) {
                $dayTotals[$date] = 0;
                foreach ($matrixData as $row) {
                    if (isset($row['attendance'][$date]) && $row['attendance'][$date] !== '-') {
                        $dayTotals[$date]++;
                    }
                }
            }

            $branchName = "";
            $selectedBranchId = ($branchId && $branchId !== 'all') ? $branchId : session('active_branch_id');
            if ($selectedBranchId && $selectedBranchId !== 'all') {
                $branch = \App\Models\Branch::find($selectedBranchId);
                if ($branch) {
                    $branchName = $branch->name;
                }
            }

            $typeLabel = match ($hourlyType) {
                'Y' => '(HOURLY)',
                'T' => '(TIMEWISE)',
                'A' => '(P/A STATUS)',
                default => '(NUMERIC)'
            };

            $data = [
                'title' => 'Workerwise Attendance Report ' . $typeLabel,
                'reportTitle' => 'Workerwise Attendance Report ' . $typeLabel,
                'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES') . (!empty($branchName) ? ' - ' . $branchName : ''),
                'branchName' => $branchName,
                'headerTitle' => 'ATTENDENT FOR THE PERIOD FROM ' . $fromDate->format('d/m/Y') . ' TO ' . $toDate->format('d/m/Y'),
                'fromDate' => $fromDate->format('d/m/Y'),
                'toDate' => $toDate->format('d/m/Y'),
                'period' => $fromDate->format('d/m/Y') . ' TO ' . $toDate->format('d/m/Y'),
                'dates' => $dates,
                'reportData' => $matrixData,
                'dayTotals' => $dayTotals,
                'reportType' => $reportType,
                'hourlyType' => $hourlyType,
                'cardType' => $cardType,
                'printDate' => now()->format('d/m/Y H:i:s'),
            ];

            \Log::info("Final Matrix Data Count", ['count' => count($matrixData)]);

            // Always group for these reports to ensure serial number resets per department/shift
            $groupByKey = ($request->get('report_id') === 'att_shift') ? 'shift' : 'dept';
            $grouped = collect($matrixData)->groupBy($groupByKey)->toArray();
            ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

            // Assign serial number per group
            foreach ($grouped as $groupName => &$rows) {
                foreach ($rows as $i => &$r) {
                    $r['sr'] = $i + 1;
                }
                unset($r);
            }
            unset($rows);

            $data['reportData'] = $grouped;

            if (in_array($request->get('report_id'), ['att_worker', 'att_dept', 'att_shift', 'att_summary', 'emp_monthly'])) {
                $data['headerTitle'] = 'ATTENDENT FOR THE PERIOD FROM ' . $fromDate->format('d/m/Y') . ' TO ' . $toDate->format('d/m/Y');

                if ($cardType === 'Y') {
                    $data['visibleDates'] = array_slice($dates, -9);
                }

                $viewName = ($cardType === 'Y') ? 'reports.departmentwise_card_y' : 'reports.departmentwise_card_n';

                if ($request->get('report_id') === 'att_shift') {
                    $data['headerTitle'] = 'SHIFTWISE ATTENDENT FOR THE PERIOD FROM ' . $fromDate->format('d/m/Y') . ' TO ' . $toDate->format('d/m/Y');
                    $viewName = 'reports.shiftwise_attendance';
                }

                if ($request->get('report_id') === 'att_summary') {
                    // Custom logic for Single Date Summary
                    $summaryData = [];
                    foreach ($matrixData as $row) {
                        $deptName = $row['section']; // Use Section for grouping as per user request
                        $desigName = $row['designation'];
                        $shiftCode = strtoupper($row['shift_code'] ?? '');
                        $isNightFlag = $row['is_night'] ?? 0;

                        // Determine if Day (PDY) or Night (PNT)
                        // User: "PD means day shift PN -> night shift"
                        $isNight = ($shiftCode === 'PN' || $isNightFlag == 1 || str_contains(strtoupper($row['shift']), 'NIGHT'));

                        $val = (float) ($row['attendance'][$fromDate->format('Y-m-d')] ?? 0);

                        if ($val > 0) {
                            if (!isset($summaryData[$deptName][$desigName])) {
                                $summaryData[$deptName][$desigName] = ['pdy' => 0, 'pnt' => 0, 'total' => 0];
                            }
                            if ($isNight) {
                                $summaryData[$deptName][$desigName]['pnt'] += $val;
                            } else {
                                $summaryData[$deptName][$desigName]['pdy'] += $val;
                            }
                            $summaryData[$deptName][$desigName]['total'] += $val;
                        }
                    }

                    // Sort Sections Naturally
                    uksort($summaryData, 'strnatcasecmp');

                    // Sort Designations Naturally within each Section
                    foreach ($summaryData as $dept => $desigs) {
                        uksort($summaryData[$dept], 'strnatcasecmp');
                    }

                    $data['reportData'] = $summaryData;
                    $data['headerTitle'] = 'SECTIONWISE WORKER SUMMARY - FOR ' . $fromDate->format('d/m/Y');
                    $viewName = 'reports.single_date_summary';
                }

                if ($request->get('report_id') === 'emp_monthly') {
                    $data['headerTitle'] = 'SINGLE EMPLOYEE ATTENDENT';
                    $viewName = 'reports.departmentwise_card_n';
                }

                $orientation = 'portrait';

                return $this->renderFinalReport($viewName, $data, $orientation, $request, $fromDate, $toDate, $titleParts);
            } else {
                $fileName = 'WORKERWISE_ATTN_' . str_replace(['(', ')'], '', $typeLabel) . '_' . $fromDate->format('d_m_Y');
                $pdf = Pdf::loadView('reports.workerwise_attendance', $data)->setPaper('a4', 'portrait');
                $pdfOutput = $pdf->output();
                $filePath = 'reports/' . $fileName . '_' . time() . '.pdf';

                \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfOutput);

                \App\Models\ReportDownload::create([
                    'user_id' => auth()->id() ?? 1,
                    'report_name' => \App\Models\ReportDownload::generateReadableName($request),
                    'file_path' => $filePath,
                    'status' => 'completed',
                    'progress' => 100,
                    'filters' => $request->all(),
                ]);

                return response($pdfOutput, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="' . $fileName . '.pdf"');
            }
        }
    }

    /**
     * Daily present list: employee must have punched IN on that calendar date.
     */
    private function pickDailyPresentRecord($dayRecords): ?\App\Models\BiometricAttendance
    {
        if (!$dayRecords || $dayRecords->isEmpty()) {
            return null;
        }

        $withIn = $dayRecords->filter(fn($r) => $r->in_time !== null);
        if ($withIn->isEmpty()) {
            return null;
        }

        return $withIn->firstWhere('status', 'P')
            ?? $withIn->firstWhere('status', 'HD')
            ?? $withIn->firstWhere('status', 'OD')
            ?? $withIn->firstWhere('status', 'CO')
            ?? $withIn->first();
    }

    private function generateBiometricReport(Request $request)
    {
        $branchId = $request->get('branch_id') ?? session('active_branch_id') ?? (auth()->user()->branch_id ?? 1);

        $isBiometricSingle = $request->get('report_id') === 'biometric_single';

        $reportType = $request->get('report_type', 'codewise');
        if ($isBiometricSingle) {
            $reportType = 'codewise';
        }

        $fromDate = $request->get('from_date')
            ? Carbon::parse($request->get('from_date'))
            : now();
        $toDate = $request->get('to_date')
            ? Carbon::parse($request->get('to_date'))
            : now();
        $sectionId = $request->get('section');
        $deptId = $request->get('department');
        $catId = $request->get('category');
        $poStatus = $request->get('po_status');
        $status = $request->get('status', 'all');

        $query = \App\Models\Employee::withoutGlobalScopes()
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['user', 'department', 'section', 'shift', 'category', 'designation']);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if (!$isBiometricSingle) {
            if ($reportType === 'dayshift') {
                $query->whereHas('shift', fn($q) => $q->dayShifts());
            } elseif ($reportType === 'nightshift') {
                $query->whereHas('shift', fn($q) => $q->nightShifts());
            }
        }

        // Build Dynamic Header Title Parts
        $titleParts = [];
        if ($isBiometricSingle) {
            $titleParts[] = 'DAILY PRESENT';
        } else {
            $titleParts[] = strtoupper($reportType);
        }

        if ($poStatus && $poStatus !== 'all') {
            $val = ($poStatus === 'P') ? 'Permanent' : 'Other';
            $query->where('po_status', $val);
            $titleParts[] = strtoupper($val);
        }

        if ($catId && $catId !== 'all') {
            $query->where('category_id', $catId);
            $cat = \App\Models\Category::find($catId);
            if ($cat)
                $titleParts[] = strtoupper($cat->name);
        }

        if ($deptId && $deptId !== 'all') {
            $query->where('department_id', $deptId);
            $dept = Department::find($deptId);
            if ($dept)
                $titleParts[] = strtoupper($dept->name);
        }

        if ($sectionId && $sectionId !== 'all') {
            $query->where('section_id', $sectionId);
            $sect = Section::find($sectionId);
            if ($sect)
                $titleParts[] = strtoupper($sect->name);
        }

        $employeeId = $request->get('employee_id');
        if ($employeeId && $employeeId !== 'all') {
            $query->where('id', $employeeId);
        }

        $employeeIdsArray = $request->get('employee_ids'); // For chunking jobs
        if (is_array($employeeIdsArray) && count($employeeIdsArray) > 0) {
            $query->whereIn('id', $employeeIdsArray);
        }

        if ($isBiometricSingle && ! $request->get('is_chunk')) {
            try {
                Artisan::call('essl:sync', ['--date' => $fromDate->format('Y-m-d')]);
            } catch (\Exception $e) {
                \Log::warning('ESSL sync before daily present report failed: ' . $e->getMessage());
            }
        }

        $activeAttQuery = \App\Models\BiometricAttendance::query()
            ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        if ($isBiometricSingle) {
            $activeAttQuery->whereNotNull('in_time');
        }

        $activeEmployeeIds = $activeAttQuery->distinct()->pluck('employee_id');

        $employees = $query->whereIn('id', $activeEmployeeIds)->orderBy('emy_code')->get();

        $dates = [];
        $curr = $fromDate->copy();
        while ($curr->lte($toDate)) {
            $dates[] = $curr->format('Y-m-d');
            $curr->addDay();
        }
        $viewName = 'reports.biometric_present';
        $orientation = 'portrait';

        // Fetch all processed attendance records for the selected employees and date range
        $attQuery = \App\Models\BiometricAttendance::with(['manualUser', 'logs', 'department'])->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('attendance_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);

        if ($status === 'overtime') {
            $attQuery->where('ot_minutes', '>', 0);
        } elseif ($status === 'latein') {
            $attQuery->where('late_in', '!=', '0m')->whereNotNull('late_in');
        } elseif ($status === 'earlyout') {
            $attQuery->where('early_out', '!=', '0m')->whereNotNull('early_out');
        } elseif ($status === 'MIS') {
            $attQuery->where('status', 'MIS');
        }

        if ($request->get('report_id') === 'mispunch_dedicated') {
            applyMispunchReportDateScope($attQuery);
        }

        $attendanceRecords = $attQuery->orderBy('attendance_date')
            ->orderBy('status', 'desc')
            ->get()
            ->groupBy([
                'employee_id',
                function ($item) {
                    return \Carbon\Carbon::parse($item->attendance_date)->format('Y-m-d');
                }
            ]);

        // Process Data for Report
        $reportData = [];
        foreach ($employees as $employee) {
            $empRecords = $attendanceRecords->get($employee->id);
            if (!$empRecords || $empRecords->count() === 0) {
                continue;
            }

            foreach ($dates as $date) {
                $dayRecords = $empRecords ? $empRecords->get($date) : null;

                if ($isBiometricSingle) {
                    $record = $this->pickDailyPresentRecord($dayRecords);
                    if (!$record) {
                        continue;
                    }
                } else {
                    $record = null;
                    if ($dayRecords) {
                        $record = $dayRecords->where('status', 'P')->first() ?? $dayRecords->first();
                    }
                }

                $isPresent = $record && $record->in_time !== null;
                if (!$isBiometricSingle) {
                    if ($record) {
                        if (in_array($record->status, ['P', 'HD', 'OD', 'CO'], true)) {
                            $isPresent = true;
                        } elseif ($record->status === 'MIS' && $record->in_time !== null) {
                            $isPresent = true;
                        }
                    }
                }

                if ($isBiometricSingle && !$isPresent) {
                    continue;
                }
                if ($status === 'P' && !$isPresent) {
                    continue;
                }
                if ($status === 'A' && $isPresent) {
                    continue;
                }
                if ($status === 'overtime' && !($record && $record->ot_minutes > 0))
                    continue;
                if ($status === 'MIS' && !($record && $record->status === 'MIS'))
                    continue;
                if ($status === 'latein' && !($record && $record->late_in && $record->late_in !== '0m'))
                    continue;
                if ($status === 'earlyout' && !($record && $record->early_out && $record->early_out !== '0m'))
                    continue;
                if ($status === 'HD' && !($record && $record->status === 'HD'))
                    continue;

                $isInManual = false;
                $isOutManual = false;
                if ($record && $record->is_manual) {
                    if ($record->logs && $record->logs->isNotEmpty()) {
                        $isInManual = $record->logs->where('punch_type', 'IN')->where('is_manual', true)->isNotEmpty();
                        $isOutManual = $record->logs->where('punch_type', 'OUT')->where('is_manual', true)->isNotEmpty();
                    } else {
                        if ($record->status === 'MIS') {
                            if ($record->in_time && $record->in_count > 0) {
                                $isInManual = true;
                            } elseif ($record->out_time && $record->out_count > 0) {
                                $isOutManual = true;
                            }
                        } else {
                            $isInManual = $record->in_time ? true : false;
                            $isOutManual = $record->out_time ? true : false;
                        }
                    }
                }

                $inTime = $record && $record->in_time ? Carbon::parse($record->in_time) : null;
                $outTime = $record && $record->out_time ? Carbon::parse($record->out_time) : null;

                $inTimeStr = $inTime ? $inTime->format('G.i') : "";
                $outTimeStr = $outTime ? $outTime->format('G.i') : "";

                if ($isInManual && $inTimeStr !== "") {
                    $inTimeStr .= ' (M)';
                }
                if ($isOutManual && $outTimeStr !== "") {
                    $outTimeStr .= ' (M)';
                }

                $workHours = "";
                if ($record && $record->total_minutes > 0) {
                    $m = $record->total_minutes;
                    $workHours = floor($m / 60) . "." . str_pad($m % 60, 2, '0', STR_PAD_LEFT);
                }

                $overtime = "-";
                if ($record && $record->ot_minutes > 0) {
                    $m = $record->ot_minutes;
                    $overtime = floor($m / 60) . "." . str_pad($m % 60, 2, '0', STR_PAD_LEFT);
                }

                $lateIn = "-";
                if ($record && $record->late_in && $record->late_in !== '0m') {
                    $lateIn = $record->late_in;
                }

                $earlyOut = "-";
                if ($record && $record->early_out && $record->early_out !== '0m') {
                    $earlyOut = $record->early_out;
                }

                $reportData[] = [
                    'company' => 'ALL EMPLOYEES',
                    'date' => Carbon::parse($date)->format('d/m/y'),
                    'code' => $employee->emy_code ?? $employee->employee_id,
                    'name' => strtoupper(optional($employee->user)->name ?? 'N/A'),
                    'dept' => optional($record->department)->name ?? optional($employee->department)->name ?? 'N/A',
                    'machine_type' => 'M',
                    'shift' => ($record->shift_code ?? optional($employee->shift)->short_code) ?? '-',
                    'in_time' => $inTimeStr,
                    'out_time' => $outTimeStr,
                    'work_hours' => $workHours,
                    'overtime' => $overtime,
                    'late_in' => $lateIn,
                    'early_out' => $earlyOut,
                    'mis_punch' => ($record && $record->status === 'MIS') ? "MISP" : "C",
                    'status' => $record->status ?? 'A',
                    'in_ts' => $inTime ? $inTime->timestamp : 0,
                    'out_ts' => $outTime ? $outTime->timestamp : 0,
                    'doj' => $employee->date_of_joining ? Carbon::parse($employee->date_of_joining)->format('d/m/Y') : '-',
                    'is_manual' => (bool) ($record->is_manual ?? false),
                    'manual_by_name' => $record && $record->manualUser ? $record->manualUser->name : '-',
                    'manual_remarks' => $record->manual_remarks ?? '',
                ];
            }
        }

        // Sort based on report type
        if ($reportType === 'namewise') {
            usort($reportData, fn($a, $b) => strcmp($a['name'], $b['name']));
        } elseif ($reportType === 'intime') {
            usort($reportData, fn($a, $b) => ($a['in_ts'] <=> $b['in_ts']));
        } elseif ($reportType === 'outtime') {
            usort($reportData, fn($a, $b) => ($a['out_ts'] <=> $b['out_ts']));
        } elseif ($reportType === 'overtime') {
            usort($reportData, fn($a, $b) => (float) $b['overtime'] <=> (float) $a['overtime']);
        } else {
            usort($reportData, fn($a, $b) => strnatcmp($a['code'], $b['code']));
        }

        $isMultipleDays = $fromDate->format('Y-m-d') !== $toDate->format('Y-m-d');
        $reportId = $request->get('report_id');

        // Consolidate Data if Multiple Days for "Single" report
        if ($isMultipleDays && $reportId === 'biometric_single') {
            $consolidated = [];
            foreach ($reportData as $row) {
                $key = $row['code'];
                if (!isset($consolidated[$key])) {
                    $consolidated[$key] = $row;
                    $consolidated[$key]['days_present'] = 0;
                }
                if ($row['status'] === 'P') {
                    $consolidated[$key]['days_present']++;
                }
            }
            $reportData = array_values($consolidated);
        }

        $finalData = [];
        $groupKey = $isBiometricSingle
            ? 'dept'
            : (($reportType === 'shift' || $reportType === 'dayshift' || $reportType === 'nightshift') ? 'shift' : 'dept');

        // Always group by department/shift to ensure serial numbers reset per group
        foreach ($reportData as $row) {
            $finalData[$row[$groupKey]][] = $row;
        }

        // Sort groups naturally
        ksort($finalData, SORT_NATURAL | SORT_FLAG_CASE);

        // Assign serial number per group
        foreach ($finalData as $groupName => &$rows) {
            foreach ($rows as $i => &$r) {
                $r['sr'] = $i + 1;
            }
            unset($r);
        }
        unset($rows);

        $selectedBranchId = ($branchId && $branchId !== 'all') ? $branchId : session('active_branch_id');
        $branchName = '';
        if ($selectedBranchId && $selectedBranchId !== 'all') {
            $branch = \App\Models\Branch::find($selectedBranchId);
            if ($branch) {
                $branchName = strtoupper($branch->name);
            }
        }

        $companyName = getSetting('titleText', 'KIRAN INDUSTRIES') . ($branchName ? " - " . $branchName : "");

        $headerTitle = !empty($titleParts)
            ? implode(" - ", $titleParts) . " BIOMETRIC REPORT" . ($branchName ? " (" . $branchName . ")" : "")
            : "BIOMETRIC PRESENT REPORT" . ($branchName ? " (" . $branchName . ")" : "");

        if ($reportId === 'biometric_dedicated') {
            if ($reportType === 'department') {
                $headerTitle = "DEPARTMENTWISE BIOMETRIC REPORT" . ($branchName ? " (" . $branchName . ")" : "");
            } else {
                $headerTitle = "CODEWISE BIOMETRIC REPORT" . ($branchName ? " (" . $branchName . ")" : "");
            }
        }

        $totalPresent = 0;
        if ($isBiometricSingle && ! $isMultipleDays) {
            $totalEmployees = count(array_unique(array_column($reportData, 'code')));
        } else {
            $totalEmployees = count($reportData);
        }
        foreach ($finalData as $deptRows) {
            foreach ($deptRows as $row) {
                if ($row['status'] === 'P')
                    $totalPresent++;
            }
        }

        $viewName = match ($request->get('report_id')) {
            'biometric_dedicated' => 'reports.biometric_dedicated',
            'mispunch_dedicated' => 'reports.mispunch_dedicated',
            'manual_entries' => 'reports.manual_entries',
            default => 'reports.biometric_present'
        };

        if ($request->get('report_id') === 'manual_entries') {
            $headerTitle = "MANUAL ENTRY ATTENDANCE REPORT" . ($branchName ? " (" . $branchName . ")" : "");
            
            $hasAnyManual = false;
            foreach ($reportData as $row) {
                if (!empty($row['is_manual'])) {
                    $hasAnyManual = true;
                    break;
                }
            }
            
            if (!$hasAnyManual && $request->get('is_chunk')) {
                return response('')->header('X-Empty-Chunk', 'true');
            }
        }

        // Get logo
        $logoDark = getSetting('logoDark');
        $logo = null;
        if ($logoDark) {
            $possiblePaths = [
                public_path('storage/media/' . $logoDark),
                public_path('storage/' . $logoDark),
                storage_path('app/public/media/' . $logoDark),
                storage_path('app/public/' . $logoDark)
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $logoData = file_get_contents($path);
                    $logo = 'data:image/' . $type . ';base64,' . base64_encode($logoData);
                    break;
                }
            }
        }
        
        if (!$logo) {
            $path = public_path('assets/images/logo.png');
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $logoData = file_get_contents($path);
                $logo = 'data:image/' . $type . ';base64,' . base64_encode($logoData);
            }
        }

        $data = [
            'reportTitle' => $headerTitle,
            'title' => $headerTitle,
            'headerTitle' => $headerTitle,
            'reportType' => $isBiometricSingle ? ('Biometric Daily Report' . (isset($cat) && $cat ? ' - ' . strtoupper($cat->name) : '')) : $reportType,
            'period' => $fromDate->format('d/m/Y') . ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d') ? " - " . $toDate->format('d/m/Y') : ""),
            'fromDate' => $fromDate->format('d/m/Y'),
            'toDate' => $toDate->format('d/m/Y'),
            'reportData' => $finalData,
            'totalPresent' => $totalPresent,
            'totalEmployees' => ($request->has('global_total_employees') && $isBiometricSingle)
                ? (int) $request->get('global_total_employees')
                : $totalEmployees,
            'companyName' => $companyName,
            'isMultipleDays' => $isMultipleDays,
            'printDate' => now()->format('d/m/Y H:i:s'),
            'isLastChunk' => $request->has('is_last_chunk') ? $request->get('is_last_chunk') : true,
            'logo' => $logo,
        ];
        return $this->renderFinalReport($viewName, $data, $orientation, $request, $fromDate, $toDate, $titleParts);
    }

    private function renderFinalReport($viewName, $data, $orientation, $request, $fromDate, $toDate, $titleParts)
    {
        $reportTitle = implode(' - ', $titleParts);
        $pdf = Pdf::setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'helvetica'
        ])->loadView($viewName, $data);
        $pdf->setPaper('a4', $orientation);

        // Render first to get canvas
        $pdf->render();
        $domPdf = $pdf->getDomPDF();
        $canvas = $domPdf->get_canvas();
        $fontBold = $domPdf->getFontMetrics()->getFont('Helvetica', 'bold');
        $fontNormal = $domPdf->getFontMetrics()->getFont('Helvetica', 'normal');

        $isMonthlyReport = str_contains($viewName, 'monthly_') || str_contains($viewName, 'incentive_report') || str_contains($viewName, 'monthly_deduction_report');
        if (!$request->get('is_chunk')) {
            if ($isMonthlyReport) {
                if (str_contains($viewName, 'monthly_earning_deduction')) {
                    $canvas->page_text(
                        760,
                        42,
                        "PAGE {PAGE_NUM} OF {PAGE_COUNT}",
                        $fontBold,
                        8,
                        [0.1, 0.21, 0.36]
                    );
                } else {
                    $canvas->page_text(
                        520,
                        36,
                        "PAGE {PAGE_NUM} OF {PAGE_COUNT}",
                        $fontBold,
                        8,
                        [0.1, 0.21, 0.36]
                    );
                }
            } else {
                $canvas->page_text(
                    520,
                    35,
                    "PAGE {PAGE_NUM} OF {PAGE_COUNT}",
                    $fontBold,
                    8,
                    [0.1, 0.21, 0.36]
                );
            }
        }

        $canvas->page_text(
            20,
            815,
            "Develop by Sridix Technology LLP",
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        if (!$request->get('is_chunk')) {
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                if ($pageNumber < $pageCount) {
                    $font = $fontMetrics->get_font("Helvetica", "bold");
                    $canvas->text(
                        240,
                        815,
                        "Continued On Page No... " . ($pageNumber + 1),
                        $font,
                        7,
                        [0.4, 0.4, 0.4]
                    );
                }
            });
        }

        $canvas->page_text(
            440,
            815,
            "Printed On : " . now()->format('d/m/Y H:i:s'),
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        $fileTitle = !empty($titleParts) ? implode('_', $titleParts) : 'REPORT';
        $fileDate = $fromDate->format('d-m-Y');
        if ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d')) {
            $fileDate .= '_to_' . $toDate->format('d-m-Y');
        }

        if ($request->get('report_id') === 'biometric_single') {
            $fileName = 'Daily_Present_Report_' . $fromDate->format('d_M_Y');
            if ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d')) {
                $fileName .= '_to_' . $toDate->format('d_M_Y');
            }
        } else {
            $fileName = 'BIOMETRIC_' . strtoupper($fileTitle) . '_' . $fileDate;
        }
        $fileName = str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $fileName);
        $fileName = preg_replace('/_+/', '_', $fileName);

        $pdfOutput = $domPdf->output();
        $filePath = 'reports/' . $fileName . '_' . time() . '.pdf';

        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfOutput);

        if (!$request->get('is_chunk')) {
            \App\Models\ReportDownload::create([
                'user_id' => auth()->id() ?? 1,
                'report_name' => \App\Models\ReportDownload::generateReadableName($request),
                'file_path' => $filePath,
                'status' => 'completed',
                'progress' => 100,
                'filters' => $request->all(),
            ]);
        }

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '.pdf"');
    }

    private function generateIncentiveReport(Request $request)
    {
        $fromDate = Carbon::parse($request->get('from_date'))->startOfDay();
        $toDate = Carbon::parse($request->get('to_date'))->endOfDay();
        $branchId = $request->get('branch_id') ?? 1;

        // Fetch Incentive entries within the date range
        $query = \App\Models\MonthlyIncentiveEntry::with(['employee.user', 'employee.section', 'employee.department', 'employee.category'])
            ->whereHas('employee.user', function ($q) {
                $q->where('status', 'active');
            })
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()]);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Apply employee-level filters via join or whereHas
        $query->whereHas('employee', function ($q) use ($request) {
            if ($request->get('section') && $request->get('section') !== 'all') {
                $q->where('section_id', $request->get('section'));
            }
            if ($request->get('department') && $request->get('department') !== 'all') {
                $q->where('department_id', $request->get('department'));
            }
            if ($request->get('category') && $request->get('category') !== 'all') {
                $q->where('category_id', $request->get('category'));
            }
            if ($request->get('po_status') && $request->get('po_status') !== 'all') {
                $q->where('po_status', $request->get('po_status'));
            }
        });

        $entries = $query->get();

        // Group by employee to sum up multiple entries in the range if any
        $groupedEntries = $entries->groupBy('employee_id');

        $reportData = [];
        foreach ($groupedEntries as $empId => $empEntries) {
            $employee = $empEntries->first()->employee;
            if (!$employee)
                continue;

            $totalIncentiveDays = $empEntries->sum('incentive_days');
            $totalIncentiveSalary = $empEntries->sum('incentive_salary');
            $totalAdvance = $empEntries->sum('advance_karchi');
            $totalInstallment = $empEntries->sum('installment_amt');

            $grossPay = (float) ($employee->gross_salary ?? 0);
            $incentAmt = (float) $totalIncentiveSalary;
            $prodIncent = 0.00; // Placeholder
            $totalAmt = $grossPay + $incentAmt + $prodIncent;

            $loanAdvance = (float) ($totalAdvance + $totalInstallment);
            $netPay = $totalAmt - $loanAdvance;

            $reportData[] = [
                'emy_code' => $employee->emy_code,
                'name' => ($employee->employee_id ? $employee->employee_id . '-' : '') . ($employee->user->name ?? 'N/A'),
                'gross_pay' => $grossPay,
                'incent_day' => $totalIncentiveDays,
                'incent_amt' => $incentAmt,
                'prod_incent' => $prodIncent,
                'total_amt' => $totalAmt,
                'loan_advance' => $loanAdvance,
                'net_pay' => $netPay,
            ];
        }

        // Sort by name
        usort($reportData, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $data = [
            'reportData' => $reportData,
            'reportTitle' => 'INCENTIVE REPORT',
            'reportType' => 'INCENTIVE',
            'period' => $fromDate->format('d/m/Y') . " - " . $toDate->format('d/m/Y'),
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES'),
            'printDate' => now()->format('d/m/Y H:i:s'),
            'fromDate' => $fromDate->format('d/m/Y'),
            'toDate' => $toDate->format('d/m/Y'),
        ];

        return $this->renderFinalReport('reports.incentive_report', $data, 'portrait', $request, $fromDate, $toDate, ['INCENTIVE REPORT']);
    }

    private function generateMonthlyDeductionReport(Request $request)
    {
        $month = $request->get('month');
        $year = $request->get('year');
        $monthYear = $year . '-' . $month;
        $branchId = $request->get('branch_id') ?? 1;

        $query = \App\Models\MonthlyIncentiveEntry::with(['details', 'employee.user', 'employee.section', 'employee.department', 'employee.category'])
            ->whereHas('employee.user', function ($q) {
                $q->where('status', 'active');
            })
            ->where('month_year', $monthYear);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Apply filters
        $query->whereHas('employee', function ($q) use ($request) {
            if ($request->get('section') && $request->get('section') !== 'all') {
                $q->where('section_id', $request->get('section'));
            }
            if ($request->get('department') && $request->get('department') !== 'all') {
                $q->where('department_id', $request->get('department'));
            }
            if ($request->get('category') && $request->get('category') !== 'all') {
                $q->where('category_id', $request->get('category'));
            }
        });

        $entries = $query->get();

        $reportData = [];
        foreach ($entries as $entry) {
            $employee = $entry->employee;
            if (!$employee)
                continue;

            $details = $entry->details;

            $getVal = function ($name, $type = 'deduction') use ($details) {
                $detail = $details->where('name', $name)->where('type', $type)->first();
                return $detail ? (float) $detail->value : 0.0;
            };

            $incAmt = $getVal('Incentive', 'earning');
            $advance = $getVal('Adv / Karchi');
            $canteen = $getVal('Canteen');
            $colony = $getVal('Colony');
            $mobile = $getVal('Mobile');
            $itax = $getVal('TDS AMT');
            $lateCome = (int) $getVal('Late Coming');
            $instal = $getVal('Instl. Amt');
            $sleepD = (int) $getVal('Sleeping');

            $total = $advance + $canteen + $colony + $mobile + $itax + $instal;

            $reportData[] = [
                'emy_code' => $employee->emy_code,
                'name' => ($employee->employee_id ? $employee->employee_id . '-' : '') . ($employee->user->name ?? 'N/A'),
                'inc_amt' => $incAmt,
                'advance' => $advance,
                'canteen' => $canteen,
                'colony' => $colony,
                'mobile' => $mobile,
                'itax' => $itax,
                'late_come' => $lateCome,
                'instal' => $instal,
                'sleep_d' => $sleepD,
                'total' => $total
            ];
        }

        if (empty($reportData)) {
            return response()->json(['error' => 'No deduction data found for the selected filters and period.'], 404);
        }

        usort($reportData, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $monthName = date('F', mktime(0, 0, 0, (int) $month, 10));

        $branch = \App\Models\Branch::find($request->get('branch_id') ?? 1);
        $branchName = $branch ? $branch->name : '';

        $data = [
            'reportData' => $reportData,
            'monthName' => strtoupper($monthName),
            'year' => $year,
            'reportTitle' => 'MONTHLY DEDUCTION LIST',
            'reportType' => $request->get('deduction_type', 'summary'),
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES'),
            'branchName' => $branchName,
            'period' => strtoupper($monthName) . " " . $year,
            'printDate' => now()->format('d/m/Y H:i:s'),
        ];

        // Mock dates for renderFinalReport
        $fromDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $toDate = $fromDate->copy()->endOfMonth();

        return $this->renderFinalReport('reports.monthly_deduction_report', $data, 'portrait', $request, $fromDate, $toDate, ['DEDUCTION LIST']);
    }

    private function generateProductionReport(Request $request)
    {
        $fromDate = Carbon::parse($request->get('from_date'))->startOfDay();
        $toDate = Carbon::parse($request->get('to_date'))->endOfDay();
        $branchId = $request->get('branch_id') ?? 1;
        $reportType = $request->get('report_type', 'summary');

        $query = DailyProductionAttendanceEntry::with(['employee.user', 'employee.section', 'employee.department', 'materialItem'])
            ->whereHas('employee.user', function ($q) {
                $q->where('status', 'active');
            })
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()]);

        if ($branchId && $branchId !== 'all') {
            $query->whereHas('employee', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        // Apply filters
        if ($request->get('section') && $request->get('section') !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('section_id', $request->get('section'));
            });
        }

        if ($request->get('department') && $request->get('department') !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->get('department'));
            });
        }

        $entries = $query->orderBy('date')->get();

        if ($reportType === 'summary') {
            // Group by employee and sum qty/amount
            $groupedData = [];
            foreach ($entries as $entry) {
                $empId = $entry->employee_id;
                if (!isset($groupedData[$empId])) {
                    $groupedData[$empId] = [
                        'code' => $entry->employee->emy_code ?? $entry->employee->employee_id,
                        'name' => $entry->employee->user->name ?? 'N/A',
                        'qty' => 0,
                        'amount' => 0,
                    ];
                }
                $groupedData[$empId]['qty'] += (float) $entry->production_qty;
                $groupedData[$empId]['amount'] += (float) $entry->amount;
            }

            usort($groupedData, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
            $reportData = $groupedData;
        } else {
            // Details: keep entries as they are
            $reportData = $entries->map(function ($entry) {
                return [
                    'code' => $entry->employee->emy_code ?? $entry->employee->employee_id,
                    'name' => $entry->employee->user->name ?? 'N/A',
                    'material' => $entry->materialItem->name ?? '---',
                    'rate' => $entry->rate,
                    'qty' => $entry->production_qty,
                    'amount' => $entry->amount,
                    'date' => $entry->date,
                ];
            })->toArray();

            usort($reportData, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }

        $data = [
            'reportData' => $reportData,
            'reportTitle' => 'PRODUCTION REPORT',
            'reportType' => $reportType,
            'fromDate' => $fromDate->format('d/m/Y'),
            'toDate' => $toDate->format('d/m/Y'),
            'period' => $fromDate->format('d/m/Y') . ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d') ? " TO " . $toDate->format('d/m/Y') : ""),
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES'),
            'branchName' => \App\Models\Branch::find($branchId)?->name ?? '',
            'printDate' => now()->format('d/m/Y H:i:s'),
        ];

        return $this->renderFinalReport('reports.production_report', $data, 'portrait', $request, $fromDate, $toDate, ['PRODUCTION REPORT']);
    }

    private function generateMonthlyProductionReport(Request $request)
    {
        if ($request->has('month') && $request->has('year') && $request->get('month') && $request->get('year')) {
            $month = $request->get('month');
            $year = $request->get('year');
            $fromDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $toDate = $fromDate->copy()->endOfMonth();
        } else {
            $fromDate = Carbon::parse($request->get('from_date', now()->startOfMonth()->toDateString()))->startOfDay();
            $toDate = Carbon::parse($request->get('to_date', now()->endOfMonth()->toDateString()))->endOfDay();
        }
        $branchId = $request->get('branch_id');
        $deptId = $request->get('department');
        $sectId = $request->get('section');
        $catId = $request->get('category');
        $employeeId = $request->get('employee_id');

        $employeesQuery = \App\Models\Employee::withoutGlobalScopes()
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['user', 'department', 'section']);

        if ($branchId && $branchId !== 'all') {
            $employeesQuery->where('branch_id', $branchId);
        }
        if ($deptId && $deptId !== 'all') {
            $employeesQuery->where('department_id', $deptId);
        }
        if ($sectId && $sectId !== 'all') {
            $employeesQuery->where('section_id', $sectId);
        }
        if ($catId && $catId !== 'all') {
            $employeesQuery->where('category_id', $catId);
        }
        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->orderBy('emy_code')->get();

        $productionEntries = \App\Models\DailyProductionAttendanceEntry::with('materialItem')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $reportData = [];
        foreach ($employees as $employee) {
            $empProd = $productionEntries->get($employee->id) ?? collect();
            $qty = (float) $empProd->sum('production_qty');
            $earning = (float) $empProd->sum('amount');
            $rate = $qty > 0 ? ($earning / $qty) : 0;

            $materials = $empProd->map(function ($entry) {
                return $entry->materialItem->name ?? '';
            })->filter()->unique()->implode(', ');
            $materials = $materials ?: '---';

            $dates = $empProd->map(function ($entry) {
                return $entry->date ? \Carbon\Carbon::parse($entry->date)->format('d/m/Y') : '';
            })->filter()->unique()->implode(', ');
            $dates = $dates ?: '---';

            if ($qty > 0 || $earning > 0) {
                $reportData[] = [
                    'code' => $employee->emy_code ?? $employee->employee_id,
                    'name' => $employee->user->name ?? 'N/A',
                    'department' => $employee->department?->name ?? 'N/A',
                    'section' => $employee->section?->name ?? 'N/A',
                    'qty' => $qty,
                    'earning' => $earning,
                    'rate' => $rate,
                    'material' => $materials,
                    'date' => $dates,
                ];
            }
        }

        if (empty($reportData)) {
            return response()->json(['error' => 'No production data found for the selected filters and period.'], 404);
        }

        usort($reportData, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        // Group by department
        $groupedData = [];
        foreach ($reportData as $row) {
            $groupedData[$row['department']][] = $row;
        }

        // Sort groups naturally
        ksort($groupedData, SORT_NATURAL | SORT_FLAG_CASE);

        // Assign serial number per group
        foreach ($groupedData as $deptName => &$rows) {
            foreach ($rows as $i => &$r) {
                $r['sr'] = $i + 1;
            }
            unset($r);
        }
        unset($rows);

        $reportData = $groupedData;

        $branchName = '';
        if ($branchId && $branchId !== 'all') {
            $branchName = \App\Models\Branch::find($branchId)?->name ?? '';
        }

        $data = [
            'reportData' => $reportData,
            'reportTitle' => 'MONTHLY PRODUCTION REPORT',
            'reportType' => 'PRODUCTION',
            'fromDate' => $fromDate->format('d/m/Y'),
            'toDate' => $toDate->format('d/m/Y'),
            'period' => $fromDate->format('d/m/Y') . ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d') ? " TO " . $toDate->format('d/m/Y') : ""),
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES'),
            'branchName' => $branchName,
            'printDate' => now()->format('d/m/Y H:i:s'),
        ];

        return $this->renderFinalReport('reports.monthly_production_report', $data, 'portrait', $request, $fromDate, $toDate, ['MONTHLY PRODUCTION REPORT']);
    }

    private function generateMonthlyEarningDeductionReport(Request $request)
    {
        $fromDate = Carbon::parse($request->get('from_date'))->startOfDay();
        $toDate = Carbon::parse($request->get('to_date'))->endOfDay();
        $branchId = $request->get('branch_id');
        $deptId = $request->get('department');
        $sectId = $request->get('section');
        $catId = $request->get('category');
        $employeeId = $request->get('employee_id');

        $employeesQuery = \App\Models\Employee::withoutGlobalScopes()
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['user', 'department', 'section', 'category', 'shift', 'designation']);

        if ($branchId && $branchId !== 'all') {
            $employeesQuery->where('branch_id', $branchId);
        }
        if ($deptId && $deptId !== 'all') {
            $employeesQuery->where('department_id', $deptId);
        }
        if ($sectId && $sectId !== 'all') {
            $employeesQuery->where('section_id', $sectId);
        }
        if ($catId && $catId !== 'all') {
            $employeesQuery->where('category_id', $catId);
        }
        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->orderBy('emy_code')->get();

        $monthYears = [];
        $curr = $fromDate->copy();
        while ($curr->lte($toDate)) {
            $monthYears[] = $curr->format('Y-m');
            $curr->addMonth();
        }
        $monthYears = array_unique($monthYears);

        $incentiveEntries = \App\Models\MonthlyIncentiveEntry::with('details')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereIn('month_year', $monthYears)
            ->get()
            ->groupBy('employee_id');

        $earningColumns = [];
        $deductionColumns = [];

        foreach ($incentiveEntries as $empId => $entries) {
            foreach ($entries as $entry) {
                foreach ($entry->details as $detail) {
                    $name = trim($detail->name ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $type = $detail->type;
                    if ($type === 'earning' || $type === 'Incentive') {
                        $earningColumns[] = $name;
                    } elseif ($type === 'deduction' || $type === 'Penalty') {
                        $deductionColumns[] = $name;
                    }
                }
            }
        }

        $earningColumns = array_values(array_unique(array_filter($earningColumns)));
        $deductionColumns = array_values(array_unique(array_filter($deductionColumns)));

        sort($earningColumns);
        sort($deductionColumns);

        $reportData = [];
        foreach ($employees as $employee) {
            $empEntries = $incentiveEntries->get($employee->id) ?? collect();
            if ($empEntries->isEmpty()) {
                continue;
            }

            $basic = (float) ($employee->basic_salary ?? 0);
            $hra = (float) ($employee->hra_allowance ?? 0);
            $allowance = (float) ($employee->special_allowance ?? 0);
            $monthlyGross = $basic + $hra + $allowance;
            $workingDays = ($employee->working_days > 0) ? $employee->working_days : 26;
            $perDaySalary = $workingDays > 0 ? ($monthlyGross / $workingDays) : 0;

            $earnings = [];
            $deductions = [];
            foreach ($earningColumns as $col) {
                $earnings[$col] = ['amount' => 0.0, 'mode' => 'amount', 'qty' => 0.0];
            }
            foreach ($deductionColumns as $col) {
                $deductions[$col] = ['amount' => 0.0, 'mode' => 'amount', 'qty' => 0.0];
            }

            foreach ($empEntries as $entry) {
                foreach ($entry->details as $detail) {
                    $name = trim($detail->name ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $type = $detail->type;
                    $mode = $detail->mode;
                    $value = (float) ($detail->value ?? 0);

                    $rupeeVal = ($mode === 'day') ? round($value * $perDaySalary, 2) : round($value, 2);

                    if ($type === 'earning' || $type === 'Incentive') {
                        $earnings[$name]['amount'] += $rupeeVal;
                        $earnings[$name]['mode'] = $mode;
                        $earnings[$name]['qty'] += $value;
                    } elseif ($type === 'deduction' || $type === 'Penalty') {
                        $deductions[$name]['amount'] += $rupeeVal;
                        $deductions[$name]['mode'] = $mode;
                        $deductions[$name]['qty'] += $value;
                    }
                }
            }

            $totalEarning = 0.0;
            foreach ($earnings as $col => $info) {
                $totalEarning += $info['amount'];
            }
            $totalDeduction = 0.0;
            foreach ($deductions as $col => $info) {
                $totalDeduction += $info['amount'];
            }
            $netPay = $totalEarning - $totalDeduction;

            $reportData[] = [
                'code' => $employee->emy_code ?? $employee->employee_id,
                'name' => $employee->user->name ?? 'N/A',
                'department' => $employee->department?->name ?? 'N/A',
                'designation' => $employee->designation?->name ?? 'N/A',
                'section' => $employee->section?->name ?? 'N/A',
                'earnings' => $earnings,
                'deductions' => $deductions,
                'total_earning' => $totalEarning,
                'total_deduction' => $totalDeduction,
                'net_pay' => $netPay,
            ];
        }

        if (empty($reportData)) {
            return response()->json(['error' => 'No earning-deduction data found for the selected filters and period.'], 404);
        }

        usort($reportData, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        // Group by department
        $groupedData = [];
        foreach ($reportData as $row) {
            $groupedData[$row['department']][] = $row;
        }

        // Sort groups naturally
        ksort($groupedData, SORT_NATURAL | SORT_FLAG_CASE);

        $reportData = $groupedData;

        $branchName = '';
        if ($branchId && $branchId !== 'all') {
            $branchName = \App\Models\Branch::find($branchId)?->name ?? '';
        }

        $reportId = $request->get('report_id');
        $viewName = 'reports.monthly_earning_deduction_report';
        $title = 'MONTHLY EARNING-DEDUCTION REPORT';
        $reportTypeLabel = 'EARNING-DEDUCTION';
        $orientation = 'landscape';

        if ($reportId === 'monthly_earning') {
            $viewName = 'reports.monthly_earning_report';
            $title = 'MONTHLY EARNING REPORT';
            $reportTypeLabel = 'EARNING';
            $orientation = 'portrait';
        } elseif ($reportId === 'monthly_deduction_payroll' || $reportId === 'monthly_deductionyroll') {
            $viewName = 'reports.monthly_deduction_payroll_report';
            $title = 'MONTHLY DEDUCTION REPORT';
            $reportTypeLabel = 'DEDUCTION';
            $orientation = 'portrait';
        } elseif ($reportId === 'monthly_payroll_summary' || $reportId === 'monthlyyroll_summary') {
            $viewName = 'reports.monthly_payroll_summary_report';
            $title = 'MONTHLY PAYROLL SUMMARY REPORT';
            $reportTypeLabel = 'PAYROLL SUMMARY';
            $orientation = 'portrait';
        }

        $data = [
            'reportData' => $reportData,
            'earningColumns' => $earningColumns,
            'deductionColumns' => $deductionColumns,
            'reportTitle' => $title,
            'reportType' => $reportTypeLabel,
            'fromDate' => $fromDate->format('d/m/Y'),
            'toDate' => $toDate->format('d/m/Y'),
            'period' => $fromDate->format('d/m/Y') . ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d') ? " TO " . $toDate->format('d/m/Y') : ""),
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES'),
            'branchName' => $branchName,
            'printDate' => now()->format('d/m/Y H:i:s'),
        ];

        return $this->renderFinalReport($viewName, $data, $orientation, $request, $fromDate, $toDate, [$title]);
    }
}
