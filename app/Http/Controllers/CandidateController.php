<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\CandidateSource;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $query = Candidate::withPermissionCheck()->with(['job', 'source', 'referralEmployee']);

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('job_id') && !empty($request->job_id) && $request->job_id !== 'all') {
            $query->where('job_id', $request->job_id);
        }

        if ($request->has('source_id') && !empty($request->source_id) && $request->source_id !== 'all') {
            $query->where('source_id', $request->source_id);
        }

        $query->orderBy('created_at', 'desc');
        $candidates = $query->paginate($request->per_page ?? 10);

        $jobPostings = JobPosting::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'title', 'job_code')
            ->get();

        $sources = CandidateSource::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $employees = User::with('employee')
            ->where('type', 'employee')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee->employee_id ?? ''
                ];
            });

        return Inertia::render('hr/recruitment/candidates/index', [
            'candidates' => $candidates,
            'jobPostings' => $jobPostings,
            'sources' => $sources,
            'employees' => $employees,
            'filters' => $request->all(['search', 'status', 'job_id', 'source_id', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:job_postings,id',
            'source_id' => 'required|exists:candidate_sources,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|strict_email|max:255',
            'phone' => 'nullable|strict_phone',
            'current_company' => 'nullable|string|max:255',
            'current_position' => 'nullable|string|max:255',
            'experience_years' => 'required|integer|min:0',
            'current_salary' => 'nullable|numeric|min:0',
            'expected_salary' => 'nullable|numeric|min:0',
            'notice_period' => 'nullable|string|max:255',
            'skills' => 'nullable|string',
            'education' => 'nullable|string',
            'portfolio_url' => 'nullable|string',
            'linkedin_url' => 'nullable|string',
            'referral_employee_id' => 'nullable|exists:users,id',
            'application_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        Candidate::create([
            'job_id' => $request->job_id,
            'source_id' => $request->source_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'current_company' => $request->current_company,
            'current_position' => $request->current_position,
            'experience_years' => $request->experience_years,
            'current_salary' => $request->current_salary,
            'expected_salary' => $request->expected_salary,
            'notice_period' => $request->notice_period,
            'skills' => $request->skills,
            'education' => $request->education,
            'portfolio_url' => $request->portfolio_url ?: null,
            'linkedin_url' => $request->linkedin_url ?: null,
            'referral_employee_id' => $request->referral_employee_id ?: null,
            'application_date' => $request->application_date,
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Candidate created successfully'));
    }

    public function update(Request $request, Candidate $candidate)
    {
        if (!in_array($candidate->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to update this candidate');
        }

        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:job_postings,id',
            'source_id' => 'required|exists:candidate_sources,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|strict_email|max:255',
            'phone' => 'nullable|strict_phone',
            'current_company' => 'nullable|string|max:255',
            'current_position' => 'nullable|string|max:255',
            'experience_years' => 'required|integer|min:0',
            'current_salary' => 'nullable|numeric|min:0',
            'expected_salary' => 'nullable|numeric|min:0',
            'notice_period' => 'nullable|string|max:255',
            'skills' => 'nullable|string',
            'education' => 'nullable|string',
            'portfolio_url' => 'nullable|string',
            'linkedin_url' => 'nullable|string',
            'referral_employee_id' => 'nullable|exists:users,id',
            'application_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $candidate->update($request->only([
            'job_id', 'source_id', 'first_name', 'last_name', 'email', 'phone',
            'current_company', 'current_position', 'experience_years', 'current_salary',
            'expected_salary', 'notice_period', 'skills', 'education', 'portfolio_url',
            'linkedin_url', 'referral_employee_id', 'application_date'
        ]));

        return redirect()->back()->with('success', __('Candidate updated successfully'));
    }

    public function destroy(Candidate $candidate)
    {
        if (!in_array($candidate->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to delete this candidate');
        }

        $candidate->delete();
        return redirect()->back()->with('success', __('Candidate deleted successfully'));
    }

    public function updateStatus(Request $request, Candidate $candidate)
    {
        if (!in_array($candidate->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to update this candidate');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:New,Screening,Interview,Offer,Hired,Rejected',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $candidate->update(['status' => $request->status]);
        return redirect()->back()->with('success', __('Candidate status updated successfully'));
    }
}