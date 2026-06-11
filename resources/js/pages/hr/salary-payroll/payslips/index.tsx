import { PageTemplate } from '@/components/page-template';
import { PayrollMonthNavigator } from '@/components/salary-payroll/PayrollMonthNavigator';
import { PayslipPreviewDialog } from '@/components/salary-payroll/PayslipPreviewDialog';
import { toast } from '@/components/custom-toast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { canAccessPayslips, canDownloadPayslips } from '@/utils/authorization';
import { getImagePath } from '@/utils/helpers';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    CalendarDays,
    Download,
    ExternalLink,
    Eye,
    FileText,
    Search,
    Users,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

type PayslipRow = {
    id: number;
    run_id: number;
    employee: {
        id: number;
        name: string;
        avatar?: string | null;
        employee_code?: string | null;
    };
    pay_date?: string | null;
    net_pay: number;
    payslip_number?: string | null;
    status: 'ready' | 'generated' | 'downloaded';
    generated_at?: string | null;
    can_download: boolean;
};

function formatRupee(value: number) {
    if (window.appSettings?.formatCurrency) {
        return window.appSettings.formatCurrency(value);
    }
    return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(value?: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function statusBadge(status: PayslipRow['status'], t: (key: string) => string) {
    const map = {
        ready: 'bg-amber-100 text-amber-800 border-amber-200',
        generated: 'bg-blue-100 text-blue-800 border-blue-200',
        downloaded: 'bg-violet-100 text-violet-800 border-violet-200',
    };
    const labels = {
        ready: t('Ready'),
        generated: t('Generated'),
        downloaded: t('Downloaded'),
    };

    return (
        <Badge variant="outline" className={`${map[status]} text-[10px] font-semibold uppercase`}>
            {labels[status]}
        </Badge>
    );
}

function EmployeeAvatar({ name, avatar }: { name?: string; avatar?: string | null }) {
    const initials = (name || '?')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

    if (avatar) {
        return (
            <img
                src={getImagePath(avatar)}
                alt={name || ''}
                className="h-9 w-9 rounded-full border object-cover"
            />
        );
    }

    return (
        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            {initials}
        </div>
    );
}

export default function PayslipsIndex() {
    const { t } = useTranslation();
    const {
        auth,
        payslips,
        summary,
        run,
        financialYear,
        financialYearOptions = [],
        monthYear,
        months = [],
        activeBranchName,
        filters = {},
        flash,
    } = usePage().props as any;

    const permissions = auth?.permissions || [];
    const canView = canAccessPayslips(permissions);
    const canDownload = canDownloadPayslips(permissions);

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [previewRow, setPreviewRow] = useState<PayslipRow | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const navigate = (overrides: Record<string, string | number | undefined> = {}) => {
        router.get(
            route('hr.salary-payroll.payslips.index'),
            {
                financial_year: financialYear,
                month_year: monthYear,
                search: search || undefined,
                status: status !== 'all' ? status : undefined,
                per_page: filters.per_page || 10,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        navigate({ search: search || undefined });
    };

    const previewUrl = previewRow
        ? route('hr.salary-payroll.payslips.preview', {
              salaryPayrollRun: previewRow.run_id,
              salaryPayrollEntry: previewRow.id,
          })
        : null;

    const openPreview = useCallback((row: PayslipRow) => {
        if (!row.can_download) {
            toast.error(t('Payslip is available only for locked or finalized payroll entries.'));
            return;
        }
        setPreviewRow(row);
        setPreviewLoading(true);
    }, [t]);

    const closePreview = useCallback(() => {
        setPreviewRow(null);
        setPreviewLoading(false);
    }, []);

    const downloadPayslip = useCallback((row: PayslipRow, fromPreview = false) => {
        if (!canDownload || !row.can_download) {
            return;
        }

        toast.success(t('Downloading payslip...'));
        window.location.href = route('hr.salary-payroll.payslips.download', {
            salaryPayrollRun: row.run_id,
            salaryPayrollEntry: row.id,
        });

        window.setTimeout(() => {
            router.reload({ only: ['payslips', 'summary'] });
            if (fromPreview) {
                closePreview();
            }
        }, 1200);
    }, [canDownload, closePreview, t]);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Salary Payroll') },
        { title: t('Payslips') },
    ];

    const pageActions = [];
    if (run?.id) {
        pageActions.push({
            label: t('Open Payroll Run'),
            icon: <ExternalLink className="mr-2 h-4 w-4" />,
            variant: 'outline' as const,
            onClick: () => router.visit(route('hr.salary-payroll.generate.show', run.id)),
        });
    }
    if (canDownload && run?.id && summary?.employee_count > 0) {
        pageActions.push({
            label: t('Download All'),
            icon: <Download className="mr-2 h-4 w-4" />,
            variant: 'outline' as const,
            onClick: () => {
                toast.success(t('Preparing ZIP download...'));
                window.location.href = route('hr.salary-payroll.generate.download-all-payslips', run.id);
            },
        });
    }

    if (!canView) {
        return (
            <PageTemplate title={t('Payslips')} breadcrumbs={breadcrumbs}>
                <p className="py-12 text-center text-sm text-slate-500">{t('You do not have permission to view payslips.')}</p>
            </PageTemplate>
        );
    }

    return (
        <PageTemplate
            title={t('Payslips')}
            description={t('View payslips in preview, then download PDF when ready.')}
            breadcrumbs={breadcrumbs}
            actions={pageActions}
        >
            <div className="space-y-4">
                <div className="rounded-xl border bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:from-slate-900 dark:to-slate-950">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{t('Financial Year')}</p>
                            <Select
                                value={financialYear}
                                onValueChange={(fy) => navigate({ financial_year: fy, month_year: undefined })}
                            >
                                <SelectTrigger className="mt-1 h-10 w-[180px] bg-white dark:bg-slate-950">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {financialYearOptions.map((fy: string) => (
                                        <SelectItem key={fy} value={fy}>
                                            FY {fy}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {activeBranchName ? (
                                <p className="mt-2 text-xs text-slate-500">
                                    {t('Branch')}: <span className="font-medium text-slate-700 dark:text-slate-300">{activeBranchName}</span>
                                </p>
                            ) : null}
                        </div>

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <SummaryCard
                                icon={<Users className="h-4 w-4 text-primary" />}
                                label={t('Employees')}
                                value={String(summary?.employee_count ?? 0)}
                            />
                            <SummaryCard
                                icon={<FileText className="h-4 w-4 text-blue-600" />}
                                label={t('Generated')}
                                value={String(summary?.generated_count ?? 0)}
                            />
                            <SummaryCard
                                icon={<Banknote className="h-4 w-4 text-violet-600" />}
                                label={t('Total Net Pay')}
                                value={formatRupee(summary?.total_net ?? 0)}
                            />
                            <SummaryCard
                                icon={<CalendarDays className="h-4 w-4 text-slate-600" />}
                                label={t('Payroll')}
                                value={run?.status ? t(run.status === 'finalized' ? 'Locked' : run.status.charAt(0).toUpperCase() + run.status.slice(1)) : '—'}
                            />
                        </div>
                    </div>
                </div>

                <div className="sticky top-0 z-10 rounded-xl border bg-white/95 p-4 shadow-sm backdrop-blur dark:bg-slate-950/95">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium text-slate-700 dark:text-slate-300">{t('Select Month')}</p>
                        <p className="text-[11px] text-slate-500">{t('Badge number = payslips available in that month')}</p>
                    </div>
                    <PayrollMonthNavigator
                        months={months}
                        selectedMonth={monthYear}
                        onSelect={(value) => navigate({ month_year: value })}
                    />
                    {run ? (
                        <p className="mt-3 text-xs text-slate-500">
                            {run.title} · {formatDate(run.pay_period_start)} → {formatDate(run.pay_period_end)}
                        </p>
                    ) : (
                        <p className="mt-3 text-xs text-amber-700 dark:text-amber-400">
                            {t('No payroll generated for this month yet.')}{' '}
                            <Link href={route('hr.salary-payroll.generate.create')} className="font-medium underline">
                                {t('Generate Payroll')}
                            </Link>
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 shadow-sm dark:bg-slate-950">
                    <form onSubmit={handleSearch} className="flex min-w-[220px] flex-1 flex-wrap items-center gap-2">
                        <div className="relative min-w-[200px] flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('Search employee name or ID...')}
                                className="h-10 bg-white pl-9 dark:bg-slate-950"
                            />
                        </div>
                        <Button type="submit" className="h-10">
                            {t('Search')}
                        </Button>
                    </form>

                    <Select
                        value={status}
                        onValueChange={(value) => {
                            setStatus(value);
                            navigate({ status: value !== 'all' ? value : undefined });
                        }}
                    >
                        <SelectTrigger className="h-10 w-[160px] bg-white dark:bg-slate-950">
                            <SelectValue placeholder={t('Status')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('All Status')}</SelectItem>
                            <SelectItem value="ready">{t('Ready')}</SelectItem>
                            <SelectItem value="generated">{t('Generated')}</SelectItem>
                            <SelectItem value="downloaded">{t('Downloaded')}</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={String(filters.per_page || 10)}
                        onValueChange={(value) => navigate({ per_page: value })}
                    >
                        <SelectTrigger className="h-10 w-[120px] bg-white dark:bg-slate-950">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[10, 25, 50, 100].map((n) => (
                                <SelectItem key={n} value={String(n)}>
                                    {n} {t('per page')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-slate-950">
                    {!run ? (
                        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                            <FileText className="h-10 w-10 text-slate-300" />
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                {t('No payslips for this month')}
                            </p>
                            <p className="max-w-md text-xs text-slate-500">
                                {t('Generate payroll for the selected month first. Payslips appear after employees are locked or payroll is finalized.')}
                            </p>
                        </div>
                    ) : payslips?.data?.length ? (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-slate-50/80 dark:bg-slate-900/50">
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>{t('Employee')}</TableHead>
                                        <TableHead>{t('Pay Date')}</TableHead>
                                        <TableHead className="text-right">{t('Net Pay')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead>{t('Generated On')}</TableHead>
                                        <TableHead className="w-24 text-right">{t('Actions')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payslips.data.map((row: PayslipRow, index: number) => (
                                        <TableRow
                                            key={row.id}
                                            className={cn(
                                                row.can_download && 'cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-900/40'
                                            )}
                                            onClick={() => row.can_download && openPreview(row)}
                                        >
                                            <TableCell className="text-slate-500">
                                                {(payslips.from ?? 0) + index}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <EmployeeAvatar name={row.employee?.name} avatar={row.employee?.avatar} />
                                                    <div>
                                                        <p className="font-medium text-sm">{row.employee?.name}</p>
                                                        <p className="text-xs text-slate-500">
                                                            {row.employee?.employee_code || '—'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">{formatDate(row.pay_date)}</TableCell>
                                            <TableCell className="text-right font-medium text-sm">
                                                {formatRupee(row.net_pay)}
                                            </TableCell>
                                            <TableCell>{statusBadge(row.status, t)}</TableCell>
                                            <TableCell className="text-sm text-slate-600">
                                                {formatDate(row.generated_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {row.can_download ? (
                                                    <div
                                                        className="flex items-center justify-end gap-0.5"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-8 w-8"
                                                            title={t('View Payslip')}
                                                            onClick={() => openPreview(row)}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {canDownload ? (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="h-8 w-8"
                                                                title={t('Download PDF')}
                                                                onClick={() => downloadPayslip(row)}
                                                            >
                                                                <Download className="h-4 w-4" />
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {payslips?.last_page > 1 ? (
                                <div className="border-t p-4">
                                    <Pagination
                                        from={payslips.from || 0}
                                        to={payslips.to || 0}
                                        total={payslips.total || 0}
                                        links={payslips.links}
                                        entityName={t('payslips')}
                                        onPageChange={(url) => router.get(url)}
                                    />
                                </div>
                            ) : null}
                        </>
                    ) : (
                        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                            <Users className="h-10 w-10 text-slate-300" />
                            <p className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                {t('No locked employees for this payroll')}
                            </p>
                            <p className="max-w-md text-xs text-slate-500">
                                {t('Lock employees in Generate Payroll or finalize the run to enable payslip download.')}
                            </p>
                            <Button asChild variant="outline" size="sm" className="mt-2">
                                <Link href={route('hr.salary-payroll.generate.show', run.id)}>
                                    {t('Open Payroll Run')}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            <PayslipPreviewDialog
                open={!!previewRow}
                onOpenChange={(open) => !open && closePreview()}
                title={previewRow?.employee?.name ?? t('Payslip')}
                subtitle={
                    previewRow
                        ? `${previewRow.employee?.employee_code || ''} · ${formatRupee(previewRow.net_pay)}`
                        : undefined
                }
                previewUrl={previewUrl}
                loading={previewLoading}
                onLoad={() => setPreviewLoading(false)}
                canDownload={canDownload}
                onDownload={previewRow ? () => downloadPayslip(previewRow, true) : undefined}
            />
        </PageTemplate>
    );
}

function SummaryCard({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-lg border bg-white px-3 py-2.5 dark:bg-slate-900">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">{icon}{label}</div>
            <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{value}</p>
        </div>
    );
}
