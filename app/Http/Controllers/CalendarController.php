<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Meeting;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->type === 'employee') {
            if (!$user->hasPermissionTo('view-calendar')) {
                abort(403, 'Unauthorized');
            }
        } else {
            if (!$user->hasPermissionTo('manage-calendar') && !$user->hasPermissionTo('view-calendar')) {
                abort(403, 'Unauthorized');
            }
        }

        $companyUserIds = getCompanyAndUsersId();
        $employmentType = $request->input('employment_type', 'Employee');


        // Get meetings
        $meetings = Meeting::query()
            ->when($user->hasRole('employee'), function ($query) use ($user) {
                $query->where('organizer_id', $user->id)
                    ->orWhereHas('attendees', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            }, function ($query) use ($companyUserIds) {
                $query->whereIn('created_by', $companyUserIds);
            })
            ->get()
            ->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'start' => Carbon::parse($meeting->meeting_date)->format('Y-m-d') . 'T' . Carbon::parse($meeting->start_time)->format('H:i:s'),
                    'end' => Carbon::parse($meeting->meeting_date)->format('Y-m-d') . 'T' . Carbon::parse($meeting->end_time)->format('H:i:s'),
                    'type' => 'meeting',
                    'status' => $meeting->status,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6'
                ];
            });

        // Get holidays
        $holidays = Holiday::whereIn('created_by', $companyUserIds)
            ->get()
            ->map(function ($holiday) {
                return [
                    'id' => $holiday->id,
                    'title' => $holiday->name,
                    'start' => $holiday->start_date,
                    'end' => $holiday->end_date ?: $holiday->start_date,
                    'type' => 'holiday',
                    'allDay' => true,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981'
                ];
            });

        // Get leave applications
        $leaves = LeaveApplication::whereIn('created_by', $companyUserIds)
            ->where('status', 'approved')
            ->whereHas('employee.employee', function($q) use ($employmentType) {
                $q->where('employment_type', $employmentType);
            })
            ->with(['employee', 'leaveType'])
            ->get()
            ->map(function ($leave) {
                $employeeName = $leave->employee ? $leave->employee->name : 'Unknown Employee';
                $leaveTypeName = $leave->leaveType ? $leave->leaveType->name : 'Unknown Leave';
                
                return [
                    'id' => $leave->id,
                    'title' => $employeeName . ' - ' . $leaveTypeName,
                    'start' => $leave->start_date,
                    'end' => Carbon::parse($leave->end_date)->addDay()->format('Y-m-d'),
                    'type' => 'leave',
                    'allDay' => true,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#f59e0b'
                ];
            });

        // Get week offs
        $weekOffEvents = collect([]);
        $activeBranchId = session('active_branch_id');

        if ($activeBranchId) {
            $weekOff = \App\Models\WeekOff::where('branch_id', $activeBranchId)
                ->where('employment_type', $employmentType)
                ->first();

            if ($weekOff) {
                $startOfYear = Carbon::now()->startOfYear();
                $endOfYear = Carbon::now()->endOfYear();
                $settings = $weekOff->settings;

                if ($weekOff->type === 'weekly' && is_array($settings)) {
                    // Iterate each day of the year
                    $period = \Carbon\CarbonPeriod::create($startOfYear, $endOfYear);
                    foreach ($period as $date) {
                        if (in_array($date->format('l'), $settings)) {
                            $weekOffEvents->push([
                                'id' => 'wo-' . $date->timestamp, // Unique string ID
                                'title' => 'Week Off',
                                'start' => $date->format('Y-m-d'),
                                'end' => $date->format('Y-m-d'),
                                'type' => 'week_off',
                                'allDay' => true,
                                'backgroundColor' => '#9ca3af', // Gray-400
                                'borderColor' => '#9ca3af'
                            ]);
                        }
                    }
                } elseif ($weekOff->type === 'monthly' && is_array($settings)) {
                    // Iterate months
                    for ($m = 1; $m <= 12; $m++) {
                        $currentDate = Carbon::create(Carbon::now()->year, $m, 1);
                        // Track day occurrences (e.g., how many Mondays have we seen this month?)
                        $dayCounts = [
                            'Monday' => 0,
                            'Tuesday' => 0,
                            'Wednesday' => 0,
                            'Thursday' => 0,
                            'Friday' => 0,
                            'Saturday' => 0,
                            'Sunday' => 0
                        ];

                        // Iterate through all days of the month
                        while ($currentDate->month == $m) {
                            $dayName = $currentDate->format('l'); // Full day name (Friday)
                            $dayCounts[$dayName]++;
                            $weekNum = $dayCounts[$dayName];

                            // Check if this specific occurrence (e.g., 2nd Friday) is in settings
                            // Settings keys are likely "1", "2", "3", etc.
                            // We cast to string to match JSON keys
                            $weekParams = $settings[(string) $weekNum] ?? ($settings[$weekNum] ?? []);

                            if (is_array($weekParams) && in_array($dayName, $weekParams)) {
                                $weekOffEvents->push([
                                    'id' => 'wo-' . $currentDate->timestamp,
                                    'title' => 'Week Off',
                                    'start' => $currentDate->format('Y-m-d'),
                                    'end' => $currentDate->format('Y-m-d'),
                                    'type' => 'week_off',
                                    'allDay' => true,
                                    'backgroundColor' => '#9ca3af',
                                    'borderColor' => '#9ca3af'
                                ]);
                            }

                            $currentDate->addDay();
                        }
                    }
                }
            }
        }

        $events = $meetings->concat($holidays)->concat($leaves)->concat($weekOffEvents);

        return Inertia::render('calendar/index', [
            'events' => $events,
            'canManage' => $user->hasPermissionTo('manage-calendar'),
            'employmentType' => $employmentType
        ]);
    }
}
