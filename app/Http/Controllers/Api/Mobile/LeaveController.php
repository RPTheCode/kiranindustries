<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileLeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(
        private MobileLeaveService $leaveService
    ) {}

    public function balances(Request $request): JsonResponse
    {
        return response()->json([
            'balances' => $this->leaveService->balances($request->user()),
        ]);
    }

    public function applications(Request $request): JsonResponse
    {
        return response()->json([
            'applications' => $this->leaveService->applications($request->user()),
        ]);
    }

    public function types(Request $request): JsonResponse
    {
        return response()->json([
            'leave_types' => $this->leaveService->leaveTypes($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('create-leave-applications')) {
            return response()->json(['message' => __('You do not have permission to apply for leave.')], 403);
        }

        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string'],
        ]);

        $application = $this->leaveService->apply($request->user(), $validated);

        return response()->json([
            'message' => __('Leave application submitted successfully.'),
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'total_days' => (float) $application->total_days,
            ],
        ], 201);
    }
}
