<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private MobileDashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->dashboardService->build($request->user()));
    }
}
