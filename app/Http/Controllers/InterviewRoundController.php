<?php

namespace App\Http\Controllers;

use App\Models\InterviewRound;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class InterviewRoundController extends Controller
{
    public function index(Request $request)
    {
        $query = InterviewRound::withPermissionCheck()->with(['job']);

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('job_id') && !empty($request->job_id) && $request->job_id !== 'all') {
            $query->where('job_id', $request->job_id);
        }

        $query->orderBy('job_id')->orderBy('sequence_number');
        $interviewRounds = $query->paginate($request->per_page ?? 10);

        $jobPostings = JobPosting::whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'title', 'job_code')
            ->get();

        return Inertia::render('hr/recruitment/interview-rounds/index', [
            'interviewRounds' => $interviewRounds,
            'jobPostings' => $jobPostings,
            'filters' => $request->all(['search', 'status', 'job_id', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:job_postings,id',
            'name' => 'required|string|max:255',
            'sequence_number' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        InterviewRound::create([
            'job_id' => $request->job_id,
            'name' => $request->name,
            'sequence_number' => $request->sequence_number,
            'description' => $request->description,
            'status' => $request->status ?? 'active',
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Interview round created successfully'));
    }

    public function update(Request $request, InterviewRound $interviewRound)
    {
        if (!in_array($interviewRound->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this interview round'));
        }

        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:job_postings,id',
            'name' => 'required|string|max:255',
            'sequence_number' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $interviewRound->update([
            'job_id' => $request->job_id,
            'name' => $request->name,
            'sequence_number' => $request->sequence_number,
            'description' => $request->description,
            'status' => $request->status ?? 'active',
        ]);

        return redirect()->back()->with('success', __('Interview round updated successfully'));
    }

    public function destroy(InterviewRound $interviewRound)
    {
        if (!in_array($interviewRound->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this interview round'));
        }

        if ($interviewRound->interviews()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete interview round as it has associated interviews'));
        }

        $interviewRound->delete();
        return redirect()->back()->with('success', __('Interview round deleted successfully'));
    }

    public function toggleStatus(InterviewRound $interviewRound)
    {
        if (!in_array($interviewRound->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this interview round'));
        }

        $interviewRound->update([
            'status' => $interviewRound->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->back()->with('success', __('Interview round status updated successfully'));
    }
}