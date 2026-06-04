<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\JobType;
use App\Models\JobLocation;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class JobPostingController extends Controller
{
    public function index(Request $request)
    {
        $query = JobPosting::withPermissionCheck()->with(['requisition', 'jobType', 'location', 'department']);

        // Handle branch scope via department
        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->whereHas('department', function ($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
            });
        }

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('job_code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('is_published') && $request->is_published !== 'all') {
            $query->where('is_published', $request->is_published === 'true');
        }

        $query->orderBy('id', 'desc');
        $jobPostings = $query->paginate($request->per_page ?? 10);

        $requisitionsQuery = JobRequisition::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'Approved')
            ->select('id', 'title', 'requisition_code');

        if ($activeBranchId) {
            $requisitionsQuery->whereHas('department', function ($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId);
            });
        }

        $requisitions = $requisitionsQuery->get();

        $jobTypes = JobType::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $locations = JobLocation::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $departmentsQuery = Department::with('branch:id,name')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name', 'branch_id');

        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }

        $departments = $departmentsQuery->get();

        return Inertia::render('hr/recruitment/job-postings/index', [
            'jobPostings' => $jobPostings,
            'requisitions' => $requisitions,
            'jobTypes' => $jobTypes,
            'locations' => $locations,
            'departments' => $departments,
            'filters' => $request->all(['search', 'status', 'is_published', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'requisition_id' => [
                'required',
                'exists:job_requisitions,id',
                function ($attribute, $value, $fail) {
                    $activeBranchId = session('active_branch_id');
                    if ($activeBranchId && $value) {
                        $req = JobRequisition::with('department')->find($value);
                        // Requisitions must belong to the active branch (via their department)
                        if ($req && $req->department && $req->department->branch_id != $activeBranchId) {
                            $fail('The selected requisition does not belong to the active branch.');
                        }
                    }
                }
            ],
            'title' => 'required|string|max:255',
            'job_type_id' => 'required|exists:job_types,id',
            'location_id' => 'required|exists:job_locations,id',
            'department_id' => [
                'nullable',
                'exists:departments,id',
                function ($attribute, $value, $fail) {
                    $activeBranchId = session('active_branch_id');
                    if ($activeBranchId && $value) {
                        $dept = Department::find($value);
                        if ($dept && $dept->branch_id != $activeBranchId) {
                            $fail('The selected department does not belong to the active branch.');
                        }
                    }
                }
            ],
            'min_experience' => 'required|integer|min:0',
            'max_experience' => 'nullable|integer|min:0',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'benefits' => 'nullable|string',
            'application_deadline' => 'nullable|date|after:today',
            'is_featured' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $jobCode = 'JOB-' . creatorId() . '-' . str_pad(
            JobPosting::whereIn('created_by', getCompanyAndUsersId())->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );

        JobPosting::create([
            'requisition_id' => $request->requisition_id,
            'job_code' => $jobCode,
            'title' => $request->title,
            'job_type_id' => $request->job_type_id,
            'location_id' => $request->location_id,
            'department_id' => $request->department_id,
            'min_experience' => $request->min_experience,
            'max_experience' => $request->max_experience,
            'min_salary' => $request->min_salary,
            'max_salary' => $request->max_salary,
            'description' => $request->description,
            'requirements' => $request->requirements,
            'benefits' => $request->benefits,
            'application_deadline' => $request->application_deadline,
            'is_featured' => $request->boolean('is_featured'),
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Job posting created successfully'));
    }

    public function update(Request $request, JobPosting $jobPosting)
    {
        if (!in_array($jobPosting->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this job posting'));
        }

        $validator = Validator::make($request->all(), [
            'requisition_id' => [
                'required',
                'exists:job_requisitions,id',
                function ($attribute, $value, $fail) {
                    $activeBranchId = session('active_branch_id');
                    if ($activeBranchId && $value) {
                        $req = JobRequisition::with('department')->find($value);
                        if ($req && $req->department && $req->department->branch_id != $activeBranchId) {
                            $fail('The selected requisition does not belong to the active branch.');
                        }
                    }
                }
            ],
            'title' => 'required|string|max:255',
            'job_type_id' => 'required|exists:job_types,id',
            'location_id' => 'required|exists:job_locations,id',
            'department_id' => [
                'nullable',
                'exists:departments,id',
                function ($attribute, $value, $fail) {
                    $activeBranchId = session('active_branch_id');
                    if ($activeBranchId && $value) {
                        $dept = Department::find($value);
                        if ($dept && $dept->branch_id != $activeBranchId) {
                            $fail('The selected department does not belong to the active branch.');
                        }
                    }
                }
            ],
            'min_experience' => 'required|integer|min:0',
            'max_experience' => 'nullable|integer|min:0',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'benefits' => 'nullable|string',
            'application_deadline' => 'nullable|date|after:today',
            'is_featured' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $jobPosting->update($request->only([
            'requisition_id',
            'title',
            'job_type_id',
            'location_id',
            'department_id',
            'min_experience',
            'max_experience',
            'min_salary',
            'max_salary',
            'description',
            'requirements',
            'benefits',
            'application_deadline',
            'is_featured'
        ]));

        return redirect()->back()->with('success', __('Job posting updated successfully'));
    }

    public function destroy(JobPosting $jobPosting)
    {
        if (!in_array($jobPosting->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this job posting'));
        }

        if ($jobPosting->candidates()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete job posting as it has associated candidates'));
        }

        $jobPosting->delete();
        return redirect()->back()->with('success', __('Job posting deleted successfully'));
    }

    public function publish(JobPosting $jobPosting)
    {
        if (!in_array($jobPosting->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to publish this job posting'));
        }

        $jobPosting->update([
            'is_published' => true,
            'publish_date' => now(),
            'status' => 'Published',
        ]);

        return redirect()->back()->with('success', __('Job posting published successfully'));
    }

    public function unpublish(JobPosting $jobPosting)
    {
        if (!in_array($jobPosting->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to unpublish this job posting'));
        }

        $jobPosting->update([
            'is_published' => false,
            'status' => 'Draft',
        ]);

        return redirect()->back()->with('success', __('Job posting unpublished successfully'));
    }
}