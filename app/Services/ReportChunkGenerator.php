<?php

namespace App\Services;

use App\Http\Controllers\BiometricReportController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;

class ReportChunkGenerator
{
    /**
     * Generate one PDF chunk. Returns temp file path, or null if chunk is empty.
     */
    public function generate(int $downloadId, int $index, array $subParams): ?string
    {
        ini_set('memory_limit', '2048M');

        $request = new Request();
        $subParams['is_chunk'] = true;
        $request->replace($subParams);

        $reportId = $subParams['report_id'] ?? '';

        if (in_array($reportId, ['biometric_dedicated_code', 'biometric_dedicated_dept', 'biometric_all_punches', 'biometric_dedicated', 'mispunch_dedicated'], true)) {
            $response = app(BiometricReportController::class)->generate($request);
        } else {
            $response = app(ReportController::class)->generate($request);
        }

        if (! $response) {
            throw new \RuntimeException("Report generator returned no response for: {$reportId}");
        }

        if ($response->headers->get('content-type') === 'application/json') {
            throw new \RuntimeException('Report chunk returned JSON error response.');
        }

        if ($response->headers->get('X-Empty-Chunk') === 'true') {
            return null;
        }

        $tempFilePath = storage_path('app/temp_pdf_' . $downloadId . '_' . $index . '.pdf');
        file_put_contents($tempFilePath, $response->getContent());

        return $tempFilePath;
    }
}
