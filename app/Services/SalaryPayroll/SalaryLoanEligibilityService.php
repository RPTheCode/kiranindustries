<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\SalaryPayroll\SalaryLoanRequest;
use App\Models\User;
use Carbon\Carbon;

class SalaryLoanEligibilityService
{
    public function forEmployee(User $employee, Carbon $asOfDate, ?int $excludeRequestId = null): array
    {
        $employee->loadMissing('employee.department', 'employee.designation', 'employee.branch');

        $salary = EmployeeSalary::where('employee_id', $employee->id)->first();
        $structureGross = (float) ($salary?->monthly_gross ?? $salary?->basic_salary ?? 0);
        $grossInputMode = $salary?->gross_input_mode ?? 'month';
        $perDayRate = (float) ($salary?->per_day_salary ?? 0);
        $workingDays = BranchPayrollSettingsService::DEFAULT_WORKING_DAYS;

        if ($grossInputMode === 'day' && $perDayRate > 0) {
            $monthlyGross = round($perDayRate * $workingDays, 2);
        } else {
            $monthlyGross = round($structureGross, 2);
        }

        $installmentCount = SalaryLoanRequest::DEFAULT_INSTALLMENTS;
        $maxLoanAmount = $monthlyGross;

        $year = $asOfDate->year;
        $activeLoansQuery = SalaryLoanRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                SalaryLoanRequest::STATUS_DISBURSED,
                SalaryLoanRequest::STATUS_RECOVERING,
                SalaryLoanRequest::STATUS_APPROVED,
            ])
            ->whereYear('application_date', $year);

        if ($excludeRequestId) {
            $activeLoansQuery->where('id', '!=', $excludeRequestId);
        }

        $activeLoanCount = (clone $activeLoansQuery)->count();
        $canApply = $activeLoanCount < SalaryLoanRequest::MAX_ACTIVE_LOANS_PER_YEAR;

        return [
            'present_salary' => $monthlyGross,
            'max_loan_amount' => $maxLoanAmount,
            'default_installment_count' => $installmentCount,
            'max_installment_count' => SalaryLoanRequest::MAX_INSTALLMENTS,
            'active_loans_this_year' => $activeLoanCount,
            'max_loans_per_year' => SalaryLoanRequest::MAX_ACTIVE_LOANS_PER_YEAR,
            'can_apply' => $canApply,
            'suggested_emi' => $maxLoanAmount > 0
                ? round($maxLoanAmount / $installmentCount, 2)
                : 0,
        ];
    }

    public function validateLoan(
        User $employee,
        float $requestedAmount,
        int $installmentCount,
        Carbon $asOfDate,
        ?int $excludeRequestId = null
    ): void {
        $eligibility = $this->forEmployee($employee, $asOfDate, $excludeRequestId);

        if (! $eligibility['can_apply']) {
            throw new \InvalidArgumentException(__(
                'Maximum :max active loans per calendar year already reached.',
                ['max' => SalaryLoanRequest::MAX_ACTIVE_LOANS_PER_YEAR]
            ));
        }

        if ($requestedAmount <= 0) {
            throw new \InvalidArgumentException(__('Loan amount must be greater than zero.'));
        }

        if ($requestedAmount > (float) $eligibility['max_loan_amount']) {
            throw new \InvalidArgumentException(__(
                'Requested amount (:requested) exceeds maximum loan (:max) — one month gross salary.',
                [
                    'requested' => number_format($requestedAmount, 2),
                    'max' => number_format((float) $eligibility['max_loan_amount'], 2),
                ]
            ));
        }

        if ($installmentCount < 1 || $installmentCount > SalaryLoanRequest::MAX_INSTALLMENTS) {
            throw new \InvalidArgumentException(__(
                'Installment count must be between 1 and :max.',
                ['max' => SalaryLoanRequest::MAX_INSTALLMENTS]
            ));
        }
    }

    public static function calculateEmi(float $principal, int $installmentCount): array
    {
        $installmentCount = max(1, min($installmentCount, SalaryLoanRequest::MAX_INSTALLMENTS));
        $baseEmi = $installmentCount > 0 ? round($principal / $installmentCount, 2) : 0.0;
        $amounts = array_fill(0, $installmentCount, $baseEmi);
        $distributed = round(array_sum($amounts), 2);
        $remainder = round($principal - $distributed, 2);
        if ($installmentCount > 0 && abs($remainder) >= 0.01) {
            $amounts[$installmentCount - 1] = round($amounts[$installmentCount - 1] + $remainder, 2);
        }

        return [
            'installment_amount' => $baseEmi,
            'amounts' => $amounts,
        ];
    }
}
