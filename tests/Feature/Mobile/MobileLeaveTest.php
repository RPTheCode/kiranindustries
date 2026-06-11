<?php

namespace Tests\Feature\Mobile;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'access-mobile-app', 'guard_name' => 'web']);
        Permission::create(['name' => 'create-leave-applications', 'guard_name' => 'web']);

        $company = User::factory()->create([
            'type' => 'company',
            'password' => Hash::make('password'),
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser = User::factory()->create([
            'type' => 'employee',
            'password' => Hash::make('password'),
            'created_by' => $company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser->givePermissionTo(['access-mobile-app', 'create-leave-applications']);

        Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_id' => 'EMP-003',
            'created_by' => $company->id,
        ]);
    }

    public function test_employee_can_list_leave_balances(): void
    {
        $leaveType = LeaveType::create([
            'name' => 'Casual Leave',
            'status' => 'active',
            'created_by' => $this->employeeUser->created_by,
        ]);

        $policy = LeavePolicy::create([
            'name' => 'Default CL Policy',
            'leave_type_id' => $leaveType->id,
            'status' => 'active',
            'min_days_per_application' => 1,
            'max_days_per_application' => 10,
            'accrual_rate' => 12,
            'created_by' => $this->employeeUser->created_by,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->employeeUser->id,
            'leave_type_id' => $leaveType->id,
            'leave_policy_id' => $policy->id,
            'year' => now()->year,
            'allocated_days' => 12,
            'used_days' => 2,
            'remaining_days' => 10,
            'created_by' => $this->employeeUser->created_by,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $this->getJson('/api/v1/mobile/leave/balances')
            ->assertOk()
            ->assertJsonCount(1, 'balances')
            ->assertJsonPath('balances.0.remaining_days', 10);
    }

    public function test_employee_can_apply_leave(): void
    {
        $leaveType = LeaveType::create([
            'name' => 'Casual Leave',
            'status' => 'active',
            'created_by' => $this->employeeUser->created_by,
        ]);

        $policy = LeavePolicy::create([
            'name' => 'Default CL Policy',
            'leave_type_id' => $leaveType->id,
            'status' => 'active',
            'min_days_per_application' => 1,
            'max_days_per_application' => 10,
            'accrual_rate' => 12,
            'created_by' => $this->employeeUser->created_by,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->employeeUser->id,
            'leave_type_id' => $leaveType->id,
            'leave_policy_id' => $policy->id,
            'year' => now()->year,
            'allocated_days' => 12,
            'used_days' => 0,
            'remaining_days' => 12,
            'created_by' => $this->employeeUser->created_by,
        ]);

        Sanctum::actingAs($this->employeeUser);

        $start = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $end = Carbon::parse($start)->addDay()->toDateString();

        $this->postJson('/api/v1/mobile/leave/applications', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Personal work',
        ])->assertCreated();
    }
}
