<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Department;
use App\Models\ResignReason;
use App\Models\Section;
use App\Models\Designation;
use App\Models\Shift;
use App\Models\BankMaster;
use App\Models\Skill;
use App\Models\PfMaster;
use App\Models\EsiMaster;
use App\Models\Employee;
use App\Models\MaterialItem;
use App\Models\WeekOff;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class MasterReportController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'branch_id' => decryptId($request->get('branch_id')),
            'employee_id' => decryptId($request->get('employee_id')),
        ]);
        
        $type = $request->get('type', 'CNT');
        $data = [];
        $title = "";
        $col1 = "CODE";
        $col2 = "SHORT CODE";
        $col3 = "FULL NAME DESCRIPTION";

        switch ($type) {
            case 'PLC':
                $title = "Branch Master List";
                $items = Branch::whereIn('created_by', getCompanyAndUsersId())->get();
                $col1 = "Name";
                $col2 = "Address";
                $col3 = "City";
                $data = $items->map(function ($item) {
                    return ['col1' => $item->name, 'col2' => $item->address, 'col3' => $item->city];
                });
                break;

            case 'DPT':
                $title = "Department Master List";
                $deptQuery = Department::withoutGlobalScopes()
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->where('status', 'active');
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $deptQuery->where('branch_id', $activeBranchId);
                }
                $items = $deptQuery->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = null;
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code ?? $item->short_code ?? '-', 'col2' => $item->name];
                });
                break;

            case 'CNT':
                $title = "Category Master List";
                $query = Category::withoutGlobalScopes()
                    ->whereIn('created_by', getCompanyAndUsersId())
                    ->where('status', 'active');
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = null;
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code ?? '-', 'col2' => $item->name];
                });
                break;

            case 'WOF':
                $title = "Employee Individual Week-Off Report";
                $query = Employee::active()
                    ->whereNotNull('week_off')
                    ->where('week_off', '!=', '')
                    ->with(['user', 'shift']);
                
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Employee Name";
                $col3 = "Shift";
                $col4 = "Week Off";
                $data = $items->map(function ($item) {
                    return [
                        'col1' => $item->employee_id ?? $item->emy_code ?? '-',
                        'col2' => $item->user->name ?? '-',
                        'col3' => $item->shift->name ?? '-',
                        'col4' => $item->week_off
                    ];
                });
                break;

            case 'RLV':
                $title = "Resign Reason Master List";
                $items = ResignReason::where('is_active', true)->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = null;
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code ?? '-', 'col2' => $item->name];
                });
                break;

            case 'DSG':
                $title = "Designation Master List";
                $query = Designation::withoutGlobalScopes()->whereIn('created_by', getCompanyAndUsersId())->where('status', 'active');
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = null;
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code ?? '-', 'col2' => $item->name];
                });
                break;

            case 'SHT':
                $title = "Shift Master List";
                $query = Shift::with('slots')->whereIn('created_by', getCompanyAndUsersId())->where('status', 'active');
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = "Time (In-Out)";
                $data = $items->map(function ($item) {
                    $timings = $item->slots->map(fn($slot) => ($slot->start_time ?? '') . ' - ' . ($slot->end_time ?? ''))->implode(', ');
                    return ['col1' => $item->short_code ?? '-', 'col2' => $item->name, 'col3' => $timings];
                });
                break;

            case 'BNK':
                $title = "Bank Master List";
                $items = BankMaster::where('status', 'active')->get();
                $col1 = "Code";
                $col2 = "Bank Name";
                $col3 = "Branch Name";
                $col4 = "IFSC Code";
                $data = $items->map(function ($item) {
                    return [
                        'col1' => $item->code ?? '-',
                        'col2' => $item->bank_name,
                        'col3' => $item->branch_name ?? '-',
                        'col4' => $item->ifsc_code
                    ];
                });
                break;

            case 'SKL':
                $title = "Skill Master List";
                $query = Skill::whereIn('created_by', getCompanyAndUsersId())->where('status', true);
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = null;
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code ?? '-', 'col2' => $item->name];
                });
                break;

            case 'PFE':
                $subtype = $request->get('subtype', 'PF');
                $title = ($subtype == 'PF' ? "PF" : "ESI") . " Scheme Master List";
                $query = $subtype == 'PF' ? PfMaster::query() : EsiMaster::query();
                $items = $query->where('status', 'active')->get();
                $col1 = "Scheme Name";
                $col2 = "Employee %";
                $col3 = "Employer %";
                $data = $items->map(function ($item) {
                    return ['col1' => $item->name, 'col2' => $item->percentage_employee . '%', 'col3' => $item->percentage_employer . '%'];
                });
                break;

            case 'MAT':
                $title = "Material Item Master List";
                $query = MaterialItem::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                    ->where('status', 'active');
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = "Rate";
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code, 'col2' => $item->name, 'col3' => 'Rs.' . number_format($item->rate, 2)];
                });
                break;

            case 'SEC':
                $title = "Section Master List";
                $query = Section::withoutGlobalScopes()->whereIn('created_by', getCompanyAndUsersId())->where('status', 'active');
                $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
                if ($activeBranchId && $activeBranchId !== 'all') {
                    $query->where('branch_id', $activeBranchId);
                }
                $items = $query->get();
                $col1 = "Code";
                $col2 = "Name";
                $col3 = null;
                $data = $items->map(function ($item) {
                    return ['col1' => $item->code ?? '-', 'col2' => $item->name];
                });
                break;

            case 'STF':
                return $this->staffListing($request);
        }

        $branchName = "";
        if (session()->has('active_branch_id')) {
            $activeBranch = Branch::find(session('active_branch_id'));
            if ($activeBranch) {
                $branchName = " - " . strtoupper($activeBranch->name);
            }
        }

        $pdfData = [
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES') . $branchName,
            'reportTitle' => $title,
            'reportType' => "MASTER LISTING",
            'typeCode' => $type,
            'reportData' => $data,
            'period' => "AS ON " . now()->format('d/m/Y'),
            'printDate' => now()->format('d/m/Y H:i:s'),
            'col1' => $col1,
            'col2' => $col2,
            'col3' => $col3,
            'col4' => $col4 ?? null
        ];

        $fileName = 'master_report_' . strtolower($type) . '_' . date('Ymd');

        $pdfData['viewName'] = 'reports.master_listing';
        $pdfData['orientation'] = 'portrait';
        $pdfData['titleParts'] = explode(' ', $title);
        $pdfData['fromDate'] = now();
        $pdfData['toDate'] = now();

        return $this->renderFinalReport(
            'reports.master_listing',
            $pdfData,
            'portrait',
            $request,
            now(),
            now(),
            explode(' ', $title)
        );
    }

    /**
     * Standard PDF Renderer with Pagination and Footer Support
     */
    private function renderFinalReport($viewName, $data, $orientation, $request, $fromDate, $toDate, $titleParts)
    {
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

        /*
        |--------------------------------------------------------------------------
        | PAGE NUMBER RIGHT CORNER (IN GREY BAR)
        |--------------------------------------------------------------------------
        */
        $canvas->page_text(
            520,
            35,  // Balanced height for all reports
            "PAGE {PAGE_NUM} OF {PAGE_COUNT}",
            $fontBold,
            8,
            [0.1, 0.21, 0.36]
        );

        /*
        |--------------------------------------------------------------------------
        | FOOTER LEFT : DEVELOPED BY
        |--------------------------------------------------------------------------
        */
        $canvas->page_text(
            20,
            815,
            "Developed By SRIDIX TECHNOLOGY LLP",
            $fontNormal,
            7,
            [0.5, 0.5, 0.5]
        );

        /*
        |--------------------------------------------------------------------------
        | FOOTER CENTER : CONTINUED ON PAGE
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | FOOTER RIGHT : PRINTED ON
        |--------------------------------------------------------------------------
        */
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

        $fileName = 'MASTER_' . strtoupper($fileTitle) . '_' . $fileDate;
        $fileName = str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $fileName);
        $fileName = preg_replace('/_+/', '_', $fileName);

        $pdfOutput = $domPdf->output();
        $filePath = 'reports/' . $fileName . '_' . time() . '.pdf';

        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfOutput);

        $readableName = str_replace('_', ' ', strtoupper($fileTitle)) . ' (' . $fileDate . ')';

        \App\Models\ReportDownload::create([
            'user_id' => auth()->id() ?? 1,
            'report_name' => $readableName,
            'file_path' => $filePath,
            'status' => 'completed',
            'progress' => 100,
            'filters' => $request->all(),
        ]);

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '.pdf"');
    }

    public function staffListing(Request $request)
    {
        $listType = $request->get('list_type', 'Alphabetic');
        $employeeId = $request->get('employee_id');

        $query = Employee::with(['user', 'department', 'designation'])
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            });

        if ($employeeId && $employeeId !== 'all') {
            $query->where('id', $employeeId);
        }

        $employees = $query->get();

        if ($listType === 'Department Wise') {
            $employees = $employees->sortBy(function ($emp) {
                $dept = $emp->department->name ?? 'ZZZ';
                $code = (int) ($emp->emy_code ?? $emp->employee_id ?? 0);
                return $dept . str_pad((string) $code, 10, '0', STR_PAD_LEFT);
            });
        } else {
            $employees = $employees->sortBy(function ($emp) {
                return $emp->user->name ?? '';
            });
        }

        $data = [];
        foreach ($employees as $emp) {
            $data[] = [
                'no' => $emp->emy_code ?? $emp->employee_id,
                'name' => $emp->user->name ?? '',
                'dly' => ($emp->po_status === 'permanent' || $emp->po_status === 'PERMANENT') ? 'YES' : 'No',
                'doj' => $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->format('d/m/Y') : '',
                'department' => $emp->department->name ?? '',
                'designation' => $emp->designation->name ?? '',
                'amount' => number_format($emp->gross_salary ?? 0, 2),
                'dept_id' => $emp->department_id,
                'dept_name' => $emp->department->name ?? 'NO DEPARTMENT'
            ];
        }

        $branchName = "";
        if (session()->has('active_branch_id')) {
            $activeBranch = Branch::find(session('active_branch_id'));
            if ($activeBranch) {
                $branchName = " - " . strtoupper($activeBranch->name);
            }
        }

        $pdfData = [
            'companyName' => getSetting('titleText', 'KIRAN INDUSTRIES') . $branchName,
            'reportTitle' => $listType === 'Department Wise' ? "STAFF / WORKER DETAIL (DEPARTMENT WISE)" : "STAFF / WORKER DETAIL (ALPHABETICALLY)",
            'reportType' => "STAFF MASTER",
            'listType' => $listType,
            'reportData' => $data,
            'period' => "AS ON " . now()->format('d/m/Y'),
            'printDate' => now()->format('d/m/Y H:i:s')
        ];

        return $this->renderFinalReport(
            'reports.staff_master',
            $pdfData,
            'portrait',
            $request,
            now(),
            now(),
            ['STAFF', 'MASTER', 'LISTING']
        );
    }
}
