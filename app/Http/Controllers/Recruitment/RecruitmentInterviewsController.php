<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewRound;
use App\Models\InterviewType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecruitmentInterviewsController extends Controller
{
    public function index(Request $request)
    {
        $companyUserIds = getCompanyAndUsersId();
        $view = $request->get('view', 'list');
        $userId = auth()->id();

        $query = Interview::withPermissionCheck()
            ->with(['candidate', 'job', 'round', 'interviewType']);

        if ($request->boolean('mine')) {
            $query->whereJsonContains('interviewers', $userId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('candidate', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $monthStart = Carbon::parse($month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $calendarInterviews = Interview::withPermissionCheck()
            ->with(['candidate:id,first_name,last_name', 'job:id,title', 'round:id,name'])
            ->whereBetween('scheduled_date', [$monthStart, $monthEnd])
            ->when($request->boolean('mine'), fn ($q) => $q->whereJsonContains('interviewers', $userId))
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        $interviews = $query->orderByDesc('scheduled_date')->orderByDesc('scheduled_time')
            ->paginate($request->per_page ?? 15)->withQueryString();

        $candidates = Candidate::whereIn('created_by', $companyUserIds)
            ->select('id', 'first_name', 'last_name', 'job_id')->orderBy('first_name')->get();

        $rounds = InterviewRound::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')->with('job:id,title')->get();

        $interviewTypes = InterviewType::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')->select('id', 'name')->get();

        $employees = User::with('employee')
            ->whereIn('type', ['manager', 'hr', 'employee'])
            ->whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee->employee_id ?? '',
            ]);

        return Inertia::render('hr/recruitment/interviews/workspace', [
            'view' => $view,
            'interviews' => $interviews,
            'calendarInterviews' => $calendarInterviews,
            'candidates' => $candidates,
            'rounds' => $rounds,
            'interviewTypes' => $interviewTypes,
            'employees' => $employees,
            'calendarMonth' => $month,
            'filters' => $request->only(['search', 'status', 'view', 'month', 'mine', 'per_page']),
        ]);
    }
}
