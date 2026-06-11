<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Offer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecruitmentDashboardService
{
    private const PIPELINE_STATUSES = ['New', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected'];

    public function aggregate(): array
    {
        $companyUserIds = getCompanyAndUsersId();
        $activeBranchId = session('active_branch_id');

        $openJobsQuery = JobPosting::whereIn('created_by', $companyUserIds)
            ->where('status', 'Open')
            ->where('is_published', true);

        if ($activeBranchId) {
            $openJobsQuery->whereHas('department', fn ($q) => $q->where('branch_id', $activeBranchId));
        }

        $candidatesQuery = Candidate::whereIn('created_by', $companyUserIds)
            ->whereNotIn('status', ['Hired', 'Rejected']);

        $offersPendingQuery = Offer::whereIn('created_by', $companyUserIds)
            ->whereIn('status', ['Draft', 'Sent', 'Negotiating']);

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $interviewsThisWeekQuery = Interview::whereIn('created_by', $companyUserIds)
            ->whereBetween('scheduled_date', [$weekStart, $weekEnd])
            ->where('status', 'Scheduled');

        $pipeline = Candidate::whereIn('created_by', $companyUserIds)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pipelineStats = collect(self::PIPELINE_STATUSES)->map(fn ($status) => [
            'status' => $status,
            'count' => (int) ($pipeline[$status] ?? 0),
        ])->values();

        $upcomingInterviews = Interview::with(['candidate', 'job', 'round'])
            ->whereIn('created_by', $companyUserIds)
            ->where('scheduled_date', '>=', Carbon::today())
            ->where('status', 'Scheduled')
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->limit(8)
            ->get()
            ->map(fn (Interview $interview) => [
                'id' => $interview->id,
                'candidate_name' => $interview->candidate?->full_name,
                'job_title' => $interview->job?->title,
                'round_name' => $interview->round?->name,
                'scheduled_date' => $interview->scheduled_date?->format('Y-m-d'),
                'scheduled_time' => $interview->scheduled_time,
                'status' => $interview->status,
            ]);

        $recentCandidates = Candidate::with(['job:id,title', 'source:id,name'])
            ->whereIn('created_by', $companyUserIds)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Candidate $c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'job_title' => $c->job?->title,
                'source' => $c->source?->name,
                'status' => $c->status,
                'application_date' => $c->application_date?->format('Y-m-d'),
                'has_resume' => ! empty($c->resume_path),
            ]);

        return [
            'stats' => [
                'open_jobs' => $openJobsQuery->count(),
                'active_candidates' => $candidatesQuery->count(),
                'interviews_this_week' => $interviewsThisWeekQuery->count(),
                'offers_pending' => $offersPendingQuery->count(),
            ],
            'pipeline' => $pipelineStats,
            'upcoming_interviews' => $upcomingInterviews,
            'recent_candidates' => $recentCandidates,
        ];
    }

    public function pipelineGrouped(): Collection
    {
        $companyUserIds = getCompanyAndUsersId();

        $candidates = Candidate::withPermissionCheck()
            ->with(['job:id,title,job_code', 'source:id,name'])
            ->withCount('interviews')
            ->orderByDesc('updated_at')
            ->get();

        return collect(self::PIPELINE_STATUSES)->mapWithKeys(function ($status) use ($candidates) {
            return [
                $status => $candidates->where('status', $status)->values()->map(fn (Candidate $c) => [
                    'id' => $c->id,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'full_name' => $c->full_name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'status' => $c->status,
                    'job_id' => $c->job_id,
                    'job_title' => $c->job?->title,
                    'source_name' => $c->source?->name,
                    'experience_years' => $c->experience_years,
                    'application_date' => $c->application_date?->format('Y-m-d'),
                    'has_resume' => ! empty($c->resume_path),
                    'interviews_count' => $c->interviews_count,
                ]),
            ];
        });
    }
}
