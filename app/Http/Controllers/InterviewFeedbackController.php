<?php

namespace App\Http\Controllers;

use App\Models\InterviewFeedback;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class InterviewFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = InterviewFeedback::withPermissionCheck()->with(['interview.candidate', 'interview.job', 'interview.round']);

        if ($request->has('search') && !empty($request->search)) {
            $query->whereHas('interview.candidate', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('recommendation') && !empty($request->recommendation) && $request->recommendation !== 'all') {
            $query->where('recommendation', $request->recommendation);
        }

        if ($request->has('interviewer_id') && !empty($request->interviewer_id) && $request->interviewer_id !== 'all') {
            $query->where('interviewer_id', $request->interviewer_id);
        }

        $query->orderBy('id', 'desc');
        $interviewFeedback = $query->paginate($request->per_page ?? 10);
        
        // Add interviewer names to each feedback
        $interviewFeedback->getCollection()->transform(function ($feedback) {
            $feedback->interviewer_names = $feedback->interviewer_names;
            return $feedback;
        });

        $interviews = Interview::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'Completed')
            ->with(['candidate', 'job'])
            ->get();

        $interviewers = User::whereIn('created_by', getCompanyAndUsersId())
            ->where('type', 'employee')
            ->select('id', 'name')
            ->get();

        return Inertia::render('hr/recruitment/interview-feedback/index', [
            'interviewFeedback' => $interviewFeedback,
            'interviews' => $interviews,
            'interviewers' => $interviewers,
            'filters' => $request->all(['search', 'recommendation', 'interviewer_id', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'interview_id' => 'required|exists:interviews,id',
            'interviewer_id' => 'required',
            'technical_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'cultural_fit_rating' => 'nullable|integer|min:1|max:5',
            'overall_rating' => 'nullable|integer|min:1|max:5',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'comments' => 'nullable|string',
            'recommendation' => 'nullable',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        InterviewFeedback::create([
            'interview_id' => $request->interview_id,
            'interviewer_id' => $request->interviewer_id,
            'technical_rating' => $request->technical_rating,
            'communication_rating' => $request->communication_rating,
            'cultural_fit_rating' => $request->cultural_fit_rating,
            'overall_rating' => $request->overall_rating,
            'strengths' => $request->strengths,
            'weaknesses' => $request->weaknesses,
            'comments' => $request->comments,
            'recommendation' => $request->recommendation,
            'created_by' => creatorId(),
        ]);

        // Update interview feedback_submitted status
        Interview::where('id', $request->interview_id)->update(['feedback_submitted' => true]);

        return redirect()->back()->with('success', __('Interview feedback submitted successfully'));
    }

    public function update(Request $request, InterviewFeedback $interviewFeedback)
    {
        // dd($request->all());
        if (!in_array($interviewFeedback->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this feedback'));
        }

        $validator = Validator::make($request->all(), [
            'interview_id' => 'required|exists:interviews,id',
            'interviewer_id' => 'required',
            'technical_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'cultural_fit_rating' => 'nullable|integer|min:1|max:5',
            'overall_rating' => 'nullable|integer|min:1|max:5',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'comments' => 'nullable|string',
            'recommendation' => 'nullable',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $interviewFeedback->update([
            'interview_id' => $request->interview_id,
            'interviewer_id' => $request->interviewer_id,
            'technical_rating' => $request->technical_rating,
            'communication_rating' => $request->communication_rating,
            'cultural_fit_rating' => $request->cultural_fit_rating,
            'overall_rating' => $request->overall_rating,
            'strengths' => $request->strengths,
            'weaknesses' => $request->weaknesses,
            'comments' => $request->comments,
            'recommendation' => $request->recommendation,
        ]);

        // Update interview feedback_submitted status
        Interview::where('id', $request->interview_id)->update(['feedback_submitted' => true]);

        return redirect()->back()->with('success', __('Interview feedback updated successfully'));
    }

    public function destroy(InterviewFeedback $interviewFeedback)
    {
        if (!in_array($interviewFeedback->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this feedback'));
        }

        $interviewId = $interviewFeedback->interview_id;
        $interviewFeedback->delete();
        
        // Check if there are any remaining feedback entries for this interview
        $remainingFeedback = InterviewFeedback::where('interview_id', $interviewId)->exists();
        Interview::where('id', $interviewId)->update(['feedback_submitted' => $remainingFeedback]);
        
        return redirect()->back()->with('success', __('Interview feedback deleted successfully'));
    }

    public function getInterviewers(Interview $interview)
    {
        if (!in_array($interview->created_by, getCompanyAndUsersId())) {
            return response()->json([]);
        }

        $interviewers = \App\Models\User::whereIn('id', $interview->interviewers)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->select('id', 'name')
            ->get();

        return response()->json($interviewers);
    }
}