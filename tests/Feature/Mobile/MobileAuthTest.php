<?php

namespace Tests\Feature\Mobile;

use App\Models\Employee;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $company;

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'access-mobile-app', 'guard_name' => 'web', 'module' => 'Mobile App']);
        Permission::create(['name' => 'view-leave-applications', 'guard_name' => 'web', 'module' => 'Leave Management']);
        Permission::create(['name' => 'clock-in-out', 'guard_name' => 'web', 'module' => 'Attendance & Bio-Sync']);

        $this->company = User::factory()->create([
            'type' => 'company',
            'password' => Hash::make('password'),
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser = User::factory()->create([
            'type' => 'employee',
            'password' => Hash::make('password'),
            'created_by' => $this->company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser->givePermissionTo('access-mobile-app');

        Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_id' => 'EMP-001',
            'created_by' => $this->company->id,
        ]);
    }

    public function test_employee_can_login_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/mobile/auth/login', [
            'email' => $this->employeeUser->email,
            'password' => 'password',
            'device_id' => 'phpunit-device-001',
            'fcm_token' => 'test-fcm-token',
            'latitude' => 23.0225,
            'longitude' => 72.5714,
        ]);

        $response->assertOk()
            ->assertExactJsonStructure(['message', 'token'])
            ->assertJsonMissing(['user', 'employee', 'menu']);

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $this->employeeUser->id,
            'device_id' => 'phpunit-device-001',
            'fcm_token' => 'test-fcm-token',
            'latitude' => 23.0225,
            'longitude' => 72.5714,
        ]);

        $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$response->json('token'),
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'employee' => ['code'],
                'menu',
            ]);
    }

    public function test_user_without_mobile_permission_cannot_login(): void
    {
        $admin = User::factory()->create([
            'type' => 'company',
            'password' => Hash::make('password'),
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/mobile/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_id' => 'phpunit-device-001',
        ])->assertStatus(403);
    }

    public function test_staff_user_with_mobile_permission_can_login_without_employee_type(): void
    {
        $staffUser = User::factory()->create([
            'type' => 'staff',
            'password' => Hash::make('password'),
            'created_by' => $this->company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $staffUser->givePermissionTo(['access-mobile-app', 'view-leave-applications']);

        $token = $this->loginAndGetToken([
            'email' => $staffUser->email,
            'password' => 'password',
            'device_id' => 'phpunit-device-staff',
        ]);

        $me = $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $me->assertOk()
            ->assertJsonPath('user.type', 'staff')
            ->assertJsonPath('employee', null);

        $titles = collect($me->json('menu'))->pluck('title')->all();
        $this->assertContains('Dashboard', $titles);
        $this->assertContains('Leave Management', $titles);
        $this->assertContains('Profile', $titles);
    }

    public function test_user_can_login_when_employee_matched_by_emp_code(): void
    {
        $linkedUser = User::factory()->create([
            'type' => 'employee',
            'password' => Hash::make('password'),
            'created_by' => $this->company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $loginUser = User::factory()->create([
            'type' => 'admin',
            'password' => Hash::make('password'),
            'created_by' => $this->company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $loginUser->givePermissionTo(['access-mobile-app', 'clock-in-out', 'view-leave-applications']);

        Employee::create([
            'user_id' => $linkedUser->id,
            'employee_id' => (string) $loginUser->id,
            'created_by' => $this->company->id,
        ]);

        $token = $this->loginAndGetToken([
            'email' => $loginUser->email,
            'password' => 'password',
            'device_id' => 'phpunit-device-empcode',
        ]);

        $me = $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $me->assertOk()
            ->assertJsonPath('employee.code', (string) $loginUser->id);

        $titles = collect($me->json('menu'))->pluck('title')->all();
        $this->assertContains('Dashboard', $titles);
        $this->assertContains('Attendance & Bio-Sync', $titles);
        $this->assertContains('Leave Management', $titles);
    }

    public function test_admin_with_mobile_permission_without_employee_profile_gets_limited_menu(): void
    {
        $admin = User::factory()->create([
            'type' => 'admin',
            'password' => Hash::make('password'),
            'created_by' => $this->company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        Permission::create(['name' => 'manage-attendance-records', 'guard_name' => 'web', 'module' => 'Attendance & Bio-Sync']);
        Permission::create(['name' => 'manage-leave-applications', 'guard_name' => 'web', 'module' => 'Leave Management']);
        Permission::create(['name' => 'view-employee-salaries', 'guard_name' => 'web', 'module' => 'Payroll']);

        $admin->givePermissionTo([
            'access-mobile-app',
            'manage-attendance-records',
            'manage-leave-applications',
            'view-employee-salaries',
            'clock-in-out',
        ]);

        $token = $this->loginAndGetToken([
            'email' => $admin->email,
            'password' => 'password',
            'device_id' => 'phpunit-device-admin',
        ]);

        $me = $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $me->assertOk()
            ->assertJsonPath('employee', null);

        $titles = collect($me->json('menu'))->pluck('title')->all();
        $this->assertContains('Dashboard', $titles);
        $this->assertContains('Attendance & Bio-Sync', $titles);
        $this->assertContains('Leave Management', $titles);
        $this->assertContains('Salary Payroll', $titles);
    }

    public function test_user_can_login_with_mobile_number(): void
    {
        Employee::where('user_id', $this->employeeUser->id)->update([
            'phone' => '9876543210',
            'employee_id' => '1001',
        ]);

        $token = $this->loginAndGetToken([
            'login' => '9876543210',
            'password' => 'password',
            'device_id' => 'phpunit-device-mobile',
        ]);

        $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $this->employeeUser->id);
    }

    public function test_user_can_login_with_employee_code(): void
    {
        Employee::where('user_id', $this->employeeUser->id)->update([
            'employee_id' => '853',
            'emy_code' => '853',
        ]);

        $token = $this->loginAndGetToken([
            'login' => '853',
            'password' => 'password',
            'device_id' => 'phpunit-device-code',
        ]);

        $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $this->employeeUser->id)
            ->assertJsonPath('employee.code', '853');
    }

    public function test_me_endpoint_requires_token(): void
    {
        $this->getJson('/api/v1/mobile/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_employee_can_fetch_me(): void
    {
        $token = $this->employeeUser->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/mobile/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $this->employeeUser->id);
    }

    public function test_employee_can_change_password(): void
    {
        $token = $this->employeeUser->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/mobile/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function loginAndGetToken(array $payload): string
    {
        $response = $this->postJson('/api/v1/mobile/auth/login', $payload);
        $response->assertOk()->assertExactJsonStructure(['message', 'token']);

        return (string) $response->json('token');
    }
}
