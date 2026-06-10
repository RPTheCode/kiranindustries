<?php

namespace App\Services\SalaryPayroll;

use App\Models\EmployeeSalary;
use App\Models\SalaryPayroll\SalaryAdvanceRequest;
use App\Models\User;
use Carbon\Carbon;

class SalaryAdvanceEligibilityService
{
    public function __construct(
        private SalaryPayrollAttendanceService $attendanceService,
        private BranchPayrollSettingsService $branchPayrollSettings
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forEmployee(User $employee, Carbon $asOfDate, ?int $branchId = null, ?string $financialYear = null, ?int $excludeRequestId = null): array
    {
        $employee->loadMissing('employee.department', 'employee.designation', 'employee.branch');

        $salary = EmployeeSalary::where('employee_id', $employee->id)->first();
        $structureGross = (float) ($salary?->monthly_gross ?? $salary?->basic_salary ?? 0);
        $grossInputMode = $salary?->gross_input_mode ?? 'month';
        $perDayRate = (float) ($salary?->per_day_salary ?? 0);

        $fy = $financialYear ?? $this->defaultFinancialYear($asOfDate);
        $branchSettings = $branchId
            ? $this->branchPayrollSettings->resolve($branchId, $fy)
            : null;
        $workingDays = (float) ($branchSettings['working_days'] ?? BranchPayrollSettingsService::DEFAULT_WORKING_DAYS);

        if ($perDayRate <= 0 && $workingDays > 0 && $structureGross > 0) {
            $perDayRate = round($structureGross / $workingDays, 2);
        }

        $monthStart = $asOfDate->copy()->startOfMonth();
        $attendance = $this->attendanceService->summarize(
            $employee,
            $monthStart,
            $asOfDate,
            $branchId,
            $workingDays
        );

        $presentDays = (float) ($attendance['paid_days'] ?? $attendance['total_worked_days'] ?? 0);

        if ($grossInputMode === 'day') {
            $earned = round($perDayRate * $presentDays, 2);
            $presentSalary = round($perDayRate, 2);
        } else {
            $presentSalary = round($structureGross, 2);
            $earned = $workingDays > 0
                ? round(($structureGross / $workingDays) * $presentDays, 2)
                : 0.0;
        }

        $monthYear = $asOfDate->format('Y-m');
        $taken = $this->takenThisMonth($employee->id, $monthYear, $excludeRequestId);
        $allowed = max(0, round($earned - $taken, 2));

        $emp = $employee->employee;

        return [
            'employee_id' => $employee->id,
            'employee_record_id' => $emp?->id,
            'name' => $employee->name,
            'employee_code' => $emp?->employee_id ?? $emp?->emy_code,
            'division' => $emp?->branch?->name ?? null,
            'department' => $emp?->department?->name ?? null,
            'designation' => $emp?->designation?->name ?? null,
            'present_salary' => $presentSalary,
            'gross_input_mode' => $grossInputMode,
            'per_day_rate' => round($perDayRate, 2),
            'working_days' => $workingDays,
            'present_days_till_date' => round($presentDays, 2),
            'earned_salary' => $earned,
            'taken_this_month' => $taken,
            'allowed_amount' => $allowed,
            'month_year' => $monthYear,
        ];
    }

    public function validateAmount(User $employee, float $requestedAmount, Carbon $asOfDate, ?int $branchId = null, ?int $excludeRequestId = null): void
    {
        $eligibility = $this->forEmployee($employee, $asOfDate, $branchId, $excludeRequestId);

        if ($requestedAmount <= 0) {
            throw new \InvalidArgumentException(__('Advance amount must be greater than zero.'));
        }

        if ($requestedAmount > $eligibility['allowed_amount']) {
            throw new \InvalidArgumentException(__(
                'Requested amount (:requested) exceeds allowed advance (:allowed). Earned: :earned, Already taken: :taken',
                [
                    'requested' => number_format($requestedAmount, 2),
                    'allowed' => number_format($eligibility['allowed_amount'], 2),
                    'earned' => number_format($eligibility['earned_salary'], 2),
                    'taken' => number_format($eligibility['taken_this_month'], 2),
                ]
            ));
        }
    }

    public function takenThisMonth(int $userId, string $monthYear, ?int $excludeRequestId = null): float
    {
        $query = SalaryAdvanceRequest::query()
            ->where('employee_id', $userId)
            ->where('month_year', $monthYear)
            ->whereIn('status', [
                SalaryAdvanceRequest::STATUS_DISBURSED,
                SalaryAdvanceRequest::STATUS_RECOVERING,
                SalaryAdvanceRequest::STATUS_RECOVERED,
                SalaryAdvanceRequest::STATUS_APPROVED,
            ]);

        if ($excludeRequestId) {
            $query->where('id', '!=', $excludeRequestId);
        }

        return (float) $query->get()
            ->sum(fn (SalaryAdvanceRequest $row) => (float) ($row->approved_amount ?? $row->requested_amount));
    }

    private function defaultFinancialYear(Carbon $date): string
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        if ($month >= 4) {
            return $year.'-'.($year + 1);
        }

        return ($year - 1).'-'.$year;
    }
}
