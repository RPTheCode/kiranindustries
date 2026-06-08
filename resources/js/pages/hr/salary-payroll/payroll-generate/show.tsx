import { Fragment, useEffect, useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft,
  Lock,
  Loader2,
  Users,
  Search,
  X,
  RefreshCw,
  Settings2,
  FilterX,
  ChevronDown,
  ChevronRight,
  FileDown,
  AlertTriangle,
} from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { canManageSalaryPayrollRuns, canFinalizeSalaryPayrollRuns } from '@/utils/authorization';
import { Pagination } from '@/components/ui/pagination';
import { Combobox } from '@/components/ui/combobox';
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
import { ConfirmActionDialog } from './components/ConfirmActionDialog';
import { PayrollEntryBreakdownPanel } from './components/PayrollEntryBreakdownPanel';
import { cn } from '@/lib/utils';

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDays(value: number) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  return n % 1 === 0 ? String(n) : n.toFixed(1);
}

function AttendanceDaysCell({
  entry,
  t,
}: {
  entry: any;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const working = Math.max(26, Number(entry.working_days ?? 26) || 26);
  const paid = Number(entry.paid_days ?? 0);
  const present = Number(entry.present_days ?? 0);
  const halfDays = Number(entry.half_days ?? 0);
  const weekOffWorkedDays = Number(entry.week_off_worked_days ?? 0);
  const incentiveDays = Number(entry.incentive_days ?? 0);
  const otEnabled = Boolean(entry.ot_enabled);
  const halfDayCredit = halfDays * 0.5;

  return (
    <div className="flex flex-col items-center gap-0.5">
      <div
        className={cn(
          'rounded-md px-2 py-0.5 text-[11px] font-bold tabular-nums leading-tight',
          paid >= working ? 'bg-emerald-100 text-emerald-800' : paid > 0 ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-500',
        )}
        title={halfDays > 0
          ? t('Paid {{paid}} of {{working}} — includes {{half}} half days (×0.5)', { paid: formatDays(paid), working, half: halfDays })
          : t('Paid {{paid}} of {{working}} working days', { paid: formatDays(paid), working })}
      >
        {formatDays(paid)}/{working}
      </div>
      <div className="flex flex-wrap items-center justify-center gap-1 text-[9px] text-slate-500">
        <span title={t('Present days (incl. half)')}>{t('Present')} {formatDays(present)}</span>
        {halfDays > 0 && (
          <span
            className="rounded bg-amber-100 px-1 py-px font-bold text-amber-800"
            title={t('{{count}} half days × 0.5 = {{credit}} paid days', { count: halfDays, credit: formatDays(halfDayCredit) })}
          >
            {t('HD')} {halfDays}
          </span>
        )}
        {weekOffWorkedDays > 0 && (
          <span
            className="rounded bg-indigo-100 px-1 py-px font-bold text-indigo-800"
            title={t('Week-off days worked — included in paid salary')}
          >
            {t('WO')} {formatDays(weekOffWorkedDays)}
          </span>
        )}
        <span
          className={cn(
            'rounded px-1 py-px font-bold uppercase',
            otEnabled ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-500',
          )}
          title={t('Overtime (P.I.) enabled on employee')}
        >
          {t('OT')} {otEnabled ? t('Yes') : t('No')}
        </span>
        {incentiveDays > 0 && (
          <span className="rounded bg-amber-100 px-1 py-px font-bold text-amber-800" title={t('Production incentive days')}>
            {t('PI')} {incentiveDays}
          </span>
        )}
        {entry.has_mispunch ? (
          <span className="inline-flex items-center rounded bg-amber-500 px-1 py-px font-bold text-white" title={t('Mispunch — fix before lock')}>
            <AlertTriangle className="h-2.5 w-2.5" />
            {entry.mispunch_count}
          </span>
        ) : (
          <span className="text-emerald-600" title={t('No mispunch')}>✓</span>
        )}
      </div>
    </div>
  );
}

function salaryDisplayMeta(entry: {
  monthly_gross: number;
  total_earnings: number;
  net_salary: number;
  daily_option?: boolean;
  employee_working_days?: number;
}) {
  const monthlyGross = Number(entry.monthly_gross ?? 0);
  const dailyOption = Boolean(entry.daily_option);
  const configDays = Number(entry.employee_working_days ?? 0);
  const salaryDays = configDays > 0 ? configDays : (dailyOption ? 1 : 26);

  if (dailyOption && salaryDays <= 1) {
    return {
      mode: 'day' as const,
      rate: monthlyGross,
      rateLabel: 'day',
      ctc: Math.round(monthlyGross * 26),
    };
  }

  if (dailyOption && salaryDays > 1) {
    return {
      mode: 'day' as const,
      rate: Math.round((monthlyGross / salaryDays) * 100) / 100,
      rateLabel: 'day',
      ctc: monthlyGross,
    };
  }

  return {
    mode: 'month' as const,
    rate: monthlyGross,
    rateLabel: 'month',
    ctc: monthlyGross,
  };
}

function SalaryCompactCell({ entry, formatRupee: fmt, t }: { entry: any; formatRupee: (v: number) => string; t: (k: string) => string }) {
  const meta = salaryDisplayMeta(entry);
  const incentiveAmount = Number(entry.incentive_amount ?? 0);
  const incentiveDays = Number(entry.incentive_days ?? 0);

  return (
    <div className="min-w-[118px] text-right text-[10px] leading-snug tabular-nums">
      <div className="mb-1 flex items-center justify-end gap-1">
        <span className={cn(
          'rounded px-1 py-px text-[8px] font-bold uppercase',
          meta.mode === 'day' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800',
        )}>
          {meta.mode === 'day' ? t('Day') : t('Month')}
        </span>
        <span className="font-semibold text-slate-800" title={t('Contract rate')}>
          ₹{fmt(meta.rate)}<span className="text-[9px] font-normal text-slate-500">/{meta.rateLabel === 'day' ? t('day') : t('mo')}</span>
        </span>
      </div>
      <div className="flex items-center justify-end gap-1.5 text-[9px] text-slate-400" title={t('Full month package (CTC)')}>
        <span>{t('CTC')}</span>
        <span className="text-slate-600">₹{fmt(meta.ctc)}</span>
      </div>
      {incentiveAmount > 0 && (
        <div className="flex items-center justify-end gap-1.5 text-[9px] text-amber-700" title={t('Production incentive for extra days')}>
          <span>{t('PI')} ({incentiveDays} {t('days')})</span>
          <span className="font-semibold">+ ₹{fmt(incentiveAmount)}</span>
        </div>
      )}
      <div
        className="my-0.5 flex items-center justify-end gap-1.5 rounded bg-emerald-50 px-1 py-0.5 text-[10px]"
        title={t('Salary after attendance — before deductions')}
      >
        <span className="font-medium text-emerald-800">{t('Total Salary')}</span>
        <span className="font-bold text-emerald-900">₹{fmt(entry.total_earnings)}</span>
      </div>
      {Number(entry.total_deductions) > 0 && (
        <div className="flex items-center justify-end gap-1.5 text-[9px] text-red-600" title={t('PF, PT & other deductions')}>
          <span>{t('Deductions')}</span>
          <span className="font-semibold">− ₹{fmt(entry.total_deductions)}</span>
        </div>
      )}
      <div
        className="flex items-center justify-end gap-1.5 border-t border-slate-200 pt-0.5 text-[11px]"
        title={t('Take-home after PF, PT & other deductions')}
      >
        <span className="font-semibold text-primary">{t('Net Salary')}</span>
        <span className="font-bold text-primary">₹{fmt(entry.net_salary)}</span>
      </div>
    </div>
  );
}

function statusBadge(status: string, t: (key: string) => string) {
  const map: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    calculated: 'bg-blue-100 text-blue-700',
    finalized: 'bg-green-100 text-green-700',
  };
  const label = status === 'finalized' ? t('Locked') : t(status.charAt(0).toUpperCase() + status.slice(1));
  return (
    <Badge className={`${map[status] || map.draft} border-0 text-[10px] uppercase`}>
      {label}
    </Badge>
  );
}

export default function PayrollGenerateShow() {
  const { t } = useTranslation();
  const {
    auth,
    run,
    entries,
    filters = {},
    categories = [],
    departments = [],
    shifts = [],
    flash,
    mispunch_count: mispunchCount = 0,
  } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const canFinalize = canFinalizeSalaryPayrollRuns(permissions);
  const canManage = canManageSalaryPayrollRuns(permissions);

  const [lockConfirmOpen, setLockConfirmOpen] = useState(false);
  const [regenerateConfirmOpen, setRegenerateConfirmOpen] = useState(false);
  const [entryRegenerateTarget, setEntryRegenerateTarget] = useState<{ id: number; name: string } | null>(null);
  const [entryLockTarget, setEntryLockTarget] = useState<{ id: number; name: string } | null>(null);
  const [isFinalizing, setIsFinalizing] = useState(false);
  const [isRegenerating, setIsRegenerating] = useState(false);
  const [regeneratingEntryId, setRegeneratingEntryId] = useState<number | null>(null);
  const [lockingEntryId, setLockingEntryId] = useState<number | null>(null);
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [expandedEntryIds, setExpandedEntryIds] = useState<Record<number, boolean>>({});

  const categoryFilter = filters.category_id ? String(filters.category_id) : 'all';
  const shiftFilter = filters.shift_id ? String(filters.shift_id) : 'all';
  const departmentFilter = filters.department_id ? String(filters.department_id) : 'all';
  const lockFilter = filters.lock_status || 'all';

  const lockStatusOptions = useMemo(() => [
    { label: t('All Lock Status'), value: 'all' },
    { label: t('Locked only'), value: 'locked' },
    { label: t('Unlocked only'), value: 'unlocked' },
  ], [t]);

  const categoryOptions = useMemo(() => [
    { label: t('All Categories'), value: 'all' },
    ...(categories as { id: number; name: string }[]).map((c) => ({ label: c.name, value: String(c.id) })),
  ], [categories, t]);

  const shiftOptions = useMemo(() => [
    { label: t('All Shifts'), value: 'all' },
    ...(shifts as { id: number; name: string }[]).map((s) => ({ label: s.name, value: String(s.id) })),
  ], [shifts, t]);

  const departmentOptions = useMemo(() => [
    { label: t('All Departments'), value: 'all' },
    ...(departments as { id: number; name: string }[]).map((d) => ({ label: d.name, value: String(d.id) })),
  ], [departments, t]);

  const hasActiveFilters = !!(
    filters.search
    || filters.category_id
    || filters.shift_id
    || filters.department_id
    || filters.lock_status
  );

  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  const handleFinalize = () => {
    setLockConfirmOpen(false);
    setIsFinalizing(true);
    router.post(route('hr.salary-payroll.generate.finalize', run.id), {}, {
      onFinish: () => setIsFinalizing(false),
    });
  };

  const handleRegenerate = () => {
    setRegenerateConfirmOpen(false);
    setIsRegenerating(true);
    router.post(route('hr.salary-payroll.generate.regenerate', run.id), {}, {
      onFinish: () => setIsRegenerating(false),
    });
  };

  const handleRegenerateEntry = () => {
    if (!entryRegenerateTarget) return;
    setRegeneratingEntryId(entryRegenerateTarget.id);
    router.post(
      route('hr.salary-payroll.generate.regenerate-entry', {
        salaryPayrollRun: run.id,
        salaryPayrollEntry: entryRegenerateTarget.id,
      }),
      {},
      {
        onFinish: () => {
          setRegeneratingEntryId(null);
          setEntryRegenerateTarget(null);
        },
      }
    );
  };

  const handleLockEntry = () => {
    if (!entryLockTarget) return;
    setLockingEntryId(entryLockTarget.id);
    router.post(
      route('hr.salary-payroll.generate.lock-entry', {
        salaryPayrollRun: run.id,
        salaryPayrollEntry: entryLockTarget.id,
      }),
      {},
      {
        onFinish: () => {
          setLockingEntryId(null);
          setEntryLockTarget(null);
        },
      }
    );
  };

  const isRunLocked = run?.status === 'finalized';
  const lockedCount = run?.locked_entry_count ?? 0;
  const unlockedCount = run?.unlocked_entry_count ?? 0;
  const payslipCount = run?.payslip_count ?? 0;
  const hasPartialLocks = !isRunLocked && lockedCount > 0;
  const canDownloadAnyPayslip = isRunLocked || lockedCount > 0;
  const handleDownloadPayslip = (entryId: number) => {
    window.location.href = route('hr.salary-payroll.generate.download-payslip', {
      salaryPayrollRun: run.id,
      salaryPayrollEntry: entryId,
    });
  };

  const handleDownloadAllPayslips = () => {
    window.location.href = route('hr.salary-payroll.generate.download-all-payslips', run.id);
  };

  const showRowActions = !isRunLocked && (canManage || canFinalize);
  const showPayslipActions = canDownloadAnyPayslip;
  const usesAttendance = run?.use_attendance !== false;
  const tableColSpan = (usesAttendance ? 5 : 4) + (showRowActions || showPayslipActions ? 1 : 0);

  const toggleEntryExpand = (entryId: number) => {
    setExpandedEntryIds((prev) => {
      const isOpen = !!prev[entryId];
      if (isOpen) return {};
      return { [entryId]: true };
    });
  };

  const expandedCount = Object.values(expandedEntryIds).filter(Boolean).length;

  const collapseAll = () => setExpandedEntryIds({});

  const applyFilters = (overrides: Record<string, string | number | undefined> = {}) => {
    const nextCategory = overrides.category_id !== undefined
      ? overrides.category_id
      : (categoryFilter !== 'all' ? categoryFilter : undefined);
    const nextShift = overrides.shift_id !== undefined
      ? overrides.shift_id
      : (shiftFilter !== 'all' ? shiftFilter : undefined);
    const nextDepartment = overrides.department_id !== undefined
      ? overrides.department_id
      : (departmentFilter !== 'all' ? departmentFilter : undefined);
    const nextLockStatus = overrides.lock_status !== undefined
      ? overrides.lock_status
      : (lockFilter !== 'all' ? lockFilter : undefined);

    router.get(
      route('hr.salary-payroll.generate.show', run.id),
      {
        search: overrides.search !== undefined ? overrides.search || undefined : searchTerm || undefined,
        category_id: nextCategory && nextCategory !== 'all' ? nextCategory : undefined,
        shift_id: nextShift && nextShift !== 'all' ? nextShift : undefined,
        department_id: nextDepartment && nextDepartment !== 'all' ? nextDepartment : undefined,
        lock_status: nextLockStatus && nextLockStatus !== 'all' ? nextLockStatus : undefined,
        per_page: overrides.per_page ?? filters.per_page ?? 50,
        page: overrides.page ?? 1,
      },
      { preserveState: true, replace: true }
    );
  };

  const handleSearch = () => applyFilters({ search: searchTerm, page: 1 });

  const handleClearFilters = () => {
    setSearchTerm('');
    router.get(route('hr.salary-payroll.generate.show', run.id), {
      per_page: filters.per_page ?? 50,
    }, { preserveState: true, replace: true });
  };

  const handlePageChange = (url: string) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Payroll'), href: route('hr.salary-payroll.generate.index') },
    { title: run?.title || t('Payroll Run') },
  ];

  const entryRows = entries?.data ?? [];

  const expandAllOnPage = () => {
    const next: Record<number, boolean> = {};
    entryRows.forEach((entry: { id: number }) => { next[entry.id] = true; });
    setExpandedEntryIds(next);
  };

  return (
    <PageTemplate title={run?.title || t('Payroll Run')} url={route('hr.salary-payroll.generate.show', run?.id)} breadcrumbs={breadcrumbs}>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/80 p-3 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <p className="text-sm font-semibold text-slate-800">{run?.title}</p>
            {statusBadge(run?.status, t)}
            {hasPartialLocks && (
              <>
                <Badge className="border-0 bg-green-100 text-[10px] uppercase text-green-700">
                  <Lock className="mr-0.5 inline h-2.5 w-2.5" />
                  {lockedCount} {t('Locked')}
                </Badge>
                <Badge className="border-0 bg-amber-100 text-[10px] uppercase text-amber-800">
                  {unlockedCount} {t('Unlocked')}
                </Badge>
              </>
            )}
          </div>
          <p className="mt-0.5 text-[11px] text-slate-500">
            {run?.pay_period_start} → {run?.pay_period_end} · {run?.scope_label} · FY {run?.financial_year}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" asChild>
            <Link href={route('hr.salary-payroll.generate.index')}>
              <ArrowLeft className="mr-1.5 h-4 w-4" />
              {t('Back')}
            </Link>
          </Button>
          {canManage && !isRunLocked && (
            <>
              <Button variant="outline" size="sm" onClick={() => setRegenerateConfirmOpen(true)} disabled={isRegenerating || unlockedCount === 0}>
                {isRegenerating ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-1.5 h-4 w-4" />}
                {lockedCount > 0
                  ? t('Regenerate Unlocked ({{count}})', { count: unlockedCount })
                  : t('Regenerate All')}
              </Button>
              {lockedCount > 0 ? (
                <Button variant="outline" size="sm" disabled title={t('Cannot customize while individual employees are locked.')}>
                  <Settings2 className="mr-1.5 h-4 w-4" />
                  {t('Customize')}
                </Button>
              ) : (
                <Button variant="outline" size="sm" asChild>
                  <Link href={route('hr.salary-payroll.generate.edit', run.id)}>
                    <Settings2 className="mr-1.5 h-4 w-4" />
                    {t('Customize')}
                  </Link>
                </Button>
              )}
            </>
          )}
          {canFinalize && run?.status === 'calculated' && (
            <Button size="sm" onClick={() => setLockConfirmOpen(true)} disabled={isFinalizing}>
              {isFinalizing ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Lock className="mr-1.5 h-4 w-4" />}
              {t('Lock Payroll')}
            </Button>
          )}
          {canDownloadAnyPayslip && (
            <Button variant="outline" size="sm" onClick={handleDownloadAllPayslips}>
              <FileDown className="mr-1.5 h-4 w-4" />
              {payslipCount > 0
                ? t('Download Payslips ({{count}})', { count: payslipCount })
                : t('Download Locked Payslips')}
            </Button>
          )}
        </div>
      </div>

      <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-sm">
        <span className="font-semibold text-slate-800">{t('Gross')} <span className="text-blue-700">₹{formatRupee(Number(run?.total_gross || 0))}</span></span>
        <span className="text-slate-300">|</span>
        <span className="font-semibold text-slate-800">{t('Net')} <span className="text-green-700">₹{formatRupee(Number(run?.total_net || 0))}</span></span>
        <span className="text-slate-300">|</span>
        <span className="text-slate-600"><Users className="mr-1 inline h-3.5 w-3.5" />{run?.employee_count} {t('emp')}</span>
        {!isRunLocked && (
          <>
            <span className="text-slate-300">|</span>
            <span className="text-green-700">{lockedCount} {t('locked')}</span>
            <span className="text-amber-700">{unlockedCount} {t('unlocked')}</span>
          </>
        )}
        {usesAttendance && mispunchCount > 0 && (
          <>
            <span className="text-slate-300">|</span>
            <Link href={route('hr.attendance.sync')} className="inline-flex items-center gap-1 font-semibold text-amber-700 hover:underline">
              <AlertTriangle className="h-3.5 w-3.5" />
              {mispunchCount} {t('mispunch')}
            </Link>
          </>
        )}
      </div>

      {(usesAttendance || !isRunLocked) && (
        <div className={cn(
          'mb-3 rounded-lg border px-3 py-2 text-[11px] leading-snug',
          usesAttendance && mispunchCount > 0 ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-blue-100 bg-blue-50/70 text-blue-900',
        )}>
          {usesAttendance ? (
            <>
              {t('Total Salary = rate × paid days ÷ 26. Deductions = PF & PT. Net Salary = take-home.')}
              {mispunchCount > 0 && (
                <> {t('Fix {{n}} mispunch before lock.', { n: mispunchCount })}</>
              )}
            </>
          ) : (
            t('Total Salary = full month. Deductions = PF & PT. Net Salary = take-home.')
          )}
        </div>
      )}

      {hasPartialLocks && (
        <div className="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-900">
          <span>{lockedCount} {t('locked')} · {unlockedCount} {t('open')}</span>
          <Button variant="outline" size="sm" className="h-6 border-green-300 bg-white px-2 text-[10px] text-green-800" onClick={() => applyFilters({ lock_status: 'locked', page: 1 })}>
            {t('Locked')}
          </Button>
          <Button variant="outline" size="sm" className="h-6 border-amber-300 bg-white px-2 text-[10px] text-amber-900" onClick={() => applyFilters({ lock_status: 'unlocked', page: 1 })}>
            {t('Open')}
          </Button>
          {lockFilter !== 'all' && (
            <Button variant="ghost" size="sm" className="h-6 px-2 text-[10px]" onClick={() => applyFilters({ lock_status: 'all', page: 1 })}>
              {t('All')}
            </Button>
          )}
        </div>
      )}

      {isRunLocked && (
        <div className="mb-3 rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-[11px] text-green-800">
          {t('Locked')} · {run.finalized_at}{run.finalizer?.name ? ` · ${run.finalizer.name}` : ''}
          {payslipCount > 0 && <> · {t('{{count}} payslip(s) ready', { count: payslipCount })}</>}
        </div>
      )}

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-3 py-2">
          <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h3 className="text-xs font-bold text-slate-800">
              {t('Employees')}
              <span className="ml-1.5 font-normal text-slate-500">— {t('click row for salary breakdown')}</span>
            </h3>
            <div className="flex flex-wrap items-center gap-2">
              {entryRows.length > 0 && (
                <>
                  <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={expandAllOnPage}>
                    {t('Expand all on page')}
                  </Button>
                  {expandedCount > 0 && (
                    <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={collapseAll}>
                      {t('Collapse all')}
                    </Button>
                  )}
                </>
              )}
              {hasActiveFilters && (
                <Button variant="ghost" size="sm" className="h-8 text-xs text-slate-600" onClick={handleClearFilters}>
                  <FilterX className="mr-1.5 h-3.5 w-3.5" />
                  {t('Clear filters')}
                </Button>
              )}
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-1.5">
            <div className="relative">
              <Search className="absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
              <Input
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                placeholder={t('Search...')}
                className="h-7 w-[120px] pl-7 text-[11px]"
              />
              {searchTerm && (
                <button type="button" onClick={() => { setSearchTerm(''); applyFilters({ search: '', page: 1 }); }} className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
            <Button variant="outline" size="sm" className="h-7 px-2 text-[11px]" onClick={handleSearch}>
              {t('Go')}
            </Button>
            <Combobox
              value={categoryFilter}
              onChange={(v) => applyFilters({ category_id: v || 'all', page: 1 })}
              options={categoryOptions}
              placeholder={t('Category')}
              searchPlaceholder={t('Search...')}
              emptyText={t('None')}
              className="h-7 w-[110px] text-[11px]"
            />
            <Combobox
              value={shiftFilter}
              onChange={(v) => applyFilters({ shift_id: v || 'all', page: 1 })}
              options={shiftOptions}
              placeholder={t('Shift')}
              searchPlaceholder={t('Search...')}
              emptyText={t('None')}
              className="h-7 w-[100px] text-[11px]"
            />
            <Combobox
              value={departmentFilter}
              onChange={(v) => applyFilters({ department_id: v || 'all', page: 1 })}
              options={departmentOptions}
              placeholder={t('Dept')}
              searchPlaceholder={t('Search...')}
              emptyText={t('None')}
              className="h-7 w-[100px] text-[11px]"
            />
            {!isRunLocked && (
              <Combobox
                value={lockFilter}
                onChange={(v) => applyFilters({ lock_status: v || 'all', page: 1 })}
                options={lockStatusOptions}
                placeholder={t('Lock')}
                searchPlaceholder={t('Search...')}
                emptyText={t('None')}
                className="h-7 w-[90px] text-[11px]"
              />
            )}
            <Select
              value={String(filters.per_page ?? 50)}
              onValueChange={(v) => applyFilters({ per_page: Number(v), page: 1 })}
            >
              <SelectTrigger className="h-7 w-[72px] text-[11px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {[25, 50, 100, 200].map((n) => (
                  <SelectItem key={n} value={String(n)}>{n} / {t('page')}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <Table className="w-full">
            <colgroup>
              <col className="w-[52px]" />
              <col />
              {usesAttendance && <col className="w-[72px]" />}
              <col className="w-[142px]" />
              {(showRowActions || showPayslipActions) && <col className="w-[80px]" />}
            </colgroup>
            <TableHeader className="bg-slate-50">
              <TableRow className="hover:bg-transparent">
                <TableHead className="h-8 px-1 text-center text-[10px]">{t('Details')}</TableHead>
                <TableHead className="h-8 px-2 text-[10px]">{t('Employee')}</TableHead>
                {usesAttendance && (
                  <TableHead className="h-8 px-1 text-center text-[10px]" title={t('Paid days / Working days')}>
                    {t('Days')}
                  </TableHead>
                )}
                <TableHead className="h-8 px-2 text-right text-[10px]" title={t('Rate → CTC → Total Salary → Deductions → Net Salary')}>
                  {t('Pay')}
                </TableHead>
                {(showRowActions || showPayslipActions) && (
                  <TableHead className="h-8 px-1 text-right text-[10px]">
                    {showRowActions ? t('Actions') : 'PDF'}
                  </TableHead>
                )}
              </TableRow>
            </TableHeader>
            <TableBody>
              {entryRows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={tableColSpan} className="py-10 text-center text-sm text-slate-400">
                    {hasActiveFilters ? t('No employees match your filters.') : t('No entries in this run.')}
                  </TableCell>
                </TableRow>
              ) : entryRows.map((entry: any) => {
                const isExpanded = !!expandedEntryIds[entry.id];
                return (
                  <Fragment key={entry.id}>
                    <TableRow
                      className={cn(
                        'cursor-pointer transition-colors',
                        entry.is_locked ? 'bg-green-50/50' : entry.has_mispunch ? 'bg-amber-50/30' : isExpanded ? 'bg-primary/5 ring-1 ring-inset ring-primary/20' : 'hover:bg-slate-50',
                      )}
                      onClick={() => toggleEntryExpand(entry.id)}
                      title={isExpanded ? t('Click to close breakdown') : t('Click to see salary breakdown')}
                    >
                      <TableCell className="px-1 py-1.5 align-top">
                        <button
                          type="button"
                          className={cn(
                            'flex w-full flex-col items-center justify-center rounded-md border px-0.5 py-1 text-[9px] font-medium leading-none',
                            isExpanded
                              ? 'border-primary/30 bg-primary/10 text-primary'
                              : 'border-slate-200 bg-white text-slate-500 hover:border-primary/30 hover:bg-primary/5 hover:text-primary',
                          )}
                          onClick={(e) => { e.stopPropagation(); toggleEntryExpand(entry.id); }}
                          aria-expanded={isExpanded}
                          aria-label={isExpanded ? t('Close breakdown') : t('View breakdown')}
                        >
                          {isExpanded ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
                          <span className="mt-0.5">{isExpanded ? t('Hide') : t('View')}</span>
                        </button>
                      </TableCell>
                      <TableCell className="min-w-0 px-2 py-1.5 align-top">
                        <div className="flex items-start gap-1.5 min-w-0">
                          {entry.is_locked && <Lock className="mt-0.5 h-3 w-3 shrink-0 text-green-600" />}
                          <div className="min-w-0 flex-1">
                            <div className="truncate text-xs font-semibold text-slate-900">{entry.name}</div>
                            <div className="truncate text-[10px] text-slate-500">
                              {entry.employee_code || '—'}
                              {(entry.category || entry.shift) && (
                                <> · {[entry.category, entry.shift].filter(Boolean).join(' · ')}</>
                              )}
                            </div>
                          </div>
                        </div>
                      </TableCell>
                      {usesAttendance && (
                        <TableCell className="whitespace-nowrap px-1 py-1.5">
                          <AttendanceDaysCell entry={entry} t={t} />
                        </TableCell>
                      )}
                      <TableCell className="whitespace-nowrap px-2 py-1.5">
                        <SalaryCompactCell entry={entry} formatRupee={formatRupee} t={t} />
                      </TableCell>
                      {(showRowActions || showPayslipActions) && (
                        <TableCell className="whitespace-nowrap px-1 py-1.5 text-right" onClick={(e) => e.stopPropagation()}>
                          <div className="flex items-center justify-end gap-0">
                            {entry.can_download_payslip && (
                              <Button variant="ghost" size="icon" className="h-7 w-7 text-green-700" title={t('Download payslip')} onClick={() => handleDownloadPayslip(entry.id)}>
                                <FileDown className="h-3.5 w-3.5" />
                              </Button>
                            )}
                            {canManage && !entry.is_locked && (
                              <Button variant="ghost" size="icon" className="h-7 w-7 text-primary" title={t('Regenerate')} disabled={regeneratingEntryId === entry.id || isRegenerating} onClick={() => setEntryRegenerateTarget({ id: entry.id, name: entry.name })}>
                                {regeneratingEntryId === entry.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
                              </Button>
                            )}
                            {canFinalize && !entry.is_locked && (
                              <Button variant="ghost" size="icon" className="h-7 w-7 text-amber-600" title={t('Lock')} disabled={lockingEntryId === entry.id} onClick={() => setEntryLockTarget({ id: entry.id, name: entry.name })}>
                                {lockingEntryId === entry.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Lock className="h-3.5 w-3.5" />}
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      )}
                    </TableRow>
                    {isExpanded && (
                      <TableRow className="hover:bg-transparent bg-slate-50/50">
                        <TableCell colSpan={tableColSpan} className="w-full p-0">
                          <div className="w-full border-t border-slate-200">
                            <PayrollEntryBreakdownPanel
                              entry={entry}
                              runUsesAttendance={usesAttendance}
                              onClose={() => toggleEntryExpand(entry.id)}
                            />
                          </div>
                        </TableCell>
                      </TableRow>
                    )}
                  </Fragment>
                );
              })}
            </TableBody>
          </Table>
        {(entries?.total ?? 0) > 0 && (
          <Pagination
            from={entries.from ?? 0}
            to={entries.to ?? 0}
            total={entries.total ?? 0}
            links={entries.links ?? []}
            entityName={t('employees')}
            onPageChange={handlePageChange}
            className="border-t border-slate-100 bg-slate-50/50 px-3 py-2"
          />
        )}
      </div>

      <ConfirmActionDialog
        open={regenerateConfirmOpen}
        onOpenChange={setRegenerateConfirmOpen}
        title={lockedCount > 0 ? t('Regenerate Unlocked Employees?') : t('Regenerate All Employees?')}
        description={lockedCount > 0
          ? t('{{unlocked}} unlocked employee(s) in "{{title}}" will be recalculated with latest salary data. {{locked}} locked employee(s) will NOT change.', {
            title: run?.title || '',
            unlocked: unlockedCount,
            locked: lockedCount,
          })
          : t('Recalculate "{{title}}" with the latest employee salaries and payroll settings. All {{count}} entries will be replaced.', {
            title: run?.title || '',
            count: run?.employee_count || 0,
          })}
        confirmLabel={lockedCount > 0 ? t('Regenerate Unlocked ({{count}})', { count: unlockedCount }) : t('Regenerate All')}
        cancelLabel={t('Cancel')}
        variant="primary"
        icon={<RefreshCw className="h-6 w-6" />}
        loading={isRegenerating}
        onConfirm={handleRegenerate}
      />

      <ConfirmActionDialog
        open={!!entryRegenerateTarget}
        onOpenChange={(open) => !open && setEntryRegenerateTarget(null)}
        title={t('Regenerate Employee?')}
        description={t('Recalculate payroll for "{{name}}" using the latest salary and settings. Only this employee will be updated.', {
          name: entryRegenerateTarget?.name || '',
        })}
        confirmLabel={t('Regenerate')}
        cancelLabel={t('Cancel')}
        variant="primary"
        icon={<RefreshCw className="h-6 w-6" />}
        loading={regeneratingEntryId !== null}
        onConfirm={handleRegenerateEntry}
      />

      <ConfirmActionDialog
        open={!!entryLockTarget}
        onOpenChange={(open) => !open && setEntryLockTarget(null)}
        title={t('Lock Employee?')}
        description={t('Lock payroll for "{{name}}"? Payslip PDF will be generated automatically.', {
          name: entryLockTarget?.name || '',
        })}
        confirmLabel={t('Lock Employee')}
        cancelLabel={t('Cancel')}
        variant="warning"
        icon={<Lock className="h-6 w-6" />}
        loading={lockingEntryId !== null}
        onConfirm={handleLockEntry}
      />

      <ConfirmActionDialog
        open={lockConfirmOpen}
        onOpenChange={setLockConfirmOpen}
        title={t('Lock Payroll?')}
        description={lockedCount > 0
          ? t('Lock all remaining {{count}} unlocked employee(s) and finalize "{{title}}". This cannot be undone.', {
            count: unlockedCount,
            title: run?.title || '',
          })
          : t('Once locked, "{{title}}" cannot be regenerated, customized, or deleted. Are you sure you want to lock this payroll?', { title: run?.title || '' })}
        confirmLabel={t('Lock Payroll')}
        cancelLabel={t('Cancel')}
        variant="warning"
        icon={<Lock className="h-6 w-6" />}
        loading={isFinalizing}
        onConfirm={handleFinalize}
      />
    </PageTemplate>
  );
}
