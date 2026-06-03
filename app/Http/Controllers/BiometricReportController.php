<?php

namespace App\Http\Controllers;

require_once app_path('Helpers/EncryptHelper.php');

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Section;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Shift;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BiometricReportController extends Controller
{
    /**
     * Generate dedicated Biometric reports.
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

        $startTime = microtime(true);
        $isChunk = $request->boolean('is_chunk');

        if (! $isChunk) {
            \Log::info('Biometric Report Started: ' . now());
        }

        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $dbStart = $mapStart = $procStart = $pdfStart = microtime(true);

        $reportId = $request->get('report_id');
        $isMispunchReport = ($reportId === 'mispunch_dedicated');
        $reportType = $request->get('report_type', 'codewise');
        $fromDate = Carbon::parse($request->get('from_date'))->startOfDay();
        $toDate = Carbon::parse($request->get('to_date'))->endOfDay();
        $branchId = $request->get('branch_id') ?? session('active_branch_id');
        $sectionId = $request->get('section');
        $deptId = $request->get('department');
        $catId = $request->get('category');
        $poStatus = $request->get('po_status');
        $status = $request->get('status', 'all');
        if ($isMispunchReport) {
            $status = 'MIS';
        }
        $statusMinutes = max(0, (int) $request->get('status_minutes', 0));
        $employeeId = $request->get('employee_id');
        $employeeIdsArray = $request->get('employee_ids'); // For chunking jobs

        if (! $isChunk) {
            \Log::info('Biometric Report Query Initiated', [
                'report_type' => $reportType,
                'status' => $status,
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
            ]);
        }

        // Eager-load relations required by the PDF template and "manual" (M) markers.
        // Keep this aligned with the original output logic to avoid changing report formatting/content.
        $eagerLoad = [
            'employee.user',
            'employee.designation',
            'department',
            'section',
            'category',
            'manualUser',
            'logs',
        ];

        // 1. Fetch Processed Attendance Data directly from the table
        $query = \App\Models\BiometricAttendance::with($eagerLoad)
            ->whereHas('employee.user', function ($q) {
                $q->where('status', 'active');
            })
            ->whereBetween('attendance_date', [$fromDate, $toDate]);

        if ($isMispunchReport) {
            applyMispunchReportDateScope($query);
        }

        $query->whereHas('employee', function ($q) use ($branchId, $sectionId, $deptId, $catId, $poStatus, $employeeId, $employeeIdsArray) {
            $q->withoutGlobalScopes();
            if ($branchId && $branchId !== 'all') {
                $q->where('branch_id', $branchId);
            }
            if ($sectionId && $sectionId !== 'all') {
                $q->where('section_id', $sectionId);
            }
            if ($deptId && $deptId !== 'all') {
                $q->where('department_id', $deptId);
            }
            if ($catId && $catId !== 'all') {
                $q->where('category_id', $catId);
            }
            if ($poStatus && $poStatus !== 'all') {
                $val = ($poStatus === 'P') ? 'Permanent' : 'Other';
                $q->where('po_status', $val);
            }
            if ($employeeIdsArray && is_array($employeeIdsArray) && count($employeeIdsArray) > 0) {
                $q->whereIn('id', $employeeIdsArray);
            } elseif ($employeeId && $employeeId !== 'all') {
                $q->where('id', $employeeId);
            }
        });

        if ($employeeIdsArray && is_array($employeeIdsArray) && count($employeeIdsArray) > 0) {
            $query->whereIn('employee_id', $employeeIdsArray);
        } elseif ($employeeId && $employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        // Apply Status Filter to Query if possible for performance
        if ($status && $status !== 'all') {
            if ($status === 'P') {
                $query->whereIn('status', ['P', 'MIS', 'HD', 'OD', 'CO']);
            } elseif ($status === 'A') {
                // Absent logic is handled differently usually, but for this report we filter records
                $query->where('status', 'A');
            } elseif ($status === 'MIS') {
                $query->where('status', 'MIS');
            } elseif ($status === 'HD') {
                $query->where('status', 'HD');
            } elseif ($status === 'overtime') {
                $query->where('ot_minutes', '>', 0);
            } elseif ($status === 'latein') {
                $query->where('late_in', '!=', '0m')->whereNotNull('late_in');
            } elseif ($status === 'earlyout') {
                $query->where('early_out', '!=', '0m')->whereNotNull('early_out');
            }
        }

        $rowCount = $request->boolean('debug') ? (clone $query)->count() : 0;

        if ($request->boolean('debug')) {
            $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
            return response()->json([
                'ok' => true,
                'message' => 'Debug only — PDF not generated. Remove &debug=1 to run full report.',
                'rows_matching_query' => $rowCount,
                'date_range_days' => $fromDate->diffInDays($toDate) + 1,
                'php' => [
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'memory_peak_mb' => $peakMb,
                ],
                'server' => [
                    'sapi' => PHP_SAPI,
                    'os' => PHP_OS,
                ],
                'filters' => [
                    'branch_id' => $branchId,
                    'status' => $status,
                    'report_type' => $reportType,
                    'from' => $fromDate->toDateString(),
                    'to' => $toDate->toDateString(),
                ],
                'hints' => [
                    'rows_over_15000' => $rowCount > 15000 ? 'Very large — PDF may timeout on live (Cloudflare/nginx 100s limit)' : null,
                    'rows_over_50000' => $rowCount > 50000 ? 'Too large for one PDF — split by month or department' : null,
                ],
            ]);
        }

        // 1. Fetch Processed Attendance Data
        $attendances = $query->orderBy('attendance_date')
            ->get();

        if (! $isChunk) {
            \Log::info('Biometric Report rows loaded', [
                'count' => $attendances->count(),
                'memory_sec' => round(microtime(true) - $startTime, 2),
            ]);
        }

        // 2. Format Data for Report
        $reportData = [];
        $allIds = [];

        foreach ($attendances as $att) {
            $emp = $att->employee;
            if (!$emp)
                continue;

            $allIds[] = $emp->id;

            // Strict Status Filtering inside loop
            if ($status === 'overtime' && (int) $att->ot_minutes <= 0)
                continue;
            if ($status === 'latein' && (!$att->late_in || $att->late_in === '0m' || $att->late_in === 'ON TIME'))
                continue;
            if ($status === 'earlyout' && (!$att->early_out || $att->early_out === '0m' || $att->early_out === '-'))
                continue;
            if (!passesStatusMinutesThreshold($status, $att, $statusMinutes))
                continue;
            if ($status === 'MIS' && $att->status !== 'MIS')
                continue;

            $isInManual = false;
            $isOutManual = false;
            if ($att->is_manual) {
                if ($att->logs && $att->logs->isNotEmpty()) {
                    $isInManual = $att->logs->where('punch_type', 'IN')->where('is_manual', true)->isNotEmpty();
                    $isOutManual = $att->logs->where('punch_type', 'OUT')->where('is_manual', true)->isNotEmpty();
                } else {
                    if ($att->status === 'MIS') {
                        if ($att->in_time && $att->in_count > 0) {
                            $isInManual = true;
                        } elseif ($att->out_time && $att->out_count > 0) {
                            $isOutManual = true;
                        }
                    } else {
                        $isInManual = (bool) $att->in_time;
                        $isOutManual = (bool) $att->out_time;
                    }
                }
            }

            // Format timings
            $displayIn = $att->in_time ? $att->in_time->format('H:i') : '---';
            $displayOut = $att->out_time ? $att->out_time->format('H:i') : '---';

            if ($att->status === 'MIS') {
                if ($att->in_count > 0 && $att->out_count == 0)
                    $displayOut = '---';
                elseif ($att->out_count > 0 && $att->in_count == 0)
                    $displayIn = '---';
            }

            if ($isInManual && $displayIn !== '---') {
                $displayIn .= ' (M)';
            }
            if ($isOutManual && $displayOut !== '---') {
                $displayOut .= ' (M)';
            }

            $totalMin = (int) $att->total_minutes;
            $formattedHours = ($totalMin > 0) ? floor($totalMin / 60) . 'h ' . ($totalMin % 60) . 'm' : '0h 0m';

            $otMin = (int) $att->ot_minutes;
            $formattedOT = ($otMin > 0) ? floor($otMin / 60) . 'h ' . ($otMin % 60) . 'm' : '0h 0m';

            $formattedLate = ($att->late_in && $att->late_in !== '0m') ? $att->late_in : 'ON TIME';
            $formattedEarly = ($att->early_out && $att->early_out !== '0m') ? $att->early_out : '-';

            $issues = [];
            if ($att->status === 'MIS') {
                if (!$att->in_time) {
                    $issues[] = 'Missing IN';
                }
                if (!$att->out_time) {
                    $issues[] = 'Missing OUT';
                }
                if (empty($issues) && function_exists('getMispunchIssuesFromPairs')) {
                    $issues = getMispunchIssuesFromPairs(parseLogDetailsToPairs($att->log_details ?? ''));
                }
            }
            $issuesText = !empty($issues) ? implode('; ', $issues) : ($att->status === 'MIS' ? 'Incomplete punch' : '-');
            $punchPairs = ($isMispunchReport || $att->status === 'MIS')
                ? buildMispunchPairsForReport($att)
                : [];

            $reportData[] = [
                'id' => $emp->id,
                'code' => $emp->emy_code,
                'name' => $emp->user->name ?? 'N/A',
                'department' => $att->department->name ?? 'N/A',
                'category' => optional($att->category)->name ?? optional($emp->category)->name ?? 'N/A',
                'doj' => $emp->date_of_joining ? Carbon::parse($emp->date_of_joining)->format('d/m/Y') : '---',
                'date' => $att->attendance_date->format('d/m/Y'),
                'shift' => $att->shift_code ?? $att->base_shift ?? '---',
                'time_in' => $displayIn,
                'time_out' => $displayOut,
                'hours' => $formattedHours,
                'overtime' => $formattedOT,
                'late_in' => $formattedLate,
                'early_out' => $formattedEarly,
                'mis_punch' => ($att->status === 'MIS') ? 'YES' : '-',
                'is_manual' => (bool) $att->is_manual,
                'manual_by_name' => $att->manualUser->name ?? '-',
                'status' => $att->status,
                'status_label' => \App\Services\ActivityLogger::humanAttendanceStatus((string) $att->status),
                'duty' => number_format((float) ($att->duty_value ?? 0), 1),
                'log_details' => $att->log_details,
                'issues_text' => $issuesText,
                'punch_pairs' => $punchPairs,
                'incomplete_pairs' => count(array_filter($punchPairs, fn ($p) => !$p['complete'])),
                'designation' => $emp->designation->name ?? '---',
                'lunch_time' => $emp->lunch_time ? $emp->lunch_time . ' Min' : '---',
            ];
        }

        // 3. Group and Sort Data
        $finalGroupedData = [];
        if ($reportType === 'codewise') {
            foreach ($reportData as $row) {
                $finalGroupedData[$row['code'] . ' - ' . $row['name']][] = $row;
            }
            ksort($finalGroupedData);
        } elseif ($reportType === 'namewise') {
            foreach ($reportData as $row) {
                $groupKey = $row['name'] . ' (' . $row['department'] . ')';
                $finalGroupedData[$groupKey][] = $row;
            }
            ksort($finalGroupedData);
        } elseif ($reportType === 'department') {
            foreach ($reportData as $row) {
                $finalGroupedData[$row['department']][] = $row;
            }
            ksort($finalGroupedData);
        } else {
            $finalGroupedData['ALL RECORDS'] = $reportData;
        }

        $reportData = $finalGroupedData;
        $uniqueEmployeesCount = count(array_unique($allIds));

        if (empty($reportData) && $request->get('is_chunk')) {
            return response('')->header('X-Empty-Chunk', 'true');
        }

        if (empty($reportData) && ! $isChunk) {
            $message = 'No mispunch records found for the selected criteria.';
            if ($isMispunchReport) {
                $message = 'No missed punch records found for the selected date range and filters.';
            }

            $pdfOutput = Pdf::loadView('reports.no_records', [
                'message' => $message,
            ])->setPaper('a4', 'portrait')->output();

            $fileName = ($isMispunchReport ? 'MISPUNCH' : 'BIOMETRIC') . '_NO_RECORDS_' . time() . '.pdf';
            $filePath = 'reports/' . $fileName;

            \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfOutput);

            if (! $request->get('is_chunk')) {
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
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('X-Empty-Chunk', 'true');
        }

        // 7. Final Rendering with Corporate Headers & Footers
        $pdfStart = microtime(true);
        $selectedBranch = null;
        if ($branchId && $branchId !== 'all') {
            $selectedBranch = Branch::find($branchId);
        } elseif (session()->has('active_branch_id')) {
            $selectedBranch = Branch::find(session('active_branch_id'));
        }

        $branchNameDisplay = $selectedBranch ? " - " . strtoupper($selectedBranch->name) : "";
        $companyNameFull = getSetting('titleText', 'KIRAN INDUSTRIES') . $branchNameDisplay;

        $reportTitle = strtoupper($reportType) . ' BIOMETRIC REPORT';
        if ($isMispunchReport) {
            $reportTitle = ($reportType === 'department')
                ? 'DEPARTMENTWISE MISPUNCH REPORT'
                : 'CODEWISE MISPUNCH REPORT';
        }
        if ($employeeId && $employeeId !== 'all') {
            $selectedEmployee = Employee::withoutGlobalScopes()
                ->with('user:id,name')
                ->find($employeeId);
            if ($selectedEmployee) {
                $empCode = $selectedEmployee->emy_code ?? $selectedEmployee->employee_id;
                $empName = strtoupper($selectedEmployee->user->name ?? '');
                $reportTitle .= ' — ' . $empCode . ($empName ? ' ' . $empName : '');
            }
        }

        $departmentName = 'ALL';
        if ($deptId && $deptId !== 'all') {
            $dept = \App\Models\Department::find($deptId);
            if ($dept) $departmentName = strtoupper($dept->name);
        }

        $sectionName = 'ALL';
        if ($sectionId && $sectionId !== 'all') {
            $sec = \App\Models\Section::find($sectionId);
            if ($sec) $sectionName = strtoupper($sec->name);
        }

        $categoryName = 'ALL';
        if ($catId && $catId !== 'all') {
            $cat = \App\Models\Category::find($catId);
            if ($cat) $categoryName = strtoupper($cat->name);
        }

        $statusFilterLabels = [
            'all' => 'ALL',
            'P' => 'Present',
            'A' => 'Absent',
            'MIS' => 'MisPunch',
            'HD' => 'Half Day',
            'overtime' => 'Overtime',
            'latein' => 'Late In',
            'earlyout' => 'Early Out',
        ];
        $statusName = $statusFilterLabels[$status] ?? strtoupper((string) ($status ?: 'ALL'));

        $pdfData = [
            'reportData' => $reportData,
            'reportTitle' => $reportTitle,
            'reportType' => strtoupper($reportType),
            'period' => $fromDate->format('d/m/Y') . " - " . $toDate->format('d/m/Y'),
            'departmentName' => $departmentName,
            'sectionName' => $sectionName,
            'categoryName' => $categoryName,
            'statusName' => $statusName,
            'companyName' => $companyNameFull,
            'uniqueEmployees' => array_unique($allIds),
            'totalRecords' => array_sum(array_map('count', $reportData)),
            'printDate' => now()->format('d/m/Y H:i:s'),
            'is_chunk' => $request->get('is_chunk', false),
            'is_last_chunk' => $request->get('is_last_chunk', false),
            'global_total_employees' => $request->get('global_total_employees', count(array_unique($allIds))),
            'isMispunchReport' => $isMispunchReport,
        ];

        $viewName = 'reports.biometric_report';
        if ($isMispunchReport) {
            $viewName = 'reports.mispunch_dedicated';
        } elseif ($reportId === 'biometric_all_punches') {
            $viewName = 'reports.biometric_all_punches';
        }

        if ($request->has('export_excel') && $request->get('export_excel') == '1') {
            $fileDate = $fromDate->format('d-m-Y');
            if ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d')) {
                $fileDate .= '_to_' . $toDate->format('d-m-Y');
            }
            $fileName = 'BIOMETRIC_' . strtoupper($reportType) . '_' . $fileDate . '.xlsx';

            $excelViewName = $viewName . '_excel';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\BiometricDedicatedExport($pdfData, $excelViewName),
                $fileName
            );
        }

        // return view($viewName, $pdfData);

        $pdf = Pdf::setOptions([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'helvetica'
        ])->loadView($viewName, $pdfData);

        $pdf->setPaper('a4', 'portrait');

        /*
|--------------------------------------------------------------------------
| DEBUG CANVAS TEXT
|--------------------------------------------------------------------------
*/

        $pdf->render();

        $domPdf = $pdf->getDomPDF();

        $canvas = $domPdf->get_canvas();

        $fontBold = $domPdf->getFontMetrics()->getFont('Helvetica', 'bold');
        $fontNormal = $domPdf->getFontMetrics()->getFont('Helvetica', 'normal');
        /*
  |--------------------------------------------------------------------------
  | PAGE NUMBER RIGHT CORNER
  |--------------------------------------------------------------------------
  */
        if (!$request->get('is_chunk')) {
            $canvas->page_text(
                505, // right side
                35,  // grey bar center
                "PAGE {PAGE_NUM} OF {PAGE_COUNT}",
                $fontBold,
                8,
                [0.1, 0.21, 0.36]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER LEFT : DEVELOPED BY
        |--------------------------------------------------------------------------
        */

        $canvas->page_text(
            20,
            812,
            "Developed By SRIDIX TECHNOLOGY LLP",
            $fontNormal,
            6,
            [0.5, 0.5, 0.5]
        );

        /*
        |--------------------------------------------------------------------------
        | FOOTER CENTER : CONTINUED
        |--------------------------------------------------------------------------
        */

        if (!$request->get('is_chunk')) {
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {

                if ($pageNumber < $pageCount) {

                    $font = $fontMetrics->get_font("Helvetica", "bold");

                    $canvas->text(
                        250,
                        812,
                        "Continued On Page No... " . ($pageNumber + 1),
                        $font,
                        7,
                        [0.4, 0.4, 0.4]
                    );
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER RIGHT : PRINTED ON
        |--------------------------------------------------------------------------
        */

        $canvas->page_text(
            455,
            812,
            "Printed On : " . now()->format('d/m/Y H:i:s'),
            $fontNormal,
            6,
            [0.5, 0.5, 0.5]
        );

        // Professional Filename Generation
        $fileDate = $fromDate->format('d-m-Y');
        if ($fromDate->format('Y-m-d') !== $toDate->format('Y-m-d')) {
            $fileDate .= '_to_' . $toDate->format('d-m-Y');
        }
        $fileName = ($isMispunchReport ? 'MISPUNCH' : 'BIOMETRIC') . '_' . strtoupper($reportType) . '_' . $fileDate . '.pdf';

        if (! $isChunk) {
            \Log::info('Biometric Report PDF ready', [
                'elapsed_sec' => round(microtime(true) - $startTime, 2),
            ]);
        }

        $pdfOutput = $pdf->output();
        $filePath = 'reports/' . str_replace('.pdf', '', $fileName) . '_' . time() . '.pdf';
        
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
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
    }
}
