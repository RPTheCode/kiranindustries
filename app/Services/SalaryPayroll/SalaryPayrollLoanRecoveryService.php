<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryPayroll\SalaryLoanInstallment;
use App\Models\SalaryPayroll\SalaryLoanRequest;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use Carbon\Carbon;

class SalaryPayrollLoanRecoveryService
{
    public const DEDUCTION_LABEL = 'Salary Loan Recovery';

    /**
     * @return array{deduction: float, allocations: array<int, array{salary_loan_installment_id: int, salary_loan_request_id: int, amount: float}>}
     */
    public function compute(int $userId, ?string $periodEndDate): array
    {
        if ($periodEndDate === null) {
            return ['deduction' => 0.0, 'allocations' => []];
        }

        $payrollMonth = Carbon::parse($periodEndDate)->format('Y-m');

        $loans = SalaryLoanRequest::query()
            ->where('employee_id', $userId)
            ->activeForRecovery($periodEndDate)
            ->orderBy('disbursement_date')
            ->orderBy('id')
            ->get();

        $totalDeduction = 0.0;
        $allocations = [];

        foreach ($loans as $loan) {
            $installment = SalaryLoanInstallment::query()
                ->where('salary_loan_request_id', $loan->id)
                ->pending()
                ->where('due_month', '<=', $payrollMonth)
                ->orderBy('installment_no')
                ->first();

            if (! $installment) {
                continue;
            }

            $amount = round((float) $installment->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            $totalDeduction += $amount;
            $allocations[] = [
                'salary_loan_installment_id' => $installment->id,
                'salary_loan_request_id' => $loan->id,
                'amount' => $amount,
            ];
        }

        return [
            'deduction' => round($totalDeduction, 2),
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  array<int, array{salary_loan_installment_id: int, salary_loan_request_id: int, amount: float}>  $allocations
     */
    public function applyAllocations(SalaryPayrollEntry $entry, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $installmentId = (int) ($allocation['salary_loan_installment_id'] ?? 0);
            $requestId = (int) ($allocation['salary_loan_request_id'] ?? 0);
            $amount = (float) ($allocation['amount'] ?? 0);

            if ($installmentId <= 0 || $requestId <= 0 || $amount <= 0) {
                continue;
            }

            $installment = SalaryLoanInstallment::query()
                ->where('id', $installmentId)
                ->where('salary_loan_request_id', $requestId)
                ->where('status', SalaryLoanInstallment::STATUS_PENDING)
                ->first();

            if (! $installment) {
                continue;
            }

            $loan = SalaryLoanRequest::query()
                ->where('id', $requestId)
                ->where('employee_id', $entry->employee_id)
                ->first();

            if (! $loan) {
                continue;
            }

            $installment->update([
                'status' => SalaryLoanInstallment::STATUS_PAID,
                'paid_at' => now(),
                'recovered_in_salary_payroll_entry_id' => $entry->id,
            ]);

            $loan->paid_amount = round((float) $loan->paid_amount + $amount, 2);

            if ($loan->pending_amount <= 0) {
                $loan->status = SalaryLoanRequest::STATUS_CLOSED;
            } else {
                $loan->status = SalaryLoanRequest::STATUS_RECOVERING;
            }

            $loan->save();
        }
    }

    public function revertForEntry(SalaryPayrollEntry $entry): void
    {
        $allocations = $entry->loan_allocations ?? [];

        if (empty($allocations)) {
            return;
        }

        foreach ($allocations as $allocation) {
            $installmentId = (int) ($allocation['salary_loan_installment_id'] ?? 0);
            $requestId = (int) ($allocation['salary_loan_request_id'] ?? 0);
            $amount = (float) ($allocation['amount'] ?? 0);

            if ($installmentId <= 0 || $amount <= 0) {
                continue;
            }

            $installment = SalaryLoanInstallment::find($installmentId);
            if ($installment && (int) $installment->recovered_in_salary_payroll_entry_id === (int) $entry->id) {
                $installment->update([
                    'status' => SalaryLoanInstallment::STATUS_PENDING,
                    'paid_at' => null,
                    'recovered_in_salary_payroll_entry_id' => null,
                ]);
            }

            $loan = SalaryLoanRequest::find($requestId);
            if (! $loan) {
                continue;
            }

            $loan->paid_amount = max(0, round((float) $loan->paid_amount - $amount, 2));
            $loan->status = $loan->paid_amount > 0
                ? SalaryLoanRequest::STATUS_RECOVERING
                : SalaryLoanRequest::STATUS_DISBURSED;
            $loan->save();
        }
    }
}
