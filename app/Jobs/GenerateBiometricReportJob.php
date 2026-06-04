<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\ReportDownload;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use App\Services\ReportChunkGenerator;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateBiometricReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    protected $parameters;
    protected $reportDownloadId;

    public function __construct(array $parameters, int $reportDownloadId)
    {
        $this->parameters = $parameters;
        $this->reportDownloadId = $reportDownloadId;
    }

    public function handle()
    {
        $download = ReportDownload::find($this->reportDownloadId);
        if (!$download)
            return;

        $download->update(['status' => 'processing', 'progress' => 5]);

        try {
            $reportId = $this->parameters['report_id'] ?? '';
            $branchId = isset($this->parameters['branch_id']) ? decryptId($this->parameters['branch_id']) : 'all';
            $deptId = isset($this->parameters['department']) ? decryptId($this->parameters['department']) : 'all';
            $sectionId = isset($this->parameters['section']) ? decryptId($this->parameters['section']) : 'all';
            $catId = isset($this->parameters['category']) ? decryptId($this->parameters['category']) : 'all';
            $poStatus = $this->parameters['po_status'] ?? 'all';
            $employeeId = isset($this->parameters['employee_id']) ? decryptId($this->parameters['employee_id']) : 'all';

            // Query employees based on filters
            $query = Employee::withoutGlobalScopes()
                ->whereHas('user', fn($q) => $q->where('status', 'active'));

            if ($branchId && $branchId !== 'all') {
                // BiometricReportController decrypts branch_id, so we need to be careful if it's encrypted.
                // Usually in the job we pass decrypted params.
                $query->where('branch_id', $branchId);
            }
            if ($poStatus && $poStatus !== 'all') {
                $val = ($poStatus === 'P') ? 'Permanent' : 'Other';
                $query->where('po_status', $val);
            }
            if ($catId && $catId !== 'all') {
                $query->where('category_id', $catId);
            }
            if ($deptId && $deptId !== 'all') {
                $query->where('department_id', $deptId);
            }
            if ($sectionId && $sectionId !== 'all') {
                $query->where('section_id', $sectionId);
            }
            if ($employeeId && $employeeId !== 'all') {
                $query->where('id', $employeeId);
            }

            $employees = $query->orderBy('emy_code')->pluck('id')->toArray();

            if (empty($employees)) {
                $message = 'No records found to generate the PDF for the selected criteria.';
                $fromDate = $this->parameters['from_date'] ?? date('Y-m-d');
                $toDate = $this->parameters['to_date'] ?? date('Y-m-d');
                if (!empty($fromDate) && !empty($toDate)) {
                    $message .= ' Period: ' . $fromDate . ' to ' . $toDate . '.';
                }

                $pdfOutput = Pdf::loadView('reports.no_records', [
                    'message' => $message,
                ])
                    ->setPaper('a4', 'portrait')
                    ->output();

                $finalPath = 'reports/' . $download->id . '_no_records_' . time() . '.pdf';
                Storage::disk('public')->put($finalPath, $pdfOutput);

                $download->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'file_path' => $finalPath,
                ]);

                return;
            }

            // Calculate the number of days in the selected date range
            $fromDate = $this->parameters['from_date'] ?? date('Y-m-d');
            $toDate = $this->parameters['to_date'] ?? date('Y-m-d');
            $days = \Carbon\Carbon::parse($fromDate)->diffInDays(\Carbon\Carbon::parse($toDate)) + 1;

            // Scale chunk size by date range (more days = smaller employee batches per PDF).
            $chunkSize = max(10, (int) floor(3500 / max(1, $days)));

            if (in_array($reportId, ['manual_entries', 'mispunch_dedicated'], true)) {
                $chunkSize = 2000;
            }
            
            $chunks = array_chunk($employees, $chunkSize);
            $totalChunks = count($chunks);
            $tempFiles = [];

            $globalTotalForReport = count($employees);
            if ($reportId === 'biometric_single') {
                $globalTotalForReport = \App\Models\BiometricAttendance::query()
                    ->whereIn('employee_id', $employees)
                    ->whereBetween('attendance_date', [$fromDate, $toDate])
                    ->whereNotNull('in_time')
                    ->distinct()
                    ->count('employee_id');
            }

            // Prepare sub-parameters with decrypted values
            $baseParams = $this->parameters;
            $baseParams['branch_id'] = $branchId;
            $baseParams['department'] = $deptId;
            $baseParams['section'] = $sectionId;
            $baseParams['category'] = $catId;
            $baseParams['employee_id'] = $employeeId;
            // Force PDF generation, not excel
            unset($baseParams['export_excel']);
            $baseParams['background'] = 0; // Prevent infinite loop if any

            $chunkGenerator = app(ReportChunkGenerator::class);

            foreach ($chunks as $index => $chunkIds) {
                $subParams = $baseParams;
                $subParams['employee_ids'] = $chunkIds;
                $subParams['is_last_chunk'] = ($index === $totalChunks - 1);
                $subParams['global_total_employees'] = $globalTotalForReport;

                // Generate in-process (avoids slow PHP subprocess bootstrap on each chunk).
                $tempFilePath = $chunkGenerator->generate($download->id, $index, $subParams);

                if ($tempFilePath === null || ! is_file($tempFilePath)) {
                    continue;
                }

                $tempFiles[] = $tempFilePath;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                $progress = 5 + round((($index + 1) / $totalChunks) * 85);
                $download->refresh();
                $download->update(['progress' => $progress]);
            }

            if (empty($tempFiles)) {
                $message = 'No records found to generate the PDF for the selected criteria.';
                if (!empty($fromDate) && !empty($toDate)) {
                    $message .= ' Period: ' . $fromDate . ' to ' . $toDate . '.';
                }

                $pdfOutput = Pdf::loadView('reports.no_records', [
                    'message' => $message,
                ])
                    ->setPaper('a4', 'portrait')
                    ->output();

                $finalPath = 'reports/' . $download->id . '_no_records_' . time() . '.pdf';
                Storage::disk('public')->put($finalPath, $pdfOutput);

                $download->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'file_path' => $finalPath,
                ]);

                return;
            }

            $download->update(['progress' => 92]); // Merging phase

            // Merge PDFs using FPDI
            $pdf = new Fpdi();

            // First pass: count total pages
            $totalPages = 0;
            foreach ($tempFiles as $file) {
                $totalPages += $pdf->setSourceFile($file);
            }

            // Second pass: import and add page numbers
            $globalPageNo = 1;
            $pdf->SetAutoPageBreak(false); // Prevent blank page for footer
            foreach ($tempFiles as $file) {
                $pageCount = $pdf->setSourceFile($file);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], $size);
                    $pdf->useTemplate($templateId);

                    $w = $size['width'];
                    $h = $size['height'];

                    // PAGE NUMBER
                    $pdf->SetFont('Helvetica', 'B', 8);

                    // DARK BLUE COLOR
                    $pdf->SetTextColor(25, 53, 91);

                    $text = "PAGE $globalPageNo OF $totalPages";

                    $textWidth = $pdf->GetStringWidth($text);

                    // Exact Y converted to MM (Baseline aligned)
                    // Right align with ~12mm margin to match DOMPDF
                    $x = $w - $textWidth - 12;
                    $y = 14.5; // Final 0.5mm adjustment DOWN to perfectly center vertically

                    $pdf->Text($x, $y, $text);

                    if ($globalPageNo < $totalPages) {

                        $continueText = "Continued On Page No... " . ($globalPageNo + 1);

                        $pdf->SetFont('Helvetica', 'B', 7);
                        $pdf->SetTextColor(120, 120, 120);

                        // Center align dynamically
                        $continueWidth = $pdf->GetStringWidth($continueText);
                        $x = ($w / 2) - ($continueWidth / 2);

                        // Footer baseline to exactly match "Developed By"
                        $y = 288.0; // Moved UP slightly from 290.0 to perfectly match the baseline

                        $pdf->Text($x, $y, $continueText);
                    }

                    // RESET COLOR
                    $pdf->SetTextColor(0, 0, 0);

                    $globalPageNo++;
                }
                @unlink($file); // Delete temp file
            }

            // Save final PDF
            $finalPath = 'reports/' . $download->id . '_' . time() . '.pdf';
            Storage::disk('public')->put($finalPath, $pdf->Output('S'));

            $download->update([
                'status' => 'completed',
                'progress' => 100,
                'file_path' => $finalPath
            ]);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (
                str_contains($message, 'No records found')
                || str_contains($message, 'No employees found')
            ) {
                try {
                    $fromDate = $this->parameters['from_date'] ?? date('Y-m-d');
                    $toDate = $this->parameters['to_date'] ?? date('Y-m-d');
                    $noDataMessage = 'No records found to generate the PDF for the selected criteria.';
                    if (! empty($fromDate) && ! empty($toDate)) {
                        $noDataMessage .= ' Period: ' . $fromDate . ' to ' . $toDate . '.';
                    }

                    $pdfOutput = Pdf::loadView('reports.no_records', [
                        'message' => $noDataMessage,
                    ])
                        ->setPaper('a4', 'portrait')
                        ->output();

                    $finalPath = 'reports/' . $download->id . '_no_records_' . time() . '.pdf';
                    Storage::disk('public')->put($finalPath, $pdfOutput);

                    $download->update([
                        'status' => 'completed',
                        'progress' => 100,
                        'file_path' => $finalPath,
                        'error_message' => null,
                    ]);

                    return;
                } catch (\Exception $inner) {
                    \Log::error('Background PDF no-records fallback failed: ' . $inner->getMessage());
                }
            }

            $download->update([
                'status' => 'failed',
                'error_message' => preg_replace('/\s*-\s*\d+\s*$/', '', $message),
            ]);
            \Log::error('Background PDF Error: ' . $message);
        }
    }
}
