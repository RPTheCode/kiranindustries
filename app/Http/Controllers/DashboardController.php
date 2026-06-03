<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\PlanRequest;
use App\Support\ChartPalette;


class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Super admin always gets dashboard
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return $this->renderDashboard();
        }

        // Check if user has dashboard permission (skip if permission doesn't exist)
        try {
            if ($user->hasPermissionTo('manage-dashboard')) {
                return $this->renderDashboard();
            }
        } catch (\Exception $e) {
            // Permission doesn't exist, continue to dashboard for authenticated users
            return $this->renderDashboard();
        }

        // Redirect to first available page
        return $this->redirectToFirstAvailablePage();
    }

    public function redirectToFirstAvailablePage()
    {
        $user = auth()->user();

        // Define available routes with their permissions
        $routes = [
            ['route' => 'hr.branches.index', 'permission' => 'manage-branches'],
            ['route' => 'hr.week-offs.index', 'permission' => 'manage-week-offs'],
            ['route' => 'hr.departments.index', 'permission' => 'manage-departments'],
            ['route' => 'hr.designations.index', 'permission' => 'manage-designations'],
            ['route' => 'hr.employees.index', 'permission' => 'manage-employees'],
            ['route' => 'hr.attendance-records.index', 'permission' => 'manage-attendance-records'],
            ['route' => 'hr.leave-applications.index', 'permission' => 'manage-leave-applications'],
            ['route' => 'users.index', 'permission' => 'manage-users'],
            ['route' => 'roles.index', 'permission' => 'manage-roles'],
            ['route' => 'plans.index', 'permission' => 'manage-plans'],
            ['route' => 'referral.index', 'permission' => 'manage-referral'],
            ['route' => 'settings.index', 'permission' => 'manage-settings'],
        ];

        // Find first available route
        foreach ($routes as $routeData) {
            if ($user->hasPermissionTo($routeData['permission'])) {
                return redirect()->route($routeData['route']);
            }
        }

        // If no permissions found, logout user
        auth()->logout();
        return redirect()->route('login')->with('error', __('No access permissions found.'));
    }

    private function renderDashboard()
    {
        $user = auth()->user();

        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return $this->renderSuperAdminDashboard();
        } else {
            return $this->renderCompanyDashboard();
        }
    }

    private function renderSuperAdminDashboard()
    {
        // Get system-wide statistics
        $totalCompanies = User::where('type', 'company')->count();
        $totalUsers = User::where('type', '!=', 'superadmin')->where('type', '!=', 'super admin')->count();
        $totalRevenue = PlanOrder::where('status', 'approved')->sum('final_price') ?? 0;
        $activePlans = Plan::where('is_plan_enable', 'on')->count();

        $pendingRequests = PlanRequest::where('status', 'pending')->count();

        // Calculate monthly growth for companies
        $currentMonthCompanies = User::where('type', 'company')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $previousMonthCompanies = User::where('type', 'company')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $monthlyGrowth = $previousMonthCompanies > 0
            ? round((($currentMonthCompanies - $previousMonthCompanies) / $previousMonthCompanies) * 100, 1)
            : ($currentMonthCompanies > 0 ? 100 : 0);

        $dashboardData = [
            'stats' => [
                'totalCompanies' => $totalCompanies,
                'totalUsers' => $totalUsers,
                'totalRevenue' => $totalRevenue,
                'activePlans' => $activePlans,
                'pendingRequests' => $pendingRequests,
                'monthlyGrowth' => $monthlyGrowth,
            ],
            'recentActivity' => [
                ['id' => 1, 'type' => 'company', 'message' => 'New company registered', 'time' => '2 hours ago', 'status' => 'success'],
                ['id' => 2, 'type' => 'plan', 'message' => 'Plan upgrade request', 'time' => '4 hours ago', 'status' => 'warning'],
                ['id' => 3, 'type' => 'payment', 'message' => 'Payment received', 'time' => '6 hours ago', 'status' => 'success'],
            ],
            'topPlans' => Plan::withCount('users')
                ->orderBy('users_count', 'desc')
                ->take(3)
                ->get()
                ->map(function ($plan) {
                    return [
                        'name' => $plan->name,
                        'subscribers' => $plan->users_count,
                        'revenue' => $plan->users_count * $plan->price,
                    ];
                })
        ];

        return Inertia::render('superadmin/dashboard', [
            'dashboardData' => $dashboardData
        ]);
    }

    private function renderCompanyDashboard()
    {
        $user = auth()->user();

        // If user is employee, show limited dashboard
        if ($user->type === 'employee') {
            return $this->renderEmployeeDashboard();
        }

        $companyUserIds = $this->getCompanyUserIds();
        $activeBranchId = session('active_branch_id');

        // Core HR Statistics
        $totalEmployeesQuery = \App\Models\Employee::query();
        if ($activeBranchId) {
            $totalEmployeesQuery->where('branch_id', $activeBranchId);
        }
        $totalEmployees = $totalEmployeesQuery->count();

        $totalBranches = \App\Models\Branch::where('status', 'active')->count();
        $totalDepartments = \App\Models\Department::count();

        // Monthly Statistics
        $newEmployeesThisMonth = \App\Models\Employee::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $jobPostsThisMonth = \App\Models\JobPosting::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $candidatesThisMonth = \App\Models\Candidate::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        // Attendance Statistics
        $totalEmployeeIds = $totalEmployeesQuery->pluck('id');

        // LIVE ATTENDANCE CALCULATION: Count unique clock-ins from raw logs today
        $todayLogsQuery = \App\Models\EsslLog::whereDate('log_date', today());

        $employeesQuery = \App\Models\Employee::query();
        if ($activeBranchId) {
            $employeesQuery->where('branch_id', $activeBranchId);
        } else {
            $employeesQuery->whereIn('created_by', $companyUserIds);
        }

        $empData = $employeesQuery->get(['id', 'essl_id', 'emy_code']);
        $machineIds = $empData->pluck('essl_id')
            ->merge($empData->pluck('emy_code'))
            ->filter()
            ->unique()
            ->toArray();

        $livePresentIds = [];
        if (!empty($machineIds)) {
            $livePresentIds = $todayLogsQuery->whereIn('user_id', $machineIds)
                ->distinct('user_id')
                ->pluck('user_id')
                ->toArray();
        }

        // Also check if any are marked 'P' in processed table (backup)
        $processedPresentIds = \App\Models\BiometricAttendance::whereDate('attendance_date', today())
            ->where('status', 'P')
            ->whereIn('employee_id', $empData->pluck('id'))
            ->pluck('employee_code')
            ->toArray();

        // Count unique present employees
        $presentToday = count(array_unique(array_merge($livePresentIds, $processedPresentIds)));

        // Absent is Total - Present (Live estimate)
        $absentToday = $totalEmployees - $presentToday;
        $absentToday = $absentToday < 0 ? 0 : $absentToday;

        $attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0;

        // 24h MisPunch — yesterday (same window as 24h PDF report on MisPunch page)
        $mispunch24hDate = now()->subDay()->toDateString();
        $todayPunchedUserIds = esslPunchedUserIdLookupForDate(now()->format('Y-m-d'));

        $mispunchCountQuery = \App\Models\BiometricAttendance::where('attendance_date', $mispunch24hDate)
            ->where('status', 'MIS');

        if ($activeBranchId) {
            $mispunchCountQuery->where('branch_id', $activeBranchId);
        } else {
            $mispunchCountQuery->whereHas('employee', function ($q) use ($companyUserIds) {
                $q->whereIn('created_by', $companyUserIds);
            });
        }

        $mispunchCount = $mispunchCountQuery
            ->with('employee:id,user_id,emy_code')
            ->get(['id', 'employee_id', 'attendance_date', 'status', 'log_details', 'in_time', 'out_time', 'employee_code'])
            ->filter(fn ($record) => !recordIsDeferredOpenInMispunch($record, $todayPunchedUserIds))
            ->count();

        // MisPunch this month (excludes today — current day still in progress)
        $mispunchMonthFrom = now()->startOfMonth()->toDateString();
        $mispunchMonthTo = $mispunch24hDate;

        $mispunchMonthQuery = \App\Models\BiometricAttendance::where('status', 'MIS')
            ->whereMonth('attendance_date', now()->month)
            ->whereYear('attendance_date', now()->year)
            ->whereDate('attendance_date', '<', today());

        if ($activeBranchId) {
            $mispunchMonthQuery->where('branch_id', $activeBranchId);
        } else {
            $mispunchMonthQuery->whereHas('employee', function ($q) use ($companyUserIds) {
                $q->whereIn('created_by', $companyUserIds);
            });
        }

        $mispunchCountMonth = $mispunchMonthQuery->count();

        $onLeaveToday = \App\Models\LeaveApplication::whereIn('created_by', $companyUserIds)
            ->where('status', 'approved');

        if (config('app.is_demo') == true) {
            $onLeaveToday = $onLeaveToday->count();
        } else {
            $onLeaveToday = $onLeaveToday->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today())->count();
        }

        // Recruitment Statistics
        $activeJobPostings = \App\Models\JobPosting::whereIn('created_by', $companyUserIds)
            ->where('status', 'Published')->count();
        $totalCandidates = \App\Models\Candidate::whereIn('created_by', $companyUserIds)->count();

        // Department Distribution for Chart (top slices + Others for readability)
        $departmentMaxSlices = config('app.is_demo') == true ? 6 : 10;
        $departmentsForChart = \App\Models\Department::whereIn('created_by', $companyUserIds)
            ->withCount('employees')
            ->with('branch')
            ->orderBy('employees_count', 'desc')
            ->get();

        $departmentStats = collect();
        foreach ($departmentsForChart->take($departmentMaxSlices) as $index => $dept) {
            $displayName = $dept->name . ($dept->branch ? ' (' . $dept->branch->name . ')' : '');
            $departmentStats->push([
                'name' => $displayName,
                'value' => $dept->employees_count,
                'color' => ChartPalette::department($index),
            ]);
        }
        $othersEmployeeCount = (int) $departmentsForChart->slice($departmentMaxSlices)->sum('employees_count');
        if ($othersEmployeeCount > 0) {
            $departmentStats->push([
                'name' => 'Other departments',
                'value' => $othersEmployeeCount,
                'color' => ChartPalette::others(),
            ]);
        }
        $departmentStats = $departmentStats->values()->all();

        // Category Distribution for Chart
        $categoryStats = \App\Models\Category::whereIn('created_by', $companyUserIds)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->get()
            ->map(function ($cat, $index) {
                return [
                    'name' => $cat->name,
                    'value' => $cat->employees_count,
                    'color' => ChartPalette::category($index),
                ];
            });


        // Monthly Hiring Trend for Chart (last 6 months)
        $hiringTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = \App\Models\Employee::whereIn('created_by', $companyUserIds)
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
            $hiringTrend[] = [
                'month' => $month->format('M Y'),
                'hires' => $count
            ];
        }

        // Candidate Status Distribution for Chart
        $candidateStatusStats = \App\Models\Candidate::whereIn('created_by', $companyUserIds)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                $colors = [
                    'New' => '#3B82F6',
                    'Screening' => '#06B6D4',
                    'Interview' => '#6366F1',
                    'Offer' => '#F59E0B',
                    'Hired' => '#10B981',
                    'Rejected' => '#EF4444'
                ];
                return [
                    'name' => $item->status,
                    'value' => $item->count,
                    'color' => $colors[$item->status] ?? '#6b7280'
                ];
            });


        // MisPunch list for dashboard (24h / yesterday only)
        $mispunchListQuery = \App\Models\BiometricAttendance::with('employee.user')
            ->where('attendance_date', $mispunch24hDate)
            ->where('status', 'MIS');

        if ($activeBranchId) {
            $mispunchListQuery->where('branch_id', $activeBranchId);
        } else {
            $mispunchListQuery->whereHas('employee', function ($q) use ($companyUserIds) {
                $q->whereIn('created_by', $companyUserIds);
            });
        }

        $mispunchList = $mispunchListQuery->latest('attendance_date')
            ->with(['employee:id,user_id,emy_code', 'employee.user:id,name'])
            ->get(['id', 'employee_id', 'attendance_date', 'status', 'employee_code'])
            ->filter(fn ($record) => !recordIsDeferredOpenInMispunch($record, $todayPunchedUserIds))
            ->take(10)
            ->map(function ($record) {
                return [
                    'employee_name' => $record->employee?->user?->name ?? 'N/A',
                    'employee_code' => $record->employee?->emy_code ?? $record->employee_code ?? 'N/A',
                    'date' => $record->attendance_date->format('d M, Y'),
                    'status' => $record->status
                ];
            });

        // Recent Activities
        $recentLeaves = \App\Models\LeaveApplication::whereIn('created_by', $companyUserIds)
            ->with(['employee', 'leaveType']);
        if (config('app.is_demo') == true) {
            $recentLeaves = $recentLeaves->whereIn('status', ['approved', 'absent'])->get();
        } else {
            $recentLeaves = $recentLeaves->whereIn('status', ['approved', 'absent'])
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today())
                ->get();
        }




        $recentCandidates = \App\Models\Candidate::whereIn('created_by', $companyUserIds)
            ->with(['job'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent Announcements
        $announcementsQuery = \App\Models\Announcement::whereIn('created_by', $companyUserIds);
        if ($activeBranchId) {
            $announcementsQuery->where(function ($q) use ($activeBranchId) {
                $q->where('is_company_wide', true)
                    ->orWhereHas('branches', function ($q) use ($activeBranchId) {
                        $q->where('branches.id', $activeBranchId);
                    });
            });
        }
        $recentAnnouncements = $announcementsQuery->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent Meetings
        $recentMeetings = \App\Models\Meeting::whereIn('created_by', $companyUserIds)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Current Month Birthdays
        $birthdaysQuery = \App\Models\Employee::with(['user', 'department'])
            ->whereMonth('date_of_birth', now()->month);

        if ($activeBranchId) {
            $birthdaysQuery->where('branch_id', $activeBranchId);
        } else {
            $birthdaysQuery->whereIn('created_by', $companyUserIds);
        }

        $currentMonthBirthdays = $birthdaysQuery->orderByRaw('DAY(date_of_birth) ASC')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'name' => $emp->user->name ?? 'Employee',
                    'department' => $emp->department->name ?? 'N/A',
                    'date_of_birth' => $emp->date_of_birth ? \Carbon\Carbon::parse($emp->date_of_birth)->format('d M') : null,
                    'day' => $emp->date_of_birth ? \Carbon\Carbon::parse($emp->date_of_birth)->format('d') : null,
                    'avatar' => $emp->user->avatar ?? null,
                ];
            });

        // Current Month Anniversaries
        $anniversariesQuery = \App\Models\Employee::with(['user', 'department'])
            ->whereNotNull('date_of_joining')
            ->whereMonth('date_of_joining', now()->month)
            ->whereYear('date_of_joining', '<', now()->year);

        if ($activeBranchId) {
            $anniversariesQuery->where('branch_id', $activeBranchId);
        } else {
            $anniversariesQuery->whereIn('created_by', $companyUserIds);
        }

        $currentMonthAnniversaries = $anniversariesQuery->orderByRaw('DAY(date_of_joining) ASC')
            ->get()
            ->map(function ($emp) {
                $years = now()->year - \Carbon\Carbon::parse($emp->date_of_joining)->year;
                return [
                    'id' => $emp->id,
                    'name' => $emp->user->name ?? 'Employee',
                    'department' => $emp->department->name ?? 'N/A',
                    'date_of_joining' => \Carbon\Carbon::parse($emp->date_of_joining)->format('d M'),
                    'day' => \Carbon\Carbon::parse($emp->date_of_joining)->format('d'),
                    'years' => $years,
                    'avatar' => $emp->user->avatar ?? null,
                ];
            });

        $esslSync = $this->resolveEsslSyncInfo($activeBranchId);

        $dashboardData = [
            'esslSync' => $esslSync,
            'stats' => [
                'totalEmployees' => $totalEmployees,
                'totalBranches' => $totalBranches,
                'totalDepartments' => $totalDepartments,
                'newEmployeesThisMonth' => $newEmployeesThisMonth,
                'jobPostsThisMonth' => $jobPostsThisMonth,
                'candidatesThisMonth' => $candidatesThisMonth,
                'attendanceRate' => (int) $attendanceRate,
                'presentToday' => (int) $presentToday,
                'absentToday' => (int) $absentToday,
                'mispunchCount' => (int) $mispunchCount,
                'mispunch24hDate' => $mispunch24hDate,
                'mispunch24hLabel' => \Carbon\Carbon::parse($mispunch24hDate)->format('d M Y'),
                'mispunchCountMonth' => (int) $mispunchCountMonth,
                'mispunchMonthLabel' => now()->format('M Y'),
                'mispunchMonthRangeLabel' => \Carbon\Carbon::parse($mispunchMonthFrom)->format('d M')
                    .' – '.\Carbon\Carbon::parse($mispunchMonthTo)->format('d M Y'),
                'mispunchMonthFrom' => $mispunchMonthFrom,
                'mispunchMonthTo' => $mispunchMonthTo,
                'onLeaveToday' => (int) $onLeaveToday,
                'activeJobPostings' => $activeJobPostings,
                'totalCandidates' => $totalCandidates,
                'totalCategories' => \App\Models\Category::whereIn('created_by', $companyUserIds)->count(),
                'branches' => $user->branches()->where('status', 'active')->get(),
                'activeBranchId' => session('active_branch_id'),
                'activeBranchName' => $activeBranchId ? \App\Models\Branch::find($activeBranchId)?->name : 'All Branches'
            ],
            'charts' => [
                'departmentStats' => $departmentStats,
                'categoryStats' => $categoryStats,
                'hiringTrend' => $hiringTrend,
                'candidateStatusStats' => $candidateStatusStats,
                'leaveTypesStats' => [],
                'mispunchList' => $mispunchList,
            ],
            'recentActivities' => [
                'leaves' => $recentLeaves,
                'candidates' => $recentCandidates,
                'announcements' => $recentAnnouncements,
                'meetings' => $recentMeetings,
                'birthdays' => $currentMonthBirthdays,
                'anniversaries' => $currentMonthAnniversaries
            ],
            'userType' => $user->type
        ];

        return Inertia::render('dashboard', [
            'dashboardData' => $dashboardData
        ]);
    }

    private function renderEmployeeDashboard()
    {
        $user = auth()->user();
        $companyUserIds = $this->getCompanyUserIds();

        $activeBranchId = session('active_branch_id');

        // Recent Announcements
        $announcementsQuery = \App\Models\Announcement::whereIn('created_by', $companyUserIds);
        if ($activeBranchId) {
            $announcementsQuery->where(function ($q) use ($activeBranchId) {
                $q->where('is_company_wide', true)
                    ->orWhereHas('branches', function ($q) use ($activeBranchId) {
                        $q->where('branches.id', $activeBranchId);
                    });
            });
        }
        $recentAnnouncements = $announcementsQuery->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent Meetings - get meetings where user is organizer
        $recentMeetings = \App\Models\Meeting::with('attendees')
            ->whereIn('created_by', $companyUserIds)
            ->where('organizer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get meetings where user is attendee
        $meetingAttendee = \App\Models\MeetingAttendee::with('meeting')
            ->where('user_id', $user->id)
            ->get();


        // Extract meetings from attendee records
        $attendeeMeetings = $meetingAttendee->pluck(value: 'meeting')->filter();

        // Merge and remove duplicates
        $recentMeetings = $recentMeetings->merge($attendeeMeetings)
            ->unique('id')
            ->filter(function ($meeting) {
                return $meeting->meeting_date >= today();
            })
            ->sortByDesc('created_at')
            ->values();

        // Employee Stats
        $totalAwards = \App\Models\Award::where('employee_id', $user->id)->count();
        $totalWarnings = \App\Models\Warning::where('employee_id', $user->id)->count();
        $totalComplaints = \App\Models\Complaint::where('against_employee_id', $user->id)->count();

        // Get shifts and attendance policies for clock in functionality
        $shifts = \App\Models\Shift::with('slots')->whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->get(['id', 'name']);

        $attendancePolicies = \App\Models\AttendancePolicy::whereIn('created_by', $companyUserIds)
            ->where('status', 'active')
            ->get(['id', 'name']);

        // Get today's attendance for the employee
        $todayAttendance = \App\Models\AttendanceRecord::where('employee_id', $user->id)
            ->where('date', \Carbon\Carbon::today())
            ->first();

        // Get employee's assigned shift
        $employeeShift = null;
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        if ($employee && $employee->shift_id) {
            $employeeShift = \App\Models\Shift::with('slots')->find($employee->shift_id);
        }

        // Auto clock out previous days if not clocked out
        $previousAttendance = \App\Models\AttendanceRecord::where('employee_id', $user->id)
            ->where('date', '<', \Carbon\Carbon::today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->get();

        foreach ($previousAttendance as $record) {
            $recordDate = \Carbon\Carbon::parse($record->date);
            $shift = \App\Models\Shift::with('slots')->find($record->shift_id) ?? $employeeShift;

            if ($shift) {
                $endTime = $shift->slots->last()->end_time ?? '18:00';
                $record->update([
                    'clock_out' => $endTime,
                ]);

                if (method_exists($record, 'processAttendance')) {
                    $record->processAttendance();
                }
            }
        }

        // Auto clock out if shift end time has passed for today
        if ($todayAttendance && $todayAttendance->clock_in && !$todayAttendance->clock_out && $employeeShift) {
            $now = \Carbon\Carbon::now();
            $endTime = $employeeShift->slots->last()->end_time ?? '18:00';
            $shiftEndTime = \Carbon\Carbon::today()->setTimeFromTimeString($endTime);

            if ($now->greaterThan($shiftEndTime)) {
                $todayAttendance->update([
                    'clock_out' => $endTime,
                ]);

                if (method_exists($todayAttendance, 'processAttendance')) {
                    $todayAttendance->processAttendance();
                }

                $todayAttendance = $todayAttendance->fresh();
            }
        }

        $dashboardData = [
            'stats' => [
                'totalAwards' => $totalAwards,
                'totalWarnings' => $totalWarnings,
                'totalComplaints' => $totalComplaints
            ],
            'recentActivities' => [
                'announcements' => $recentAnnouncements,
                'meetings' => $recentMeetings
            ],
            'shifts' => $shifts,
            'attendancePolicies' => $attendancePolicies,
            'todayAttendance' => $todayAttendance,
            'currentTime' => \Carbon\Carbon::now()->format('H:i:s'),
            'employeeShift' => $employeeShift,
            'userType' => $user->type
        ];
        return Inertia::render('employee-dashboard', [
            'dashboardData' => $dashboardData
        ]);
    }

    private function resolveEsslSyncInfo(?int $activeBranchId): array
    {
        $path = '/essl-sync';

        $query = \Illuminate\Support\Facades\DB::table('essl_logs')
            ->join('employees', 'essl_logs.user_id', '=', 'employees.user_id');

        if ($activeBranchId) {
            $query->where('employees.branch_id', $activeBranchId);
        }

        $lastRaw = $query->max('essl_logs.log_date');

        if (!$lastRaw) {
            if ($activeBranchId) {
                $lastRaw = getSetting('last_biometric_sync_date_branch_'.$activeBranchId);
            }
            if (!$lastRaw) {
                $lastRaw = getSetting('last_biometric_sync_date');
            }
        }

        $branchName = $activeBranchId
            ? \App\Models\Branch::find($activeBranchId)?->name
            : null;

        if (!$lastRaw) {
            return [
                'url' => $path,
                'path' => $path,
                'lastSyncAt' => null,
                'lastSyncDate' => null,
                'lastSyncTime' => null,
                'lastSyncLabel' => null,
                'branchName' => $branchName,
                'hasSync' => false,
            ];
        }

        $at = \Carbon\Carbon::parse($lastRaw);

        return [
            'url' => $path,
            'path' => $path,
            'lastSyncAt' => $at->toIso8601String(),
            'lastSyncDate' => $at->format('d M Y'),
            'lastSyncTime' => $at->format('h:i A'),
            'lastSyncLabel' => $at->format('d M Y, h:i A'),
            'branchName' => $branchName,
            'hasSync' => true,
        ];
    }

    private function getCompanyUserIds()
    {
        $user = auth()->user();
        if ($user->type === 'company') {
            $companyUserIds = User::where('created_by', $user->id)->pluck('id')->toArray();
            $companyUserIds[] = $user->id;
            return $companyUserIds;
        } else {
            $userCreatedBy = User::where('id', $user->created_by)->value('id');
            $companyUserIds = User::where('created_by', $userCreatedBy)->pluck('id')->toArray();
            $companyUserIds[] = $userCreatedBy;
            return $companyUserIds;
        }
    }
}
