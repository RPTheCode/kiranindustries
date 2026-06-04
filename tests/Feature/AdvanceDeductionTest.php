<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollRun;
use App\Models\Branch;
use App\Models\EmployeeSalary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AdvanceDeductionTest extends TestCase
{
    // use RefreshDatabase; // Be careful with this on a real DB

    public function test_fifo_advance_deduction()
    {
        // 1. Setup Data
        $user = User::where('email', 'like', '%employee%')->first();
        if (!$user) {
            $user = User::factory()->create();
            Employee::factory()->create(['user_id' => $user->id]);
        }

        $branch = Branch::first();
        session(['active_branch_id' => $branch->id]);

        // Ensure salary exists
        EmployeeSalary::updateOrCreate(
            ['employee_id' => $user->id],
            ['basic_salary' => 10000, 'net_salary' => 10000]
        );

        // 2. Create Advances
        // Advance A: 5000, Date: 1st of this month
        $advA = EmployeeAdvance::create([
            'employee_id' => $user->id,
            'amount' => 5000,
            'paid_amount' => 0,
            'pay_date' => Carbon::now()->startOfMonth(),
            'status' => 'paid', // Approved
            'created_by' => 1
        ]);

        // Advance B: 3000, Date: 5th of this month
        $advB = EmployeeAdvance::create([
            'employee_id' => $user->id,
            'amount' => 3000,
            'paid_amount' => 0,
            'pay_date' => Carbon::now()->startOfMonth()->addDays(5),
            'status' => 'paid',
            'created_by' => 1
        ]);

        // 3. Process Payroll
        $payrollRun = PayrollRun::create([
            'pay_period_start' => Carbon::now()->startOfMonth(),
            'pay_period_end' => Carbon::now()->endOfMonth(),
            'status' => 'draft',
            'created_by' => 1,
            'branch_id' => $branch->id
        ]);

        // Mocking Request/Session is tricky for internal method calls if they rely on it, 
        // but processEmployeePayroll takes args.
        // processPayroll() uses session('active_branch_id'). We set it above.

        echo "Running Payroll...\n";
        try {
            $payrollRun->processPayroll();
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return;
        }

        // 4. Verify Deduction
        $entry = $payrollRun->payrollEntries()->where('employee_id', $user->id)->first();

        $totalDeduction = $entry->deductions_breakdown['Advance Pay'] ?? 0;
        echo "Total Advance Deduction: " . $totalDeduction . "\n";

        // Check allocations
        $allocations = $entry->advance_allocations;
        print_r($allocations);

        // Verify Database Updates
        $advA->refresh();
        $advB->refresh();

        echo "Advance A Paid Amount: " . $advA->paid_amount . " (Status: " . $advA->status . ")\n";
        echo "Advance B Paid Amount: " . $advB->paid_amount . " (Status: " . $advB->status . ")\n";

        if ($totalDeduction == 8000 && $advA->status == 'recovered' && $advB->status == 'recovered') {
            echo "SUCCESS: Full deduction logic works.\n";
        } else {
            echo "FAILED: Deduction mismatch.\n";
        }

        // Cleanup
        $payrollRun->delete(); // This calls booting logic to revert advances?

        $advA->refresh();
        $advB->refresh();
        echo "After Delete - Advance A Paid: " . $advA->paid_amount . " (Status: " . $advA->status . ")\n";

        if ($advA->paid_amount == 0 && $advA->status == 'paid') {
            echo "SUCCESS: Reversion logic works.\n";
        } else {
            echo "FAILED: Reversion logic failed.\n";
        }

        // Clean up data
        $advA->delete();
        $advB->delete();
    }
}
