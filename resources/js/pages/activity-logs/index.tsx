import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Pagination } from '@/components/ui/pagination';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import {
    Activity,
    MapPin,
    User,
    ShieldAlert,
    CheckCircle2,
    RefreshCw,
    Trash2,
    Filter,
    LogIn,
    LogOut,
    Plus,
    Pencil,
    X,
    Layers,
    FileText,
    Download,
    Calendar,
} from 'lucide-react';

type ActivityLogItem = {
    id: number;
    user_name: string;
    user_role: string;
    module: string;
    action: string;
    description: string;
    branch_name?: string;
    created_at: string;
    when_date?: string;
    when_time?: string;
};

type VisibilityMeta = {
    title: string;
    description: string;
    canFilterByRole: boolean;
    filterRoles: { value: string; label: string }[];
};

const ACTION_CONFIG: Record<string, { label: string; icon: React.ReactNode; className: string }> = {
    created: {
        label: 'Created',
        icon: <Plus className="h-3 w-3" />,
        className: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    },
    updated: {
        label: 'Updated',
        icon: <Pencil className="h-3 w-3" />,
        className: 'bg-blue-50 text-blue-800 border-blue-200',
    },
    deleted: {
        label: 'Deleted',
        icon: <Trash2 className="h-3 w-3" />,
        className: 'bg-rose-50 text-rose-800 border-rose-200',
    },
    synced: {
        label: 'Synced',
        icon: <RefreshCw className="h-3 w-3" />,
        className: 'bg-violet-50 text-violet-800 border-violet-200',
    },
    logged_in: {
        label: 'Login',
        icon: <LogIn className="h-3 w-3" />,
        className: 'bg-teal-50 text-teal-800 border-teal-200',
    },
    logged_out: {
        label: 'Logout',
        icon: <LogOut className="h-3 w-3" />,
        className: 'bg-slate-100 text-slate-700 border-slate-200',
    },
    approved: {
        label: 'Approved',
        icon: <CheckCircle2 className="h-3 w-3" />,
        className: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    },
    rejected: {
        label: 'Rejected',
        icon: <X className="h-3 w-3" />,
        className: 'bg-amber-50 text-amber-800 border-amber-200',
    },
    bulk_updated: {
        label: 'Bulk',
        icon: <Layers className="h-3 w-3" />,
        className: 'bg-indigo-50 text-indigo-800 border-indigo-200',
    },
    generated: {
        label: 'Generated',
        icon: <FileText className="h-3 w-3" />,
        className: 'bg-violet-50 text-violet-800 border-violet-200',
    },
    downloaded: {
        label: 'Downloaded',
        icon: <Download className="h-3 w-3" />,
        className: 'bg-teal-50 text-teal-800 border-teal-200',
    },
};

const ROLE_STYLES: Record<string, string> = {
    company: 'bg-indigo-100 text-indigo-800',
    admin: 'bg-amber-100 text-amber-900',
    manager: 'bg-cyan-100 text-cyan-900',
    staff: 'bg-slate-200 text-slate-800',
};

const MODULE_LABELS: Record<string, string> = {
    MisPunch: 'MisPunch',
    Attendance: 'Attendance',
    'Attendance Sync': 'Attendance Sync',
    BiometricAttendance: 'Attendance',
    AttendanceRegularization: 'MisPunch Request',
    Report: 'Report',
    ReportDownload: 'Report',
};

const MODULE_STYLES: Record<string, string> = {
    MisPunch: 'bg-orange-100 text-orange-900',
    Attendance: 'bg-sky-100 text-sky-900',
    'Attendance Sync': 'bg-violet-100 text-violet-900',
    AttendanceRegularization: 'bg-orange-50 text-orange-800',
    Report: 'bg-violet-100 text-violet-900',
    ReportDownload: 'bg-violet-100 text-violet-900',
};

function formatModuleName(module: string): string {
    if (MODULE_LABELS[module]) {
        return MODULE_LABELS[module];
    }

    return module
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/_/g, ' ')
        .trim();
}

function getModuleStyle(module: string): string {
    return MODULE_STYLES[module] ?? 'bg-slate-100 text-slate-700';
}

function parseFilterDate(value: string): Date | undefined {
    if (!value) {
        return undefined;
    }
    const parts = value.split('-').map(Number);
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        return undefined;
    }
    return new Date(parts[0], parts[1] - 1, parts[2]);
}

function formatFilterDate(date: Date | undefined): string {
    return date ? format(date, 'yyyy-MM-dd') : '';
}

function getActionDisplay(action: string) {
    const key = action?.toLowerCase() ?? '';
    return (
        ACTION_CONFIG[key] ?? {
            label: action?.replace(/_/g, ' ') ?? 'Action',
            icon: <Activity className="h-3 w-3" />,
            className: 'bg-slate-100 text-slate-700 border-slate-200',
        }
    );
}

function parseLogCreatedAt(iso: string | undefined): Date | null {
    if (!iso) {
        return null;
    }

    const trimmed = iso.trim();
    if (trimmed.endsWith('Z') || /[+-]\d{2}:?\d{2}$/.test(trimmed)) {
        const d = new Date(trimmed);
        return Number.isNaN(d.getTime()) ? null : d;
    }

    const normalized = trimmed.includes('T')
        ? trimmed.replace(/\.\d+$/, '')
        : trimmed.replace(' ', 'T').replace(/\.\d+$/, '');

    const d = new Date(`${normalized}Z`);
    return Number.isNaN(d.getTime()) ? null : d;
}

function formatLogWhenLocal(
    log: ActivityLogItem,
    timezone: string
): { date: string; time: string } {
    const tz =
        timezone && timezone !== 'UTC'
            ? timezone
            : window.appSettings?.timezone && window.appSettings.timezone !== 'UTC'
              ? window.appSettings.timezone
              : 'Asia/Kolkata';

    const d = parseLogCreatedAt(log.created_at);
    if (d) {
        const date = new Intl.DateTimeFormat('en-GB', {
            timeZone: tz,
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(d);
        const time = new Intl.DateTimeFormat('en-IN', {
            timeZone: tz,
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }).format(d);

        return { date, time };
    }

    if (log.when_date && log.when_time) {
        return { date: log.when_date, time: log.when_time };
    }

    return { date: '-', time: '-' };
}

export default function ActivityLogs({
    logs,
    filters,
    stats,
    visibility,
    activeBranch,
    displayTimezone = 'Asia/Kolkata',
    timezoneLabel = 'IST',
}: {
    logs: { data: ActivityLogItem[]; links: any; from: number; to: number; total: number };
    filters: { role?: string; from_date?: string; to_date?: string };
    stats: { total: number; today: number; created: number; updated: number; deleted: number };
    visibility: VisibilityMeta;
    activeBranch: string;
    displayTimezone?: string;
    timezoneLabel?: string;
}) {
    const { t } = useTranslation();

    const [filterData, setFilterData] = useState({
        role: filters?.role || 'all',
        from_date: filters?.from_date || '',
        to_date: filters?.to_date || '',
    });

    const applyFilters = () => {
        router.get(route('hr.activity-logs.index'), filterData as Record<string, string>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        const resetData = { role: 'all', from_date: '', to_date: '' };
        setFilterData(resetData);
        router.get(route('hr.activity-logs.index'), resetData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const todayMax = format(new Date(), 'yyyy-MM-dd');

    const handleFromDate = (date: Date | undefined) => {
        const from = formatFilterDate(date);
        setFilterData((prev) => {
            const next = { ...prev, from_date: from };
            if (from && prev.to_date && from > prev.to_date) {
                next.to_date = from;
            }
            return next;
        });
    };

    const handleToDate = (date: Date | undefined) => {
        const to = formatFilterDate(date);
        setFilterData((prev) => {
            const next = { ...prev, to_date: to };
            if (to && prev.from_date && to < prev.from_date) {
                next.from_date = to;
            }
            return next;
        });
    };

    const statItems = [
        { label: t('Total'), value: stats?.total ?? 0, className: 'text-slate-900' },
        { label: t('Today'), value: stats?.today ?? 0, className: 'text-primary' },
        { label: t('Created'), value: stats?.created ?? 0, className: 'text-emerald-600' },
        { label: t('Updated'), value: stats?.updated ?? 0, className: 'text-blue-600' },
        { label: t('Deleted'), value: stats?.deleted ?? 0, className: 'text-rose-600' },
    ];

    return (
        <AppLayout>
            <Head title={t('Activity Logs')} />

            <div className="flex flex-col gap-3 p-3 md:p-4">
                {/* Header + stats — one compact row */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2 min-w-0">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <Activity className="h-4 w-4" />
                        </div>
                        <div className="min-w-0">
                            <h1 className="text-lg font-bold text-slate-900 leading-tight">
                                {t('Activity Logs')}
                            </h1>
                            <span className="inline-flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                <span className="inline-flex items-center gap-1">
                                    <MapPin className="h-3 w-3 text-primary shrink-0" />
                                    {activeBranch}
                                </span>
                                <span className="text-slate-300">·</span>
                                <span>{t('Times in')} {timezoneLabel}</span>
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs">
                        {statItems.map((item, i) => (
                            <span key={item.label} className="inline-flex items-center gap-1.5">
                                {i > 0 && <span className="text-slate-200 hidden sm:inline">|</span>}
                                <span className="text-slate-500">{item.label}</span>
                                <strong className={`tabular-nums ${item.className}`}>{item.value}</strong>
                            </span>
                        ))}
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-slate-200 bg-white p-3">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-5">
                        {visibility?.canFilterByRole && (
                            <div className="space-y-1.5 w-full sm:w-40 shrink-0">
                                <Label className="text-xs font-medium text-slate-600">
                                    {t('Role')}
                                </Label>
                                <Select
                                    value={filterData.role}
                                    onValueChange={(val) => setFilterData({ ...filterData, role: val })}
                                >
                                    <SelectTrigger className="h-9 w-full text-sm border-slate-200">
                                        <SelectValue placeholder={t('All Roles')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(visibility.filterRoles ?? []).map((role) => (
                                            <SelectItem key={role.value} value={role.value}>
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="flex-1 min-w-0">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:max-w-[28rem]">
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600 inline-flex items-center gap-1.5">
                                        <Calendar className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                        {t('From date')}
                                    </Label>
                                    <DatePicker
                                        selected={parseFilterDate(filterData.from_date)}
                                        onSelect={handleFromDate}
                                        max={
                                            filterData.to_date && filterData.to_date < todayMax
                                                ? filterData.to_date
                                                : todayMax
                                        }
                                        placeholder={t('From date')}
                                        inputClassName="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600 inline-flex items-center gap-1.5">
                                        <Calendar className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                        {t('To date')}
                                    </Label>
                                    <DatePicker
                                        selected={parseFilterDate(filterData.to_date)}
                                        onSelect={handleToDate}
                                        min={filterData.from_date || undefined}
                                        max={todayMax}
                                        placeholder={t('To date')}
                                        inputClassName="h-9"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex gap-2 shrink-0 w-full sm:w-auto lg:ml-auto">
                            <Button
                                onClick={applyFilters}
                                className="h-9 flex-1 sm:flex-none gap-1.5 px-4"
                            >
                                <Filter className="h-3.5 w-3.5" />
                                {t('Apply')}
                            </Button>
                            <Button
                                variant="outline"
                                className="h-9 flex-1 sm:flex-none px-4 border-slate-200"
                                onClick={resetFilters}
                            >
                                {t('Reset')}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <th className="px-3 py-2 font-semibold w-[110px]">{t('When')}</th>
                                    <th className="px-3 py-2 font-semibold w-[150px]">{t('Who')}</th>
                                    <th className="px-3 py-2 font-semibold w-[100px]">{t('Action')}</th>
                                    <th className="px-3 py-2 font-semibold w-[110px]">{t('Module')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('What happened')}</th>
                                    <th className="px-3 py-2 font-semibold w-[90px]">{t('Branch')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-3 py-10 text-center">
                                            <ShieldAlert className="mx-auto h-8 w-8 text-slate-300" />
                                            <p className="mt-2 text-sm font-medium text-slate-700">
                                                {t('No activity found')}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {t('Try another date range or role filter.')}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    logs.data.map((log) => {
                                        const actionDisplay = getActionDisplay(log.action);
                                        const roleKey = log.user_role?.toLowerCase() ?? 'staff';
                                        const when = formatLogWhenLocal(log, displayTimezone);

                                        return (
                                            <tr
                                                key={log.id}
                                                className="hover:bg-slate-50/80 align-top"
                                            >
                                                <td className="whitespace-nowrap px-3 py-2">
                                                    <div className="text-xs font-medium text-slate-900">
                                                        {when.date}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500">
                                                        {when.time}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-1.5 min-w-0">
                                                        <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100">
                                                            <User className="h-3 w-3 text-slate-500" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="truncate text-xs font-medium text-slate-900 max-w-[120px]" title={log.user_name}>
                                                                {log.user_name}
                                                            </div>
                                                            <span
                                                                className={`inline-block rounded px-1 py-px text-[9px] font-bold uppercase leading-none ${ROLE_STYLES[roleKey] ?? ROLE_STYLES.staff}`}
                                                            >
                                                                {log.user_role}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={`inline-flex items-center gap-0.5 rounded border px-1.5 py-0.5 text-[10px] font-semibold whitespace-nowrap ${actionDisplay.className}`}
                                                    >
                                                        {actionDisplay.icon}
                                                        {t(actionDisplay.label)}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold whitespace-nowrap ${getModuleStyle(log.module)}`}
                                                    >
                                                        {formatModuleName(log.module)}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <p className="text-xs leading-snug text-slate-700 break-words">
                                                        {log.description}
                                                    </p>
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-2">
                                                    {log.branch_name ? (
                                                        <span className="inline-flex items-center gap-0.5 text-[11px] font-medium text-slate-600">
                                                            <MapPin className="h-3 w-3 text-primary shrink-0" />
                                                            <span className="truncate max-w-[72px]" title={log.branch_name}>
                                                                {log.branch_name}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {logs.links && logs.data.length > 0 && (
                        <div className="border-t border-slate-100 px-3 py-2">
                            <Pagination
                                links={logs.links}
                                from={logs.from}
                                to={logs.to}
                                total={logs.total}
                            />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
