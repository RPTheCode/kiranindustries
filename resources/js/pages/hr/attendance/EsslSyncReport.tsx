import React, { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, usePage, router } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription
} from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from '@/components/ui/modal';
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from "@/components/ui/select";
import { Combobox } from "@/components/ui/combobox";
import {
    Search,
    RefreshCw,
    Download,
    Filter,
    Calendar as CalendarIcon,
    ArrowRightLeft,
    User as UserIcon,
    MapPin,
    Thermometer,
    Zap,
    Clock,
} from 'lucide-react';
import { Badge } from "@/components/ui/badge";
import { Pagination } from '@/components/ui/pagination';
import { useTranslation } from 'react-i18next';
import { format, eachDayOfInterval, parseISO, isAfter, startOfDay } from 'date-fns';
import { toast } from '@/components/custom-toast';
import axios from 'axios';

interface EsslLog {
    id: number;
    device_log_id: string;
    user_id: number;
    log_date: string;
    direction: string | number;
    att_direction: string | number;
    device_id: string;
    body_temperature: string | null;
    is_mask_on: boolean | null;
    location_address: string | null;
    user?: {
        name: string;
        employee?: {
            employee_id: string;
            emy_code: string;
            branch_id: number;
        }
    }
}

interface Props {
    logs: {
        data: EsslLog[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    employees: { id: number, name: string }[];
    branches: { id: number, name: string }[];
    categories: { id: number, name: string }[];
    last_sync_date: string | null;
    branch_sync_dates?: Record<string | number, string | null>;
    filters: any;
}

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

const EsslSyncReport = ({ logs, employees, branches, categories, last_sync_date, branch_sync_dates, filters }: Props) => {
    const { t } = useTranslation();
    const { active_branch_id } = usePage().props as any;
    const [loading, setLoading] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [syncProgress, setSyncProgress] = useState<SyncProgressState | null>(null);
    const [liveElapsedSec, setLiveElapsedSec] = useState(0);
    const syncStartedAtRef = React.useRef<number | null>(null);

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

    const [filterData, setFilterData] = useState({
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
        employee_id: filters.employee_id ? String(filters.employee_id) : 'all',
        direction: filters.direction || 'all',
        branch_id: filters.branch_id ? String(filters.branch_id) : 'all',
        category_id: filters.category_id ? String(filters.category_id) : 'all',
    });

    React.useEffect(() => {
        if (active_branch_id) {
            setFilterData(prev => ({
                ...prev,
                branch_id: String(active_branch_id),
                employee_id: 'all',
                category_id: 'all',
            }));
        } else {
            setFilterData(prev => ({
                ...prev,
                branch_id: 'all',
                employee_id: 'all',
                category_id: 'all',
            }));
        }
    }, [active_branch_id]);

    const handleFilterChange = (key: string, value: string) => {
        setFilterData(prev => ({ ...prev, [key]: value }));
    };

    const applyFilters = () => {
        setLoading(true);
        router.get(route('hr.essl-sync.index'), filterData, {
            preserveState: true,
            onFinish: () => setLoading(false)
        });
    };

    const resetFilters = () => {
        setFilterData({
            date_from: '',
            date_to: '',
            employee_id: 'all',
            direction: 'all',
            branch_id: 'all',
            category_id: 'all',
        });
        router.get(route('hr.essl-sync.index'));
    };

    const [isSyncModalOpen, setIsSyncModalOpen] = useState(false);

    const todayStr = format(new Date(), 'yyyy-MM-dd');

    const getActiveBranchLastSyncDate = () => {
        const activeBranch = filterData.branch_id || active_branch_id || 'all';
        if (branch_sync_dates && branch_sync_dates[activeBranch]) {
            return branch_sync_dates[activeBranch];
        }
        return last_sync_date;
    };

    const activeBranchName = React.useMemo(() => {
        const activeBranch = filterData.branch_id || active_branch_id || 'all';
        if (activeBranch === 'all') return t('All Branches');
        const found = branches.find(b => String(b.id) === String(activeBranch));
        return found ? found.name : t('Branch');
    }, [filterData.branch_id, active_branch_id, branches]);

    const getDefaultSyncDates = () => {
        const today = startOfDay(new Date());
        let from = today;
        const activeBranchLastSync = getActiveBranchLastSyncDate();
        if (activeBranchLastSync) {
            const last = startOfDay(parseISO(activeBranchLastSync));
            // Start from last synced day (not the day after); never default to a future date
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
        if (isSyncModalOpen) {
            setSyncDates(getDefaultSyncDates());
        }
    }, [isSyncModalOpen, last_sync_date, branch_sync_dates, filterData.branch_id]);

    const activeBranchLastSync = getActiveBranchLastSyncDate();
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

                const response = await axios.post(route('hr.essl-sync.sync-chunk'), {
                    date: dateStr,
                    employee_id: syncEmployeeId === 'all' ? null : syncEmployeeId,
                    branch_id: (filterData.branch_id && filterData.branch_id !== 'all') ? filterData.branch_id : null,
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
                `${t('Sync complete')} — ${days.length} ${days.length === 1 ? t('day') : t('days')} in ${formatDuration(elapsedTotalSec)}`
            );
            setIsSyncModalOpen(false);
            setSyncProgress(null);
            router.reload({ only: ['logs', 'last_sync_date', 'branch_sync_dates'] });
        } catch (err: any) {
            toast.error(
                err?.response?.data?.message ||
                    `${t('Synchronization failed on')} ${failedDate}`
            );
        } finally {
            setSyncing(false);
            setSyncProgress(null);
        }
    };

    const getDirectionBadge = (direction: any) => {
        const dir = String(direction).toLowerCase();
        if (dir === '0' || dir === 'in') {
            return <Badge className="bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 border-emerald-500/20 font-bold uppercase">{t('IN')}</Badge>;
        } else if (dir === '1' || dir === 'out') {
            return <Badge className="bg-rose-500/10 text-rose-600 hover:bg-rose-500/20 border-rose-500/20 font-bold uppercase">{t('OUT')}</Badge>;
        }
        return <Badge variant="outline" className="uppercase">{direction}</Badge>;
    };

    const handleExport = () => {
        const queryParams = new URLSearchParams();
        Object.entries(filterData).forEach(([key, value]) => {
            if (value && value !== 'all') {
                queryParams.append(key, value);
            }
        });
        window.open(`${route('hr.essl-sync.export')}?${queryParams.toString()}`, '_blank');
    };

    const pageActions = [
        {
            label: syncing ? t('Syncing...') : t('Sync attendance'),
            icon: <RefreshCw className={`w-4 h-4 ${syncing ? 'animate-spin' : ''}`} />,
            variant: 'outline' as const,
            onClick: () => setIsSyncModalOpen(true)
        },
        {
            label: t('Export Excel'),
            icon: <Download className="w-4 h-4" />,
            variant: 'default' as const,
            onClick: handleExport
        }
    ];

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Essl sync') },
    ];

    return (
        <PageTemplate
            title={t('Essl sync')}
            url="/essl-sync"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <div className="space-y-6">
                <Card className="border-primary/10 shadow-sm overflow-visible">
                    <CardHeader className="pb-3 pt-4">
                        <div className="flex items-center gap-2">
                            <Filter className="w-4 h-4 text-primary" />
                            <CardTitle className="text-sm font-medium">{t('Filters')}</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">


                            <div className="space-y-2">
                                <Label className="text-xs">{t('Employee')}</Label>
                                <Combobox
                                    value={filterData.employee_id}
                                    onChange={(v) => handleFilterChange('employee_id', v || 'all')}
                                    placeholder={t('Select Employee')}
                                    searchPlaceholder={t('Search by name or code...')}
                                    className="h-9 text-xs"
                                    options={[
                                        { value: 'all', label: t('All Employees') },
                                        ...(employees || []).map(emp => ({
                                            value: String(emp.id),
                                            label: emp.emp_code ? `${emp.name} (${emp.emp_code})` : emp.name
                                        }))
                                    ]}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs">{t('Category')}</Label>
                                <Select value={filterData.category_id} onValueChange={(v) => handleFilterChange('category_id', v)}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder={t('Select Category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Categories')}</SelectItem>
                                        {categories.map(cat => (
                                            <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs">{t('Direction')}</Label>
                                <Select value={filterData.direction} onValueChange={(v) => handleFilterChange('direction', v)}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder={t('Direction')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All')}</SelectItem>
                                        <SelectItem value="in">{t('In')}</SelectItem>
                                        <SelectItem value="out">{t('Out')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs">{t('From Date')}</Label>
                                <Input
                                    type="date"
                                    className="h-9 text-xs"
                                    value={filterData.date_from}
                                    onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="text-xs">{t('To Date')}</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="date"
                                        className="h-9 text-xs"
                                        value={filterData.date_to}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                    />
                                    <Button variant="outline" size="sm" className="h-9 w-9 p-0" onClick={applyFilters} disabled={loading}>
                                        <Search className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-2">
                            <Button variant="ghost" size="sm" className="text-xs h-8" onClick={resetFilters}>
                                {t('Reset Filters')}
                            </Button>
                            <Button size="sm" className="text-xs h-8" onClick={applyFilters} disabled={loading}>
                                {loading ? t('Loading...') : t('Apply Filters')}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden">
                    <div className="relative overflow-x-auto">
                        <Table>
                            <TableHeader className="bg-primary/5">
                                <TableRow>
                                    <TableHead className="w-[100px] text-xs uppercase font-bold">{t('Log ID')}</TableHead>
                                    <TableHead className="text-xs uppercase font-bold">{t('Employee')}</TableHead>
                                    <TableHead className="text-xs uppercase font-bold">{t('Emp Code')}</TableHead>
                                    <TableHead className="text-xs uppercase font-bold">{t('Date & Time')}</TableHead>
                                    <TableHead className="text-xs uppercase font-bold">{t('Direction')}</TableHead>
                                    <TableHead className="text-xs uppercase font-bold">{t('Device')}</TableHead>
                                    <TableHead className="text-xs uppercase font-bold">{t('Details')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.length > 0 ? (
                                    logs.data.map((log) => (
                                        <TableRow key={log.id} className="hover:bg-primary/5 transition-colors">
                                            <TableCell className="font-medium text-xs text-muted-foreground">{log.device_log_id}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-[10px]">
                                                        {log.user?.name?.charAt(0)}
                                                    </div>
                                                    <span className="font-semibold text-xs">{log.user?.name || t('N/A')}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="font-mono text-[10px] py-0">{log.user?.employee?.emy_code || t('N/A')}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-xs">{format(new Date(log.log_date), 'dd MMM yyyy')}</span>
                                                    <span className="text-[10px] text-muted-foreground">{format(new Date(log.log_date), 'hh:mm:ss a')}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {getDirectionBadge(log.direction)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1 text-[11px]">
                                                    <MapPin className="w-3 h-3 text-muted-foreground" />
                                                    {log.device_id}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {log.body_temperature && (
                                                        <Badge variant="secondary" className="flex items-center gap-0.5 text-[9px] py-0 px-1">
                                                            <Thermometer className="w-2.5 h-2.5" />
                                                            {log.body_temperature}°C
                                                        </Badge>
                                                    )}
                                                    {log.is_mask_on !== null && (
                                                        <Badge variant={log.is_mask_on ? "outline" : "destructive"} className="text-[9px] py-0 px-1">
                                                            {log.is_mask_on ? t('Mask On') : t('No Mask')}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-32 text-center">
                                            <div className="flex flex-col items-center justify-center text-muted-foreground">
                                                <ArrowRightLeft className="w-10 h-10 mb-2 opacity-20" />
                                                <p className="text-sm">{t('No synchronization logs found.')}</p>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div className="flex justify-center mt-4">
                    <Pagination
                        from={logs.from || 0}
                        to={logs.to || 0}
                        total={logs.total || 0}
                        links={logs.links}
                        onPageChange={(url) => {
                            const urlObj = new URL(url);
                            const params = new URLSearchParams(urlObj.search);
                            const page = params.get('page');
                            router.get(route('hr.essl-sync.index'), { ...filterData, page }, { preserveState: true });
                        }}
                    />
                </div>
            </div>

            <Modal
                isOpen={isSyncModalOpen}
                onClose={() => {
                    if (!syncing) setIsSyncModalOpen(false);
                }}
                title={t('Sync attendance')}
                size="md"
            >
                <div className="p-4 space-y-4">
                    {syncing && syncProgress ? (
                        <div className="rounded-xl border-2 border-primary/25 bg-gradient-to-br from-primary/5 to-indigo-50/50 p-5 space-y-4">
                            <div className="flex items-center gap-3">
                                <div className="w-11 h-11 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                    <RefreshCw className="w-5 h-5 text-primary animate-spin" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-bold text-base text-gray-900">
                                        {t('Syncing day')} {syncProgress.current} / {syncProgress.total}
                                    </p>
                                    {syncProgress.date && (
                                        <p className="text-sm text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                            <CalendarIcon className="w-3.5 h-3.5" />
                                            {format(parseISO(syncProgress.date), 'dd MMM yyyy')}
                                        </p>
                                    )}
                                </div>
                                <span className="text-2xl font-black text-primary tabular-nums">{syncPercent}%</span>
                            </div>

                            <div className="h-3 bg-white/80 rounded-full overflow-hidden border border-primary/10 shadow-inner">
                                <div
                                    className="h-full bg-primary rounded-full transition-all duration-500 ease-out"
                                    style={{ width: `${syncPercent}%` }}
                                />
                            </div>

                            <div className="grid grid-cols-3 gap-2">
                                <div className="rounded-lg bg-white/80 border border-gray-100 p-2.5 text-center">
                                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground flex items-center justify-center gap-1">
                                        <Clock className="w-3 h-3" />
                                        {t('Elapsed')}
                                    </p>
                                    <p className="text-sm font-bold tabular-nums mt-1">{formatDuration(liveElapsedSec)}</p>
                                </div>
                                <div className="rounded-lg bg-white/80 border border-gray-100 p-2.5 text-center">
                                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{t('Last day')}</p>
                                    <p className="text-sm font-bold tabular-nums mt-1">
                                        {syncProgress.lastDaySec > 0 ? formatDuration(syncProgress.lastDaySec) : '—'}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-white/80 border border-gray-100 p-2.5 text-center">
                                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{t('Est. left')}</p>
                                    <p className="text-sm font-bold tabular-nums mt-1">
                                        {etaSec > 0
                                            ? `~${formatDuration(etaSec)}`
                                            : syncProgress.current >= syncProgress.total
                                              ? '—'
                                              : '…'}
                                    </p>
                                </div>
                            </div>

                            {syncProgress.current > 0 && (
                                <p className="text-[11px] text-center text-muted-foreground">
                                    {syncProgress.processedCount} {t('attendance rows')}
                                    {syncProgress.newEsslLogs > 0
                                        ? ` · ${syncProgress.newEsslLogs} ${t('new punches')}`
                                        : ` · ${t('no new punches (already synced)')}`}
                                </p>
                            )}

                            <p className="text-xs text-center text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                                {t('Please keep this window open until sync finishes.')}
                            </p>
                        </div>
                    ) : (
                        <>
                    {activeBranchLastSync ? (
                        <div className="bg-primary/5 border border-primary/10 rounded-lg p-3.5 text-xs text-primary/80 flex items-start gap-2.5 shadow-sm">
                            <Zap className="w-4 h-4 text-amber-500 mt-0.5 animate-pulse" />
                            <div className="space-y-1">
                                <p className="font-bold text-primary">{t('Last Sync Information')} ({activeBranchName})</p>
                                <p className="leading-relaxed">
                                    {t('Biometric logs are synchronized up to')} <strong className="text-primary font-bold">{format(new Date(activeBranchLastSync), activeBranchLastSync.includes(':') ? 'dd MMMM yyyy, hh:mm a' : 'dd MMMM yyyy')}</strong>.
                                    {t(' From date starts from that day (today is the default end date).')}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-amber-500/5 border border-amber-500/10 rounded-lg p-3.5 text-xs text-amber-700/80 flex items-start gap-2.5 shadow-sm">
                            <Zap className="w-4 h-4 text-amber-500 mt-0.5" />
                            <div className="space-y-1">
                                <p className="font-bold text-amber-800">{t('Last Sync Information')} ({activeBranchName})</p>
                                <p className="leading-relaxed">
                                    {t('No prior biometric synchronization logs found. Showing standard default settings.')}
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="space-y-2 mb-4">
                        <Label>{t('Employee')}</Label>
                        <Combobox
                            value={syncEmployeeId}
                            onChange={(v) => setSyncEmployeeId(v || 'all')}
                            placeholder={t('Select Employee (Default: All)')}
                            searchPlaceholder={t('Search by name or code...')}
                            options={[
                                { value: 'all', label: t('All Employees') },
                                ...(employees || []).map(emp => ({
                                    value: String(emp.id),
                                    label: emp.emp_code ? `${emp.name} (${emp.emp_code})` : emp.name
                                }))
                            ]}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('From Date')}</Label>
                            <Input
                                type="date"
                                value={syncDates.date_from}
                                max={todayStr}
                                onChange={(e) => setSyncDates(prev => ({ ...prev, date_from: e.target.value }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('To Date')}</Label>
                            <Input
                                type="date"
                                value={syncDates.date_to}
                                min={syncDates.date_from}
                                max={todayStr}
                                onChange={(e) => setSyncDates(prev => ({ ...prev, date_to: e.target.value }))}
                            />
                        </div>
                    </div>

                    {isResyncRange && (
                        <div className="text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3">
                            {t('Some dates in this range were already synced. Duplicate punches (same employee + same time) are skipped. Attendance for those days will be recalculated.')}
                        </div>
                    )}

                    <div className="text-xs text-muted-foreground bg-muted/50 rounded-lg p-3">
                        {t('Sync runs one day at a time to avoid server timeout on live site. Large ranges may take several minutes — keep this window open.')}
                    </div>
                        </>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" onClick={() => setIsSyncModalOpen(false)} disabled={syncing}>
                            {t('Cancel')}
                        </Button>
                        {!syncing && (
                            <Button onClick={handleSync}>
                                <ArrowRightLeft className="w-4 h-4 mr-2" />
                                {t('Start Sync')}
                            </Button>
                        )}
                        {syncing && (
                            <Button disabled>
                                <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                                {t('Syncing...')}
                            </Button>
                        )}
                    </div>
                </div>
            </Modal>
        </PageTemplate>
    );
};

export default EsslSyncReport;
