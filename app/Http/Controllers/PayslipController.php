<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BulkPayslipExport;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $query = Payslip::withPermissionCheck()
            ->with([
                'employee.employee' => function ($q) {
                    $q->withoutGlobalScopes()->with('branch');
                },
                'employee.activeAdvances' => function ($q) {
                    $q->withoutGlobalScopes();
                }, // Fetch active advances for calculating total pending
                'payrollEntry.payrollRun',
                'payrollEntry.employeeAdvances',
                'creator'
            ]);

        // Filter by active branch
        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->whereHas('payrollEntry.payrollRun', function ($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
            });
        }

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payslip_number', 'like', '%' . $search . '%')
                    ->orWhereHas('employee', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', '%' . $search . '%')
                            ->orWhereHas('employee', function ($empQ) use ($search) {
                                $empQ->withoutGlobalScopes()
                                    ->where('emy_code', 'like', '%' . $search . '%')
                                    ->orWhere('employee_id', 'like', '%' . $search . '%');
                            });
                    });
            });
        }

        // Handle employee filter
        if ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') {
            $query->where('employee_id', $request->employee_id);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('pay_period_start', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('pay_period_end', '<=', $request->date_to);
        }

        // Handle salary calculation type filter
        if ($request->has('salary_calculation_type') && !empty($request->salary_calculation_type) && $request->salary_calculation_type !== 'all') {
            $query->whereHas('payrollEntry.payrollRun', function ($q) use ($request) {
                $q->where('salary_calculation_type', $request->salary_calculation_type);
            });
        }

        // Handle salary status filter
        if ($request->has('salary_status') && !empty($request->salary_status) && $request->salary_status !== 'all') {
            if ($request->salary_status === 'hold') {
                $query->where('salary_status', 'hold');
            } else {
                $query->where(function ($q) {
                    $q->where('salary_status', 'released')
                        ->orWhereNull('salary_status');
                });
            }
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $payslips = $query->paginate($request->per_page ?? 10);

        // Get employees for filter dropdown - Show all employees from all branches with their IDs
        $employees = User::join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->whereIn('users.created_by', getCompanyAndUsersId())
            ->select('users.id', 'users.name', 'employees.employee_id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id ?? ''
                ];
            });

        // Ensure selected employee is in the list
        if ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') {
            if (!$employees->contains('id', (int) $request->employee_id)) {
                $selectedEmployee = User::join('employees', 'users.id', '=', 'employees.user_id')
                    ->where('users.id', $request->employee_id)
                    ->select('users.id', 'users.name', 'employees.employee_id')
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'employee_id' => $user->employee_id ?? ''
                        ];
                    });
                $employees = $employees->merge($selectedEmployee);
            }
        }

        return Inertia::render('hr/payslips/index', [
            'payslips' => $payslips,
            'employees' => $employees,
            'filters' => $request->all(['search', 'employee_id', 'status', 'salary_status', 'date_from', 'date_to', 'salary_calculation_type', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'payroll_entry_ids' => 'required|array',
            'payroll_entry_ids.*' => 'exists:payroll_entries,id',
        ]);

        $generatedCount = 0;
        $errors = [];

        foreach ($validated['payroll_entry_ids'] as $entryId) {
            try {

                $payrollEntry = PayrollEntry::whereIn('created_by', getCompanyAndUsersId())
                    ->find($entryId);

                if (!$payrollEntry) {
                    continue;
                }

                // Check if payslip already exists
                $exists = Payslip::where('payroll_entry_id', $entryId)->exists();
                if ($exists) {
                    continue;
                }

                $payslipNumber = Payslip::generatePayslipNumber(
                    $payrollEntry->employee_id,
                    $payrollEntry->payrollRun->pay_date
                );

                $payslip = Payslip::create([
                    'payroll_entry_id' => $entryId,
                    'employee_id' => $payrollEntry->employee_id,
                    'payslip_number' => $payslipNumber,
                    'pay_period_start' => $payrollEntry->payrollRun->pay_period_start,
                    'pay_period_end' => $payrollEntry->payrollRun->pay_period_end,
                    'pay_date' => $payrollEntry->payrollRun->pay_date,
                    'status' => 'generated',
                    'created_by' => creatorId(),
                ]);

                // Generate PDF
                $payslip->generatePDF();
                $generatedCount++;

            } catch (\Exception $e) {
                $errors[] = "Failed to generate payslip for entry ID {$entryId}: " . $e->getMessage();
            }
        }

        if ($generatedCount > 0) {
            $message = "Generated {$generatedCount} payslip(s) successfully.";
            if (!empty($errors)) {
                $message .= " Some errors occurred: " . implode(', ', $errors);
            }
            return redirect()->back()->with('success', __($message));
        } else {
            return redirect()->back()->with('error', __('No payslips were generated. :errors', ['errors' => implode(', ', $errors)]));
        }
    }

    public function download($payslipId)
    {
        $payslip = Payslip::where('id', $payslipId)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->first();

        if (!$payslip) {
            return redirect()->back()->with('error', __('Payslip not found.'));
        }

        if (!$payslip->file_path || !Storage::disk('public')->exists($payslip->file_path)) {
            // Generate PDF if not exists
            try {
                $payslip->generatePDF();
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to generate payslip PDF: :message', ['message' => $e->getMessage()]));
            }
        }

        $payslip->markAsDownloaded();

        return Storage::disk('public')->download($payslip->file_path, 'payslip-' . $payslip->payslip_number . '.pdf');
    }

    public static function bulkGenerateForPayrollRun($payrollRun)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $payrollEntries = $payrollRun->payrollEntries()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->get();

        $holdEmployeeIds = session('payroll_hold_ids_' . $payrollRun->id, []);
        $generatedCount = 0;

        foreach ($payrollEntries as $entry) {
            $existing = Payslip::where('payroll_entry_id', $entry->id)->first();
            if ($existing) {
                if ((int) $existing->employee_id !== (int) $entry->employee_id) {
                    $existing->employee_id = $entry->employee_id;
                    $existing->save();
                    try {
                        $existing->generatePDF();
                    } catch (\Exception $e) {
                        // logged below if needed
                    }
                }
                continue;
            }

            $payslipNumber = Payslip::generatePayslipNumber(
                $entry->employee_id,
                $payrollRun->pay_date
            );

            $isOnHold = in_array($entry->employee_id, $holdEmployeeIds);

            $payslip = Payslip::create([
                'payroll_entry_id' => $entry->id,
                'employee_id' => $entry->employee_id,
                'payslip_number' => $payslipNumber,
                'pay_period_start' => $payrollRun->pay_period_start,
                'pay_period_end' => $payrollRun->pay_period_end,
                'pay_date' => $isOnHold ? null : $payrollRun->pay_date,
                'status' => 'generated',
                'salary_status' => $isOnHold ? 'hold' : 'released',
                'created_by' => creatorId(),
            ]);

            // Generate PDF only if NOT on hold
            if (!$isOnHold) {
                $payslip->generatePDF();
            }
            $generatedCount++;
        }

        // Clear session after use
        session()->forget('payroll_hold_ids_' . $payrollRun->id);

        return $generatedCount;
    }

    public function bulkGenerate(Request $request)
    {
        $validated = $request->validate([
            'payroll_run_id' => 'required|exists:payroll_runs,id',
        ]);

        try {
            $payrollRun = \App\Models\PayrollRun::findOrFail($validated['payroll_run_id']);

            $generatedCount = self::bulkGenerateForPayrollRun($payrollRun);

            return redirect()->back()->with('success', __('Generated :count payslips successfully.', ['count' => $generatedCount]));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate payslips: :message', ['message' => $e->getMessage()]));
        }
    }

    public function regenerate(Payslip $payslip)
    {
        try {
            $payslip->generatePDF();
            return redirect()->back()->with('success', __('Payslip regenerated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to regenerate payslip: :message', ['message' => $e->getMessage()]));
        }
    }

    public function update(Request $request, Payslip $payslip)
    {
        $validated = $request->validate([
            'earnings' => 'array',
            'deductions' => 'array',
        ]);

        try {
            DB::beginTransaction();

            $payrollEntry = $payslip->payrollEntry;

            if (!$payrollEntry) {
                throw new \Exception('Payroll entry not found');
            }

            // Validation: Only allow editing if Payroll Run is In-Review
            if ($payrollEntry->payrollRun && $payrollEntry->payrollRun->status !== 'in_review') {
                throw new \Exception(__('Payslip cannot be edited because the payroll run is :status.', ['status' => $payrollEntry->payrollRun->status]));
            }

            // Update Earnings
            $earningsBreakdown = $payrollEntry->earnings_breakdown ?? [];
            if ($request->has('earnings')) {
                foreach ($request->earnings as $key => $amount) {
                    $earningsBreakdown[$key] = floatval($amount);
                }
            }

            // Update Deductions
            $deductionsBreakdown = $payrollEntry->deductions_breakdown ?? [];
            if ($request->has('deductions')) {
                foreach ($request->deductions as $key => $amount) {
                    $deductionsBreakdown[$key] = floatval($amount);
                }
            }

            // Update Attendance Shortfall (Dedicated Field)
            $shortfallAmount = $payrollEntry->shortfall_amount;
            if ($request->has('shortfall_amount')) {
                $shortfallAmount = floatval($request->shortfall_amount);
                if ($shortfallAmount > 0) {
                    $deductionsBreakdown['Attendance Shortfall'] = $shortfallAmount;
                } else {
                    unset($deductionsBreakdown['Attendance Shortfall']);
                }
            }

            // Update Employee Advances (Partial Deduction Logic)
            if ($request->has('advance_deduction_amount')) {
                $newAdvanceDeduction = floatval($request->advance_deduction_amount);

                // 1. Revert previous allocations
                if (!empty($payrollEntry->advance_allocations)) {
                    foreach ($payrollEntry->advance_allocations as $allocation) {
                        $advanceId = $allocation['advance_id'] ?? null;
                        $amount = $allocation['amount'] ?? 0;
                        if ($advanceId && $amount > 0) {
                            $advance = \App\Models\EmployeeAdvance::find($advanceId);
                            if ($advance) {
                                $advance->paid_amount -= $amount;
                                if ($advance->paid_amount < 0)
                                    $advance->paid_amount = 0;
                                if ($advance->paid_amount < $advance->amount) {
                                    $advance->status = 'paid';
                                }
                                $advance->save();
                            }
                        }
                    }
                }

                // 2. Validate New Amount (Cannot exceed total pending)
                // Fetch all eligible advances again to check total pending
                $advances = \App\Models\EmployeeAdvance::where('employee_id', $payrollEntry->employee_id)
                    ->whereIn('status', ['paid', 'recovered']) // Include recovered ones as we just reverted them? 
                    // Wait, if we reverted them above, they should be 'paid' now if not fully paid.
                    // But if they were fully paid naturally before this payroll, they remain recovered.
                    // We only want to target advances that *can* be paid in this payroll.
                    // Actually, the simplest way is: we reverted the *current payroll's contribution*.
                    // So now we check all 'paid' advances (which includes the ones we just opened up).
                    ->where('pay_date', '<=', $payrollEntry->payrollRun->pay_period_end)
                    ->whereColumn('amount', '>', 'paid_amount')
                    ->orderBy('pay_date', 'asc')
                    ->get();

                $totalPending = $advances->sum(function ($adv) {
                    return $adv->amount - $adv->paid_amount;
                });

                if ($newAdvanceDeduction > $totalPending) {
                    // This should ideally be a validation error, but we are inside transaction.
                    // We cap it to total pending to be safe.
                    $newAdvanceDeduction = $totalPending;
                }

                // 3. FIFO Allocation of New Amount
                $newAllocations = [];
                $remainingDeduction = $newAdvanceDeduction;

                foreach ($advances as $advance) {
                    if ($remainingDeduction <= 0)
                        break;

                    $pending = $advance->amount - $advance->paid_amount;
                    $toDeduct = min($pending, $remainingDeduction);

                    $advance->paid_amount += $toDeduct;
                    if ($advance->paid_amount >= $advance->amount) {
                        $advance->status = 'recovered';
                    }
                    $advance->save();

                    $newAllocations[] = [
                        'advance_id' => $advance->id,
                        'amount' => $toDeduct
                    ];

                    $remainingDeduction -= $toDeduct;
                }

                $payrollEntry->advance_allocations = $newAllocations;

                // Update deductions breakdown
                if ($newAdvanceDeduction > 0) {
                    $deductionsBreakdown['Advance Pay'] = $newAdvanceDeduction;
                } else {
                    unset($deductionsBreakdown['Advance Pay']);
                }
            } elseif ($request->has('advances')) {
                // Fallback for old request format (not expected with new UI, but safe to keep or remove)
                // Keeping empty to prioritize new logic.
            }
            // Recalculate total deductions
            $totalDeductions = array_sum($deductionsBreakdown);

            // Update Overtime Amount
            $overtimeAmount = $payrollEntry->overtime_amount;
            if ($request->has('overtime_amount')) {
                $overtimeAmount = floatval($request->overtime_amount);
            }

            // Sync breakdown with specific OT field for consistency
            $earningsBreakdown['Overtime Amount'] = $overtimeAmount;
            $earningsBreakdown['Overtime Amount'] = $overtimeAmount;

            // Recalculate Totals from breakdown
            $totalEarnings = array_sum($earningsBreakdown);
            $grossPay = $totalEarnings;
            $totalDeductions = array_sum($deductionsBreakdown);
            $netPay = $grossPay - $totalDeductions;

            // Update Payroll Entry
            $payrollEntry->update([
                'earnings_breakdown' => $earningsBreakdown,
                'deductions_breakdown' => $deductionsBreakdown,
                'overtime_amount' => $overtimeAmount,
                'shortfall_amount' => $shortfallAmount,
                'total_earnings' => $totalEarnings,
                'total_deductions' => $totalDeductions,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay,
            ]);

            // Regenerate PDF
            $payslip->generatePDF();
            $payslip->touch(); // Force updated_at to refresh even if file_path didn't change

            // Recalculate Payroll Run Totals
            if ($payrollEntry->payrollRun) {
                $payrollEntry->payrollRun->calculateTotals();
            }

            DB::commit();

            return redirect()->route('hr.payslips.index')
                ->with('success', 'Payslip updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Failed to update payslip: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Toggle hold/release status of a payslip.
     */
    public function toggleHold($id)
    {
        $payslip = Payslip::whereIn('created_by', getCompanyAndUsersId())
            ->findOrFail($id);

        if ($payslip->salary_status === 'released') {
            // Mark as Hold
            $payslip->salary_status = 'hold';
            $payslip->released_at = null;
            $payslip->pay_date = null; // Clear pay_date when on hold
            $payslip->save();
        } else {
            // Release: set pay_date to today
            $payslip->salary_status = 'released';
            $payslip->released_at = \Carbon\Carbon::now();
            $payslip->pay_date = \Carbon\Carbon::now();
            $payslip->save();
            $payslip->generatePDF();
        }

        return redirect()->back()->with('success', __('Salary status updated successfully.'));
    }

    public function exportBulkExcel(Request $request)
    {
        $filters = $request->all();
        $fileName = 'bulk_payslips_' . now()->format('Y_m_d_h_i_s_A') . '.xlsx';

        return Excel::download(new BulkPayslipExport($filters), $fileName);
    }
}
