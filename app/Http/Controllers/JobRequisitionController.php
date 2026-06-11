<?php

namespace App\Http\Controllers;

use App\Models\JobRequisition;
use App\Models\JobCategory;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class JobRequisitionController extends Controller
{
    public function index(Request $request)
    {
        $query = JobRequisition::withPermissionCheck()->with(['jobCategory', 'department.branch', 'creator']);

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
                    ->orWhere('requisition_code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && !empty($request->priority) && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        $query->orderBy('id', 'desc');
        $jobRequisitions = $query->paginate($request->per_page ?? 10);

        $jobCategories = JobCategory::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $departmentsQuery = Department::with('branch')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name', 'branch_id');

        if ($activeBranchId) {
            $departmentsQuery->where('branch_id', $activeBranchId);
        }

        $departments = $departmentsQuery->get();

        return Inertia::render('hr/recruitment/job-requisitions/index', [
            'jobRequisitions' => $jobRequisitions,
            'jobCategories' => $jobCategories,
            'departments' => $departments,
            'filters' => $request->all(['search', 'status', 'priority', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'job_category_id' => 'required|exists:job_categories,id',
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
            'positions_count' => 'required|integer|min:1',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
            'skills_required' => 'nullable|string',
            'education_required' => 'nullable|string',
            'experience_required' => 'nullable|string',
            'description' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'priority' => 'required|in:Low,Medium,High',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $requisitionCode = 'REQ-' . creatorId() . '-' . str_pad(
            JobRequisition::whereIn('created_by', getCompanyAndUsersId())->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );

        JobRequisition::create([
            'requisition_code' => $requisitionCode,
            'title' => $request->title,
            'job_category_id' => $request->job_category_id,
            'department_id' => $request->department_id,
            'positions_count' => $request->positions_count,
            'budget_min' => $request->budget_min,
            'budget_max' => $request->budget_max,
            'skills_required' => $request->skills_required,
            'education_required' => $request->education_required,
            'experience_required' => $request->experience_required,
            'description' => $request->description,
            'responsibilities' => $request->responsibilities,
            'priority' => $request->priority,
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Job requisition created successfully'));
    }

    public function update(Request $request, JobRequisition $jobRequisition)
    {
        if (!in_array($jobRequisition->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to update this job requisition');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'job_category_id' => 'required|exists:job_categories,id',
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
            'positions_count' => 'required|integer|min:1',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
            'skills_required' => 'nullable|string',
            'education_required' => 'nullable|string',
            'experience_required' => 'nullable|string',
            'description' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'priority' => 'required|in:Low,Medium,High',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $jobRequisition->update($request->only([
            'title',
            'job_category_id',
            'department_id',
            'positions_count',
            'budget_min',
            'budget_max',
            'skills_required',
            'education_required',
            'experience_required',
            'description',
            'responsibilities',
            'priority'
        ]));

        return redirect()->back()->with('success', __('Job requisition updated successfully'));
    }

    public function destroy(JobRequisition $jobRequisition)
    {
        if (!in_array($jobRequisition->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to delete this job requisition');
        }

        if ($jobRequisition->jobPostings()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete job requisition as it has associated job postings'));
        }

        $jobRequisition->delete();
        return redirect()->back()->with('success', __('Job requisition deleted successfully'));
    }

    public function updateStatus(Request $request, JobRequisition $jobRequisition)
    {
        if (!in_array($jobRequisition->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', 'You do not have permission to update this job requisition');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Draft,Pending Approval,Approved,On Hold,Closed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $newStatus = $request->status;
        $user = Auth::user();

        if ($newStatus === 'Pending Approval') {
            if (!$user->can('edit-job-requisitions')) {
                return redirect()->back()->with('error', __('You do not have permission to submit hiring requests'));
            }
            if ($jobRequisition->status !== 'Draft') {
                return redirect()->back()->with('error', __('Only draft hiring requests can be submitted for approval'));
            }

            $jobRequisition->update(['status' => 'Pending Approval']);

            return redirect()->back()->with('success', __('Hiring request submitted for approval'));
        }

        if ($newStatus === 'Approved') {
            if (!$user->can('approve-job-requisitions')) {
                return redirect()->back()->with('error', __('You do not have permission to approve hiring requests'));
            }
            if (!in_array($jobRequisition->status, ['Draft', 'Pending Approval'], true)) {
                return redirect()->back()->with('error', __('This hiring request cannot be approved'));
            }

            $jobRequisition->update([
                'status' => 'Approved',
                'approved_by' => creatorId(),
                'approval_date' => now(),
            ]);

            return redirect()->back()->with('success', __('Hiring request approved'));
        }

        if (!$user->can('approve-job-requisitions')) {
            return redirect()->back()->with('error', __('You do not have permission to change hiring request status'));
        }

        $jobRequisition->update(['status' => $newStatus]);

        return redirect()->back()->with('success', __('Job requisition status updated successfully'));
    }
}