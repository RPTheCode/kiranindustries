<?php

namespace App\Http\Controllers;

use App\Models\CandidateOnboarding;
use App\Models\Candidate;
use App\Models\OnboardingChecklist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class CandidateOnboardingController extends Controller
{
    public function index(Request $request)
    {
        $query = CandidateOnboarding::withPermissionCheck()->with(['candidate', 'checklist', 'buddyEmployee']);

        if ($request->has('search') && !empty($request->search)) {
            $query->whereHas('candidate', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('candidate_id') && !empty($request->candidate_id) && $request->candidate_id !== 'all') {
            $query->where('candidate_id', $request->candidate_id);
        }

        $query->orderBy('id', 'desc');
        $candidateOnboarding = $query->paginate($request->per_page ?? 10);

        $candidates = Candidate::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'Hired')
            ->select('id', 'first_name', 'last_name')
            ->get();

        $checklists = OnboardingChecklist::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $employees = User::whereIn('created_by', getCompanyAndUsersId())
            ->where('type', 'employee')
            ->select('id', 'name')
            ->get();

        return Inertia::render('hr/recruitment/candidate-onboarding/index', [
            'candidateOnboarding' => $candidateOnboarding,
            'candidates' => $candidates,
            'checklists' => $checklists,
            'employees' => $employees,
            'filters' => $request->all(['search', 'status', 'candidate_id', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'checklist_id' => 'required|exists:onboarding_checklists,id',
            'start_date' => 'required|date',
            'buddy_employee_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        CandidateOnboarding::create([
            'candidate_id' => $request->candidate_id,
            'checklist_id' => $request->checklist_id,
            'start_date' => $request->start_date,
            'buddy_employee_id' => $request->buddy_employee_id,
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Candidate onboarding created successfully'));
    }

    public function update(Request $request, CandidateOnboarding $candidateOnboarding)
    {
        if (!in_array($candidateOnboarding->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this onboarding'));
        }

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'checklist_id' => 'required|exists:onboarding_checklists,id',
            'start_date' => 'required|date',
            'buddy_employee_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $candidateOnboarding->update([
            'candidate_id' => $request->candidate_id,
            'checklist_id' => $request->checklist_id,
            'start_date' => $request->start_date,
            'buddy_employee_id' => $request->buddy_employee_id,
        ]);

        return redirect()->back()->with('success', __('Candidate onboarding updated successfully'));
    }

    public function destroy(CandidateOnboarding $candidateOnboarding)
    {
        if (!in_array($candidateOnboarding->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this onboarding'));
        }

        $candidateOnboarding->delete();
        return redirect()->back()->with('success', __('Candidate onboarding deleted successfully'));
    }

    public function updateStatus(Request $request, CandidateOnboarding $candidateOnboarding)
    {
        if (!in_array($candidateOnboarding->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this onboarding'));
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending,In Progress,Completed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $candidateOnboarding->update(['status' => $request->status]);
        return redirect()->back()->with('success', __('Onboarding status updated successfully'));
    }
}