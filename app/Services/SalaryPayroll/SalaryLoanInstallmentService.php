<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryPayroll\SalaryLoanInstallment;
use App\Models\SalaryPayroll\SalaryLoanRequest;
use Carbon\Carbon;

class SalaryLoanInstallmentService
{
    public function generateSchedule(SalaryLoanRequest $loan, ?Carbon $disbursementDate = null): void
    {
        $loan->installments()->delete();

        $disbursementDate = $disbursementDate ?? Carbon::parse($loan->disbursement_date ?? now());
        $principal = (float) ($loan->approved_amount ?? $loan->requested_amount);
        $count = (int) ($loan->installment_count ?: SalaryLoanRequest::DEFAULT_INSTALLMENTS);
        $emi = SalaryLoanEligibilityService::calculateEmi($principal, $count);

        $startMonth = $disbursementDate->copy()->startOfMonth();

        for ($i = 0; $i < $count; $i++) {
            SalaryLoanInstallment::create([
                'salary_loan_request_id' => $loan->id,
                'installment_no' => $i + 1,
                'due_month' => $startMonth->copy()->addMonths($i)->format('Y-m'),
                'amount' => $emi['amounts'][$i],
                'status' => SalaryLoanInstallment::STATUS_PENDING,
            ]);
        }

        $loan->update([
            'deduction_start_month' => $startMonth->format('Y-m'),
            'installment_amount' => $emi['installment_amount'],
        ]);
    }
}
