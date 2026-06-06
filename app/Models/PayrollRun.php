<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'title',
        'payroll_frequency',
        'pay_period_start',
        'pay_period_end',
        'pay_date',
        'total_gross_pay',
        'total_deductions',
        'total_net_pay',
        'employee_count',
        'status',
        'notes',
        'created_by',
        'branch_id',
        'department_id',
        'salary_calculation_type',
        'scope_filters',
        'scope_filters_hash',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'pay_date' => 'date',
        'total_gross_pay' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'salary_calculation_type' => 'string',
        'department_id' => 'integer',
        'scope_filters' => 'array',
    ];

    public function resolvedScopeFilters(): array
    {
        return \App\Services\PayrollScopeService::resolvedScope($this);
    }

    /**
     * Get the payroll entries.
     */
    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * Get the payslips through payroll entries.
     */
    public function payslips()
    {
        return $this->hasManyThrough(Payslip::class, PayrollEntry::class);
    }

    /**
     * Get the user who created the payroll run.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the department.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Calculate and update totals.
     */
    public function calculateTotals()
    {
        // Refresh the relationship to get newly created entries
        $this->load('payrollEntries');
        $entries = $this->payrollEntries;

        $this->total_gross_pay = $entries->sum('gross_pay');
        $this->total_deductions = $entries->sum('total_deductions');
        $this->total_net_pay = $entries->sum('net_pay');
        $this->employee_count = $entries->count();

        $this->save();
    }

    /**
     * Get all employees that need to be processed for this run.
     */
    public function getEmployeesQuery()
    {
        return \App\Services\PayrollScopeService::employeesQueryForScope(
            $this->resolvedScopeFilters(),
            $this->pay_period_start,
            $this->pay_period_end,
            $this->salary_calculation_type
        );
    }

    public function previewEmployeeScope(): array
    {
        return \App\Services\PayrollScopeService::previewEmployees(
            $this->resolvedScopeFilters(),
            $this->pay_period_start,
            $this->pay_period_end,
            $this->salary_calculation_type,
            $this->branch_id
        );
    }

    /**
     * Check for mispunches in the payroll period.
     */
    public function getMispunches()
    {
        $startDate = $this->pay_period_start;
        $endDate = $this->pay_period_end;

        // Get employee codes for this run's scope
        $employees = $this->getEmployeesQuery()->with(['employee', 'employee.user'])->get();
        $employeeCodes = $employees->pluck('employee.emy_code')->filter()->toArray();

        if (empty($employeeCodes)) {
            return collect();
        }

        return BiometricAttendance::whereIn('employee_code', $employeeCodes)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'MIS')
            ->with(['employee.user'])
            ->get();
    }

    /**
     * Get IDs of employees who are valid for processing (no mispunches).
     */
    public function getValidEmployeesForProcess()
    {
        $preview = $this->previewEmployeeScope();
        $validIds = $preview['valid_employee_ids'];

        if (empty($validIds)) {
            return collect();
        }

        return $this->getEmployeesQuery()
            ->with('employee')
            ->whereIn('id', $validIds)
            ->get();
    }

    /**
     * Process payroll for all employees (Synchronous fallback).
     */
    public function processPayroll()
    {
        set_time_limit(0);
        if ($this->status !== 'draft') {
            return false;
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $employees = $this->getEmployeesQuery()->orderby('id', 'desc')->get();

            foreach ($employees as $employee) {
                $targetBranchId = $this->branch_id ?: ($employee->employee?->branch_id);
                if ($targetBranchId) {
                    $this->processEmployeePayroll($employee, $targetBranchId);
                }
            }

            $this->calculateTotals();
            $this->status = 'in_review';
            $this->save();

            \Illuminate\Support\Facades\DB::commit();

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            $this->status = 'draft';
            $this->save();
            throw $e;
        }
    }

    /**
     * Remove a single payroll entry (payslips + advance revert).
     */
    public function removePayrollEntry(PayrollEntry $entry): void
    {
        $holdEmployeeIds = session('payroll_hold_ids_' . $this->id, []);
        $payslip = $entry->payslip;
        if ($payslip && $payslip->salary_status === 'hold') {
            $holdEmployeeIds[] = $entry->employee_id;
            session(['payroll_hold_ids_' . $this->id => array_values(array_unique($holdEmployeeIds))]);
        }

        $this->revertAdvanceAllocationsForEntry($entry);
        $entry->payslips()->delete();
        $entry->delete();
    }

    /**
     * Delete all entries/payslips for this run (draft delete or cleanup).
     */
    public function purgeAllPayrollEntries(): void
    {
        $entries = $this->payrollEntries()->get();
        foreach ($entries as $entry) {
            $this->removePayrollEntry($entry);
        }
    }

    /**
     * Revert advance allocations stored on a payroll entry.
     */
    protected function revertAdvanceAllocationsForEntry(PayrollEntry $entry): void
    {
        if (! empty($entry->advance_allocations)) {
            foreach ($entry->advance_allocations as $allocation) {
                $advanceId = $allocation['advance_id'] ?? null;
                $amount = $allocation['amount'] ?? 0;

                if ($advanceId && $amount > 0) {
                    $advance = \App\Models\EmployeeAdvance::find($advanceId);
                    if ($advance) {
                        $advance->paid_amount -= $amount;
                        if ($advance->paid_amount < 0) {
                            $advance->paid_amount = 0;
                        }

                        if ($advance->paid_amount < $advance->amount) {
                            $advance->status = 'paid';
                        }
                        $advance->save();
                    }
                }
            }
        }

        if ($entry->employeeAdvances()->exists()) {
            foreach ($entry->employeeAdvances as $advance) {
                $advance->payroll_entry_id = null;
                $advance->status = 'paid';
                $advance->save();
            }
        }
    }

    /**
     * Reprocess specific employees (salary recalc). Used by batch process and per-employee regenerate.
     */
    public function reprocessEmployeeIds(array $employeeIds): bool
    {
        $allowedIds = $this->getEmployeesQuery()
            ->whereIn('id', $employeeIds)
            ->pluck('id')
            ->all();

        if (empty($allowedIds)) {
            return false;
        }

        if ($this->status === 'draft') {
            $this->status = 'processing';
            $this->save();
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $employees = User::withoutGlobalScopes()
                ->whereIn('id', $allowedIds)
                ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()])
                ->get();

            $existingEntries = $this->payrollEntries()->whereIn('employee_id', $allowedIds)->get();
            foreach ($existingEntries as $entry) {
                $this->removePayrollEntry($entry);
            }

            foreach ($employees as $employee) {
                $targetBranchId = $this->branch_id ?: ($employee->employee?->branch_id);
                if ($targetBranchId) {
                    $this->processEmployeePayroll($employee, $targetBranchId);
                }
            }

            \Illuminate\Support\Facades\DB::commit();
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            throw $e;
        }
    }

    public function processBatch($employeeIds)
    {
        if (! in_array($this->status, ['draft', 'processing', 'in_review', 'completed'], true)) {
            return false;
        }

        return $this->reprocessEmployeeIds($employeeIds);
    }

    /**
     * Recalculate one employee, refresh run totals, optionally refresh payslip.
     */
    public function regenerateEmployeePayroll(int $employeeId): bool
    {
        if (! in_array($this->status, ['draft', 'processing', 'in_review', 'completed'], true)) {
            return false;
        }

        if (! $this->getEmployeesQuery()->where('id', $employeeId)->exists()) {
            return false;
        }

        $previousPayslipMeta = null;
        $existingEntry = $this->payrollEntries()->where('employee_id', $employeeId)->first();
        if ($existingEntry?->payslip) {
            $previousPayslipMeta = [
                'salary_status' => $existingEntry->payslip->salary_status,
                'pay_date' => $existingEntry->payslip->pay_date,
                'released_at' => $existingEntry->payslip->released_at,
            ];
        }

        if (! $this->reprocessEmployeeIds([$employeeId])) {
            return false;
        }

        $this->calculateTotals();

        if (in_array($this->status, ['in_review', 'completed'], true)) {
            $entry = $this->payrollEntries()->where('employee_id', $employeeId)->first();
            if ($entry) {
                $this->regeneratePayslipForEntry($entry, $previousPayslipMeta);
            }
        }

        return true;
    }

    /**
     * Create or replace payslip PDF for a payroll entry.
     */
    public function regeneratePayslipForEntry(PayrollEntry $entry, ?array $previousMeta = null): void
    {
        $entry->payslips()->delete();

        $payslipNumber = \App\Models\Payslip::generatePayslipNumber(
            $entry->employee_id,
            $this->pay_date
        );

        $holdIds = session('payroll_hold_ids_' . $this->id, []);
        $onHold = in_array($entry->employee_id, $holdIds, true);

        $payslip = \App\Models\Payslip::create([
            'payroll_entry_id' => $entry->id,
            'employee_id' => $entry->employee_id,
            'payslip_number' => $payslipNumber,
            'pay_period_start' => $this->pay_period_start,
            'pay_period_end' => $this->pay_period_end,
            'pay_date' => $this->pay_date,
            'status' => 'generated',
            'salary_status' => $onHold ? 'hold' : 'pending',
            'created_by' => creatorId(),
        ]);

        if ($this->status === 'completed' && $previousMeta) {
            if (($previousMeta['salary_status'] ?? '') === 'released') {
                $payslip->salary_status = 'released';
                $payslip->pay_date = $previousMeta['pay_date'] ?? $this->pay_date;
                $payslip->released_at = $previousMeta['released_at'] ?? now();
            } elseif (($previousMeta['salary_status'] ?? '') === 'hold') {
                $payslip->salary_status = 'hold';
                $payslip->pay_date = null;
            }
            $payslip->save();
        }

        $payslip->generatePDF();
    }

    /**
     * Revert payroll processing (for regeneration).
     */
    public function revertPayroll()
    {
        if (! in_array($this->status, ['in_review', 'completed'], true)) {
            return false;
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $entries = $this->payrollEntries;
            $holdEmployeeIds = [];

            foreach ($entries as $entry) {
                $payslip = $entry->payslip;
                if ($payslip && $payslip->salary_status === 'hold') {
                    $holdEmployeeIds[] = $entry->employee_id;
                }

                $this->removePayrollEntry($entry);
            }

            // Store hold IDs in session
            if (!empty($holdEmployeeIds)) {
                session(['payroll_hold_ids_' . $this->id => $holdEmployeeIds]);
            }

            // Reset totals
            $this->total_gross_pay = 0;
            $this->total_deductions = 0;
            $this->total_net_pay = 0;
            $this->employee_count = 0;

            $this->status = 'draft';
            $this->save();

            \Illuminate\Support\Facades\DB::commit();
            return true;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process payroll for individual employee.
     */
    private function processEmployeePayroll($employee, $branchId)
    {
        $calculationType = $this->salary_calculation_type;

        // Get employee salary
        $employeeSalary = EmployeeSalary::getActiveSalary($employee->id);

        if (!$employeeSalary) {
            return;
        }

        $emp = $employee->employee;
        if (!$emp) {
            return;
        }
        $emp->load(['shift.slots']);

        $baseSalary = ($this->salary_calculation_type === 'minimum_wages') ? ($employeeSalary->minimum_wages ?? 0) : $employeeSalary->basic_salary;

        // --- 1. Calculate Calendar & Working Days ---
        $startDate = $this->pay_period_start;
        $endDate = $this->pay_period_end;

        // Week off is managed per employee (employees.week_off / week_off_type) — not holiday master
        $leaveData = $this->getEmployeeLeaveData($employee, $emp);
        $paidLeaveDays = $leaveData['paid_leave_days'];
        $unpaidLeaveDays = $leaveData['unpaid_leave_days'];
        $leaveDates = $leaveData['dates'];

        // Initialize Counters
        $presentDays = 0;
        $halfDays = 0;
        $absentDays = 0;
        $holidayDays = 0;
        $weekOffWorkedDays = 0;
        $totalOvertimeHours = 0;
        $totalOvertimeAmount = 0; // Hourly OT
        $totalShortfallHours = 0;
        $totalShortfallAmount = 0;

        // Attendance source: biometric_attendances only (attendance_records not used)
        $biometricRecords = BiometricAttendance::where('employee_code', $emp->emy_code ?? '')
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => $item->attendance_date->format('Y-m-d'));

        // Loop through each day
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $isWeekOff = $this->isEmployeeWeekOff($emp, $date);
            $bio = $biometricRecords[$dateString] ?? null;

            if ($isWeekOff) {
                if ($bio && $this->biometricHasWork($bio)) {
                    $this->applyBiometricDutyCounts($bio, true, $presentDays, $halfDays, $weekOffWorkedDays);
                }
                continue;
            }

            if ($bio && $this->biometricHasWork($bio)) {
                $counted = $this->applyBiometricDutyCounts($bio, false, $presentDays, $halfDays, $weekOffWorkedDays);
                if (!$counted) {
                    $absentDays++;
                }
            } elseif (isset($leaveDates[$dateString])) {
                // Counted in paidLeaveDays / unpaidLeaveDays
            } else {
                $absentDays++;
            }
        }

        // --- Worked Days Formula ---
        // Regular days (capped at working_days) vs week-off work (paid per shift/day, not hourly)
        $regularWorkedDays = $presentDays + ($halfDays * 0.5) + $paidLeaveDays;
        $totalWorkedDays = $regularWorkedDays + $weekOffWorkedDays;

        // --- Salary Calculation Logic (Unified) ---
        $basic = (float) ($employeeSalary->basic_salary ?? 0);
        $hra = (float) ($emp->hra_allowance ?? 0);
        $allowance = (float) ($emp->special_allowance ?? 0);
        $monthlyGross = $basic + $hra + $allowance;
        $workingDays = ($emp && $emp->working_days > 0) ? $emp->working_days : 26;

        $perDaySalary = $workingDays > 0 ? ($monthlyGross / $workingDays) : 0;

        // Week-off work = 1 day pay per shift (not hour-based OT)
        $weekOffWorkAmount = round($perDaySalary * $weekOffWorkedDays, 2);

        // Split regular worked days into normal and extra-day OT (e.g. 26 standard → 30 = 4 OT days)
        $normalPayableDays = min($regularWorkedDays, $workingDays);
        $overtimeDays = max(0, $regularWorkedDays - $workingDays);

        // Calculate Salaries
        $normalSalary = round($perDaySalary * $normalPayableDays, 2);
        $overtimeSalary = round($perDaySalary * $overtimeDays, 2);
        $finalGross = $normalSalary + $overtimeSalary;

        // --- Hourly OT from biometric ot_minutes only (normal working days; week-off is per-day above) ---
        $biometricOtBreakdown = ['hours' => 0, 'amount' => 0, 'holiday_amount' => 0];
        if (employeeHourlyOtEnabled($emp)) {
            $otRatePerHour = $this->resolveOvertimeHourlyRate($emp, $employeeSalary, $perDaySalary);
            if ($otRatePerHour > 0) {
                $biometricOtBreakdown = $this->calculateBiometricOvertime(
                    $biometricRecords,
                    $emp,
                    $otRatePerHour
                );
                $totalOvertimeHours = $biometricOtBreakdown['hours'];
                $totalOvertimeAmount = $biometricOtBreakdown['amount'];
            }
        }

        // --- Earnings Breakdown ---
        // Components follow the normalPayableDays pro-rata factor
        $proRataFactor = $workingDays > 0 ? ($normalPayableDays / $workingDays) : 0;
        if ($emp && $emp->daily_option == 1) {
            if ($workingDays > 1) {
                // Daily rate with a monthly day cap (e.g. 26 days standard, extra days = OT)
                $normalPayableDays = min($regularWorkedDays, $workingDays);
                $overtimeDays = max(0, $regularWorkedDays - $workingDays);
                $normalSalary = round($monthlyGross * $normalPayableDays, 2);
                $overtimeSalary = round($perDaySalary * $overtimeDays, 2);
                $finalGross = $normalSalary + $overtimeSalary + $weekOffWorkAmount;
                $proRataFactor = $normalPayableDays / $workingDays;
            } else {
                // Pure daily: per-day rate × regular worked days + week-off days (hourly OT added separately)
                $proRataFactor = $regularWorkedDays;
                $overtimeDays = 0;
                $overtimeSalary = 0;
                $normalSalary = round($monthlyGross * $regularWorkedDays, 2);
                $finalGross = $normalSalary + $weekOffWorkAmount;
            }
        }

        $earningsBreakdown = [];
        $handledStandard = [];

        // 1. Process Dynamic Components (from JSON)
        $componentsData = is_string($employeeSalary->components) ? json_decode($employeeSalary->components, true) : ($employeeSalary->components ?? []);
        $dynamicComponents = SalaryComponent::withoutGlobalScopes()
            ->whereIn('id', array_keys($componentsData))
            ->where('status', 'active')
            ->get();

        $deductionsBreakdown = [];

        foreach ($dynamicComponents as $component) {
            $compAmount = (float) ($componentsData[$component->id] ?? 0);
            if ($compAmount <= 0) {
                continue;
            }

            if ($component->type === 'earning') {
                $earnedAmount = round($compAmount * $proRataFactor, 2);
                $earningsBreakdown[$component->name] = $earnedAmount;

                // Track standard components to avoid duplication
                $cleanName = strtoupper(str_replace([' ', '_'], '', $component->name));
                if (in_array($cleanName, ['BASIC', 'BASICSALARY']))
                    $handledStandard['basic'] = true;
                if (in_array($cleanName, ['HRA', 'HRAALLOWANCE']))
                    $handledStandard['hra'] = true;
                if (in_array($cleanName, ['ALLOWANCE', 'SPECIALALLOWANCE']))
                    $handledStandard['allowance'] = true;
            } elseif ($component->type === 'deduction' && $totalWorkedDays > 0) {
                // Salary deductions (e.g. Mediclaim) are monthly fixed for daily workers
                $deductionAmount = ($emp && $emp->daily_option == 1)
                    ? $compAmount
                    : round($compAmount * ($workingDays > 0 ? ($normalPayableDays / $workingDays) : 0), 2);

                if ($deductionAmount > 0) {
                    $deductionsBreakdown[$component->name] = $deductionAmount;
                }
            }
        }

        // 2. Fallback to Employee Table Columns if not handled dynamically
        if (!isset($handledStandard['basic'])) {
            $earningsBreakdown['Basic Salary'] = round($basic * $proRataFactor, 2);
        }
        if (!isset($handledStandard['hra']) && $hra > 0) {
            $earningsBreakdown['HRA'] = round($hra * $proRataFactor, 2);
        }
        if (!isset($handledStandard['allowance']) && $allowance > 0) {
            $earningsBreakdown['Allowance'] = round($allowance * $proRataFactor, 2);
        }

        if ($overtimeSalary > 0) {
            $earningsBreakdown['Overtime Salary (Extra Days)'] = $overtimeSalary;
        }
        if ($weekOffWorkAmount > 0) {
            $earningsBreakdown['Week Off Work'] = $weekOffWorkAmount;
        }
        if ($totalOvertimeAmount > 0) {
            $earningsBreakdown['Overtime (Hourly)'] = $totalOvertimeAmount;
        }

        // --- 3. Monthly Manual Adjustments (Incentives/Earnings) ---
        $monthYear = $this->pay_period_start->format('Y-m');
        $incentiveEntry = \App\Models\MonthlyIncentiveEntry::with(['details.deductionType.categoryAmounts'])
            ->where('employee_id', $emp->id)
            ->where('month_year', $monthYear)
            ->first();

        \Log::info("[Payroll] Checking Incentives for {$emp->emy_code}", [
            'user_id' => $emp->user_id,
            'month' => $monthYear,
            'found' => $incentiveEntry ? 'Yes' : 'No'
        ]);

        if ($incentiveEntry) {
            foreach ($incentiveEntry->details as $detail) {
                $detailAmount = $this->resolveIncentiveDetailAmount($detail, $perDaySalary, $emp->category_id ?? null);
                \Log::info("[Payroll] Adding Detail: {$detail->name}", ['type' => $detail->type, 'mode' => $detail->mode, 'val' => $detailAmount]);
                if ($detail->type === 'earning' || $detail->type === 'Incentive') {
                    $earningsBreakdown[$detail->name] = $detailAmount;
                }
            }
        }

        // --- Daily production attendance (piece-rate) for dates in this pay period ---
        if ($emp) {
            $this->applyProductionEarningsToBreakdown((int) $emp->id, $earningsBreakdown);
        }

        // --- Statutory Parameters (financial year of pay period) ---
        $payrollParams = PayrollParameter::forDate($this->pay_period_end ?? $this->pay_period_start);

        // --- Bonus (Earning) ---
        if ($emp && $emp->bonus_flag == 1 && $payrollParams) {
            $bonusPct = (float) ($payrollParams->bonus_pct ?? 0);
            $bonusMax = (float) ($payrollParams->bonus_max_limit ?? 0);
            if ($bonusPct > 0) {
                $bonusBase = ($emp && $emp->daily_option == 1)
                    ? ($basic * $totalWorkedDays)
                    : (($totalWorkedDays < $workingDays && $workingDays > 0) ? ($basic / $workingDays * $totalWorkedDays) : $basic);
                $bonusAmount = round(($bonusBase * $bonusPct) / 100, 2);
                if ($bonusMax > 0) {
                    $bonusAmount = min($bonusAmount, $bonusMax);
                }
                if ($bonusAmount > 0) {
                    $earningsBreakdown['Bonus'] = $bonusAmount;
                }
            }
        }

        // --- PF Logic ---
        $pfAmountEmployee = 0;
        $pfAmountEmployer = 0;
        if ($emp && $emp->pf_flag == 1) {
            $pfPercentage = PayrollParameter::pfEmployeePct($payrollParams);
            $maxPfAmount = (float) ($payrollParams->max_pf_amount ?? 15000);
            $pfBasic = ($emp->pf_basic_salary > 0) ? $emp->pf_basic_salary : $basic;

            if ($emp && $emp->daily_option == 1) {
                $pfBase = $pfBasic * $totalWorkedDays;
            } else {
                $pfBase = ($totalWorkedDays < $workingDays && $workingDays > 0)
                    ? ($pfBasic / $workingDays * $totalWorkedDays)
                    : $pfBasic;
            }

            $pfBase = min($pfBase, $maxPfAmount);
            $pfAmountEmployee = round(($pfBase * $pfPercentage) / 100, 2);
            if ($emp->pfMaster) {
                $pfAmountEmployer = round($pfBase * (PayrollParameter::pfEmployerPct($payrollParams) / 100), 2);
            } elseif ($payrollParams) {
                $pfAmountEmployer = round($pfBase * (PayrollParameter::pfEmployerPct($payrollParams) / 100), 2);
            }
        }

        if ($pfAmountEmployee > 0) {
            $deductionsBreakdown['Provident Fund (PF)'] = $pfAmountEmployee;
        }
        if ($totalShortfallAmount > 0) {
            $deductionsBreakdown['Attendance Shortfall'] = $totalShortfallAmount;
        }

        // --- ESIC Logic ---
        $esiAmountEmployee = 0;
        $esiAmountEmployer = 0;
        if ($emp && $emp->esic_flag == 1 && $payrollParams) {
            $esiPct = $emp->esiMaster && $emp->esiMaster->percentage_employee > 0
                ? (float) $emp->esiMaster->percentage_employee
                : PayrollParameter::esicEmployeePct($payrollParams);
            $esiCeiling = ($emp->esiMaster && $emp->esiMaster->limit > 0)
                ? (float) $emp->esiMaster->limit
                : PayrollParameter::esicWageLimit($payrollParams);
            $currentGross = array_sum($earningsBreakdown);
            $esiBase = min($currentGross, $esiCeiling);
            $esiAmountEmployee = round(($esiBase * $esiPct) / 100, 2);
            if ($emp->esiMaster && $emp->esiMaster->percentage_employer > 0) {
                $esiAmountEmployer = round(($esiBase * $emp->esiMaster->percentage_employer) / 100, 2);
            } else {
                $esiAmountEmployer = round(($esiBase * PayrollParameter::esicEmployerPct($payrollParams)) / 100, 2);
            }
            if ($esiAmountEmployee > 0) {
                $deductionsBreakdown['ESIC'] = $esiAmountEmployee;
            }
        }

        // --- Professional Tax ---
        if ($emp && $emp->ptax_flag == 1) {
            $ptAmount = $this->calculateProfessionalTax(array_sum($earningsBreakdown));
            if ($ptAmount > 0) {
                $deductionsBreakdown['Professional Tax (P.Tax)'] = $ptAmount;
            }
        }

        $advanceAllocations = [];
        $this->applyEmployeeAdvanceDeductions(
            $employee->id,
            $endDate,
            $emp,
            $totalWorkedDays,
            $deductionsBreakdown,
            $advanceAllocations
        );

        // --- 4. Monthly Manual Adjustments (Deductions) ---
        if ($incentiveEntry) {
            foreach ($incentiveEntry->details as $detail) {
                if ($detail->type === 'deduction' || $detail->type === 'Penalty') {
                    $deductionsBreakdown[$detail->name] = $this->resolveIncentiveDetailAmount($detail, $perDaySalary, $emp->category_id ?? null);
                }
            }
        }

        $totalEarnings = array_sum($earningsBreakdown);
        $totalDeductions = array_sum($deductionsBreakdown);
        $netSalary = $totalEarnings - $totalDeductions;

        $payrollEntry = PayrollEntry::create([
            'payroll_run_id' => $this->id,
            'employee_id' => $employee->id,
            'basic_salary' => $basic,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'gross_pay' => $totalEarnings, // Gross includes OT Salary
            'net_pay' => $netSalary,
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'week_off_present_days' => $weekOffWorkedDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'holiday_days' => $holidayDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'overtime_hours' => $totalOvertimeHours,
            'overtime_amount' => $totalOvertimeAmount,
            'overtime_days' => $overtimeDays,
            'overtime_salary' => $overtimeSalary,
            'shortfall_hours' => $totalShortfallHours,
            'shortfall_amount' => $totalShortfallAmount,
            'pf_amount_employee' => $pfAmountEmployee,
            'pf_amount_employer' => $pfAmountEmployer,
            'esi_amount_employee' => $esiAmountEmployee,
            'esi_amount_employer' => $esiAmountEmployer,
            'per_day_salary' => $perDaySalary,
            'earnings_breakdown' => $earningsBreakdown,
            'deductions_breakdown' => $deductionsBreakdown,
            'advance_allocations' => $advanceAllocations,
            'created_by' => $this->created_by,
        ]);

        foreach ($advanceAllocations as $allocation) {
            $advanceId = $allocation['advance_id'] ?? null;
            if ($advanceId) {
                \App\Models\EmployeeAdvance::where('id', $advanceId)->update([
                    'payroll_entry_id' => $payrollEntry->id,
                ]);
            }
        }
    }

    /**
     * Recover pending salary advances (HR → Employee Advance Pay) in payroll deductions.
     */
    private function applyEmployeeAdvanceDeductions(
        int $userId,
        $periodEnd,
        $emp,
        float $totalWorkedDays,
        array &$deductionsBreakdown,
        array &$advanceAllocations
    ): void {
        $periodEndDate = \Carbon\Carbon::parse($periodEnd)->toDateString();

        $advances = \App\Models\EmployeeAdvance::query()
            ->where('employee_id', $userId)
            ->pendingRecovery($periodEndDate)
            ->orderBy('pay_date', 'asc')
            ->get();

        $totalAdvanceDeduction = 0;
        $installmentLimit = ($emp && (float) $emp->loan_installment_amount > 0)
            ? (float) $emp->loan_installment_amount
            : null;
        $remainingLimit = $installmentLimit;

        foreach ($advances as $advance) {
            $pendingAmount = (float) $advance->amount - (float) $advance->paid_amount;
            if ($pendingAmount <= 0) {
                continue;
            }

            $deductionAmount = $pendingAmount;
            if ($remainingLimit !== null) {
                if ($remainingLimit <= 0) {
                    break;
                }
                $deductionAmount = min($pendingAmount, $remainingLimit);
                $remainingLimit -= $deductionAmount;
            }

            if ($deductionAmount <= 0) {
                continue;
            }

            $deductionAmount = round($deductionAmount, 2);
            $totalAdvanceDeduction += $deductionAmount;
            $advanceAllocations[] = [
                'advance_id' => $advance->id,
                'amount' => $deductionAmount,
            ];
            $advance->paid_amount = round((float) $advance->paid_amount + $deductionAmount, 2);
            if ($advance->paid_amount >= (float) $advance->amount) {
                $advance->status = \App\Models\EmployeeAdvance::STATUS_RECOVERED;
            }
            $advance->save();
        }

        if ($totalAdvanceDeduction > 0) {
            $deductionsBreakdown['Employee Advance Recovery'] = round($totalAdvanceDeduction, 2);
        } elseif ($emp && (float) $emp->loan_installment_amount > 0 && $totalWorkedDays > 0) {
            // Monthly loan EMI from employee profile when no pending advance rows exist
            $deductionsBreakdown['Loan Installment'] = round((float) $emp->loan_installment_amount, 2);
        }
    }

    /**
     * Sum daily production attendance entries in the pay period into earnings (piece-rate).
     */
    private function applyProductionEarningsToBreakdown(int $employeeId, array &$earningsBreakdown): void
    {
        $entries = DailyProductionAttendanceEntry::with('materialItem')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [
                $this->pay_period_start->toDateString(),
                $this->pay_period_end->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        $byMaterial = [];
        foreach ($entries as $entry) {
            $materialName = $entry->materialItem?->name ?? __('Production');
            $key = __('Production') . ' - ' . $materialName;
            $byMaterial[$key] = ($byMaterial[$key] ?? 0) + (float) $entry->amount;
        }

        foreach ($byMaterial as $label => $amount) {
            $rounded = round($amount, 2);
            if ($rounded > 0) {
                $earningsBreakdown[$label] = $rounded;
            }
        }
    }

    /**
     * Week off check using employees.week_off / week_off_type only (not holiday or branch week-off master).
     */
    private function isEmployeeWeekOff($emp, \Carbon\Carbon $date): bool
    {
        if (!$emp || empty($emp->week_off)) {
            return false;
        }

        $raw = trim((string) $emp->week_off);
        if ($raw === '' || in_array(strtolower($raw), ['none', 'n/a', 'na', 'null'], true)) {
            return false;
        }

        $dayName = $date->format('l');

        $isMonthly = ($emp->week_off_type ?? '') === 'monthly' || str_starts_with($raw, '{');
        if ($isMonthly) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return false;
            }
            $weekOfMonth = (int) min(5, max(1, (int) ceil($date->day / 7)));
            $weekDays = $decoded[(string) $weekOfMonth] ?? $decoded[$weekOfMonth] ?? [];

            return is_array($weekDays) && in_array($dayName, $weekDays, true);
        }

        $days = array_filter(array_map('trim', explode(',', $raw)));

        return in_array($dayName, $days, true);
    }

    /**
     * Get employee leave data for pay period.
     */
    private function getEmployeeLeaveData($employee, $emp)
    {
        $employeeId = $employee->id;
        $leaveApplications = \App\Models\LeaveApplication::withoutGlobalScopes()->where('employee_id', $employeeId)
            // Removed branch_id filter to allow capturing leaves assigned to home branch while working at another
            ->where('status', 'approved')
            ->where(function ($query) {
                $query->whereBetween('start_date', [$this->pay_period_start, $this->pay_period_end])
                    ->orWhereBetween('end_date', [$this->pay_period_start, $this->pay_period_end])
                    ->orWhere(function ($q) {
                        $q->where('start_date', '<=', $this->pay_period_start)
                            ->where('end_date', '>=', $this->pay_period_end);
                    });
            })
            ->with([
                'leaveType' => function ($q) {
                    $q->withoutGlobalScopes();
                }
            ])
            ->get();

        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;
        $leaveDates = [];

        foreach ($leaveApplications as $leave) {
            // Calculate days within pay period
            $leaveStart = $leave->start_date->copy()->max($this->pay_period_start);
            $leaveEnd = $leave->end_date->copy()->min($this->pay_period_end);

            for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                $dateStr = $date->format('Y-m-d');

                // Skip if this date has already been counted (avoid double counting overlapping leaves)
                if (isset($leaveDates[$dateStr])) {
                    continue;
                }

                // Skip employee week off days — not counted as leave days
                if ($this->isEmployeeWeekOff($emp, $date)) {
                    continue;
                }

                $leaveDates[$dateStr] = $leave->leaveType->is_paid ? 'paid' : 'unpaid';

                if ($leave->leaveType->is_paid) {
                    $paidLeaveDays++;
                } else {
                    $unpaidLeaveDays++;
                }
            }
        }

        return [
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'dates' => $leaveDates,
        ];
    }

    /**
     * Resolve per-hour OT rate from employee profile (ot_rate) or derived from daily salary.
     */
    private function resolveOvertimeHourlyRate($emp, $employeeSalary, float $perDaySalary): float
    {
        if ($emp && (float) $emp->ot_rate > 0) {
            return (float) $emp->ot_rate;
        }

        $shiftHours = employeeShiftHoursForOtRate($emp);

        if ($perDaySalary > 0 && $shiftHours > 0) {
            return round($perDaySalary / $shiftHours, 2);
        }

        $basic = (float) ($employeeSalary->basic_salary ?? 0);
        if ($basic > 0 && $shiftHours > 0) {
            return round($basic / $shiftHours, 2);
        }

        return 0;
    }

    /**
     * Whether a biometric row represents actual work (has punches or positive duty).
     */
    private function biometricHasWork($bio): bool
    {
        return ($bio->punch_count ?? 0) > 0
            || $bio->in_time
            || (float) ($bio->duty_value ?? 0) > 0;
    }

    /**
     * Map biometric duty_value to present / half / week-off-worked counters.
     */
    private function applyBiometricDutyCounts(
        $bio,
        bool $isWeekOff,
        int &$presentDays,
        int &$halfDays,
        int &$weekOffWorkedDays
    ): bool {
        $duty = (float) ($bio->duty_value ?? 0);
        $status = strtoupper((string) ($bio->status ?? ''));

        // Duty value drives pay units; status/manual only when duty is unset.
        if ($isWeekOff) {
            if ($duty >= 2.0) {
                $weekOffWorkedDays += 2;
                return true;
            }
            if ($duty >= 1.0) {
                $weekOffWorkedDays++;
                return true;
            }
            if ($duty >= 0.5 || $status === 'HD') {
                $halfDays++;
                return true;
            }
            if ($bio->is_manual && $status === 'P') {
                $weekOffWorkedDays++;
                return true;
            }

            return false;
        }

        if ($duty >= 2.0) {
            $presentDays += 2;
            return true;
        }
        if ($duty >= 1.0) {
            $presentDays++;
            return true;
        }
        if ($duty >= 0.5 || $status === 'HD') {
            $halfDays++;
            return true;
        }
        if ($bio->is_manual && $status === 'P') {
            $presentDays++;
            return true;
        }

        return false;
    }

    /**
     * Hourly OT from biometric ot_minutes on normal working days only (not week-off).
     */
    private function calculateBiometricOvertime(
        $biometricRecords,
        $emp,
        float $otRatePerHour
    ): array {
        $hourlyMinutes = 0;

        foreach ($biometricRecords as $dateString => $bio) {
            if (!$this->biometricHasWork($bio)) {
                continue;
            }

            $date = \Carbon\Carbon::parse($dateString);
            if ($this->isEmployeeWeekOff($emp, $date)) {
                continue;
            }

            $rawOt = (int) ($bio->ot_minutes ?? 0);
            $hourlyMinutes += applyEmployeeOtMinimum($emp, $rawOt);
        }

        return [
            'minutes' => $hourlyMinutes,
            'hours' => round($hourlyMinutes / 60, 2),
            'amount' => round(($hourlyMinutes / 60) * $otRatePerHour, 2),
            'holiday_amount' => 0,
        ];
    }

    /**
     * Convert monthly incentive detail value based on amount/day mode.
     */
    private function resolveIncentiveDetailAmount($detail, float $perDaySalary, ?int $categoryId = null): float
    {
        $value = (float) ($detail->value ?? 0);

        $deductionType = $detail->deductionType;
        if (! $deductionType && ! empty($detail->deduction_type_id)) {
            $deductionType = \App\Models\DeductionType::with('categoryAmounts')
                ->find($detail->deduction_type_id);
        }
        if ($deductionType) {
            return $deductionType->resolveAmount($value, $categoryId);
        }

        if (($detail->mode ?? 'amount') === 'day') {
            return round($value * $perDaySalary, 2);
        }

        return round($value, 2);
    }

    /**
     * Calculate professional tax from configured salary slabs.
     */
    private function calculateProfessionalTax(float $monthlyGross): float
    {
        if ($monthlyGross <= 0) {
            return 0;
        }

        $financialYear = financialYearLabelForDate($this->pay_period_end ?? $this->pay_period_start);
        $slabs = ProfessionalTaxSlab::where('financial_year', $financialYear)
            ->orderBy('min_amt')
            ->get();

        if ($slabs->isEmpty()) {
            $slabs = ProfessionalTaxSlab::orderBy('min_amt')->get();
        }
        foreach ($slabs as $slab) {
            $min = (float) $slab->min_amt;
            $max = $slab->max_amt !== null ? (float) $slab->max_amt : null;

            if ($monthlyGross >= $min && ($max === null || $monthlyGross <= $max)) {
                return round((float) $slab->pt_amt, 2);
            }
        }

        return 0;
    }
}
