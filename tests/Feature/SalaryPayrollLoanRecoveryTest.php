<?php

namespace Tests\Feature;

use App\Models\SalaryPayroll\SalaryLoanRequest;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Services\SalaryPayroll\SalaryLoanEligibilityService;
use App\Services\SalaryPayroll\SalaryPayrollLoanRecoveryService;
use Tests\TestCase;

class SalaryPayrollLoanRecoveryTest extends TestCase
{
    public function test_pending_amount_calculation(): void
    {
        $loan = new SalaryLoanRequest([
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'paid_amount' => 1666.67,
            'status' => SalaryLoanRequest::STATUS_RECOVERING,
        ]);

        $this->assertSame(3333.33, $loan->pending_amount);
    }

    public function test_fully_recovered_pending_is_zero(): void
    {
        $loan = new SalaryLoanRequest([
            'requested_amount' => 3000,
            'approved_amount' => 3000,
            'paid_amount' => 3000,
            'status' => SalaryLoanRequest::STATUS_CLOSED,
        ]);

        $this->assertSame(0.0, $loan->pending_amount);
    }

    public function test_calculate_emi_splits_remainder_on_last_installment(): void
    {
        $emi = SalaryLoanEligibilityService::calculateEmi(5000, 3);

        $this->assertSame(1666.67, $emi['installment_amount']);
        $this->assertSame([1666.67, 1666.67, 1666.66], $emi['amounts']);
        $this->assertSame(5000.0, round(array_sum($emi['amounts']), 2));
    }

    public function test_calculate_emi_respects_max_installments(): void
    {
        $emi = SalaryLoanEligibilityService::calculateEmi(6000, 10);

        $this->assertCount(SalaryLoanRequest::MAX_INSTALLMENTS, $emi['amounts']);
        $this->assertSame(6000.0, round(array_sum($emi['amounts']), 2));
    }

    public function test_loan_allocations_shape_on_entry(): void
    {
        $entry = new SalaryPayrollEntry([
            'loan_allocations' => [
                [
                    'salary_loan_installment_id' => 12,
                    'salary_loan_request_id' => 4,
                    'amount' => 1666.67,
                ],
            ],
        ]);

        $this->assertSame(1666.67, (float) $entry->loan_allocations[0]['amount']);
        $this->assertSame(12, (int) $entry->loan_allocations[0]['salary_loan_installment_id']);
    }

    public function test_deduction_label_constant(): void
    {
        $this->assertSame('Salary Loan Recovery', SalaryPayrollLoanRecoveryService::DEDUCTION_LABEL);
    }

    public function test_compute_returns_zero_without_period_end(): void
    {
        $service = app(SalaryPayrollLoanRecoveryService::class);
        $result = $service->compute(1, null);

        $this->assertSame(0.0, $result['deduction']);
        $this->assertSame([], $result['allocations']);
    }

    public function test_max_installments_and_loans_per_year_constants(): void
    {
        $this->assertSame(6, SalaryLoanRequest::MAX_INSTALLMENTS);
        $this->assertSame(3, SalaryLoanRequest::DEFAULT_INSTALLMENTS);
        $this->assertSame(2, SalaryLoanRequest::MAX_ACTIVE_LOANS_PER_YEAR);
    }
}
