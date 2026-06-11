<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobCategory;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\JobType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecruitmentJobsController extends Controller
{
    public function index(Request $request)
    {
        $activeBranchId = session('active_branch_id');
        $companyUserIds = getCompanyAndUsersId();
        $tab = $request->get('tab', 'postings');

        $requisitionsQuery = JobRequisition::withPermissionCheck()
            ->with(['jobCategory', 'department.branch', 'creator', 'approver:id,name'])
            ->orderByDesc('id');

        $postingsQuery = JobPosting::withPermissionCheck()
            ->with(['requisition', 'jobType', 'location', 'department'])
            ->withCount('candidates')
            ->orderByDesc('id');

        if ($activeBranchId) {
            $scope = fn ($q) => $q->where('branch_id', $activeBranchId);
            $requisitionsQuery->whereHas('department', $scope);
            $postingsQuery->whereHas('department', $scope);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $requisitionsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('requisition_code', 'like', "%{$search}%");
            });
            $postingsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('job_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($tab === 'requisitions') {
                $requisitionsQuery->where('status', $request->status);
            } else {
                $postingsQuery->where('status', $request->status);
            }
        }

        if ($tab === 'postings' && $request->filled('published') && $request->published !== 'all') {
            $postingsQuery->where('is_published', $request->published === 'true');
        }

        $jobCategories = JobCategory::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')->select('id', 'name')->get();

        $departmentsQuery = Department::with('branch:id,name')
            ->whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->select('id', 'name', 'branch_id');

        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }

        $jobTypes = JobType::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')->select('id', 'name')->get();

        $branches = Branch::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')->select('id', 'name', 'city')->orderBy('name')->get();

        $approvedRequisitions = JobRequisition::whereIn('created_by', $companyUserIds)
            ->where('status', 'Approved')
            ->select('id', 'title', 'requisition_code');

        if ($activeBranchId) {
            $approvedRequisitions->whereHas('department', fn ($q) => $q->where('branch_id', $activeBranchId));
        }

        return Inertia::render('hr/recruitment/jobs/index', [
            'tab' => $tab,
            'requisitions' => $requisitionsQuery->paginate($request->per_page ?? 12)->withQueryString(),
            'jobPostings' => $postingsQuery->paginate($request->per_page ?? 12)->withQueryString(),
            'jobCategories' => $jobCategories,
            'departments' => $departmentsQuery->get(),
            'jobTypes' => $jobTypes,
            'branches' => $branches,
            'approvedRequisitions' => $approvedRequisitions->get(),
            'filters' => $request->only(['search', 'status', 'published', 'per_page', 'tab']),
        ]);
    }
}
