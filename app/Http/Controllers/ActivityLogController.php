<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends BaseController
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! ActivityLogger::canAccess($user)) {
            abort(403, __('Unauthorized Access Prevented'));
        }

        $query = ActivityLogger::queryForCurrentUser();
        ActivityLogger::applyRequestFilters($query, $request);

        $statsBase = clone $query;
        $todayQuery = clone $statsBase;
        ActivityLogger::scopeTodayInAppTimezone($todayQuery);

        $stats = [
            'total' => (clone $statsBase)->count(),
            'today' => $todayQuery->count(),
            'created' => (clone $statsBase)->where('action', 'created')->count(),
            'updated' => (clone $statsBase)->where('action', 'updated')->count(),
            'deleted' => (clone $statsBase)->where('action', 'deleted')->count(),
        ];

        $logs = $query->paginate(15)->withQueryString();
        $logs->through(function ($log) {
            $formatted = ActivityLogger::formatLogTimestamp(
                $log->created_at,
                $log->getRawOriginal('created_at')
            );
            $log->setAttribute('when_date', $formatted['when_date']);
            $log->setAttribute('when_time', $formatted['when_time']);

            return $log;
        });

        $tz = ActivityLogger::displayTimezone();

        return Inertia::render('activity-logs/index', [
            'logs' => $logs,
            'filters' => $request->only(['role', 'from_date', 'to_date']),
            'stats' => $stats,
            'visibility' => ActivityLogger::visibilityMetaFor($user),
            'activeBranch' => session('active_branch_id')
                ? (\App\Models\Branch::find(session('active_branch_id'))?->name ?? __('Selected branch'))
                : __('All branches'),
            'displayTimezone' => $tz,
            'timezoneLabel' => $tz === 'Asia/Kolkata' ? 'IST' : $tz,
        ]);
    }

    public function latest(Request $request)
    {
        $user = auth()->user();

        if (! ActivityLogger::canAccess($user)) {
            return response()->json([]);
        }

        $logs = ActivityLogger::queryForCurrentUser()->take(10)->get();

        return response()->json($logs);
    }
}
