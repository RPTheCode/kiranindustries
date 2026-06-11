<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\Attendance\AttendanceClockService;
use App\Services\Mobile\MobileDashboardService;
use App\Services\Mobile\MobileMispunchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceClockService $clockService,
        private MobileDashboardService $dashboardService,
        private MobileMispunchService $mispunchService
    ) {}

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $attendance = $this->clockService->todayPayload($user->id);
        $employee = Employee::withoutGlobalScopes()->where('user_id', $user->id)->with('shift.slots')->first();
        $shift = null;

        if ($employee?->shift) {
            $firstSlot = $employee->shift->slots->first();
            $lastSlot = $employee->shift->slots->last();
            $shift = [
                'id' => $employee->shift->id,
                'name' => $employee->shift->name,
                'start_time' => $firstSlot?->start_time,
                'end_time' => $lastSlot?->end_time,
            ];
        }

        return response()->json([
            'attendance' => $attendance,
            'shift' => $shift,
            'attendance_mode' => $employee?->attendance_mode ?? 'both',
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        if (! $request->user()->can('clock-in-out')) {
            return response()->json(['message' => __('You do not have permission to clock in.')], 403);
        }

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->clockService->clockIn(
            $request->user()->id,
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );
        $status = $result['success'] ? 200 : 422;

        return response()->json([
            'message' => $result['message'],
            'attendance' => $result['attendance'] ?? null,
        ], $status);
    }

    public function clockOut(Request $request): JsonResponse
    {
        if (! $request->user()->can('clock-in-out')) {
            return response()->json(['message' => __('You do not have permission to clock out.')], 403);
        }

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->clockService->clockOut(
            $request->user()->id,
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );
        $status = $result['success'] ? 200 : 422;

        return response()->json([
            'message' => $result['message'],
            'attendance' => $result['attendance'] ?? null,
        ], $status);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');

        return response()->json([
            'month' => $month,
            'records' => $this->dashboardService->attendanceHistory($request->user(), $month),
        ]);
    }

    /**
     * @deprecated Use GET /attendance/history — same payload.
     */
    public function biometric(Request $request): JsonResponse
    {
        $response = $this->history($request);
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Link', '</api/v1/mobile/attendance/history>; rel="successor-version"');

        return $response;
    }

    public function mispunch(Request $request): JsonResponse
    {
        return response()->json([
            'records' => $this->mispunchService->listForUser($request->user()),
            'message' => __('Contact HR to clear mispunch records.'),
        ]);
    }

    public function shift(Request $request): JsonResponse
    {
        $employee = Employee::withoutGlobalScopes()->where('user_id', $request->user()->id)->with('shift.slots')->first();

        if (! $employee?->shift_id) {
            return response()->json(['shift' => null]);
        }

        $shift = Shift::with('slots')->find($employee->shift_id);

        return response()->json([
            'shift' => $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'slots' => $shift->slots->map(fn ($slot) => [
                    'slot_name' => $slot->slot_name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                ])->values(),
            ] : null,
        ]);
    }
}
