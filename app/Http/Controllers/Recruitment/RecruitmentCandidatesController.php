<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\Recruitment\RecruitmentDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class RecruitmentCandidatesController extends Controller
{
    public function __construct(
        private RecruitmentDashboardService $dashboard
    ) {}

    public function index(Request $request)
    {
        $companyUserIds = getCompanyAndUsersId();
        $view = $request->get('view', 'pipeline');

        $query = Candidate::withPermissionCheck()
            ->with(['job:id,title,job_code', 'source:id,name'])
            ->withCount('interviews');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('job_id') && $request->job_id !== 'all') {
            $query->where('job_id', $request->job_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $jobPostings = JobPosting::whereIn('created_by', $companyUserIds)
            ->select('id', 'title', 'job_code')->orderBy('title')->get();

        $sources = CandidateSource::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')->select('id', 'name')->get();

        $employees = User::with('employee')
            ->where('type', 'employee')
            ->whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee->employee_id ?? '',
            ]);

        if ($view === 'list') {
            $candidates = $query->orderByDesc('updated_at')->paginate($request->per_page ?? 20)->withQueryString();

            return Inertia::render('hr/recruitment/candidates/index', [
                'view' => 'list',
                'candidates' => $candidates,
                'pipeline' => null,
                'jobPostings' => $jobPostings,
                'sources' => $sources,
                'employees' => $employees,
                'filters' => $request->only(['search', 'status', 'job_id', 'view', 'per_page']),
            ]);
        }

        return Inertia::render('hr/recruitment/candidates/index', [
            'view' => 'pipeline',
            'pipeline' => $this->dashboard->pipelineGrouped(),
            'candidates' => null,
            'jobPostings' => $jobPostings,
            'sources' => $sources,
            'employees' => $employees,
            'filters' => $request->only(['search', 'status', 'job_id', 'view']),
        ]);
    }

    public function show(Candidate $candidate)
    {
        if (! in_array($candidate->created_by, getCompanyAndUsersId())) {
            abort(403);
        }

        $candidate->load([
            'job.department',
            'source',
            'referralEmployee',
            'employee',
            'interviews.round',
            'interviews.interviewType',
            'interviews.feedback',
            'assessments',
            'offers.department',
        ]);

        return Inertia::render('hr/recruitment/candidates/show', [
            'candidate' => $candidate,
            'resumeUrl' => $candidate->resume_path
                ? Storage::disk('public')->url($candidate->resume_path)
                : null,
            'coverLetterUrl' => $candidate->cover_letter_path
                ? Storage::disk('public')->url($candidate->cover_letter_path)
                : null,
        ]);
    }

    public function uploadResume(Request $request, Candidate $candidate)
    {
        if (! in_array($candidate->created_by, getCompanyAndUsersId())) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'cover_letter' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $dir = 'recruitment/resumes/' . creatorId();

        if ($request->hasFile('resume')) {
            if ($candidate->resume_path) {
                Storage::disk('public')->delete($candidate->resume_path);
            }
            $candidate->resume_path = $request->file('resume')->store($dir, 'public');
        }

        if ($request->hasFile('cover_letter')) {
            if ($candidate->cover_letter_path) {
                Storage::disk('public')->delete($candidate->cover_letter_path);
            }
            $candidate->cover_letter_path = $request->file('cover_letter')->store($dir, 'public');
        }

        $candidate->save();

        return redirect()->back()->with('success', __('Documents uploaded successfully'));
    }

    public function convertToEmployee(Candidate $candidate)
    {
        if (! in_array($candidate->created_by, getCompanyAndUsersId())) {
            abort(403);
        }

        if ($candidate->employee_id) {
            return redirect()->route('hr.employees.index')
                ->with('info', __('Candidate is already linked to an employee record'));
        }

        return redirect()->route('hr.employees.create', [
            'from_candidate' => $candidate->id,
        ]);
    }
}
