<?php

namespace App\Http\Controllers;

use App\Models\EsslLog;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\EsslLogExport;
use Maatwebsite\Excel\Facades\Excel;

class EsslLogController extends Controller
{
    use \App\Traits\LogsActivity;

    public function index(Request $request)
    {
        $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');

        $query = EsslLog::select('essl_logs.*')
            ->join('users', 'essl_logs.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->with([
                'user.employee' => function ($q) {
                    $q->withoutGlobalScopes();
                }
            ]);

        $needsEmployeeJoin = ($activeBranchId && $activeBranchId !== 'all') ||
            ($request->has('category_id') && !empty($request->category_id) && $request->category_id !== 'all');

        if ($needsEmployeeJoin) {
            $query->join('employees as emp_filter', 'users.id', '=', 'emp_filter.user_id');

            if ($activeBranchId && $activeBranchId !== 'all') {
                $query->where('emp_filter.branch_id', $activeBranchId);
            }

            if ($request->has('category_id') && !empty($request->category_id) && $request->category_id !== 'all') {
                $query->where('emp_filter.category_id', $request->category_id);
            }
        }

        // Filter by Date Range
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('log_date', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('log_date', '<=', $request->date_to . ' 23:59:59');
        }

        // Filter by Employee
        if ($request->has('employee_id') && !empty($request->employee_id) && $request->employee_id !== 'all') {
            $query->where('essl_logs.user_id', $request->employee_id);
        }

        // Filter by Direction (In/Out)
        if ($request->has('direction') && $request->direction !== null && $request->direction !== '' && $request->direction !== 'all') {
            $direction = strtolower($request->direction);
            if ($direction === 'in' || $direction === '0') {
                $query->whereIn('direction', ['in', '0']);
            } elseif ($direction === 'out' || $direction === '1') {
                $query->whereIn('direction', ['out', '1']);
            } else {
                $query->where('direction', $request->direction);
            }
        }

        $logs = $query->orderBy('log_date', 'desc')->paginate($request->per_page ?? 20);

        $employeesQuery = User::join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->whereIn('users.created_by', getCompanyAndUsersId());

        if ($activeBranchId && $activeBranchId !== 'all') {
            $employeesQuery->where('employees.branch_id', $activeBranchId);
        }

        $employees = $employeesQuery->select('users.id', 'users.name', 'employees.employee_id as emp_code')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'emp_code' => $user->emp_code ?? ''
            ];
        });

        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $categoriesQuery = \App\Models\Category::withoutGlobalScopes()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId && $activeBranchId !== 'all') {
            $categoriesQuery->where(function ($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId)->orWhereNull('branch_id');
            });
        }
        $categories = $categoriesQuery->get(['id', 'name']);

        // Fetch actual max dates for all branches from DB
        $maxDates = \Illuminate\Support\Facades\DB::table('essl_logs')
            ->join('employees', 'essl_logs.user_id', '=', 'employees.user_id')
            ->select('employees.branch_id', \Illuminate\Support\Facades\DB::raw('MAX(essl_logs.log_date) as max_date'))
            ->groupBy('employees.branch_id')
            ->pluck('max_date', 'branch_id')
            ->toArray();

        $branchSyncDates = [];
        foreach ($branches as $branch) {
            if (isset($maxDates[$branch->id])) {
                $date = Carbon::parse($maxDates[$branch->id])->format('Y-m-d H:i:s');
            } else {
                $date = getSetting('last_biometric_sync_date_branch_' . $branch->id);
                if (!$date) {
                    $date = getSetting('last_biometric_sync_date');
                }
            }
            $branchSyncDates[$branch->id] = $date;
        }

        $latestGlobalLog = EsslLog::orderBy('log_date', 'desc')->first();
        $globalLastSync = $latestGlobalLog ? Carbon::parse($latestGlobalLog->log_date)->format('Y-m-d H:i:s') : getSetting('last_biometric_sync_date');
        $branchSyncDates['all'] = $globalLastSync;

        if ($activeBranchId && $activeBranchId !== 'all') {
            $lastSyncDate = $branchSyncDates[$activeBranchId] ?? $globalLastSync;
        } else {
            $lastSyncDate = $globalLastSync;
        }

        return Inertia::render('hr/attendance/EsslSyncReport', [
            'logs' => $logs,
            'employees' => $employees,
            'branches' => $branches,
            'categories' => $categories,
            'last_sync_date' => $lastSyncDate,
            'branch_sync_dates' => $branchSyncDates,
            'filters' => array_merge($request->all(['date_from', 'date_to', 'employee_id', 'direction', 'category_id', 'per_page']), [
                'branch_id' => $activeBranchId
            ]),
        ]);
    }

    public function sync(Request $request)
    {
        $from = $request->date_from ?: Carbon::today()->format('Y-m-d');
        $to = $request->date_to ?: Carbon::today()->format('Y-m-d');
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        if ($days > 3) {
            return redirect()->back()->with(
                'error',
                __('Date range is too large for one request (:days days). The browser sync now runs day-by-day automatically — please use Start Sync again.', ['days' => $days])
            );
        }

        try {
            $this->runSyncForDateRange($from, $to, $request->employee_id, $request->branch_id);

            return redirect()->back()->with(
                'success',
                __('Biometric logs synced and attendance processed from ') . Carbon::parse($from)->format('d-m-Y') . __(' to ') . Carbon::parse($to)->format('d-m-Y') . '!'
            );
        } catch (\Exception $e) {
            Log::error('ESSL sync failed', ['from' => $from, 'to' => $to, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', __('Synchronization failed: ') . $e->getMessage());
        }
    }

    public function syncChunk(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'employee_id' => 'nullable',
            'branch_id' => 'nullable',
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $started = microtime(true);

        try {
            $result = $this->runSyncForDateRange($date, $date, $request->employee_id, $request->branch_id);
            $elapsed = round(microtime(true) - $started, 2);

            Log::info('ESSL chunk sync OK', array_merge($result, ['elapsed_sec' => $elapsed]));

            return response()->json([
                'success' => true,
                'date' => $date,
                'elapsed_sec' => $elapsed,
                'processed_count' => $result['processed_count'],
                'new_essl_logs' => $result['new_essl_logs'] ?? 0,
                'message' => __('Day synced successfully'),
            ]);
        } catch (\Exception $e) {
            Log::error('ESSL chunk sync failed', ['date' => $date, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'date' => $date,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function runSyncForDateRange(string $from, string $to, $employeeId = null, $branchId = null): array
    {
        set_time_limit(0);

        $args = [
            '--from' => $from,
            '--to' => $to,
        ];
        if ($employeeId) {
            $args['--employee_id'] = $employeeId;
        }
        if ($branchId && $branchId !== 'all') {
            $args['--branch_id'] = $branchId;
        }

        Artisan::call('essl:sync', $args);

        $esslOutput = Artisan::output();
        $newEsslLogs = 0;
        if (preg_match('/New logs added:\s*(\d+)/', $esslOutput, $matches)) {
            $newEsslLogs = (int) $matches[1];
        }

        $biometricController = app(\App\Http\Controllers\BiometricAttendanceSyncController::class);

        $requestArgs = [
            'from_date' => $from,
            'to_date' => $to,
        ];
        if ($employeeId) {
            $requestArgs['employee_id'] = $employeeId;
        }
        if ($branchId) {
            $requestArgs['branch_id'] = $branchId;
        }

        $result = $biometricController->runSync(new Request($requestArgs));

        if ($branchId && $branchId !== 'all') {
            updateSetting('last_biometric_sync_date_branch_' . $branchId, $to);
        } else {
            updateSetting('last_biometric_sync_date', $to);
        }

        return [
            'processed_count' => $result['processed_count'],
            'new_essl_logs' => $newEsslLogs,
        ];
    }

    public function export(Request $request)
    {
        $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
        $filters = $request->only(['date_from', 'date_to', 'employee_id', 'direction', 'category_id']);
        $filters['branch_id'] = $activeBranchId;
        $fileName = 'essl_logs_' . date('Y_m_d_His') . '.xlsx';

        return Excel::download(new EsslLogExport($filters), $fileName);
    }
}
