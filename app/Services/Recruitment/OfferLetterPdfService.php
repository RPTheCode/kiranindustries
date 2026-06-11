<?php

namespace App\Services\Recruitment;

use App\Models\Branch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class OfferLetterPdfService
{
    public function letterContext(?string $documentTitle = null): array
    {
        $branchSuffix = '';
        $branchId = session('active_branch_id');
        if ($branchId && $branchId !== 'all') {
            $branch = Branch::find($branchId);
            if ($branch) {
                $branchSuffix = ' - ' . strtoupper($branch->name);
            }
        }

        $title = $documentTitle ?? 'Appointment Letter';
        $letterDate = now()->format('d-M-Y');

        return [
            'companyName' => getSetting('titleText', 'Kiran Industries Private Limited') . $branchSuffix,
            'reportTitle' => strtoupper($title),
            'reportType' => strtoupper($title),
            'period' => $letterDate,
            'departmentName' => 'HR / RECRUITMENT',
            'sectionName' => 'OFFER LETTER',
            'categoryName' => '-',
            'statusName' => 'CONFIDENTIAL',
            'letterDate' => $letterDate,
            'refNo' => 'HR/OFFER/' . now()->format('Y'),
        ];
    }

    public function renderHtml(string $bodyContent, ?string $documentTitle = null, bool $forPreview = false): string
    {
        return view('recruitment.offer-letter', array_merge(
            $this->letterContext($documentTitle),
            [
                'bodyContent' => $bodyContent,
                'forPreview' => $forPreview,
            ]
        ))->render();
    }

    public function downloadPdf(string $bodyContent, string $filename, ?string $documentTitle = null): Response
    {
        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])->loadHTML($this->renderHtml($bodyContent, $documentTitle));

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download($filename . '.pdf');
    }
}
