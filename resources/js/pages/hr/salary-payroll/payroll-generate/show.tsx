import { Fragment, useEffect, useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft,
  Lock,
  Loader2,
  IndianRupee,
  Users,
  ShieldCheck,
  Search,
  X,
  RefreshCw,
  Settings2,
  FilterX,
  ChevronDown,
  ChevronRight,
  FileDown,
} from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { hasPermission } from '@/utils/authorization';
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
import { StatutoryAmountCell, StatutoryLegend } from './components/StatutoryIndicators';
import { cn } from '@/lib/utils';

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
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
  } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const canFinalize = hasPermission(permissions, 'finalize-salary-payroll-runs')
    || hasPermission(permissions, 'manage-employee-salaries')
    || hasPermission(permissions, 'manage-any-employee-salaries');
  const canManage = hasPermission(permissions, 'create-salary-payroll-runs')
    || hasPermission(permissions, 'create-employee-salaries')
    || hasPermission(permissions, 'edit-employee-salaries')
    || hasPermission(permissions, 'manage-employee-salaries')
    || hasPermission(permissions, 'manage-any-employee-salaries');

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
  const tableColSpan = (showRowActions || showPayslipActions ? 12 : 11) + 1;

  const toggleEntryExpand = (entryId: number) => {
    setExpandedEntryIds((prev) => ({ ...prev, [entryId]: !prev[entryId] }));
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

  const summaryCards = [
    { label: t('Total Gross'), value: run?.total_gross, icon: IndianRupee, color: 'text-blue-600 bg-blue-50' },
    { label: t('Total Net'), value: run?.total_net, icon: IndianRupee, color: 'text-green-600 bg-green-50' },
    { label: t('Employees'), value: run?.employee_count, icon: Users, color: 'text-violet-600 bg-violet-50', isCount: true },
    ...(!isRunLocked ? [
      { label: t('Locked'), value: lockedCount, icon: Lock, color: 'text-green-700 bg-green-50', isCount: true, onClick: lockedCount > 0 ? () => applyFilters({ lock_status: 'locked', page: 1 }) : undefined },
      { label: t('Unlocked'), value: unlockedCount, icon: RefreshCw, color: 'text-amber-700 bg-amber-50', isCount: true, onClick: unlockedCount > 0 ? () => applyFilters({ lock_status: 'unlocked', page: 1 }) : undefined },
    ] : []),
    { label: t('PF (Employee)'), value: run?.total_pf_employee, icon: ShieldCheck, color: 'text-orange-600 bg-orange-50' },
    { label: t('ESI (Employee)'), value: run?.total_esi_employee, icon: ShieldCheck, color: 'text-teal-600 bg-teal-50' },
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

      <div className={cn('mb-4 grid gap-3 sm:grid-cols-2', !isRunLocked ? 'lg:grid-cols-7' : 'lg:grid-cols-5')}>
        {summaryCards.map((card) => (
          <div
            key={card.label}
            className={cn(
              'rounded-xl border border-slate-200 bg-white p-3 shadow-sm',
              card.onClick && 'cursor-pointer transition-shadow hover:border-primary/30 hover:shadow-md',
            )}
            onClick={card.onClick}
            role={card.onClick ? 'button' : undefined}
            tabIndex={card.onClick ? 0 : undefined}
            onKeyDown={card.onClick ? (e) => e.key === 'Enter' && card.onClick?.() : undefined}
            title={card.onClick ? t('Click to filter') : undefined}
          >
            <div className={`mb-2 inline-flex rounded-lg p-2 ${card.color}`}>
              <card.icon className="h-4 w-4" />
            </div>
            <p className="text-[10px] uppercase tracking-wider text-slate-500">{card.label}</p>
            <p className="text-lg font-bold text-slate-800">
              {card.isCount ? card.value : `₹${formatRupee(Number(card.value || 0))}`}
            </p>
          </div>
        ))}
      </div>

      {isRunLocked && (
        <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-xs text-green-800">
          {t('Locked on')} {run.finalized_at} {run.finalizer?.name ? `${t('by')} ${run.finalizer.name}` : ''}
          {' — '}{t('Regenerate and customize are disabled after lock.')}
        </div>
      )}

      {hasPartialLocks && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
          <p className="font-medium">
            {t('{{locked}} locked · {{unlocked}} unlocked — locking an employee auto-generates their payslip PDF.', {
              locked: lockedCount,
              unlocked: unlockedCount,
            })}
          </p>
          <div className="mt-2 flex flex-wrap gap-2">
            <Button
              variant="outline"
              size="sm"
              className="h-7 border-green-300 bg-white text-[11px] text-green-800 hover:bg-green-50"
              onClick={() => applyFilters({ lock_status: 'locked', page: 1 })}
            >
              <Lock className="mr-1 h-3 w-3" />
              {t('Show Locked ({{count}})', { count: lockedCount })}
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="h-7 border-amber-300 bg-white text-[11px] text-amber-900 hover:bg-amber-100/50"
              onClick={() => applyFilters({ lock_status: 'unlocked', page: 1 })}
            >
              <RefreshCw className="mr-1 h-3 w-3" />
              {t('Show Unlocked ({{count}})', { count: unlockedCount })}
            </Button>
            {lockFilter !== 'all' && (
              <Button
                variant="ghost"
                size="sm"
                className="h-7 text-[11px] text-amber-800"
                onClick={() => applyFilters({ lock_status: 'all', page: 1 })}
              >
                {t('Show all employees')}
              </Button>
            )}
          </div>
        </div>
      )}

      {!isRunLocked && lockedCount === 0 && (
        <div className="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-xs text-blue-800">
          {t('Lock individual employees to freeze salary and generate payslip PDF. Use "Lock Payroll" to finalize everyone and generate all payslips.')}
        </div>
      )}

      {isRunLocked && payslipCount > 0 && (
        <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-xs text-green-800">
          {t('{{count}} payslip(s) ready. Download individually from each row or use "Download Payslips" for a ZIP file.', { count: payslipCount })}
        </div>
      )}

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-4 py-3">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 className="text-sm font-bold text-slate-800">{t('Employee Payroll Details')}</h3>
              <p className="mt-0.5 text-[11px] text-slate-500">
                {t('Click the arrow on any row to expand full earnings & deductions breakdown.')}
              </p>
            </div>
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
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <Input
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                placeholder={t('Search name or code...')}
                className="h-8 w-[170px] pl-8 text-xs"
              />
              {searchTerm && (
                <button type="button" onClick={() => { setSearchTerm(''); applyFilters({ search: '', page: 1 }); }} className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
            <Button variant="outline" size="sm" className="h-8 text-xs" onClick={handleSearch}>
              {t('Search')}
            </Button>
            <Combobox
              value={categoryFilter}
              onChange={(v) => applyFilters({ category_id: v || 'all', page: 1 })}
              options={categoryOptions}
              placeholder={t('All Categories')}
              searchPlaceholder={t('Search category...')}
              emptyText={t('No category found.')}
              className="h-8 w-[140px] text-xs"
            />
            <Combobox
              value={shiftFilter}
              onChange={(v) => applyFilters({ shift_id: v || 'all', page: 1 })}
              options={shiftOptions}
              placeholder={t('All Shifts')}
              searchPlaceholder={t('Search shift...')}
              emptyText={t('No shift found.')}
              className="h-8 w-[130px] text-xs"
            />
            <Combobox
              value={departmentFilter}
              onChange={(v) => applyFilters({ department_id: v || 'all', page: 1 })}
              options={departmentOptions}
              placeholder={t('All Departments')}
              searchPlaceholder={t('Search department...')}
              emptyText={t('No department found.')}
              className="h-8 w-[150px] text-xs"
            />
            {!isRunLocked && (
              <Combobox
                value={lockFilter}
                onChange={(v) => applyFilters({ lock_status: v || 'all', page: 1 })}
                options={lockStatusOptions}
                placeholder={t('All Lock Status')}
                searchPlaceholder={t('Search...')}
                emptyText={t('No option found.')}
                className="h-8 w-[130px] text-xs"
              />
            )}
            <Select
              value={String(filters.per_page ?? 50)}
              onValueChange={(v) => applyFilters({ per_page: Number(v), page: 1 })}
            >
              <SelectTrigger className="h-8 w-[90px] text-xs">
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
        <StatutoryLegend />
        <div className="overflow-x-auto">
          <Table>
            <TableHeader className="bg-slate-50">
              <TableRow>
                <TableHead className="w-8" />
                <TableHead className="text-[10px]">{t('Code')}</TableHead>
                <TableHead className="text-[10px]">{t('Name')}</TableHead>
                <TableHead className="text-center text-[10px]">{t('Lock')}</TableHead>
                <TableHead className="text-[10px]">{t('Category')}</TableHead>
                <TableHead className="text-[10px]">{t('Shift')}</TableHead>
                <TableHead className="text-right text-[10px]">{t('Gross')}</TableHead>
                <TableHead className="text-right text-[10px]">{t('Basic')}</TableHead>
                <TableHead className="text-right text-[10px]">{t('PF')}<span className="mt-0.5 block font-normal normal-case text-slate-400">{t('On/Off')}</span></TableHead>
                <TableHead className="text-right text-[10px]">{t('ESI')}<span className="mt-0.5 block font-normal normal-case text-slate-400">{t('On/Off')}</span></TableHead>
                <TableHead className="text-right text-[10px]">{t('P.Tax')}</TableHead>
                <TableHead className="text-right text-[10px]">{t('Net')}</TableHead>
                {showRowActions && (
                  <TableHead className="text-right text-[10px]">{t('Action')}</TableHead>
                )}
                {!showRowActions && showPayslipActions && (
                  <TableHead className="text-right text-[10px]">{t('Payslip')}</TableHead>
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
                        entry.is_locked ? 'bg-green-50/60' : isExpanded ? 'bg-primary/5' : 'hover:bg-slate-50',
                      )}
                      onClick={() => toggleEntryExpand(entry.id)}
                    >
                      <TableCell className="w-8 px-2">
                        <button
                          type="button"
                          className="flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-slate-200/80"
                          onClick={(e) => { e.stopPropagation(); toggleEntryExpand(entry.id); }}
                          title={isExpanded ? t('Collapse') : t('Expand breakdown')}
                        >
                          {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        </button>
                      </TableCell>
                      <TableCell className="text-xs">{entry.employee_code || '—'}</TableCell>
                      <TableCell className="text-xs font-medium">{entry.name}</TableCell>
                      <TableCell className="text-center">
                        {entry.is_locked ? (
                          <Badge
                            className="border-0 bg-green-100 px-1.5 py-0.5 text-[9px] uppercase text-green-700"
                            title={entry.locked_at ? `${t('Locked on')} ${entry.locked_at}` : t('Locked')}
                          >
                            <Lock className="mr-0.5 inline h-2.5 w-2.5" />
                            {t('Locked')}
                          </Badge>
                        ) : (
                          <Badge className="border-0 bg-slate-100 px-1.5 py-0.5 text-[9px] uppercase text-slate-500">
                            {t('Open')}
                          </Badge>
                        )}
                        {entry.payslip_number && (
                          <div className="mt-0.5 text-[9px] font-normal normal-case text-green-700">{entry.payslip_number}</div>
                        )}
                      </TableCell>
                      <TableCell className="text-xs">{entry.category || '—'}</TableCell>
                      <TableCell className="text-xs">{entry.shift || '—'}</TableCell>
                      <TableCell className="text-right text-xs">₹{formatRupee(entry.monthly_gross)}</TableCell>
                      <TableCell className="text-right text-xs">₹{formatRupee(entry.basic)}</TableCell>
                      <TableCell className="text-right">
                        <StatutoryAmountCell
                          enabled={!!entry.pf_enabled}
                          amount={entry.pf_employee}
                          formatRupee={formatRupee}
                        />
                      </TableCell>
                      <TableCell className="text-right">
                        <StatutoryAmountCell
                          enabled={!!entry.esi_enabled}
                          amount={entry.esi_employee}
                          formatRupee={formatRupee}
                        />
                      </TableCell>
                      <TableCell className="text-right text-xs">₹{formatRupee(entry.pt_amount)}</TableCell>
                      <TableCell className="text-right text-xs font-semibold text-primary">₹{formatRupee(entry.net_salary)}</TableCell>
                      {(showRowActions || (showPayslipActions && entry.can_download_payslip)) && (
                        <TableCell className="text-right" onClick={(e) => e.stopPropagation()}>
                          <div className="flex items-center justify-end gap-0.5">
                            {entry.can_download_payslip && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-green-700 hover:bg-green-50 hover:text-green-800"
                                title={entry.payslip_number ? t('Download payslip {{no}}', { no: entry.payslip_number }) : t('Download payslip')}
                                onClick={() => handleDownloadPayslip(entry.id)}
                              >
                                <FileDown className="h-4 w-4" />
                              </Button>
                            )}
                            {canManage && !entry.is_locked && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-primary hover:bg-primary/10 hover:text-primary"
                                title={t('Regenerate this employee')}
                                disabled={regeneratingEntryId === entry.id || isRegenerating}
                                onClick={() => setEntryRegenerateTarget({ id: entry.id, name: entry.name })}
                              >
                                {regeneratingEntryId === entry.id
                                  ? <Loader2 className="h-4 w-4 animate-spin" />
                                  : <RefreshCw className="h-4 w-4" />}
                              </Button>
                            )}
                            {canManage && entry.is_locked && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 cursor-not-allowed text-slate-300"
                                title={t('Locked — cannot regenerate')}
                                disabled
                              >
                                <RefreshCw className="h-4 w-4" />
                              </Button>
                            )}
                            {canFinalize && entry.is_locked && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 cursor-default text-green-600"
                                title={t('Already locked')}
                                disabled
                              >
                                <Lock className="h-4 w-4" />
                              </Button>
                            )}
                            {canFinalize && !entry.is_locked && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-amber-600 hover:bg-amber-50 hover:text-amber-700"
                                title={t('Lock this employee')}
                                disabled={lockingEntryId === entry.id}
                                onClick={() => setEntryLockTarget({ id: entry.id, name: entry.name })}
                              >
                                {lockingEntryId === entry.id
                                  ? <Loader2 className="h-4 w-4 animate-spin" />
                                  : <Lock className="h-4 w-4" />}
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      )}
                    </TableRow>
                    {isExpanded && (
                      <TableRow className="hover:bg-transparent">
                        <TableCell colSpan={tableColSpan} className="p-0">
                          <PayrollEntryBreakdownPanel entry={entry} />
                        </TableCell>
                      </TableRow>
                    )}
                  </Fragment>
                );
              })}
            </TableBody>
          </Table>
        </div>
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
