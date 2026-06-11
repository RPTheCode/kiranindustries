<?php

namespace Tests\Feature\Mobile;

use App\Http\Controllers\BiometricAttendanceSyncController;
use App\Models\AttendancePolicy;
use App\Models\BiometricAttendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\AttendanceClockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Kolkata']);
        date_default_timezone_set('Asia/Kolkata');

        Permission::create(['name' => 'access-mobile-app', 'guard_name' => 'web']);
        Permission::create(['name' => 'clock-in-out', 'guard_name' => 'web']);

        $company = User::factory()->create([
            'type' => 'company',
            'password' => Hash::make('password'),
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        Setting::updateOrCreate(
            ['user_id' => $company->id, 'key' => 'defaultTimezone'],
            ['value' => 'Asia/Kolkata']
        );

        $this->employeeUser = User::factory()->create([
            'type' => 'employee',
            'password' => Hash::make('password'),
            'created_by' => $company->id,
            'is_enable_login' => 1,
            'status' => 'active',
        ]);

        $this->employeeUser->givePermissionTo(['access-mobile-app', 'clock-in-out']);

        $shift = Shift::create([
            'name' => 'General',
            'status' => 'active',
            'created_by' => $company->id,
            'start_time' => '10:00',
            'end_time' => '19:00',
        ]);

        $policy = AttendancePolicy::create([
            'name' => 'Default',
            'status' => 'active',
            'created_by' => $company->id,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_id' => 'EMP-002',
            'emy_code' => 'EMP-002',
            'shift_id' => $shift->id,
            'attendance_policy_id' => $policy->id,
            'attendance_mode' => 'both',
            'created_by' => $company->id,
        ]);
    }

    public function test_save_attendance_record_completes_mobile_punch_pair(): void
    {
        $service = app(AttendanceClockService::class);

        $this->travelTo(Carbon::today()->setTime(10, 0));
        $in = $service->clockIn($this->employeeUser->id);
        $this->assertTrue($in['success']);
        $this->assertFalse((bool) BiometricAttendance::first()?->is_manual);

        $this->travel(9)->hours();
        $out = $service->clockOut($this->employeeUser->id);
        $this->assertTrue($out['success'], $out['message'] ?? 'clock out failed');

        $this->assertSame(1, BiometricAttendance::count(), 'expected single attendance row');
        $record = BiometricAttendance::first();
        $this->assertNotNull($record?->out_time, 'out_time should be saved on clock out. log='.($record?->log_details ?? ''));
    }

    public function test_employee_can_clock_in_and_out_via_api_with_location(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $clockIn = $this->postJson('/api/v1/mobile/attendance/clock-in', [
            'latitude' => 23.0225,
            'longitude' => 72.5714,
        ]);
        $clockIn->assertOk()
            ->assertJsonPath('attendance.clock_in', fn ($v) => $v !== null)
            ->assertJsonPath('attendance.source', 'mobile')
            ->assertJsonPath('attendance.can_clock_in', false)
            ->assertJsonPath('attendance.can_clock_out', true)
            ->assertJsonPath('attendance.clock_in_location.latitude', 23.0225)
            ->assertJsonPath('attendance.clock_in_location.longitude', 72.5714);

        $this->assertDatabaseHas('biometric_attendances', [
            'employee_id' => $this->employee->id,
            'primary_source' => 'mobile',
            'is_manual' => false,
            'clock_in_latitude' => 23.0225,
            'clock_in_longitude' => 72.5714,
        ]);

        $this->travel(5)->minutes();

        $clockOut = $this->postJson('/api/v1/mobile/attendance/clock-out', [
            'latitude' => 23.0230,
            'longitude' => 72.5720,
        ]);

        $clockOut->assertOk()
            ->assertJsonPath('attendance.clock_out', fn ($v) => $v !== null)
            ->assertJsonPath('attendance.can_clock_out', false)
            ->assertJsonPath('attendance.clock_out_location.latitude', 23.023)
            ->assertJsonPath('attendance.clock_out_location.longitude', 72.572);
    }

    public function test_dashboard_returns_today_attendance_from_biometric(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $this->postJson('/api/v1/mobile/attendance/clock-in')->assertOk();

        $this->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'today_attendance' => [
                    'attendance_date',
                    'date',
                    'clock_in',
                    'clock_out',
                    'status',
                    'source',
                    'is_manual',
                    'punches',
                    'can_clock_in',
                    'can_clock_out',
                ],
                'attendance_mode',
                'shift',
                'leave_balances',
                'upcoming_holidays',
            ])
            ->assertJsonPath('today_attendance.source', 'mobile')
            ->assertJsonPath('attendance_mode', 'both');
    }

    public function test_dashboard_shows_essl_multi_punch_and_blocks_mobile_clock(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $date = Carbon::today()->format('Y-m-d');
        $inTime = Carbon::parse("{$date} 09:09:00", 'Asia/Kolkata');
        $outTime = Carbon::parse("{$date} 12:01:00", 'Asia/Kolkata');

        BiometricAttendance::create([
            'employee_id' => $this->employee->id,
            'employee_code' => '853',
            'attendance_date' => today(),
            'in_time' => $inTime,
            'out_time' => $outTime,
            'status' => 'MIS',
            'primary_source' => 'essl',
            'is_manual' => false,
            'log_details' => '09:09 IN, 12:01 OUT, 13:05 IN',
            'total_minutes' => 172,
            'duty_value' => 0.5,
        ]);

        $this->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('today_attendance.source', 'essl')
            ->assertJsonPath('today_attendance.clock_in', '09:09:00')
            ->assertJsonPath('today_attendance.can_clock_in', false)
            ->assertJsonPath('today_attendance.can_clock_out', false)
            ->assertJsonPath('today_attendance.punches.0.in', '09:09:00')
            ->assertJsonPath('today_attendance.punches.0.out', '12:01:00')
            ->assertJsonPath('today_attendance.punches.1.in', '13:05:00')
            ->assertJsonPath('today_attendance.punches.1.out', null)
            ->assertJsonPath('today_attendance.message', __('ESSL attendance recorded. Mobile clock disabled.'));

        $this->postJson('/api/v1/mobile/attendance/clock-in')
            ->assertStatus(422);
    }

    public function test_mobile_clock_blocked_when_manual_entry_exists(): void
    {
        Sanctum::actingAs($this->employeeUser);

        BiometricAttendance::create([
            'employee_id' => $this->employee->id,
            'employee_code' => '853',
            'attendance_date' => today(),
            'in_time' => Carbon::today()->setTime(10, 0),
            'out_time' => Carbon::today()->setTime(19, 0),
            'status' => 'P',
            'is_manual' => true,
            'primary_source' => 'manual',
            'log_details' => '10:00 IN, 19:00 OUT',
            'total_minutes' => 540,
            'duty_value' => 1.0,
        ]);

        $this->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('today_attendance.source', 'manual')
            ->assertJsonPath('today_attendance.is_manual', true)
            ->assertJsonPath('today_attendance.can_clock_in', false);

        $this->postJson('/api/v1/mobile/attendance/clock-in')
            ->assertStatus(422);
    }

    public function test_attendance_mode_essl_blocks_mobile_clock(): void
    {
        $this->employee->update(['attendance_mode' => 'essl']);
        Sanctum::actingAs($this->employeeUser);

        $this->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('attendance_mode', 'essl')
            ->assertJsonPath('today_attendance.can_clock_in', false);

        $this->postJson('/api/v1/mobile/attendance/clock-in')
            ->assertStatus(422);
    }

    public function test_attendance_history_includes_source_and_punches(): void
    {
        Sanctum::actingAs($this->employeeUser);

        BiometricAttendance::create([
            'employee_id' => $this->employee->id,
            'employee_code' => '853',
            'attendance_date' => today(),
            'in_time' => Carbon::today()->setTime(10, 0),
            'out_time' => Carbon::today()->setTime(19, 0),
            'status' => 'P',
            'primary_source' => 'essl',
            'is_manual' => false,
            'log_details' => '10:00 IN, 19:00 OUT',
            'total_minutes' => 540,
            'duty_value' => 1.0,
        ]);

        $this->getJson('/api/v1/mobile/attendance/history?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('records.0.source', 'essl')
            ->assertJsonPath('records.0.is_manual', false)
            ->assertJsonPath('records.0.punches.0.in', '10:00');
    }

    public function test_mispunch_list_returns_past_mis_records_read_only(): void
    {
        Sanctum::actingAs($this->employeeUser);

        BiometricAttendance::create([
            'employee_id' => $this->employee->id,
            'employee_code' => '853',
            'attendance_date' => today()->subDay(),
            'in_time' => Carbon::yesterday()->setTime(10, 0),
            'out_time' => null,
            'status' => 'MIS',
            'primary_source' => 'essl',
            'is_manual' => false,
            'log_details' => '10:00 IN',
            'total_minutes' => 0,
            'duty_value' => 0.0,
        ]);

        $this->getJson('/api/v1/mobile/attendance/mispunch')
            ->assertOk()
            ->assertJsonPath('records.0.status', 'MIS')
            ->assertJsonPath('records.0.message', __('Contact HR to clear this mispunch.'));
    }

    public function test_mobile_attendance_preserved_when_sync_has_no_essl_punches(): void
    {
        $service = app(AttendanceClockService::class);
        $this->travelTo(Carbon::today()->setTime(10, 0));
        $service->clockIn($this->employeeUser->id, 23.01, 72.01);

        $record = BiometricAttendance::first();
        $this->assertNotNull($record);
        $this->assertSame('mobile', $record->primary_source);

        $controller = app(BiometricAttendanceSyncController::class);
        $request = Request::create('/mispunch', 'POST', [
            'from_date' => today()->format('Y-m-d'),
            'to_date' => today()->format('Y-m-d'),
        ]);

        $controller->runSync($request);

        $record->refresh();
        $this->assertSame('mobile', $record->primary_source);
        $this->assertNotNull($record->in_time);
        $this->assertSame(23.01, (float) $record->clock_in_latitude);
    }

    public function test_biometric_history_endpoint_is_deprecated_alias(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->getJson('/api/v1/mobile/attendance/biometric?month='.now()->format('Y-m'));
        $response->assertOk();
        $this->assertSame('true', $response->headers->get('Deprecation'));
    }
}
