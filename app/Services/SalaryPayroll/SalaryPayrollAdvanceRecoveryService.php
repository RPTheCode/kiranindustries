<?php

namespace App\Services\SalaryPayroll;

use App\Models\Employee;
use App\Models\SalaryPayroll\SalaryAdvanceRequest;
use App\Models\SalaryPayroll\SalaryPayrollEntry;

class SalaryPayrollAdvanceRecoveryService
{
    public const DEDUCTION_LABEL = 'Salary Advance Recovery';

    /**
     * Compute advance recovery without mutating database (for calculator / preview).
     *
     * @return array{deduction: float, allocations: array<int, array{salary_advance_request_id: int, amount: float}>}
     */
    public function compute(int $userId, ?string $periodEndDate, ?Employee $employeeProfile = null): array
    {
        if ($periodEndDate === null) {
            return ['deduction' => 0.0, 'allocations' => []];
        }

        $advances = SalaryAdvanceRequest::query()
            ->where('employee_id', $userId)
            ->pendingRecovery($periodEndDate)
            ->orderBy('disbursement_date')
            ->orderBy('id')
            ->get();

        $installmentLimit = ($employeeProfile && (float) $employeeProfile->loan_installment_amount > 0)
            ? (float) $employeeProfile->loan_installment_amount
            : null;
        $remainingLimit = $installmentLimit;

        $totalDeduction = 0.0;
        $allocations = [];

        foreach ($advances as $advance) {
            $pending = $advance->pending_amount;
            if ($pending <= 0) {
                continue;
            }

            $deductionAmount = $pending;
            if ($remainingLimit !== null) {
                if ($remainingLimit <= 0) {
                    break;
                }
                $deductionAmount = min($pending, $remainingLimit);
                $remainingLimit -= $deductionAmount;
            }

            if ($deductionAmount <= 0) {
                continue;
            }

            $deductionAmount = round($deductionAmount, 2);
            $totalDeduction += $deductionAmount;
            $allocations[] = [
                'salary_advance_request_id' => $advance->id,
                'amount' => $deductionAmount,
            ];
        }

        return [
            'deduction' => round($totalDeduction, 2),
            'allocations' => $allocations,
        ];
    }

    /**
     * Persist recovery after payroll entry is saved.
     *
     * @param  array<int, array{salary_advance_request_id: int, amount: float}>  $allocations
     */
    public function applyAllocations(SalaryPayrollEntry $entry, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $requestId = (int) ($allocation['salary_advance_request_id'] ?? 0);
            $amount = (float) ($allocation['amount'] ?? 0);

            if ($requestId <= 0 || $amount <= 0) {
                continue;
            }

            $advance = SalaryAdvanceRequest::query()
                ->where('id', $requestId)
                ->where('employee_id', $entry->employee_id)
                ->first();

            if (! $advance) {
                continue;
            }

            $advance->paid_amount = round((float) $advance->paid_amount + $amount, 2);
            $advance->recovered_in_salary_payroll_entry_id = $entry->id;

            if ($advance->pending_amount <= 0) {
                $advance->status = SalaryAdvanceRequest::STATUS_RECOVERED;
            } else {
                $advance->status = SalaryAdvanceRequest::STATUS_RECOVERING;
            }

            $advance->save();
        }
    }

    /**
     * Reverse advance recovery when payroll entry is deleted or recalculated.
     */
    public function revertForEntry(SalaryPayrollEntry $entry): void
    {
        $allocations = $entry->advance_allocations ?? [];

        if (! empty($allocations)) {
            foreach ($allocations as $allocation) {
                $requestId = (int) ($allocation['salary_advance_request_id'] ?? 0);
                $amount = (float) ($allocation['amount'] ?? 0);

                if ($requestId <= 0 || $amount <= 0) {
                    continue;
                }

                $advance = SalaryAdvanceRequest::find($requestId);
                if (! $advance) {
                    continue;
                }

                $advance->paid_amount = max(0, round((float) $advance->paid_amount - $amount, 2));

                if ($advance->paid_amount <= 0) {
                    $advance->status = SalaryAdvanceRequest::STATUS_DISBURSED;
                } elseif ($advance->pending_amount > 0) {
                    $advance->status = SalaryAdvanceRequest::STATUS_RECOVERING;
                }

                if ((int) $advance->recovered_in_salary_payroll_entry_id === (int) $entry->id) {
                    $advance->recovered_in_salary_payroll_entry_id = null;
                }

                $advance->save();
            }

            return;
        }

        SalaryAdvanceRequest::query()
            ->where('recovered_in_salary_payroll_entry_id', $entry->id)
            ->each(function (SalaryAdvanceRequest $advance) use ($entry) {
                $linkedAlloc = collect($entry->advance_allocations ?? [])
                    ->firstWhere('salary_advance_request_id', $advance->id);
                $amount = (float) ($linkedAlloc['amount'] ?? 0);

                if ($amount > 0) {
                    $advance->paid_amount = max(0, round((float) $advance->paid_amount - $amount, 2));
                }

                $advance->status = $advance->paid_amount > 0
                    ? SalaryAdvanceRequest::STATUS_RECOVERING
                    : SalaryAdvanceRequest::STATUS_DISBURSED;
                $advance->recovered_in_salary_payroll_entry_id = null;
                $advance->save();
            });
    }
}
