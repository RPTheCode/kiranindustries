<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\RecruitmentDashboardService;
use Inertia\Inertia;

class RecruitmentHubController extends Controller
{
    public function __construct(
        private RecruitmentDashboardService $dashboard
    ) {}

    public function index()
    {
        return Inertia::render('hr/recruitment/hub', $this->dashboard->aggregate());
    }
}
