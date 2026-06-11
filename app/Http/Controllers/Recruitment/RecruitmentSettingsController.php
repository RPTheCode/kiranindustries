<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InterviewType;
use App\Models\JobCategory;
use App\Models\JobType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecruitmentSettingsController extends Controller
{
    public function index(Request $request)
    {
        $companyUserIds = getCompanyAndUsersId();
        $tab = $request->get('tab', 'categories');

        if ($tab === 'sources') {
            return redirect()->route('hr.recruitment.settings.index', ['tab' => 'categories']);
        }

        if ($tab === 'locations') {
            $tab = 'branches';
        }

        return Inertia::render('hr/recruitment/settings/index', [
            'tab' => $tab,
            'jobCategories' => JobCategory::whereIn('created_by', $companyUserIds)->orderBy('name')->get(['id', 'name', 'description', 'status']),
            'jobTypes' => JobType::whereIn('created_by', $companyUserIds)->orderBy('name')->get(['id', 'name', 'status']),
            'branches' => Branch::whereIn('created_by', $companyUserIds)->orderBy('name')->get(['id', 'name', 'city', 'address', 'status']),
            'interviewTypes' => InterviewType::whereIn('created_by', $companyUserIds)->orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }
}
