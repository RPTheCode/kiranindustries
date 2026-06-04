<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeWorkHistory;
use App\Models\Skill;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class EmployeeWorkHistoryController extends Controller
{
    public function index()
    {
        // 1. First find which employees match the criteria (Branch + Search + Filters)
        // AND ensures we only find people who belong to the current active branch
        $activeBranchId = session('active_branch_id');

        $query = EmployeeWorkHistory::query()
            ->when($activeBranchId, function ($query) use ($activeBranchId) {
                $query->where(function ($q) use ($activeBranchId) {
                    // Record was explicitly created in this branch
                    $q->where('branch_id', $activeBranchId)
                        // OR the employee belongs to this branch (even if this specific history was elsewhere)
                        ->orWhereHas('employee.employee', function ($q2) use ($activeBranchId) {
                            $q2->where('branch_id', $activeBranchId);
                        });
                });
            })
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('site_name', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        // Apply skill filter if present (check both 'skill' and 'skill_id' for compatibility)
        $skillParam = request('skill') ?? request('skill_id');
        if ($skillParam && !empty($skillParam) && $skillParam !== 'all') {
            $skillId = $skillParam;

            // Get user IDs that have the skill (Independent Query logic like EmployeeController)
            $userIdsWithSkill = EmployeeWorkHistory::whereHas('skills', function ($q) use ($skillId) {
                $q->where('skills.id', $skillId);
            })->pluck('employee_id');

            // Get emails of those users to handle cross-branch identity (same email = same person logic)
            $emailsWithSkill = \App\Models\User::whereIn('id', $userIdsWithSkill)->pluck('email');

            // Filter main query by user email
            $query->whereHas('employee', function ($q) use ($emailsWithSkill) {
                $q->whereIn('email', $emailsWithSkill);
            });
        }

        // Apply employee_id filter if present
        if (request()->has('employee_id') && !empty(request()->employee_id)) {
            $query->where('employee_id', request()->employee_id);
        }

        $matchingUserIds = $query->pluck('employee_id')->unique();

        // 2. Get the emails of these users filters
        $emails = \App\Models\User::whereIn('id', $matchingUserIds)
            ->distinct()
            ->pluck('email');

        if (request('search')) {
            // also Allow searching directly for emails even if they have no history? 
            // No, standard flow is searching *within* history.
            // But what if user searches "Harsh"? We want to find Harsh's email.
            // The above $matchingUserIds approach covers it if the relationship is correct.
        }

        // 3. Paginate the unique emails
        // We use a manual paginator or query distinct emails from Users table.
        // Let's Query Users table for distinct emails that are in our matching list.
        $paginatedEmails = \App\Models\User::whereIn('email', $emails)
            ->select('email') // Select only email to group by
            ->groupBy('email')
            // If we want to order by latest created_at of work history? That's complex. 
            // Let's just order by Email for now or simple default.
            ->orderBy('email')
            ->paginate(10)
            ->withQueryString();

        // 4. For the emails in the current page, fetch ALL their work histories (across all user accounts)
        $targetEmails = $paginatedEmails->pluck('email');

        // Find all User IDs associated with these emails
        $allUserIdsForTheseEmails = \App\Models\User::whereIn('email', $targetEmails)->pluck('id');

        // Fetch Work Histories
        $rawHistories = EmployeeWorkHistory::whereIn('employee_id', $allUserIdsForTheseEmails)
            ->with(['employee', 'skills', 'branch']) // employee = User, added branch
            ->get();

        // 5. Structure the data for Frontend
        // We map the paginated emails to our desired structure
        $groupedData = $paginatedEmails->getCollection()->map(function ($emailObj) use ($rawHistories) {
            $email = $emailObj->email;

            // Get all histories for this email
            $histories = $rawHistories->filter(function ($history) use ($email) {
                return $history->employee && $history->employee->email === $email;
            })->values();

            // Derive a "Display User" (just pick the first one)
            // If no histories (shouldn't happen per logic), we can't show much.
            $firstHistory = $histories->first();
            $user = $firstHistory ? $firstHistory->employee : null;

            // Sort histories by start_date descending
            $histories = $histories->sortByDesc('start_date')->values();

            // Get distinct skills from all histories
            $distinctSkills = $histories->pluck('skills')->flatten()->unique('id')->values();

            return [
                'id' => $user ? $user->id : 'email-' . $email, // Unique key for row
                'email' => $email,
                'name' => $user ? $user->name : $email,
                'avatar' => $user ? $user->avatar : null,
                'work_histories' => $histories,
                'total_experience_count' => $histories->count(),
                'skills' => $distinctSkills,
                'latest_site' => $histories->first() ? $histories->first()->site_name : '-',
            ];
        });

        // Replace the collection in the paginator
        $workHistories = new \Illuminate\Pagination\LengthAwarePaginator(
            $groupedData,
            $paginatedEmails->total(),
            $paginatedEmails->perPage(),
            $paginatedEmails->currentPage(),
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        $activeBranchId = session('active_branch_id');

        // Get employees for filter dropdown
        $employees = User::join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->whereIn('users.created_by', getCompanyAndUsersId())
            ->select('users.id', 'users.name', 'employees.employee_id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id ?? ''
                ];
            });

        $branches = Branch::all()->map(function ($branch) {
            return [
                'value' => (string) $branch->id,
                'label' => $branch->name,
            ];
        });

        $skills = Skill::where('status', true)->get()->map(function ($skill) {
            return [
                'value' => (string) $skill->id,
                'label' => $skill->name,
            ];
        })->values();

        return Inertia::render('hr/work-history/index', [
            'workHistories' => $workHistories,
            'filters' => request()->only(['search', 'employee_id', 'skill']),
            'employees' => $employees,
            'skills' => $skills,
            'branches' => $branches,
            'active_branch_id' => $activeBranchId ? (string) $activeBranchId : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'site_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,id',
        ]);

        $workHistory = EmployeeWorkHistory::create([
            'employee_id' => $request->employee_id,
            'branch_id' => $request->branch_id,
            'site_name' => $request->site_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'created_by' => Auth::id(),
        ]);

        if ($request->has('skills')) {
            $workHistory->skills()->sync($request->skills);
        }

        return redirect()->back()->with('success', 'Work history created successfully.');
    }

    public function update(Request $request, EmployeeWorkHistory $workHistory)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'site_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,id',
        ]);

        $workHistory->update([
            'employee_id' => $request->employee_id,
            'branch_id' => $request->branch_id,
            'site_name' => $request->site_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        if ($request->has('skills')) {
            $workHistory->skills()->sync($request->skills);
        }

        return redirect()->back()->with('success', 'Work history updated successfully.');
    }

    public function destroy(EmployeeWorkHistory $workHistory)
    {
        $workHistory->delete();

        return redirect()->back()->with('success', 'Work history deleted successfully.');
    }

    public function getEmployeesByBranch($branchId)
    {
        $employees = Employee::where('branch_id', $branchId)
            ->with('user') // Eager load user to get name
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->user_id, // Return User ID as the identifier
                    'name' => $employee->user ? $employee->user->name : 'Unknown',
                ];
            });

        return response()->json($employees);
    }
}
