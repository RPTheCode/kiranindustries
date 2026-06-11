<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BiometricAttendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\ReportDownload;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogger
{
    private static int $suppressDepth = 0;

    /** Internal / high-volume models — not shown in audit trail. */
    public const SKIP_MODULES = [
        'ActivityLog',
        'BiometricAttendanceLog',
        'PersonalAccessToken',
        'Setting',
        'Cache',
        'Session',
        'Employee', // Logged explicitly from EmployeeController (clear single entry)
        'BiometricAttendance', // Logged explicitly via logAttendance() with clear module labels
        'ShiftSlot', // Logged as part of Shift master update in ShiftController
        'ShiftDutyRule',
    ];

    /** Human-readable labels for attendance field changes. */
    protected const ATTENDANCE_FIELD_LABELS = [
        'in_time' => 'In time',
        'out_time' => 'Out time',
        'status' => 'Status',
        'shift_code' => 'Shift',
        'base_shift' => 'Base shift',
        'shift_slot_id' => 'Shift slot',
        'duty_value' => 'Duty',
        'total_minutes' => 'Work minutes',
        'punch_count' => 'Punches',
        'in_count' => 'In count',
        'out_count' => 'Out count',
        'late_in' => 'Late in',
        'early_out' => 'Early out',
        'log_details' => 'Punch details',
        'manual_remarks' => 'Remarks',
        'remarks' => 'Remarks',
        'is_manual' => 'Manual entry',
    ];

    /** Logged via LogsMasterCrud in master controllers. */
    public const MASTER_MODULES = [
        'Branch',
        'WeekOff',
        'Shift',
        'Department',
        'Designation',
        'Skill',
        'Section',
        'Category',
        'BankMaster',
        'ResignReason',
        'Overtime',
        'MaterialItem',
        'DocumentType',
        'SalaryComponent',
    ];

    public static function withoutLogging(callable $callback): mixed
    {
        self::$suppressDepth++;

        try {
            return $callback();
        } finally {
            self::$suppressDepth--;
        }
    }

    public static function isSuppressed(): bool
    {
        return self::$suppressDepth > 0;
    }

    public static function logModel(Model $model, string $action): void
    {
        if (self::isSuppressed() || ! Auth::check()) {
            return;
        }

        $module = class_basename($model);

        if (in_array($module, self::SKIP_MODULES, true)) {
            return;
        }

        // Employee user updates are logged via the Employee model / controller.
        if ($module === 'User') {
            if (($model->type ?? null) === 'employee') {
                return;
            }

            $changes = collect(method_exists($model, 'getChanges') ? $model->getChanges() : [])
                ->except(['updated_at', 'remember_token', 'password'])
                ->keys();

            if ($changes->isNotEmpty() && $changes->every(
                fn ($key) => in_array($key, ['last_active_branch_id'], true)
            )) {
                return;
            }
        }

        // Masters logged explicitly from controllers (avoids duplicate / missed entries).
        if (in_array($module, self::MASTER_MODULES, true)) {
            return;
        }

        if ($module === 'ReportDownload') {
            self::handleReportDownloadActivity($model, $action);

            return;
        }

        self::write(
            $module,
            $action,
            self::buildModelDescription($model, $action),
            self::resolveBranchIdForModel($model)
        );
    }

    /**
     * Clear activity log for report queue / download history (not generic "Record #id").
     */
    public static function logReportDownload(ReportDownload $download, string $event = 'requested'): void
    {
        if (self::isSuppressed()) {
            return;
        }

        $user = Auth::user() ?? ($download->user_id ? User::find($download->user_id) : null);
        if (! $user) {
            return;
        }

        $reportName = trim((string) ($download->report_name ?? 'Report'));

        $description = match ($event) {
            'requested' => "Started generating report in background: {$reportName}",
            'completed' => "Report ready to download: {$reportName}",
            'generated' => "Generated report PDF: {$reportName}",
            'failed' => "Report generation failed: {$reportName}",
            'deleted' => "Removed report from download history: {$reportName}",
            'downloaded' => "Downloaded report file: {$reportName}",
            default => "Report activity: {$reportName}",
        };

        $logAction = match ($event) {
            'downloaded' => 'downloaded',
            'deleted' => 'deleted',
            'failed' => 'rejected',
            default => 'generated',
        };

        $branchId = session('active_branch_id');
        $branchIdForLog = ($branchId && $branchId !== 'all') ? (int) $branchId : null;

        self::write('Report', $logAction, $description, $branchIdForLog, $user);
    }

    protected static function handleReportDownloadActivity(Model $model, string $action): void
    {
        if (! $model instanceof ReportDownload) {
            return;
        }

        if ($action === 'created') {
            $event = ($model->status ?? '') === 'completed' ? 'generated' : 'requested';
            self::logReportDownload($model, $event);

            return;
        }

        if ($action === 'updated') {
            $changes = collect(method_exists($model, 'getChanges') ? $model->getChanges() : []);
            $newStatus = $changes->get('status');

            if ($newStatus === 'completed') {
                self::logReportDownload($model, 'completed');
            } elseif ($newStatus === 'failed') {
                self::logReportDownload($model, 'failed');
            }

            return;
        }

        if ($action === 'deleted') {
            self::logReportDownload($model, 'deleted');
        }
    }

    /** Explicit master CRUD logging (bypasses MASTER_MODULES skip in logModel). */
    public static function logMaster(Model $model, string $action): void
    {
        if (self::isSuppressed() || ! Auth::check()) {
            return;
        }

        $module = class_basename($model);

        if (in_array($module, self::SKIP_MODULES, true)) {
            return;
        }

        self::write(
            $module,
            $action,
            self::buildModelDescription($model, $action),
            self::resolveBranchIdForModel($model)
        );
    }

    /**
     * Log employee create / update / delete (explicit, always one clear entry).
     */
    public static function logEmployee(Employee $employee, string $action): void
    {
        if (self::isSuppressed() || ! Auth::check()) {
            return;
        }

        $employee->loadMissing('user');
        $actor = Auth::user()->name ?? 'User';
        $name = self::resolveEmployeeName($employee);
        $code = $employee->employee_id ?? $employee->emy_code;
        $label = $code ? "{$name} (Code: {$code})" : $name;

        $description = match ($action) {
            'created' => "{$actor} created new employee: {$label}",
            'updated' => "{$actor} updated employee profile: {$label}",
            'deleted' => "{$actor} deleted employee: {$label}",
            default => "{$actor} {$action} employee: {$label}",
        };

        self::write('Employee', $action, $description, $employee->branch_id);
    }

    public static function canAccess($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (in_array($user->type, ['company', 'admin', 'manager', 'staff'], true)) {
            return true;
        }

        return $user->can('view-activity-logs');
    }

    public static function log(string $module, string $action, string $description, ?int $branchIdOverride = null): void
    {
        if (self::isSuppressed() || ! Auth::check()) {
            return;
        }

        self::write($module, $action, $description, $branchIdOverride);
    }

    /**
     * Log a manual attendance / mispunch correction (one clear entry per save).
     *
     * @param  'mispunch'|'attendance'|'sync_engine'  $source
     */
    public static function logAttendance(BiometricAttendance $record, string $action, string $source = 'attendance'): void
    {
        if (self::isSuppressed() || ! Auth::check()) {
            return;
        }

        $module = match ($source) {
            'mispunch' => 'MisPunch',
            'sync_engine' => 'Attendance Sync',
            default => 'Attendance',
        };

        self::write(
            $module,
            $action,
            self::buildAttendanceDescription($record, $action, $source),
            $record->branch_id ? (int) $record->branch_id : null
        );
    }

    /**
     * Role-based visibility (final spec).
     * Company → company, admin, manager, staff
     * Admin   → admin, manager, staff (not company)
     * Manager → own only
     * Staff   → own only
     */
    /** Company timezone for display; defaults to India (IST) when unset or UTC. */
    public static function displayTimezone(): string
    {
        $tz = data_get(settings(), 'defaultTimezone', 'Asia/Kolkata');

        if (! $tz || $tz === 'UTC') {
            return 'Asia/Kolkata';
        }

        return $tz;
    }

    /** @return array{when_date: string, when_time: string} */
    public static function formatLogTimestamp($value, ?string $rawCreatedAt = null): array
    {
        $tz = self::displayTimezone();
        $raw = $rawCreatedAt;

        if ($raw === null && $value instanceof \Carbon\CarbonInterface) {
            $raw = $value->format('Y-m-d H:i:s');
        } elseif ($raw === null) {
            $raw = (string) $value;
        }

        $raw = substr(str_replace('T', ' ', preg_replace('/\.\d+Z?$/', '', (string) $raw)), 0, 19);
        $dt = \Carbon\Carbon::parse($raw, 'UTC')->setTimezone($tz);

        return [
            'when_date' => $dt->format('d M Y'),
            'when_time' => $dt->format('h:i A'),
        ];
    }

    public static function scopeTodayInAppTimezone(Builder $query, string $column = 'created_at'): void
    {
        $tz = self::displayTimezone();
        $start = \Carbon\Carbon::now($tz)->startOfDay()->utc();
        $end = \Carbon\Carbon::now($tz)->endOfDay()->utc();

        $query->whereBetween($column, [$start, $end]);
    }

    protected static function filterDateStartUtc(string $date): \Carbon\Carbon
    {
        $parsed = \Carbon\Carbon::parse($date, self::displayTimezone())->startOfDay();

        if ($parsed->isFuture()) {
            $parsed = \Carbon\Carbon::now(self::displayTimezone())->startOfDay();
        }

        return $parsed->utc();
    }

    protected static function filterDateEndUtc(string $date): \Carbon\Carbon
    {
        $parsed = \Carbon\Carbon::parse($date, self::displayTimezone())->endOfDay();

        if ($parsed->isFuture()) {
            $parsed = \Carbon\Carbon::now(self::displayTimezone())->endOfDay();
        }

        return $parsed->utc();
    }

    public static function queryForCurrentUser(): Builder
    {
        $user = Auth::user();
        $query = ActivityLog::with(['user', 'branch'])->latest();

        if ($user && ! $user->isSuperAdmin()) {
            $query->whereIn('user_id', getCompanyAndUsersId());
        }

        self::applyBranchScope($query, $user);
        self::applyRoleVisibilityScope($query, $user);

        return $query;
    }

    public static function applyRequestFilters(Builder $query, Request $request): Builder
    {
        $user = Auth::user();

        if ($request->filled('role') && $request->role !== 'all') {
            $allowed = self::allowedFilterRoles($user);
            if (in_array($request->role, $allowed, true)) {
                $query->where('user_role', $request->role);
            }
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', self::filterDateStartUtc($request->from_date));
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', self::filterDateEndUtc($request->to_date));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('branch_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public static function allowedFilterRoles($user): array
    {
        if (! $user) {
            return [];
        }

        return match ($user->type) {
            'company', 'superadmin', 'super admin' => ['company', 'admin', 'manager', 'staff'],
            'admin' => ['admin', 'manager', 'staff'],
            default => [],
        };
    }

    public static function visibilityMetaFor($user): array
    {
        if (! $user) {
            return [
                'title' => '',
                'description' => '',
                'canFilterByRole' => false,
                'filterRoles' => [],
            ];
        }

        if ($user->isSuperAdmin() || $user->type === 'company') {
            return [
                'title' => __('Full company audit'),
                'description' => __('You can view activity performed by Company, Admin, Manager, and Staff users across your organization.'),
                'canFilterByRole' => true,
                'filterRoles' => [
                    ['value' => 'all', 'label' => __('All Roles')],
                    ['value' => 'company', 'label' => __('Company')],
                    ['value' => 'admin', 'label' => __('Admin')],
                    ['value' => 'manager', 'label' => __('Manager')],
                    ['value' => 'staff', 'label' => __('Staff')],
                ],
            ];
        }

        if ($user->type === 'admin') {
            return [
                'title' => __('Admin audit view'),
                'description' => __('You can view activity from Admin, Manager, and Staff users. Company-level logs are hidden.'),
                'canFilterByRole' => true,
                'filterRoles' => [
                    ['value' => 'all', 'label' => __('All Roles')],
                    ['value' => 'admin', 'label' => __('Admin')],
                    ['value' => 'manager', 'label' => __('Manager')],
                    ['value' => 'staff', 'label' => __('Staff')],
                ],
            ];
        }

        return [
            'title' => __('My activity'),
            'description' => __('You can view only actions performed by you (create, update, delete, and other operations).'),
            'canFilterByRole' => false,
            'filterRoles' => [],
        ];
    }

    protected static function applyBranchScope(Builder $query, $user): void
    {
        $activeBranchId = session('active_branch_id');

        if ($activeBranchId && $activeBranchId !== 'all') {
            $query->where(function (Builder $q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId)->orWhereNull('branch_id');
            });

            return;
        }

        if ($user && ! in_array($user->type, ['company', 'admin', 'superadmin', 'super admin'], true)) {
            $allowedBranchIds = $user->branches()->pluck('branches.id')->toArray();
            if (! empty($allowedBranchIds)) {
                $query->where(function (Builder $q) use ($allowedBranchIds) {
                    $q->whereIn('branch_id', $allowedBranchIds)->orWhereNull('branch_id');
                });
            }
        }
    }

    protected static function applyRoleVisibilityScope(Builder $query, $user): void
    {
        if (! $user || $user->isSuperAdmin() || $user->type === 'company') {
            return;
        }

        if ($user->type === 'admin') {
            $query->whereIn('user_role', ['admin', 'manager', 'staff']);

            return;
        }

        // Manager & Staff: only own logs
        $query->where('user_id', $user->id);
    }

    protected static function buildModelDescription(Model $model, string $action): string
    {
        $userName = Auth::user()->name ?? 'User';
        $module = class_basename($model);
        $moduleLabel = self::humanModuleName($module);
        $recordLabel = self::resolveRecordLabel($model);

        return match ($action) {
            'created' => "{$userName} created {$moduleLabel}: {$recordLabel}",
            'updated' => self::buildUpdatedDescription($model, $userName, $moduleLabel, $recordLabel),
            'deleted' => "{$userName} deleted {$moduleLabel}: {$recordLabel}",
            default => "{$userName} ".self::humanActionName($action)." {$moduleLabel}: {$recordLabel}",
        };
    }

    protected static function buildUpdatedDescription(Model $model, string $userName, string $moduleLabel, string $recordLabel): string
    {
        $changes = collect(method_exists($model, 'getChanges') ? $model->getChanges() : [])
            ->except(['updated_at', 'created_at', 'password', 'remember_token']);

        $base = "{$userName} updated {$moduleLabel}: {$recordLabel}";

        if ($changes->isEmpty()) {
            return $base;
        }

        $fields = $changes->keys()
            ->map(fn ($key) => Str::headline($key))
            ->take(5)
            ->implode(', ');

        return "{$base} — Changed: {$fields}";
    }

    public static function humanModuleName(string $module): string
    {
        $map = [
            'BiometricAttendance' => 'attendance record',
            'Attendance' => 'attendance',
            'MisPunch' => 'mispunch correction',
            'Attendance Sync' => 'attendance sync',
            'AttendanceRecord' => 'attendance record',
            'AttendanceRegularization' => 'MisPunch request',
            'LeaveApplication' => 'leave application',
            'LeaveBalance' => 'leave balance',
            'LeaveType' => 'leave type',
            'LeavePolicy' => 'leave policy',
            'Employee' => 'employee profile',
            'EmployeeSalary' => 'employee salary',
            'EmployeeAdvance' => 'employee advance',
            'User' => 'system user',
            'Role' => 'role',
            'Branch' => 'branch',
            'Department' => 'department',
            'Designation' => 'designation',
            'Shift' => 'shift',
            'Category' => 'category',
            'Section' => 'section',
            'Skill' => 'skill',
            'PayrollRun' => 'payroll run',
            'Payslip' => 'payslip',
            'Holiday' => 'holiday',
            'WeekOff' => 'week off',
            'BankMaster' => 'bank master',
            'ResignReason' => 'resign reason',
            'Overtime' => 'overtime rule',
            'MaterialItem' => 'material item',
            'DocumentType' => 'document type',
            'SalaryComponent' => 'salary component',
            'MonthlyIncentiveEntry' => 'earnings/deduction entry',
            'DailyProductionAttendanceEntry' => 'production entry',
            'Authentication' => 'authentication',
            'Report' => 'report',
            'ReportDownload' => 'report',
        ];

        return $map[$module] ?? Str::headline(preg_replace('/(?<!^)[A-Z]/', ' $0', $module) ?: $module);
    }

    public static function humanActionName(string $action): string
    {
        return match (strtolower($action)) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'synced' => 'synced',
            'logged_in' => 'logged in',
            'logged_out' => 'logged out',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'bulk_updated' => 'bulk updated',
            'downloaded' => 'downloaded',
            'generated' => 'generated',
            default => strtolower($action),
        };
    }

    protected static function write(string $module, string $action, string $description, ?int $branchIdOverride = null, ?User $userOverride = null): void
    {
        $user = $userOverride ?? Auth::user();

        if (! $user) {
            return;
        }

        if ($branchIdOverride) {
            $branch = Branch::find($branchIdOverride);
            $branchId = $branchIdOverride;
            $branchName = $branch?->name ?? 'All Branches';
        } else {
            [$branchId, $branchName] = self::resolveBranchContext($user);
        }

        ActivityLog::withoutEvents(function () use ($user, $module, $action, $description, $branchId, $branchName) {
            ActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name ?? 'System',
                'user_role' => $user->type ?? $user->roles()->first()?->name ?? 'System',
                'branch_id' => $branchId,
                'branch_name' => $branchName,
                'module' => $module,
                'action' => $action,
                'description' => $description,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    protected static function resolveBranchIdForModel(Model $model): ?int
    {
        // Branch master edits: use sidebar context so logs match the active branch filter.
        if (class_basename($model) === 'Branch') {
            $active = session('active_branch_id');

            if ($active && $active !== 'all') {
                return (int) $active;
            }

            return null;
        }

        $branchId = $model->getAttribute('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    protected static function resolveBranchContext($user): array
    {
        $activeBranchId = session('active_branch_id');

        if ($activeBranchId === 'all' || ! $activeBranchId) {
            if (in_array($user->type ?? '', ['company', 'admin'], true)) {
                return [null, 'All Branches'];
            }

            $firstBranch = $user->branches()->first();

            return [$firstBranch?->id, $firstBranch?->name ?? 'All Branches'];
        }

        $branch = Branch::find($activeBranchId);

        return [$activeBranchId, $branch?->name ?? 'All Branches'];
    }

    public static function resolveRecordLabel(Model $model): string
    {
        $module = class_basename($model);

        if (in_array($module, ['BiometricAttendance', 'AttendanceRecord'], true)) {
            if ($module === 'AttendanceRecord') {
                $model->loadMissing('employee');
            } else {
                $model->loadMissing('employee.user');
            }

            $dateField = $module === 'AttendanceRecord' ? $model->date : $model->attendance_date;
            $date = $dateField
                ? \Carbon\Carbon::parse($dateField)->format('d M Y')
                : '';

            return self::resolveEmployeeName($model->employee).($date ? " on {$date}" : '');
        }

        if (in_array($module, ['EmployeeSalary', 'EmployeeAdvance', 'LeaveApplication', 'LeaveBalance'], true)) {
            return self::resolveEmployeeRelatedLabel($model);
        }

        if ($module === 'Employee') {
            $model->loadMissing('user');

            return self::resolveEmployeeName($model);
        }

        if ($module === 'User') {
            return $model->name ?? $model->email ?? ('ID '.$model->id);
        }

        if ($module === 'Shift') {
            $name = trim((string) ($model->name ?? ''));
            $code = trim((string) ($model->short_code ?? ''));

            if ($name && $code) {
                return "{$name} (Code: {$code})";
            }

            return $name ?: $code ?: 'Record #'.$model->id;
        }

        if ($module === 'Branch') {
            return (string) ($model->name ?? 'Record #'.$model->id);
        }

        foreach (['name', 'title', 'label', 'bank_name', 'short_code', 'code', 'employee_id'] as $field) {
            if (! empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return 'Record #'.$model->id;
    }

    protected static function buildAttendanceDescription(BiometricAttendance $record, string $action, string $source): string
    {
        $record->loadMissing('employee.user');

        $employeeName = self::resolveEmployeeName($record->employee);
        $code = $record->employee?->emy_code ?? $record->employee_code;
        $who = $code ? "{$employeeName} ({$code})" : $employeeName;

        $date = $record->attendance_date
            ? \Carbon\Carbon::parse($record->attendance_date)->format('d M Y')
            : '';

        $verb = match ($source) {
            'mispunch' => 'Corrected mispunch for',
            'sync_engine' => 'Corrected attendance in Sync Engine for',
            default => 'Updated attendance for',
        };

        if ($action === 'created') {
            $verb = match ($source) {
                'mispunch' => 'Added mispunch correction for',
                'sync_engine' => 'Added attendance in Sync Engine for',
                default => 'Added attendance for',
            };
        }

        $summary = self::formatAttendanceChangeSummary($record);

        $line = trim("{$verb} {$who}".($date ? " on {$date}" : ''));

        return $summary ? "{$line} — {$summary}" : $line;
    }

    protected static function formatAttendanceChangeSummary(BiometricAttendance $record): string
    {
        $changes = collect(method_exists($record, 'getChanges') ? $record->getChanges() : [])
            ->except(['updated_at', 'created_at', 'employee_id', 'employee_code', 'department_id', 'category_id', 'section_id', 'branch_id']);

        if ($changes->isEmpty()) {
            return self::formatAttendanceSnapshot($record);
        }

        return $changes
            ->map(fn ($value, $key) => self::formatAttendanceFieldLine((string) $key, $value, $record))
            ->filter()
            ->take(6)
            ->implode(' · ');
    }

    protected static function formatAttendanceSnapshot(BiometricAttendance $record): string
    {
        $parts = [];

        if ($record->in_time) {
            $parts[] = 'In: '.\Carbon\Carbon::parse($record->in_time)->format('h:i A');
        }
        if ($record->out_time) {
            $parts[] = 'Out: '.\Carbon\Carbon::parse($record->out_time)->format('h:i A');
        }
        if ($record->status) {
            $parts[] = 'Status: '.self::humanAttendanceStatus((string) $record->status);
        }
        if ($record->duty_value !== null && $record->duty_value !== '') {
            $parts[] = 'Duty: '.self::humanDutyValue($record->duty_value);
        }

        return implode(' · ', $parts);
    }

    protected static function formatAttendanceFieldLine(string $key, mixed $value, BiometricAttendance $record): ?string
    {
        $label = self::ATTENDANCE_FIELD_LABELS[$key] ?? Str::headline($key);

        if (in_array($key, ['in_time', 'out_time'], true)) {
            if (! $value) {
                return "{$label}: cleared";
            }

            return "{$label}: ".\Carbon\Carbon::parse($value)->format('h:i A');
        }

        if ($key === 'status') {
            return "{$label}: ".self::humanAttendanceStatus((string) $value);
        }

        if ($key === 'duty_value') {
            return "{$label}: ".self::humanDutyValue($value);
        }

        if ($key === 'log_details') {
            $short = Str::limit((string) $value, 60);

            return $short ? "{$label}: {$short}" : null;
        }

        if (is_bool($value)) {
            return "{$label}: ".($value ? 'Yes' : 'No');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return "{$label}: {$value}";
    }

    public static function humanAttendanceStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'P' => 'Present',
            'A' => 'Absent',
            'MIS' => 'MisPunch',
            'HD' => 'Half Day',
            'WO' => 'Week Off',
            'H' => 'Holiday',
            default => $status,
        };
    }

    protected static function humanDutyValue(mixed $duty): string
    {
        $d = (float) $duty;

        return match (true) {
            $d >= 1.0 => 'Full day',
            $d >= 0.5 => 'Half day',
            $d > 0 => (string) $duty,
            default => 'None',
        };
    }

    protected static function resolveEmployeeRelatedLabel(Model $model): string
    {
        if (! method_exists($model, 'employee')) {
            return 'Unknown employee';
        }

        $model->loadMissing('employee');
        $related = $model->employee;

        if ($related instanceof Employee) {
            return self::resolveEmployeeName($related);
        }

        if ($related instanceof User) {
            $related->loadMissing('employee');

            if ($related->employee) {
                return self::resolveEmployeeName($related->employee);
            }

            return $related->name ?? $related->email ?? 'Unknown employee';
        }

        return 'Unknown employee';
    }

    protected static function resolveEmployeeName(Employee|\App\Models\User|null $employee): string
    {
        if (! $employee) {
            return 'Unknown employee';
        }

        if ($employee instanceof \App\Models\User) {
            return $employee->name ?? $employee->email ?? 'Unknown employee';
        }

        $employee->loadMissing('user');

        return $employee->user?->name
            ?? ($employee->employee_id ? "Employee #{$employee->employee_id}" : null)
            ?? ($employee->emy_code ? "Code {$employee->emy_code}" : null)
            ?? 'Unknown employee';
    }
}
