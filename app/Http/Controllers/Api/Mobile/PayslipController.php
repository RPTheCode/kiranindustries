<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\SalaryPayroll\SalaryPayrollPayslip;
use App\Services\SalaryPayroll\PayslipAuthorizationService;
use App\Services\SalaryPayroll\SalaryPayrollPayslipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayslipController extends Controller
{
    public function __construct(
        private SalaryPayrollPayslipService $payslipService,
        private PayslipAuthorizationService $payslipAuth
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->payslipAuth->canAccessModule($user)) {
            return response()->json(['message' => __('Payslips are not available for your account.')], 403);
        }

        $payslips = SalaryPayrollPayslip::query()
            ->with(['run:id,title,pay_period_start,pay_period_end,pay_date'])
            ->where('employee_id', $user->id)
            ->orderByDesc('generated_at')
            ->limit(24)
            ->get()
            ->map(fn (SalaryPayrollPayslip $payslip) => [
                'id' => $payslip->id,
                'payslip_number' => $payslip->payslip_number,
                'generated_at' => $payslip->generated_at?->toIso8601String(),
                'run' => $payslip->run ? [
                    'id' => $payslip->run->id,
                    'title' => $payslip->run->title,
                    'pay_period_start' => $payslip->run->pay_period_start?->format('Y-m-d'),
                    'pay_period_end' => $payslip->run->pay_period_end?->format('Y-m-d'),
                    'pay_date' => $payslip->run->pay_date?->format('Y-m-d'),
                ] : null,
            ]);

        return response()->json(['payslips' => $payslips]);
    }

    public function download(Request $request, SalaryPayrollPayslip $payslip): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();

        try {
            $entry = $payslip->entry ?? $payslip->entry()->first();
            if (! $entry) {
                return response()->json(['message' => __('Payslip entry not found.')], 404);
            }

            if (! $this->payslipAuth->canDownload($user)) {
                return response()->json(['message' => __('Payslips are not available for your account.')], 403);
            }

            if (! $this->payslipAuth->canViewAll($user) && (int) $entry->employee_id !== (int) $user->id) {
                return response()->json(['message' => __('Forbidden.')], 403);
            }

            $payslip = $this->payslipService->ensurePayslip($entry);
            $path = $this->payslipService->downloadPath($payslip);

            return response()->download($path, $this->payslipService->downloadFilename($payslip));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('Failed to download payslip: :message', ['message' => $e->getMessage()]),
            ], 422);
        }
    }

}
