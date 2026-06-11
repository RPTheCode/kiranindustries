<?php

namespace Tests\Feature\Mobile;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'access-mobile-app', 'guard_name' => 'web']);

        $company = User::factory()->create([
            'type' => 'company',
            'password' => Hash::make('password'),
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser = User::factory()->create([
            'type' => 'employee',
            'email' => null,
            'password' => Hash::make('password'),
            'created_by' => $company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser->givePermissionTo('access-mobile-app');

        Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_id' => 'EMP-010',
            'emy_code' => 'EMP-010',
            'phone' => '9000000000',
            'created_by' => $company->id,
        ]);
    }

    public function test_employee_can_update_profile_email_and_phone(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $this->patchJson('/api/v1/mobile/profile', [
            'name' => 'Updated Name',
            'email' => 'omprakash@example.com',
            'phone' => '9825800483',
        ])
            ->assertOk()
            ->assertJsonPath('message', __('Profile updated successfully.'))
            ->assertJsonPath('user.email', 'omprakash@example.com')
            ->assertJsonPath('employee.phone', '9825800483');

        $this->assertDatabaseHas('users', [
            'id' => $this->employeeUser->id,
            'email' => 'omprakash@example.com',
        ]);
    }

    public function test_employee_can_upload_profile_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->employeeUser);

        $file = UploadedFile::fake()->image('profile.jpg');

        $this->post('/api/v1/mobile/profile', [
            '_method' => 'PATCH',
            'profile_image' => $file,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('user.avatar_url', fn ($url) => is_string($url) && $url !== '');

        $this->employeeUser->refresh();
        $this->assertNotNull($this->employeeUser->avatar);
        Storage::disk('public')->assertExists($this->employeeUser->avatar);
    }
}
