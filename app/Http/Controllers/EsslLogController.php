<?php

namespace App\Http\Controllers;

use App\Models\EsslLog;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\EsslLogExport;
use App\Services\EsslAutoSyncConfig;
use App\Services\EsslSyncOrchestrator;
use Maatwebsite\Excel\Facades\Excel;

class EsslLogController extends Controller
{
    use \App\Traits\LogsActivity;

    public function __construct(private EsslSyncOrchestrator $syncOrchestrator) {}

    public function index(Request $request)
    {
        $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
        $perPage = min(max((int) ($request->per_page ?? 25), 10), 50);
        $dates = $this->resolveLogDateRange($request);
        $dateFrom = $dates['date_from'];
        $dateTo = $dates['date_to'];
        $activeMonth = $dates['month'];

        $query = EsslLog::query()
            ->select([
                'essl_logs.id',
                'essl_logs.device_log_id',
                'essl_logs.user_id',
                'essl_logs.log_date',
                'essl_logs.direction',
                'essl_logs.device_id',
                'essl_logs.body_temperature',
                'essl_logs.is_mask_on',
            ])
            ->join('users', 'essl_logs.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->where('essl_logs.log_date', '>=', $dateFrom . ' 00:00:00')
            ->where('essl_logs.log_date', '<=', $dateTo . ' 23:59:59')
            ->with([
                'user:id,name',
                'user.employee' => function ($q) {
                    $q->withoutGlobalScopes()->select('id', 'user_id', 'employee_id', 'emy_code');
                },
            ]);

        $needsEmployeeJoin = ($activeBranchId && $activeBranchId !== 'all') ||
            ($request->filled('category_id') && $request->category_id !== 'all');

        if ($needsEmployeeJoin) {
            $query->join('employees as emp_filter', 'users.id', '=', 'emp_filter.user_id');

            if ($activeBranchId && $activeBranchId !== 'all') {
                $query->where('emp_filter.branch_id', $activeBranchId);
            }

            if ($request->filled('category_id') && $request->category_id !== 'all') {
                $query->where('emp_filter.category_id', $request->category_id);
            }
        }

        if ($request->filled('employee_id') && $request->employee_id !== 'all') {
            $query->where('essl_logs.user_id', $request->employee_id);
        }

        if ($request->filled('direction') && $request->direction !== 'all') {
            $direction = strtolower($request->direction);
            if ($direction === 'in' || $direction === '0') {
                $query->whereIn('essl_logs.direction', ['in', '0']);
            } elseif ($direction === 'out' || $direction === '1') {
                $query->whereIn('essl_logs.direction', ['out', '1']);
            } else {
                $query->where('essl_logs.direction', $request->direction);
            }
        }

        $logs = $query->orderByDesc('essl_logs.log_date')->paginate($perPage)->withQueryString();

        $employees = User::query()
            ->join('employees', 'users.id', '=', 'employees.user_id')
            ->where('users.type', 'employee')
            ->where('users.status', 'active')
            ->whereIn('users.created_by', getCompanyAndUsersId())
            ->when($activeBranchId && $activeBranchId !== 'all', fn ($q) => $q->where('employees.branch_id', $activeBranchId))
            ->orderBy('users.name')
            ->select('users.id', 'users.name', 'employees.employee_id', 'employees.emy_code')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'emp_code' => $user->employee_id ?? $user->emy_code ?? '',
            ]);

        $categoriesQuery = \App\Models\Category::withoutGlobalScopes()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active');
        if ($activeBranchId && $activeBranchId !== 'all') {
            $categoriesQuery->where(function ($q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId)->orWhereNull('branch_id');
            });
        }
        $categories = $categoriesQuery->orderBy('name')->get(['id', 'name']);

        $branchSyncDates = $this->resolveBranchSyncDates();
        $globalLastSync = $branchSyncDates['all'] ?? getSetting('last_biometric_sync_date');
        $lastSyncDate = ($activeBranchId && $activeBranchId !== 'all')
            ? ($branchSyncDates[$activeBranchId] ?? $globalLastSync)
            : $globalLastSync;

        return Inertia::render('hr/attendance/EsslSyncReport', [
            'logs' => $logs,
            'employees' => $employees,
            'categories' => $categories,
            'last_sync_date' => $lastSyncDate,
            'branch_sync_dates' => $branchSyncDates,
            'auto_sync_settings' => $this->getAutoSyncSettings(),
            'filters' => [
                'month' => $activeMonth,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'employee_id' => $request->get('employee_id', 'all'),
                'direction' => $request->get('direction', 'all'),
                'category_id' => $request->get('category_id', 'all'),
                'branch_id' => $activeBranchId,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Month-wise default; explicit day range capped at 31 days for DB load. */
    protected function resolveLogDateRange(Request $request): array
    {
        $today = Carbon::today();

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->date_from)->startOfDay();
            $to = Carbon::parse($request->date_to)->startOfDay();
            if ($to->gt($today)) {
                $to = $today->copy();
            }
            if ($from->gt($to)) {
                $from = $to->copy();
            }
            if ($from->diffInDays($to) > 31) {
                $to = $from->copy()->addDays(31);
                if ($to->gt($today)) {
                    $to = $today->copy();
                }
            }

            return [
                'month' => $from->format('Y-m'),
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ];
        }

        $monthKey = $request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->month)
            ? $request->month
            : $today->format('Y-m');

        $monthStart = Carbon::parse($monthKey . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        if ($monthEnd->gt($today)) {
            $monthEnd = $today->copy();
        }

        return [
            'month' => $monthKey,
            'date_from' => $monthStart->format('Y-m-d'),
            'date_to' => $monthEnd->format('Y-m-d'),
        ];
    }

    /** Last sync from settings only — no MAX() scan on essl_logs. */
    protected function resolveBranchSyncDates(): array
    {
        $branches = Branch::whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->get(['id']);

        $dates = [];
        foreach ($branches as $branch) {
            $dates[$branch->id] = getSetting('last_biometric_sync_date_branch_' . $branch->id)
                ?: getSetting('last_biometric_sync_date');
        }

        $dates['all'] = getSetting('last_biometric_sync_date');

        return $dates;
    }

    public function updateAutoSyncSettings(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'ranges' => 'required|array|min:1|max:' . EsslAutoSyncConfig::MAX_RANGES,
            'ranges.*.label' => 'required|string|max:50',
            'ranges.*.from' => 'required|date_format:H:i',
            'ranges.*.to' => 'required|date_format:H:i',
            'ranges.*.interval_minutes' => 'required|integer|min:5|max:60',
        ]);

        $ranges = EsslAutoSyncConfig::normalizeRanges($request->ranges);

        if (count($ranges) === 0) {
            return redirect()->back()->withErrors(['ranges' => __('Each range must have end time after start time.')]);
        }

        updateSetting('essl_auto_sync_enabled', $request->boolean('enabled') ? '1' : '0');
        updateSetting('essl_auto_sync_ranges', json_encode($ranges));

        return redirect()->back()->with('success', __('Automatic sync settings saved.'));
    }

    protected function getAutoSyncSettings(): array
    {
        return EsslAutoSyncConfig::settingsPayload();
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
            $this->syncOrchestrator->runSyncForDateRange($from, $to, $request->employee_id, $request->branch_id);

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
            $result = $this->syncOrchestrator->runSyncForDateRange($date, $date, $request->employee_id, $request->branch_id);
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

    public function export(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $from = Carbon::parse($request->date_from);
        $to = Carbon::parse($request->date_to);
        if ($from->diffInDays($to) > 31) {
            return redirect()->back()->with('error', __('Export is limited to 31 days. Please narrow the date range.'));
        }

        $activeBranchId = $request->get('branch_id') ?? session('active_branch_id');
        $filters = $request->only(['date_from', 'date_to', 'employee_id', 'direction', 'category_id']);
        $filters['branch_id'] = $activeBranchId;
        $fileName = 'essl_logs_' . date('Y_m_d_His') . '.xlsx';

        return Excel::download(new EsslLogExport($filters), $fileName);
    }
}
