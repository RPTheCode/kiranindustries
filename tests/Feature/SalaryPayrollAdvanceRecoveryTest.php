<?php

namespace Tests\Feature;

use App\Models\SalaryPayroll\SalaryAdvanceRequest;
use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Services\SalaryPayroll\AmountInWordsService;
use App\Services\SalaryPayroll\SalaryPayrollAdvanceRecoveryService;
use Tests\TestCase;

class SalaryPayrollAdvanceRecoveryTest extends TestCase
{
    public function test_pending_amount_calculation(): void
    {
        $request = new SalaryAdvanceRequest([
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'paid_amount' => 2000,
            'status' => SalaryAdvanceRequest::STATUS_RECOVERING,
        ]);

        $this->assertSame(3000.0, $request->pending_amount);
    }

    public function test_fully_recovered_pending_is_zero(): void
    {
        $request = new SalaryAdvanceRequest([
            'requested_amount' => 1000,
            'approved_amount' => 1000,
            'paid_amount' => 1000,
            'status' => SalaryAdvanceRequest::STATUS_RECOVERED,
        ]);

        $this->assertSame(0.0, $request->pending_amount);
        $this->assertFalse($request->isPendingRecovery());
    }

    public function test_disbursed_is_pending_recovery(): void
    {
        $request = new SalaryAdvanceRequest([
            'requested_amount' => 1000,
            'approved_amount' => 1000,
            'paid_amount' => 0,
            'status' => SalaryAdvanceRequest::STATUS_DISBURSED,
        ]);

        $this->assertTrue($request->isPendingRecovery());
    }

    public function test_advance_allocations_shape_on_entry(): void
    {
        $entry = new SalaryPayrollEntry([
            'advance_allocations' => [
                ['salary_advance_request_id' => 1, 'amount' => 250],
            ],
        ]);

        $this->assertSame(250.0, (float) $entry->advance_allocations[0]['amount']);
    }

    public function test_deduction_label_constant(): void
    {
        $this->assertSame('Salary Advance Recovery', SalaryPayrollAdvanceRecoveryService::DEDUCTION_LABEL);
    }

    public function test_amount_in_words_service(): void
    {
        $service = app(AmountInWordsService::class);
        $words = $service->rupees(1500);

        $this->assertStringContainsString('Rupees', $words);
        $this->assertStringContainsString('Only', $words);
    }
}
