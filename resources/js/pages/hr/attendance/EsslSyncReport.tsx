import React, { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Combobox } from '@/components/ui/combobox';
import {
    Search,
    RefreshCw,
    Download,
    ArrowRightLeft,
    MapPin,
    Thermometer,
    Clock,
    X,
    Settings2,
    Plus,
    Trash2,
    ChevronDown,
} from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Pagination } from '@/components/ui/pagination';
import { useTranslation } from 'react-i18next';
import { format, eachDayOfInterval, parseISO, isAfter, startOfDay, subMonths } from 'date-fns';
import { toast } from '@/components/custom-toast';
import axios from 'axios';
import { cn } from '@/lib/utils';
import {
    canExportEsslSync,
    canManageEsslAutoSync,
    canManualEsslSync,
} from '@/utils/authorization';

interface EsslLog {
    id: number;
    device_log_id: string;
    user_id: number;
    log_date: string;
    direction: string | number;
    device_id: string;
    body_temperature: string | null;
    is_mask_on: boolean | null;
    user?: {
        name: string;
        employee?: {
            employee_id: string;
            emy_code: string;
        };
    };
}

interface Props {
    logs: {
        data: EsslLog[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    employees: { id: number; name: string; emp_code?: string }[];
    branches: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    last_sync_date: string | null;
    branch_sync_dates?: Record<string | number, string | null>;
    auto_sync_settings?: {
        enabled: boolean;
        ranges: { label: string; from: string; to: string; interval_minutes: number }[];
        last_run_at?: string | null;
        last_run_slot?: string | null;
        timezone?: string;
        timezone_label?: string;
        current_time?: string;
        active_range?: string | null;
        scheduler_running?: boolean;
        scheduler_last_ping?: string | null;
    };
    filters: any;
}

type AutoSyncRange = { label: string; from: string; to: string; interval_minutes: number };

const MAX_AUTO_RANGES = 4;
const DEFAULT_RANGE_INTERVAL = 15;

const defaultAutoRanges = (): AutoSyncRange[] => [
    { label: 'Morning IN', from: '07:00', to: '10:00', interval_minutes: DEFAULT_RANGE_INTERVAL },
    { label: 'Evening OUT', from: '18:00', to: '20:00', interval_minutes: DEFAULT_RANGE_INTERVAL },
];

type SyncProgressState = {
    current: number;
    total: number;
    date: string;
    lastDaySec: number;
    elapsedTotalSec: number;
    processedCount: number;
    newEsslLogs: number;
};

function formatDuration(secs: number): string {
    if (secs <= 0) return '0s';
    if (secs < 60) return `${Math.round(secs)}s`;
    const m = Math.floor(secs / 60);
    const s = Math.round(secs % 60);
    return s > 0 ? `${m}m ${s}s` : `${m}m`;
}

function empCode(log: EsslLog): string {
    return log.user?.employee?.employee_id || log.user?.employee?.emy_code || '—';
}

function SyncAccordion({
    title,
    icon,
    open,
    onToggle,
    badge,
    headerRight,
    summary,
    variant = 'default',
    children,
}: {
    title: string;
    icon: React.ReactNode;
    open: boolean;
    onToggle: () => void;
    badge?: React.ReactNode;
    headerRight?: React.ReactNode;
    summary?: React.ReactNode;
    variant?: 'default' | 'primary';
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'rounded-lg border overflow-hidden transition-colors self-start w-full',
                variant === 'primary' ? 'border-primary/20 bg-primary/[0.03]' : 'border-slate-200/80 bg-white',
                open && variant === 'default' && 'ring-1 ring-slate-200/60',
                open && variant === 'primary' && 'ring-1 ring-primary/15',
            )}
        >
            <div className="flex items-center gap-2 px-2.5 py-2 min-h-[40px]">
                <button
                    type="button"
                    onClick={onToggle}
                    className="flex flex-1 items-center gap-1.5 text-left min-w-0"
                >
                    <span className="text-primary shrink-0">{icon}</span>
                    <span className="text-xs font-semibold text-slate-700 truncate">{title}</span>
                    {badge}
                </button>
                {headerRight}
                <button
                    type="button"
                    onClick={onToggle}
                    className="shrink-0 p-0.5 rounded hover:bg-slate-100"
                    aria-expanded={open}
                    aria-label={open ? 'Collapse' : 'Expand'}
                >
                    <ChevronDown
                        className={cn('h-4 w-4 text-slate-400 transition-transform duration-200', open && 'rotate-180')}
                    />
                </button>
            </div>
            {!open && summary && (
                <button
                    type="button"
                    onClick={onToggle}
                    className="w-full px-2.5 pb-2 pt-0 text-left border-t border-slate-100/80 hover:bg-slate-50/50 transition-colors"
                >
                    {summary}
                </button>
            )}
            {open && <div className="px-2.5 pb-2.5 pt-0 border-t border-slate-100/80">{children}</div>}
        </div>
    );
}

const EsslSyncReport = ({
    logs,
    employees,
    categories,
    last_sync_date,
    branch_sync_dates,
    auto_sync_settings,
    filters,
}: Props) => {
    const { t } = useTranslation();
    const { active_branch_id, auth } = usePage().props as {
        active_branch_id?: number | string;
        auth?: { permissions?: string[] };
    };
    const userPermissions = auth?.permissions ?? [];
    const canManualSync = canManualEsslSync(userPermissions);
    const canAutoSync = canManageEsslAutoSync(userPermissions);
    const canExport = canExportEsslSync(userPermissions);
    const [loading, setLoading] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [syncProgress, setSyncProgress] = useState<SyncProgressState | null>(null);
    const [liveElapsedSec, setLiveElapsedSec] = useState(0);
    const syncStartedAtRef = React.useRef<number | null>(null);
    const [savingAuto, setSavingAuto] = useState(false);
    const [autoSettings, setAutoSettings] = useState({
        enabled: auto_sync_settings?.enabled ?? false,
        ranges: (auto_sync_settings?.ranges?.length
            ? auto_sync_settings.ranges.map((r) => ({
                  label: r.label,
                  from: r.from?.slice(0, 5) ?? '07:00',
                  to: r.to?.slice(0, 5) ?? '10:00',
                  interval_minutes: Math.min(60, Math.max(5, r.interval_minutes ?? DEFAULT_RANGE_INTERVAL)),
              }))
            : defaultAutoRanges()) as AutoSyncRange[],
    });

    const [autoOpen, setAutoOpen] = useState(false);
    const [manualOpen, setManualOpen] = useState(true);
    const [openDays, setOpenDays] = useState<Record<string, boolean>>({});
    const [istClock, setIstClock] = useState('');

    React.useEffect(() => {
        const tick = () => {
            setIstClock(
                new Date().toLocaleString('en-IN', {
                    timeZone: auto_sync_settings?.timezone || 'Asia/Kolkata',
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                }),
            );
        };
        tick();
        const timer = setInterval(tick, 1000);
        return () => clearInterval(timer);
    }, [auto_sync_settings?.timezone]);

    const currentMonth = format(new Date(), 'yyyy-MM');

    const [filterData, setFilterData] = useState({
        month: filters.month || currentMonth,
        date_from: filters.date_from || format(new Date(), 'yyyy-MM-dd'),
        date_to: filters.date_to || format(new Date(), 'yyyy-MM-dd'),
        employee_id: filters.employee_id ? String(filters.employee_id) : 'all',
        direction: filters.direction || 'all',
        branch_id: filters.branch_id ? String(filters.branch_id) : 'all',
        category_id: filters.category_id ? String(filters.category_id) : 'all',
        per_page: String(filters.per_page ?? 25),
    });

    const logsByDay = React.useMemo(() => {
        const groups: Record<string, EsslLog[]> = {};
        for (const log of logs.data) {
            const day = format(new Date(log.log_date), 'yyyy-MM-dd');
            if (!groups[day]) groups[day] = [];
            groups[day].push(log);
        }
        return Object.entries(groups).sort(([a], [b]) => b.localeCompare(a));
    }, [logs.data]);

    React.useEffect(() => {
        if (logsByDay.length > 0 && Object.keys(openDays).length === 0) {
            setOpenDays({ [logsByDay[0][0]]: true });
        }
    }, [logsByDay]);

    React.useEffect(() => {
        setFilterData((prev) => ({
            ...prev,
            month: filters.month || currentMonth,
            date_from: filters.date_from ?? prev.date_from,
            date_to: filters.date_to ?? prev.date_to,
            employee_id: filters.employee_id && filters.employee_id !== 'all' ? String(filters.employee_id) : 'all',
            direction: filters.direction || 'all',
            category_id: filters.category_id && filters.category_id !== 'all' ? String(filters.category_id) : 'all',
            branch_id: filters.branch_id ? String(filters.branch_id) : prev.branch_id,
            per_page: String(filters.per_page ?? prev.per_page),
        }));
    }, [
        filters.month,
        filters.date_from,
        filters.date_to,
        filters.employee_id,
        filters.direction,
        filters.category_id,
        filters.branch_id,
        filters.per_page,
    ]);

    React.useEffect(() => {
        if (active_branch_id) {
            setFilterData((prev) => ({
                ...prev,
                branch_id: String(active_branch_id),
                employee_id: 'all',
                category_id: 'all',
            }));
        } else {
            setFilterData((prev) => ({
                ...prev,
                branch_id: 'all',
                employee_id: 'all',
                category_id: 'all',
            }));
        }
    }, [active_branch_id]);

    React.useEffect(() => {
        if (!syncing) {
            setLiveElapsedSec(0);
            syncStartedAtRef.current = null;
            return;
        }
        syncStartedAtRef.current = Date.now();
        const timer = setInterval(() => {
            if (syncStartedAtRef.current) {
                setLiveElapsedSec(Math.floor((Date.now() - syncStartedAtRef.current) / 1000));
            }
        }, 1000);
        return () => clearInterval(timer);
    }, [syncing]);

    const getActiveBranchLastSyncDate = () => {
        const activeBranch = filterData.branch_id || active_branch_id || 'all';
        if (branch_sync_dates && branch_sync_dates[activeBranch]) {
            return branch_sync_dates[activeBranch];
        }
        return last_sync_date;
    };

    const activeBranchLastSync = getActiveBranchLastSyncDate();

    const getDefaultSyncDates = () => {
        const today = startOfDay(new Date());
        let from = today;
        if (activeBranchLastSync) {
            const last = startOfDay(parseISO(activeBranchLastSync));
            from = isAfter(last, today) ? today : last;
        }
        return {
            date_from: format(from, 'yyyy-MM-dd'),
            date_to: format(today, 'yyyy-MM-dd'),
        };
    };

    const [syncDates, setSyncDates] = useState(getDefaultSyncDates());
    const [syncEmployeeId, setSyncEmployeeId] = useState<string>('all');

    React.useEffect(() => {
        setSyncDates(getDefaultSyncDates());
    }, [last_sync_date, branch_sync_dates, filterData.branch_id]);

    const isResyncRange =
        !!activeBranchLastSync &&
        !isAfter(startOfDay(parseISO(syncDates.date_from)), startOfDay(parseISO(activeBranchLastSync)));

    const syncPercent =
        syncProgress && syncProgress.total > 0
            ? Math.round((syncProgress.current / syncProgress.total) * 100)
            : 0;

    const avgDaySec =
        syncProgress && syncProgress.current > 0
            ? syncProgress.elapsedTotalSec / syncProgress.current
            : 0;

    const etaSec =
        syncProgress && syncProgress.current > 0 && syncProgress.current < syncProgress.total
            ? avgDaySec * (syncProgress.total - syncProgress.current)
            : 0;

    const employeeOptions = [
        { value: 'all', label: t('All Employees') },
        ...(employees || []).map((emp) => ({
            value: String(emp.id),
            label: emp.emp_code ? `${emp.name} (${emp.emp_code})` : emp.name,
        })),
    ];

    const handleFilterChange = (key: string, value: string) => {
        setFilterData((prev) => ({ ...prev, [key]: value }));
    };

    const navigateLogs = (params: Record<string, string | undefined>) => {
        setLoading(true);
        setOpenDays({});
        router.get(route('hr.essl-sync.index'), params, {
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    };

    const buildLogParams = (extra: Record<string, string | undefined> = {}) => {
        const isSingleDay =
            filterData.date_from &&
            filterData.date_to &&
            filterData.date_from === filterData.date_to;

        const base: Record<string, string | undefined> = {
            per_page: filterData.per_page,
            employee_id: filterData.employee_id !== 'all' ? filterData.employee_id : undefined,
            direction: filterData.direction !== 'all' ? filterData.direction : undefined,
            category_id: filterData.category_id !== 'all' ? filterData.category_id : undefined,
            branch_id: filterData.branch_id !== 'all' ? filterData.branch_id : undefined,
            ...extra,
        };

        if (isSingleDay) {
            return { ...base, date_from: filterData.date_from, date_to: filterData.date_to };
        }

        return { ...base, month: filterData.month };
    };

    const applyFilters = () => navigateLogs(buildLogParams());

    const todayStr = format(new Date(), 'yyyy-MM-dd');

    const resetFilters = () => {
        const cleared = {
            month: currentMonth,
            date_from: '',
            date_to: '',
            employee_id: 'all',
            direction: 'all',
            branch_id: active_branch_id ? String(active_branch_id) : 'all',
            category_id: 'all',
            per_page: '25',
        };
        setFilterData({
            ...cleared,
            date_from: format(new Date(), 'yyyy-MM-dd'),
            date_to: format(new Date(), 'yyyy-MM-dd'),
        });
        navigateLogs({ month: currentMonth, per_page: '25' });
    };

    const applyQuickView = (preset: 'today' | 'this_month' | 'last_month') => {
        const today = startOfDay(new Date());
        if (preset === 'today') {
            const d = format(today, 'yyyy-MM-dd');
            const next = { ...filterData, month: format(today, 'yyyy-MM'), date_from: d, date_to: d };
            setFilterData(next);
            navigateLogs({ date_from: d, date_to: d, per_page: filterData.per_page });
            return;
        }
        const month =
            preset === 'last_month' ? format(subMonths(today, 1), 'yyyy-MM') : format(today, 'yyyy-MM');
        const next = { ...filterData, month, date_from: '', date_to: '' };
        setFilterData(next);
        navigateLogs({ month, per_page: filterData.per_page });
    };

    const monthLabel = React.useMemo(() => {
        try {
            return format(parseISO(`${filterData.month}-01`), 'MMMM yyyy');
        } catch {
            return filterData.month;
        }
    }, [filterData.month]);

    const handleSync = async () => {
        const from = parseISO(syncDates.date_from);
        const to = parseISO(syncDates.date_to);

        if (from > to) {
            toast.error(t('From date cannot be after To date'));
            return;
        }

        const today = startOfDay(new Date());
        if (isAfter(from, today) || isAfter(to, today)) {
            toast.error(t('Sync date cannot be in the future'));
            return;
        }

        const days = eachDayOfInterval({ start: from, end: to });
        if (days.length > 90) {
            toast.error(t('Maximum 90 days per sync. Please use a smaller date range.'));
            return;
        }

        setSyncing(true);
        setSyncProgress({
            current: 0,
            total: days.length,
            date: '',
            lastDaySec: 0,
            elapsedTotalSec: 0,
            processedCount: 0,
            newEsslLogs: 0,
        });

        let elapsedTotalSec = 0;
        let failedDate = syncDates.date_from;

        try {
            for (let i = 0; i < days.length; i++) {
                const dateStr = format(days[i], 'yyyy-MM-dd');
                failedDate = dateStr;

                setSyncProgress({
                    current: i + 1,
                    total: days.length,
                    date: dateStr,
                    lastDaySec: 0,
                    elapsedTotalSec,
                    processedCount: 0,
                    newEsslLogs: 0,
                });

                const response = await axios.post(route('hr.essl-sync.sync-chunk'), {
                    date: dateStr,
                    employee_id: syncEmployeeId === 'all' ? null : syncEmployeeId,
                    branch_id: filterData.branch_id && filterData.branch_id !== 'all' ? filterData.branch_id : null,
                });

                const daySec = response.data.elapsed_sec ?? 0;
                elapsedTotalSec += daySec;

                setSyncProgress({
                    current: i + 1,
                    total: days.length,
                    date: dateStr,
                    lastDaySec: daySec,
                    elapsedTotalSec,
                    processedCount: response.data.processed_count ?? 0,
                    newEsslLogs: response.data.new_essl_logs ?? 0,
                });
            }

            toast.success(
                `${t('Sync complete')} — ${days.length} ${days.length === 1 ? t('day') : t('days')} in ${formatDuration(elapsedTotalSec)}`,
            );
            setSyncProgress(null);
            router.reload({ only: ['logs', 'last_sync_date', 'branch_sync_dates'] });
        } catch (err: any) {
            toast.error(
                err?.response?.data?.message || `${t('Synchronization failed on')} ${failedDate}`,
            );
        } finally {
            setSyncing(false);
            setSyncProgress(null);
        }
    };

    const getDirectionBadge = (direction: string | number) => {
        const dir = String(direction).toLowerCase();
        if (dir === '0' || dir === 'in') {
            return (
                <span className="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700">
                    {t('IN')}
                </span>
            );
        }
        if (dir === '1' || dir === 'out') {
            return (
                <span className="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700">
                    {t('OUT')}
                </span>
            );
        }
        return <span className="text-xs text-slate-500">{direction}</span>;
    };

    const handleExport = () => {
        if (!filterData.date_from || !filterData.date_to) {
            toast.error(t('Select date range before export (max 31 days).'));
            return;
        }
        const queryParams = new URLSearchParams();
        queryParams.append('date_from', filterData.date_from);
        queryParams.append('date_to', filterData.date_to);
        Object.entries(filterData).forEach(([key, value]) => {
            if (key === 'date_from' || key === 'date_to') return;
            if (value && value !== 'all') {
                queryParams.append(key, value);
            }
        });
        window.open(`${route('hr.essl-sync.export')}?${queryParams.toString()}`, '_blank');
    };

    const lastSyncLabel = activeBranchLastSync
        ? format(
              new Date(activeBranchLastSync),
              activeBranchLastSync.includes(':') ? 'dd MMM yyyy, hh:mm a' : 'dd MMM yyyy',
          )
        : t('Never');

    const lastAutoRunLabel = auto_sync_settings?.last_run_at
        ? `${format(new Date(auto_sync_settings.last_run_at), 'dd MMM yyyy, hh:mm a')}${
              auto_sync_settings.last_run_slot ? ` (${auto_sync_settings.last_run_slot})` : ''
          }`
        : t('Never');

    const autoSyncSummary = React.useMemo(() => {
        if (!autoSettings.enabled) {
            return (
                <p className="text-[10px] text-slate-400 leading-relaxed">
                    {t('Off — expand to set time ranges')}
                </p>
            );
        }
        const rangeText = autoSettings.ranges
            .map((r) => `${r.label || t('Range')}: ${r.from}–${r.to} (${r.interval_minutes}m)`)
            .join(' · ');
        return (
            <div className="space-y-0.5">
                <p className="text-[10px] text-slate-600 leading-relaxed">{rangeText}</p>
                <p className="text-[10px] text-slate-400">
                    {t('Last auto')}: {lastAutoRunLabel}
                </p>
            </div>
        );
    }, [autoSettings, lastAutoRunLabel, t]);

    const manualSyncSummary = React.useMemo(() => {
        const empLabel =
            syncEmployeeId === 'all'
                ? t('All Employees')
                : employeeOptions.find((o) => o.value === syncEmployeeId)?.label ?? t('Employee');
        const fromLabel = syncDates.date_from
            ? format(parseISO(syncDates.date_from), 'dd MMM yyyy')
            : '—';
        const toLabel = syncDates.date_to ? format(parseISO(syncDates.date_to), 'dd MMM yyyy') : '—';
        return (
            <p className="text-[10px] text-slate-500 leading-relaxed">
                <span className="font-medium text-slate-600">
                    {fromLabel === toLabel ? fromLabel : `${fromLabel} – ${toLabel}`}
                </span>
                <span className="text-slate-300 mx-1">|</span>
                {empLabel}
            </p>
        );
    }, [syncDates.date_from, syncDates.date_to, syncEmployeeId, employeeOptions, t]);

    const expandAllDays = () => {
        setOpenDays(Object.fromEntries(logsByDay.map(([day]) => [day, true])));
    };

    const collapseAllDays = () => setOpenDays({});

    const updateRange = (index: number, field: keyof AutoSyncRange, value: string | number) => {
        setAutoSettings((prev) => ({
            ...prev,
            ranges: prev.ranges.map((r, i) => (i === index ? { ...r, [field]: value } : r)),
        }));
    };

    const updateRangeInterval = (index: number, raw: string) => {
        const minutes = Math.min(60, Math.max(5, parseInt(raw, 10) || DEFAULT_RANGE_INTERVAL));
        updateRange(index, 'interval_minutes', minutes);
    };

    const addRange = () => {
        if (autoSettings.ranges.length >= MAX_AUTO_RANGES) return;
        setAutoSettings((prev) => ({
            ...prev,
            ranges: [
                ...prev.ranges,
                {
                    label: `Range ${prev.ranges.length + 1}`,
                    from: '14:00',
                    to: '16:00',
                    interval_minutes: DEFAULT_RANGE_INTERVAL,
                },
            ],
        }));
    };

    const removeRange = (index: number) => {
        if (autoSettings.ranges.length <= 1) return;
        setAutoSettings((prev) => ({
            ...prev,
            ranges: prev.ranges.filter((_, i) => i !== index),
        }));
    };

    const saveAutoSettings = () => {
        for (const range of autoSettings.ranges) {
            if (range.from >= range.to) {
                toast.error(t('End time must be after start time for each range.'));
                return;
            }
            if (range.interval_minutes < 5 || range.interval_minutes > 60) {
                toast.error(t('Each range interval must be between 5 and 60 minutes.'));
                return;
            }
        }

        setSavingAuto(true);
        router.post(
            route('hr.essl-sync.auto-settings'),
            {
                enabled: autoSettings.enabled,
                ranges: autoSettings.ranges,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(t('Automatic sync settings saved.')),
                onError: () => toast.error(t('Failed to save automatic sync settings.')),
                onFinish: () => setSavingAuto(false),
            },
        );
    };

    const pageActions = canExport
        ? [
              {
                  label: t('Export Excel'),
                  icon: <Download className="h-3.5 w-3.5" />,
                  variant: 'outline' as const,
                  onClick: handleExport,
              },
          ]
        : [];

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('ESSL Device Sync') },
    ];

    return (
        <PageTemplate
            title={t('ESSL Device Sync')}
            url="/essl-sync"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Status strip */}
            <div className="flex flex-wrap items-center justify-between gap-2 mb-2 px-2.5 py-2 rounded-lg border border-slate-200/80 bg-white text-xs">
                <div className="flex flex-wrap items-center gap-3 text-slate-600">
                    <span>
                        <span className="text-slate-500">{t('Last sync')}:</span>{' '}
                        <strong className="text-slate-800">{lastSyncLabel}</strong>
                    </span>
                    <span className="hidden sm:inline text-slate-300">|</span>
                    <span>
                        <span className="text-slate-500">{t('Month')}:</span>{' '}
                        <strong className="text-slate-800">{monthLabel}</strong>
                    </span>
                    <span className="hidden sm:inline text-slate-300">|</span>
                    <span>
                        <span className="text-slate-500">{t('Records')}:</span>{' '}
                        <strong className="text-slate-800">{logs.total?.toLocaleString() ?? 0}</strong>
                        <span className="text-slate-400 ml-1">({filterData.date_from} – {filterData.date_to})</span>
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-1.5">
                    {autoSettings.enabled && auto_sync_settings?.scheduler_running && (
                        <span className="text-[10px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                            {t('Auto sync active')}
                        </span>
                    )}
                    {autoSettings.enabled && !auto_sync_settings?.scheduler_running && (
                        <span className="text-[10px] text-red-700 bg-red-50 px-2 py-0.5 rounded font-medium">
                            {t('Scheduler offline — auto sync will not run')}
                        </span>
                    )}
                    {isResyncRange && !syncing && (
                        <span className="text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded">
                            {t('Re-sync: duplicates skipped')}
                        </span>
                    )}
                </div>
            </div>

            {(canAutoSync || canManualSync) && (
            <div
                className={cn(
                    'grid grid-cols-1 gap-2 mb-2 items-start',
                    canAutoSync && canManualSync && 'xl:grid-cols-2',
                )}
            >
            {canAutoSync && (
            <SyncAccordion
                title={t('Automatic Sync')}
                icon={<Settings2 className="h-3.5 w-3.5" />}
                open={autoOpen}
                onToggle={() => setAutoOpen((v) => !v)}
                summary={autoSyncSummary}
                badge={
                    autoSettings.enabled ? (
                        <span className="text-[10px] font-normal text-emerald-600 bg-emerald-50 px-1.5 rounded">ON</span>
                    ) : (
                        <span className="text-[10px] font-normal text-slate-500 bg-slate-100 px-1.5 rounded">OFF</span>
                    )
                }
                headerRight={
                    <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <span className="text-[10px] text-slate-500">{t('Enable')}</span>
                        <Switch
                            checked={autoSettings.enabled}
                            onCheckedChange={(v) => {
                                setAutoSettings((prev) => ({ ...prev, enabled: v }));
                                if (v) setAutoOpen(true);
                            }}
                        />
                    </div>
                }
            >
                <div className="flex flex-wrap items-center gap-2 mb-2 pt-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8"
                        onClick={addRange}
                        disabled={!autoSettings.enabled || autoSettings.ranges.length >= MAX_AUTO_RANGES}
                    >
                        <Plus className="h-3.5 w-3.5 mr-1" />
                        {t('Add range')} ({autoSettings.ranges.length}/{MAX_AUTO_RANGES})
                    </Button>
                    <Button
                        size="sm"
                        variant="default"
                        className="h-8 ml-auto"
                        onClick={saveAutoSettings}
                        disabled={savingAuto}
                    >
                        {savingAuto ? t('Saving...') : t('Save')}
                    </Button>
                </div>

                <div className="hidden sm:grid grid-cols-[minmax(72px,1fr)_80px_80px_64px_32px] gap-1 px-1.5 pb-1 text-[10px] font-medium text-slate-400">
                    <span>{t('Label')}</span>
                    <span>{t('From')}</span>
                    <span>{t('To')}</span>
                    <span>{t('Every (min)')}</span>
                    <span />
                </div>

                <div className="space-y-1">
                    {autoSettings.ranges.map((range, index) => (
                        <div
                            key={index}
                            className="grid grid-cols-[1fr_auto_auto_auto_auto] sm:grid-cols-[minmax(72px,1fr)_80px_80px_64px_32px] gap-1 items-center rounded border border-slate-100 bg-slate-50/50 px-1.5 py-1"
                        >
                            <Input
                                type="text"
                                className="h-7 text-xs"
                                placeholder={t('Label')}
                                value={range.label}
                                disabled={!autoSettings.enabled}
                                onChange={(e) => updateRange(index, 'label', e.target.value)}
                            />
                            <Input
                                type="time"
                                className="h-7 text-xs"
                                value={range.from}
                                disabled={!autoSettings.enabled}
                                onChange={(e) => updateRange(index, 'from', e.target.value)}
                            />
                            <Input
                                type="time"
                                className="h-7 text-xs"
                                value={range.to}
                                disabled={!autoSettings.enabled}
                                onChange={(e) => updateRange(index, 'to', e.target.value)}
                            />
                            <Input
                                type="number"
                                min={5}
                                max={60}
                                className="h-7 text-xs"
                                value={range.interval_minutes}
                                disabled={!autoSettings.enabled}
                                onChange={(e) => updateRangeInterval(index, e.target.value)}
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="h-7 w-7 text-red-500"
                                disabled={!autoSettings.enabled || autoSettings.ranges.length <= 1}
                                onClick={() => removeRange(index)}
                            >
                                <Trash2 className="h-3 w-3" />
                            </Button>
                        </div>
                    ))}
                </div>

                <div className="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]">
                    <span className="text-slate-500">
                        {t('Schedule time')}:{' '}
                        <strong className="text-slate-700">
                            {auto_sync_settings?.timezone_label || 'IST (India)'}
                        </strong>
                    </span>
                    <span className="text-slate-300">|</span>
                    <span className="text-slate-500">
                        {t('Now')}: <strong className="text-slate-700 tabular-nums">{istClock || '—'}</strong>
                    </span>
                    {auto_sync_settings?.active_range && autoSettings.enabled && (
                        <>
                            <span className="text-slate-300">|</span>
                            <span className="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded font-medium">
                                {t('Active')}: {auto_sync_settings.active_range}
                            </span>
                        </>
                    )}
                </div>
                <p className="text-[10px] text-slate-500 mt-1">
                    {t('Last auto')}: {lastAutoRunLabel}
                    {auto_sync_settings?.timezone_label ? ` (${auto_sync_settings.timezone_label})` : ''}
                </p>
                {autoSettings.enabled && !auto_sync_settings?.scheduler_running && (
                    <p className="text-[10px] text-red-600 bg-red-50/80 border border-red-100 rounded px-2 py-1.5 mt-1.5">
                        {t('Server: enable supervisor (schedule:work) or run: php artisan queue:work + php artisan essl:ensure-scheduler')}
                    </p>
                )}
                {auto_sync_settings?.scheduler_running && (
                    <p className="text-[10px] text-emerald-600 mt-0.5">
                        {t('Scheduler running')}
                        {auto_sync_settings.scheduler_last_ping
                            ? ` · ${t('last ping')} ${auto_sync_settings.scheduler_last_ping}`
                            : ''}
                    </p>
                )}
                <p className="text-[10px] text-slate-400 mt-0.5">
                    {t('All branches · each range runs on its own interval (India time).')}
                </p>
            </SyncAccordion>
            )}

            {canManualSync && (
            <SyncAccordion
                title={t('Manual Sync')}
                icon={<RefreshCw className="h-3.5 w-3.5" />}
                open={manualOpen}
                onToggle={() => setManualOpen((v) => !v)}
                summary={manualSyncSummary}
                variant="primary"
            >
                <div className="flex flex-wrap items-end gap-x-2 gap-y-2 pt-1">
                    <div className="flex flex-col min-w-[120px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('From')}</Label>
                        <Input
                            type="date"
                            className="h-8 text-xs"
                            value={syncDates.date_from}
                            max={todayStr}
                            disabled={syncing}
                            onChange={(e) => setSyncDates((prev) => ({ ...prev, date_from: e.target.value }))}
                        />
                    </div>
                    <div className="flex flex-col min-w-[120px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('To')}</Label>
                        <Input
                            type="date"
                            className="h-8 text-xs"
                            value={syncDates.date_to}
                            min={syncDates.date_from}
                            max={todayStr}
                            disabled={syncing}
                            onChange={(e) => setSyncDates((prev) => ({ ...prev, date_to: e.target.value }))}
                        />
                    </div>
                    <div className="flex flex-col flex-1 min-w-[160px] max-w-xs">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('Employee')}</Label>
                        <Combobox
                            value={syncEmployeeId}
                            onChange={(v) => setSyncEmployeeId(v || 'all')}
                            placeholder={t('All Employees')}
                            searchPlaceholder={t('Search by name or code...')}
                            className="h-8 text-xs"
                            disabled={syncing}
                            options={employeeOptions}
                        />
                    </div>
                    <Button
                        size="sm"
                        className="h-8 shrink-0"
                        onClick={handleSync}
                        disabled={syncing}
                    >
                        <RefreshCw className={cn('h-3.5 w-3.5 mr-1.5', syncing && 'animate-spin')} />
                        {syncing ? t('Syncing...') : t('Start Sync')}
                    </Button>
                </div>

                {syncing && syncProgress && (
                    <div className="mt-2 pt-2 border-t border-primary/10 space-y-1.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-700">
                                {t('Day')} {syncProgress.current}/{syncProgress.total}
                                {syncProgress.date && (
                                    <span className="text-slate-500 font-normal ml-1.5">
                                        {format(parseISO(syncProgress.date), 'dd MMM yyyy')}
                                    </span>
                                )}
                            </span>
                            <span className="font-bold text-primary tabular-nums">{syncPercent}%</span>
                        </div>
                        <div className="h-1.5 bg-white rounded-full overflow-hidden border border-slate-200">
                            <div
                                className="h-full bg-primary rounded-full transition-all duration-300"
                                style={{ width: `${syncPercent}%` }}
                            />
                        </div>
                        <div className="flex flex-wrap gap-3 text-[10px] text-slate-500">
                            <span className="inline-flex items-center gap-1">
                                <Clock className="h-3 w-3" />
                                {formatDuration(liveElapsedSec)}
                            </span>
                            {syncProgress.lastDaySec > 0 && (
                                <span>{t('Last day')}: {formatDuration(syncProgress.lastDaySec)}</span>
                            )}
                            {etaSec > 0 && <span>{t('Est. left')}: ~{formatDuration(etaSec)}</span>}
                            {syncProgress.current > 0 && (
                                <span>
                                    {syncProgress.processedCount} {t('rows')}
                                    {syncProgress.newEsslLogs > 0
                                        ? ` · ${syncProgress.newEsslLogs} ${t('new')}`
                                        : ` · ${t('no new punches')}`}
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </SyncAccordion>
            )}
            </div>
            )}

            {/* Filters — month wise */}
            <div className="bg-white rounded-lg border border-slate-200/80 mb-2 px-2.5 py-1.5">
                <div className="flex flex-wrap gap-1 mb-1.5">
                    <Button type="button" variant="outline" size="sm" className="h-7 text-[10px] px-2" onClick={() => applyQuickView('this_month')}>
                        {t('This month')}
                    </Button>
                    <Button type="button" variant="outline" size="sm" className="h-7 text-[10px] px-2" onClick={() => applyQuickView('last_month')}>
                        {t('Last month')}
                    </Button>
                    <Button type="button" variant="outline" size="sm" className="h-7 text-[10px] px-2" onClick={() => applyQuickView('today')}>
                        {t('Today only')}
                    </Button>
                </div>
                <div className="flex flex-wrap items-end gap-x-1.5 gap-y-2">
                    <div className="flex flex-col min-w-[130px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('Month')}</Label>
                        <Input
                            type="month"
                            className="h-8 text-xs"
                            value={filterData.month}
                            max={currentMonth}
                            onChange={(e) => {
                                const month = e.target.value;
                                setFilterData((prev) => ({ ...prev, month, date_from: '', date_to: '' }));
                            }}
                        />
                    </div>
                    <div className="flex flex-col min-w-[140px] flex-1 max-w-[200px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('Employee')}</Label>
                        <Combobox
                            value={filterData.employee_id}
                            onChange={(v) => handleFilterChange('employee_id', v || 'all')}
                            placeholder={t('All')}
                            searchPlaceholder={t('Search...')}
                            className="h-8 text-xs"
                            options={employeeOptions}
                        />
                    </div>
                    <div className="flex flex-col min-w-[110px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('Category')}</Label>
                        <Select value={filterData.category_id} onValueChange={(v) => handleFilterChange('category_id', v)}>
                            <SelectTrigger className="h-8 text-xs">
                                <SelectValue placeholder={t('All')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.id} value={String(cat.id)}>
                                        {cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col min-w-[90px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('Direction')}</Label>
                        <Select value={filterData.direction} onValueChange={(v) => handleFilterChange('direction', v)}>
                            <SelectTrigger className="h-8 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All')}</SelectItem>
                                <SelectItem value="in">{t('In')}</SelectItem>
                                <SelectItem value="out">{t('Out')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col min-w-[70px]">
                        <Label className="text-[10px] font-medium text-slate-500 mb-1">{t('Per page')}</Label>
                        <Select
                            value={filterData.per_page}
                            onValueChange={(v) => handleFilterChange('per_page', v)}
                        >
                            <SelectTrigger className="h-8 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="25">25</SelectItem>
                                <SelectItem value="50">50</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <Button variant="default" size="sm" className="h-8" onClick={applyFilters} disabled={loading}>
                            <Search className="h-3.5 w-3.5 mr-1" />
                            {loading ? t('...') : t('Apply')}
                        </Button>
                        <Button variant="ghost" size="sm" className="h-8 px-2" onClick={resetFilters} title={t('Reset')}>
                            <X className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>

            {/* Logs — grouped by day */}
            <div className="rounded-lg border border-slate-200/80 bg-white overflow-hidden">
                {logsByDay.length > 0 ? (
                    <>
                    {logsByDay.length > 1 && (
                        <div className="flex items-center justify-between gap-2 px-2.5 py-1.5 border-b border-slate-100 bg-slate-50/50">
                            <span className="text-[10px] text-slate-500">
                                {logsByDay.length} {t('days on this page')}
                            </span>
                            <div className="flex gap-1">
                                <Button type="button" variant="ghost" size="sm" className="h-6 text-[10px] px-2" onClick={expandAllDays}>
                                    {t('Expand all')}
                                </Button>
                                <Button type="button" variant="ghost" size="sm" className="h-6 text-[10px] px-2" onClick={collapseAllDays}>
                                    {t('Collapse all')}
                                </Button>
                            </div>
                        </div>
                    )}
                    <div className="divide-y divide-slate-100">
                        {logsByDay.map(([day, dayLogs]) => {
                            const isOpen = openDays[day] ?? false;
                            const dayTitle = format(parseISO(day), 'EEEE, dd MMM yyyy');
                            return (
                                <div key={day}>
                                    <button
                                        type="button"
                                        onClick={() => setOpenDays((prev) => ({ ...prev, [day]: !isOpen }))}
                                        className={cn(
                                            'w-full flex items-center justify-between gap-2 px-2.5 py-2 text-left transition-colors',
                                            isOpen ? 'bg-slate-50' : 'bg-white hover:bg-slate-50/70',
                                        )}
                                    >
                                        <span className="text-[11px] font-semibold text-slate-700">{dayTitle}</span>
                                        <span className="flex items-center gap-2 shrink-0">
                                            <span className="text-[10px] text-slate-500 tabular-nums">
                                                {dayLogs.length} {dayLogs.length === 1 ? t('log') : t('logs')}
                                            </span>
                                            <ChevronDown
                                                className={cn(
                                                    'h-4 w-4 text-slate-400 transition-transform duration-200',
                                                    isOpen && 'rotate-180',
                                                )}
                                            />
                                        </span>
                                    </button>
                                    {isOpen && (
                                        <div className="overflow-x-auto border-t border-slate-100">
                                            <table className="w-full text-xs">
                                                <thead className="bg-slate-50/80">
                                                    <tr>
                                                        <th className="px-2 py-1.5 text-left font-medium text-slate-500 whitespace-nowrap">{t('Emp Code')}</th>
                                                        <th className="px-2 py-1.5 text-left font-medium text-slate-500">{t('Employee')}</th>
                                                        <th className="px-2 py-1.5 text-left font-medium text-slate-500 whitespace-nowrap">{t('Time')}</th>
                                                        <th className="px-2 py-1.5 text-left font-medium text-slate-500">{t('Dir')}</th>
                                                        <th className="px-2 py-1.5 text-left font-medium text-slate-500">{t('Device')}</th>
                                                        <th className="px-2 py-1.5 text-left font-medium text-slate-500 hidden lg:table-cell">{t('Log ID')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-50">
                                                    {dayLogs.map((log) => (
                                                        <tr key={log.id} className="hover:bg-slate-50/80">
                                                            <td className="px-2 py-1.5">
                                                                <span className="font-mono font-bold text-primary text-[11px]">{empCode(log)}</span>
                                                            </td>
                                                            <td className="px-2 py-1.5 font-medium text-slate-800 max-w-[140px] truncate">
                                                                {log.user?.name || '—'}
                                                            </td>
                                                            <td className="px-2 py-1.5 whitespace-nowrap text-slate-600">
                                                                {format(new Date(log.log_date), 'hh:mm a')}
                                                            </td>
                                                            <td className="px-2 py-1.5">{getDirectionBadge(log.direction)}</td>
                                                            <td className="px-2 py-1.5">
                                                                <span className="inline-flex items-center gap-1 text-slate-600">
                                                                    <MapPin className="h-3 w-3 text-slate-400" />
                                                                    {log.device_id}
                                                                </span>
                                                                {log.body_temperature && (
                                                                    <span className="inline-flex items-center gap-0.5 text-[9px] text-slate-500 ml-1">
                                                                        <Thermometer className="h-2.5 w-2.5" />
                                                                        {log.body_temperature}°
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-1.5 text-slate-400 hidden lg:table-cell">{log.device_log_id}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    </>
                ) : (
                    <div className="px-4 py-10 text-center text-slate-400">
                        <ArrowRightLeft className="h-8 w-8 mx-auto mb-2 opacity-20" />
                        {t('No synchronization logs found.')}
                    </div>
                )}
            </div>

            {(logs.total ?? 0) > 0 && (
                <div className="flex justify-center mt-2">
                    <Pagination
                        from={logs.from || 0}
                        to={logs.to || 0}
                        total={logs.total || 0}
                        links={logs.links}
                        onPageChange={(url) => {
                            const urlObj = new URL(url);
                            const page = new URLSearchParams(urlObj.search).get('page') ?? undefined;
                            router.get(route('hr.essl-sync.index'), buildLogParams({ page }), {
                                preserveState: true,
                                onStart: () => setOpenDays({}),
                            });
                        }}
                    />
                </div>
            )}
        </PageTemplate>
    );
};

export default EsslSyncReport;
