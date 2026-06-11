<?php

use App\Http\Controllers\Api\Mobile\AttendanceController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\DashboardController;
use App\Http\Controllers\Api\Mobile\HolidayController;
use App\Http\Controllers\Api\Mobile\LeaveController;
use App\Http\Controllers\Api\Mobile\PayslipController;
use App\Http\Controllers\Api\Mobile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile')->group(function () {
    Route::get('docs', function () {
        return view('api.mobile-swagger', [
            'specUrl' => route('api.mobile.docs.openapi'),
        ]);
    })->name('api.mobile.docs');

    Route::get('docs/openapi.yaml', function () {
        $path = base_path('docs/mobile-api.openapi.yaml');
        abort_unless(is_readable($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    })->name('api.mobile.docs.openapi');

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'setting', 'mobile.employee'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/password', [AuthController::class, 'changePassword']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);

        Route::get('attendance/today', [AttendanceController::class, 'today']);
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('attendance/history', [AttendanceController::class, 'history']);
        Route::get('attendance/mispunch', [AttendanceController::class, 'mispunch']);
        Route::get('attendance/biometric', [AttendanceController::class, 'biometric']);
        Route::get('shift', [AttendanceController::class, 'shift']);

        Route::get('holidays', [HolidayController::class, 'index']);

        Route::get('leave/balances', [LeaveController::class, 'balances']);
        Route::get('leave/applications', [LeaveController::class, 'applications']);
        Route::get('leave/types', [LeaveController::class, 'types']);
        Route::post('leave/applications', [LeaveController::class, 'store']);

        Route::get('payslips', [PayslipController::class, 'index']);
        Route::get('payslips/{payslip}/download', [PayslipController::class, 'download']);
    });
});
