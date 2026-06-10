import { useEffect, useMemo, useRef, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  FileDown,
  FilterX,
  Landmark,
  LayoutGrid,
  Search,
  Eye,
  EyeOff,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Combobox } from '@/components/ui/combobox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { type StatutoryChallanSummary } from './components/StatutoryChallanPanel';
import { cn } from '@/lib/utils';

interface ChallanRow {
  sr: number;
  employee_code: string;
  name: string;
  category: string;
  department: string;
  uan_number: string;
  pf_number: string;
  esic_number: string;
  paid_days: number;
  total_earnings: number;
  pf_wages: number;
  govt_min_wage_used: number;
  pf_employee: number;
  pf_eps_employer: number;
  pf_epf_employer: number;
  pf_admin_employer: number;
  pf_employer: number;
  pf_challan_ac1: number;
  pf_challan_ac2: number;
  pf_challan_ac10: number;
  pf_challan_total: number;
  esi_employee: number;
  esi_employer: number;
  pt_amount: number;
}

type ScrollMeta = {
  canLeft: boolean;
  canRight: boolean;
  canUp: boolean;
  canDown: boolean;
};

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatNum(value: number) {
  const n = Number(value);
  if (!Number.isFinite(n) || n === 0) return '—';
  return n % 1 === 0 ? String(n) : n.toFixed(1);
}

function formatCell(value: number) {
  return value > 0 ? formatRupee(value) : '—';
}

const stickyHead = 'sticky z-20 bg-amber-100 shadow-[2px_0_0_0_#fde68a]';
const stickyCell = 'sticky z-10 bg-white shadow-[2px_0_0_0_#f1f5f9] group-hover:bg-orange-50/40';
const stickyCellAlt = 'sticky z-10 bg-slate-50/90 shadow-[2px_0_0_0_#f1f5f9]';
const stickyRightHead = 'sticky z-20 bg-orange-200 shadow-[-2px_0_0_0_#fdba74]';
const stickyRightCell = 'sticky z-[15] bg-orange-50 shadow-[-2px_0_0_0_#fed7aa] group-hover:bg-orange-100/60';
const stickyRightCellAlt = 'sticky z-[15] bg-orange-50/90 shadow-[-2px_0_0_0_#fed7aa]';
const stickyRightTotal = 'sticky z-[15] bg-amber-100 shadow-[-2px_0_0_0_#fcd34d]';

const SCROLLBAR_STYLES = `
  .challan-report-scroll {
    scrollbar-width: auto;
    scrollbar-color: #94a3b8 #e2e8f0;
  }
  .challan-report-scroll::-webkit-scrollbar {
    width: 14px;
    height: 14px;
  }
  .challan-report-scroll::-webkit-scrollbar-track {
    background: #e2e8f0;
    border-radius: 7px;
  }
  .challan-report-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 7px;
    border: 3px solid #e2e8f0;
  }
  .challan-report-scroll::-webkit-scrollbar-thumb:hover {
    background: #64748b;
  }
  .challan-report-scroll::-webkit-scrollbar-corner {
    background: #e2e8f0;
  }
`;

function SummaryCard({
  label,
  sublabel,
  amount,
  tone = 'default',
}: {
  label: string;
  sublabel?: string;
  amount: number;
  tone?: 'default' | 'primary' | 'red';
}) {
  return (
    <div className={cn(
      'rounded-lg border px-3 py-2.5',
      tone === 'primary' ? 'border-orange-300 bg-orange-100' : 'border-slate-200 bg-white',
    )}>
      <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{label}</p>
      {sublabel && <p className="mt-0.5 text-[9px] leading-snug text-slate-400">{sublabel}</p>}
      <p className={cn(
        'mt-1 text-lg font-bold tabular-nums',
        tone === 'primary' ? 'text-orange-950' : tone === 'red' ? 'text-red-700' : 'text-slate-900',
      )}>
        ₹{formatRupee(amount)}
      </p>
    </div>
  );
}

function ScrollToolbar({
  meta,
  onScrollBy,
  onJump,
  t,
}: {
  meta: ScrollMeta;
  onScrollBy: (dx: number, dy: number) => void;
  onJump: (section: 'start' | 'wages' | 'pf' | 'challan') => void;
  t: (key: string) => string;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-3 py-2">
      <div className="flex flex-wrap items-center gap-1">
        <Button type="button" variant="outline" size="sm" className="h-7 w-7 p-0" disabled={!meta.canLeft} onClick={() => onScrollBy(-260, 0)}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" className="h-7 w-7 p-0" disabled={!meta.canRight} onClick={() => onScrollBy(260, 0)}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" className="h-7 w-7 p-0" disabled={!meta.canUp} onClick={() => onScrollBy(0, -180)}>
          <ChevronUp className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" className="h-7 w-7 p-0" disabled={!meta.canDown} onClick={() => onScrollBy(0, 180)}>
          <ChevronDown className="h-4 w-4" />
        </Button>
        <span className="mx-1 hidden h-4 w-px bg-slate-300 sm:inline" />
        {([
          ['start', t('Start')],
          ['wages', t('Wages')],
          ['pf', t('PF Share')],
          ['challan', t('Challan')],
        ] as const).map(([section, label]) => (
          <Button
            key={section}
            type="button"
            variant="ghost"
            size="sm"
            className="h-7 px-2 text-[10px] font-semibold text-slate-600"
            onClick={() => onJump(section)}
          >
            {label}
          </Button>
        ))}
      </div>
      <p className="text-[10px] text-slate-500">
        {t('Wheel = vertical · Shift/Alt + wheel = horizontal · Name & Challan stay fixed')}
      </p>
    </div>
  );
}

export default function StatutoryChallanReportPage() {
  const { t } = useTranslation();
  const {
    run,
    report,
    summary,
    filters = {},
    categories = [],
    departments = [],
    shifts = [],
  } = usePage().props as {
    run: any;
    report: { rows: ChallanRow[]; totals: Record<string, number>; pf_employee_count: number };
    summary: StatutoryChallanSummary;
    filters: Record<string, string | number | undefined>;
    categories: { id: number; name: string }[];
    departments: { id: number; name: string }[];
    shifts: { id: number; name: string }[];
  };

  const [searchTerm, setSearchTerm] = useState(String(filters.search || ''));
  const [downloadOpen, setDownloadOpen] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [filePassword, setFilePassword] = useState('');
  const [filePasswordConfirmation, setFilePasswordConfirmation] = useState('');
  const [downloadErrors, setDownloadErrors] = useState<Record<string, string>>({});
  const [isDownloading, setIsDownloading] = useState(false);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showFilePassword, setShowFilePassword] = useState(false);
  const [showFilePasswordConfirmation, setShowFilePasswordConfirmation] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const [scrollMeta, setScrollMeta] = useState<ScrollMeta>({
    canLeft: false,
    canRight: false,
    canUp: false,
    canDown: false,
  });

  const rows = report?.rows ?? [];
  const totals = report?.totals ?? {};
  const pfCount = report?.pf_employee_count ?? 0;

  const categoryFilter = filters.category_id ? String(filters.category_id) : 'all';
  const shiftFilter = filters.shift_id ? String(filters.shift_id) : 'all';
  const departmentFilter = filters.department_id ? String(filters.department_id) : 'all';
  const lockFilter = filters.lock_status || 'all';

  const categoryOptions = useMemo(() => [
    { label: t('All Categories'), value: 'all' },
    ...categories.map((c) => ({ label: c.name, value: String(c.id) })),
  ], [categories, t]);

  const shiftOptions = useMemo(() => [
    { label: t('All Shifts'), value: 'all' },
    ...shifts.map((s) => ({ label: s.name, value: String(s.id) })),
  ], [shifts, t]);

  const departmentOptions = useMemo(() => [
    { label: t('All Departments'), value: 'all' },
    ...departments.map((d) => ({ label: d.name, value: String(d.id) })),
  ], [departments, t]);

  const lockStatusOptions = useMemo(() => [
    { label: t('All Lock Status'), value: 'all' },
    { label: t('Locked only'), value: 'locked' },
    { label: t('Unlocked only'), value: 'unlocked' },
  ], [t]);

  const refreshScrollMeta = () => {
    const el = scrollRef.current;
    if (!el) return;
    setScrollMeta({
      canLeft: el.scrollLeft > 4,
      canRight: el.scrollLeft + el.clientWidth < el.scrollWidth - 4,
      canUp: el.scrollTop > 4,
      canDown: el.scrollTop + el.clientHeight < el.scrollHeight - 4,
    });
  };

  const scrollBy = (dx: number, dy: number) => {
    scrollRef.current?.scrollBy({ left: dx, top: dy, behavior: 'smooth' });
  };

  const jumpToSection = (section: 'start' | 'wages' | 'pf' | 'challan') => {
    const el = scrollRef.current;
    if (!el) return;
    if (section === 'start') {
      el.scrollTo({ left: 0, top: el.scrollTop, behavior: 'smooth' });
      return;
    }
    if (section === 'challan') {
      el.scrollTo({ left: el.scrollWidth - el.clientWidth, top: el.scrollTop, behavior: 'smooth' });
      return;
    }
    const target = el.querySelector(`[data-challan-section="${section}"]`) as HTMLElement | null;
    if (target) {
      el.scrollTo({ left: Math.max(0, target.offsetLeft - 300), top: el.scrollTop, behavior: 'smooth' });
    }
  };

  useEffect(() => {
    const el = scrollRef.current;
    if (!el) return;

    const onWheel = (e: WheelEvent) => {
      if ((e.shiftKey || e.altKey) && e.deltaY !== 0) {
        el.scrollLeft += e.deltaY;
        e.preventDefault();
      }
    };

    refreshScrollMeta();
    el.addEventListener('wheel', onWheel, { passive: false });
    el.addEventListener('scroll', refreshScrollMeta);
    window.addEventListener('resize', refreshScrollMeta);

    return () => {
      el.removeEventListener('wheel', onWheel);
      el.removeEventListener('scroll', refreshScrollMeta);
      window.removeEventListener('resize', refreshScrollMeta);
    };
  }, [rows.length]);

  const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
    router.get(route('hr.salary-payroll.generate.challan-report', run.id), {
      search: overrides.search !== undefined ? overrides.search || undefined : searchTerm || undefined,
      category_id: overrides.category_id !== undefined
        ? (overrides.category_id !== 'all' ? overrides.category_id : undefined)
        : (categoryFilter !== 'all' ? categoryFilter : undefined),
      shift_id: overrides.shift_id !== undefined
        ? (overrides.shift_id !== 'all' ? overrides.shift_id : undefined)
        : (shiftFilter !== 'all' ? shiftFilter : undefined),
      department_id: overrides.department_id !== undefined
        ? (overrides.department_id !== 'all' ? overrides.department_id : undefined)
        : (departmentFilter !== 'all' ? departmentFilter : undefined),
      lock_status: overrides.lock_status !== undefined
        ? (overrides.lock_status !== 'all' ? overrides.lock_status : undefined)
        : (lockFilter !== 'all' ? lockFilter : undefined),
    }, { preserveState: true, replace: true });
  };

  const resetDownloadForm = () => {
    setCurrentPassword('');
    setFilePassword('');
    setFilePasswordConfirmation('');
    setDownloadErrors({});
    setShowCurrentPassword(false);
    setShowFilePassword(false);
    setShowFilePasswordConfirmation(false);
  };

  const handleDownload = async () => {
    setDownloadErrors({});
    setIsDownloading(true);

    try {
      const response = await fetch(route('hr.salary-payroll.generate.challan-report.export', run.id), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          current_password: currentPassword,
          file_password: filePassword,
          file_password_confirmation: filePasswordConfirmation,
          search: filters.search || undefined,
          category_id: filters.category_id || undefined,
          shift_id: filters.shift_id || undefined,
          department_id: filters.department_id || undefined,
          lock_status: filters.lock_status || undefined,
        }),
      });

      if (!response.ok) {
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          const data = await response.json();
          const nextErrors: Record<string, string> = {};
          if (data.errors) {
            Object.entries(data.errors).forEach(([key, messages]) => {
              nextErrors[key] = Array.isArray(messages) ? messages[0] : String(messages);
            });
          } else if (data.message) {
            nextErrors.general = data.message;
          }
          setDownloadErrors(nextErrors);
          return;
        }
        setDownloadErrors({ general: t('Export failed. Please try again.') });
        return;
      }

      const blob = await response.blob();
      const disposition = response.headers.get('content-disposition') || '';
      const match = disposition.match(/filename="?([^"]+)"?/);
      const filename = match?.[1] || `Statutory_Challan_${run.id}.xlsx`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      a.click();
      URL.revokeObjectURL(url);
      setDownloadOpen(false);
      resetDownloadForm();
    } catch {
      setDownloadErrors({ general: t('Export failed. Please try again.') });
    } finally {
      setIsDownloading(false);
    }
  };

  const pf = summary?.pf;
  const th = 'whitespace-nowrap border border-slate-300 px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide';
  const td = 'whitespace-nowrap border border-slate-200 px-2 py-1.5 text-[11px] tabular-nums text-slate-800';

  return (
    <PageTemplate
      title={t('Statutory Challan Report')}
      description={run?.title}
      url={route('hr.salary-payroll.generate.challan-report', run.id)}
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Generate Payroll'), href: route('hr.salary-payroll.generate.index') },
        { title: run?.title || t('Payroll'), href: route('hr.salary-payroll.generate.show', run.id) },
        { title: t('Challan Report') },
      ]}
      noPadding
    >
      <style>{SCROLLBAR_STYLES}</style>
      <div className="mx-auto max-w-full space-y-3 px-1 pb-6">
        {/* Header */}
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
          <div>
            <div className="flex items-center gap-2">
              <Landmark className="h-4 w-4 text-orange-700" />
              <p className="text-sm font-semibold text-slate-800">{t('Statutory Challan Report')}</p>
            </div>
            <p className="mt-0.5 text-xs text-slate-500">
              {run?.title} · {run?.pay_period_start} → {run?.pay_period_end} · {rows.length} {t('employees')} · {pfCount} {t('with PF')}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" asChild>
              <Link href={route('hr.salary-payroll.generate.show', run.id)}>
                <ArrowLeft className="mr-1.5 h-4 w-4" />
                {t('Detail View')}
              </Link>
            </Button>
            <Button variant="outline" size="sm" asChild>
              <Link href={route('hr.salary-payroll.generate.register', run.id)}>
                <LayoutGrid className="mr-1.5 h-4 w-4" />
                {t('Excel View')}
              </Link>
            </Button>
            <Button size="sm" onClick={() => { resetDownloadForm(); setDownloadOpen(true); }}>
              <FileDown className="mr-1.5 h-4 w-4" />
              {t('Export Excel')}
            </Button>
          </div>
        </div>

        {/* EPFO deposit summary — always visible */}
        {pf && pf.challan.total_deposit > 0 && (
          <div className="rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50/80 p-3 shadow-sm">
            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-orange-900">
              {t('EPFO PF Challan — fill these in EPFO portal')} ({pf.employee_count} {t('employees')})
            </p>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
              <SummaryCard
                label={t('A/C 1 — Employees PF')}
                sublabel={t('Emp {{pct}}% + Empr EPF', { pct: pf.rates.employee_pct })}
                amount={pf.challan.ac1_employees_pf}
              />
              <SummaryCard
                label={t('A/C 2 — Pension (EPS)')}
                sublabel={t('Employer {{pct}}%', { pct: pf.rates.eps_pct })}
                amount={pf.challan.ac2_pension_eps}
              />
              <SummaryCard
                label={t('A/C 10 — Admin')}
                sublabel={t('{{pct}}% on PF wages', { pct: pf.rates.admin_pct })}
                amount={pf.challan.ac10_admin}
              />
              <SummaryCard
                label={t('Total PF Deposit')}
                sublabel={t('A/C 1 + 2 + 10')}
                amount={pf.challan.total_deposit}
                tone="primary"
              />
              <SummaryCard
                label={t('Employee PF total')}
                sublabel={t('Red column in table below')}
                amount={pf.employee_contribution}
                tone="red"
              />
            </div>
            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-600">
              <span>{t('PF wages')}: <strong>₹{formatRupee(pf.wages)}</strong></span>
              <span>{t('Employer share')}: <strong>₹{formatRupee(pf.employer_total)}</strong></span>
              {(summary?.pt?.total ?? 0) > 0 && (
                <span>{t('PT total')}: <strong>₹{formatRupee(summary.pt.total)}</strong></span>
              )}
              {(summary?.esi?.total ?? 0) > 0 && (
                <span>{t('ESIC total')}: <strong>₹{formatRupee(summary.esi.total)}</strong></span>
              )}
            </div>
          </div>
        )}

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-muted/40 px-3 py-2">
          <div className="relative min-w-[160px] flex-1 sm:max-w-xs">
            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search: searchTerm })}
              placeholder={t('Search name or code...')}
              className="h-8 bg-white pl-8 text-sm"
            />
          </div>
          <Combobox value={categoryFilter} onChange={(v) => applyFilters({ category_id: v || 'all' })} options={categoryOptions} placeholder={t('Category')} className="h-8 w-[130px] text-xs" />
          <Combobox value={departmentFilter} onChange={(v) => applyFilters({ department_id: v || 'all' })} options={departmentOptions} placeholder={t('Department')} className="h-8 w-[130px] text-xs" />
          <Combobox value={shiftFilter} onChange={(v) => applyFilters({ shift_id: v || 'all' })} options={shiftOptions} placeholder={t('Shift')} className="h-8 w-[120px] text-xs" />
          <Select value={lockFilter} onValueChange={(v) => applyFilters({ lock_status: v })}>
            <SelectTrigger className="h-8 w-[120px] text-xs"><SelectValue /></SelectTrigger>
            <SelectContent>
              {lockStatusOptions.map((o) => (
                <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button variant="outline" size="sm" className="h-8" onClick={() => applyFilters({ search: searchTerm })}>{t('Search')}</Button>
          {(filters.search || filters.category_id || filters.shift_id || filters.department_id || filters.lock_status) && (
            <Button variant="ghost" size="sm" className="h-8" onClick={() => { setSearchTerm(''); applyFilters({ search: '', category_id: 'all', department_id: 'all', shift_id: 'all', lock_status: 'all' }); }}>
              <FilterX className="mr-1 h-3.5 w-3.5" />
              {t('Clear')}
            </Button>
          )}
        </div>

        {/* Table */}
        <div className="relative overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
          {scrollMeta.canLeft && (
            <div className="pointer-events-none absolute left-[288px] top-0 z-[25] h-[calc(100%-48px)] w-8 bg-gradient-to-r from-slate-900/10 to-transparent" aria-hidden />
          )}
          {scrollMeta.canRight && (
            <div className="pointer-events-none absolute right-[96px] top-0 z-[25] h-[calc(100%-48px)] w-8 bg-gradient-to-l from-slate-900/10 to-transparent" aria-hidden />
          )}
          <div
            ref={scrollRef}
            className="challan-report-scroll max-h-[calc(100vh-340px)] min-h-[280px] w-full overflow-auto"
          >
            <table className="min-w-max w-full border-collapse">
              <thead className="sticky top-0 z-30">
                <tr className="bg-slate-200/80 text-[9px] font-bold uppercase text-slate-600">
                  <th className={cn(th, stickyHead, 'sticky top-0 left-0 z-[45]')} colSpan={3} />
                  <th className={cn(th, 'min-w-[200px] text-center')} colSpan={2}>{t('Employee')}</th>
                  <th className={cn(th, 'min-w-[180px] text-center text-sky-800 bg-sky-50/80')} colSpan={3} data-challan-section="wages">{t('Wages')}</th>
                  <th className={cn(th, 'min-w-[280px] text-center text-red-800 bg-red-50/60')} colSpan={4} data-challan-section="pf">{t('PF Contribution')}</th>
                  <th className={cn(th, 'min-w-[280px] text-center text-orange-800 bg-orange-50/80')} colSpan={3}>{t('EPFO Challan')}</th>
                  <th className={cn(th, stickyRightHead, 'sticky top-0 right-0 z-[45] min-w-[96px] text-orange-950')} data-challan-section="challan">{t('Total')}</th>
                  <th className={cn(th, 'min-w-[120px] text-center text-slate-500')} colSpan={2}>{t('Other')}</th>
                </tr>
                <tr className="bg-amber-50 text-amber-950">
                  <th className={cn(th, stickyHead, 'sticky top-[29px] left-0 z-[45] min-w-[36px]')}>#</th>
                  <th className={cn(th, stickyHead, 'sticky top-[29px] left-[36px] z-[45] min-w-[72px]')}>{t('Code')}</th>
                  <th className={cn(th, stickyHead, 'sticky top-[29px] left-[108px] z-[45] min-w-[180px] text-left')}>{t('Name')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[90px] text-left')}>{t('Dept')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[110px] text-left')}>{t('UAN')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[48px] text-right')} title={t('Paid days for PF')}>{t('Paid')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[80px] text-right')} title={t('Gross salary for the month')}>{t('Gross')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[80px] text-right bg-sky-50/50')} title={t('PF wages (basic for PF calculation)')}>{t('PF Wages')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[72px] text-right text-red-800 bg-red-50/50')} title={t('Employee PF deduction')}>{t('Emp PF')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[72px] text-right')} title={t('Employer EPS (Pension)')}>{t('EPS')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[72px] text-right')} title={t('Employer EPF share')}>{t('EPF Empr')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[64px] text-right')} title={t('Admin charges')}>{t('Admin')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[72px] text-right font-bold text-orange-900 bg-orange-50/50')} title={t('A/C 1 — Employees PF')}>{t('A/C 1')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[72px] text-right font-bold text-orange-900 bg-orange-50/50')} title={t('A/C 2 — Pension EPS')}>{t('A/C 2')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[72px] text-right font-bold text-orange-900 bg-orange-50/50')} title={t('A/C 10 — Admin charges')}>{t('A/C 10')}</th>
                  <th className={cn(th, stickyRightHead, 'sticky top-[29px] right-0 z-[45] min-w-[96px] text-right font-bold text-orange-950')} title={t('Total PF challan deposit for this employee')}>{t('Challan')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[64px] text-right')}>{t('ESIC')}</th>
                  <th className={cn(th, 'sticky top-[29px] z-30 min-w-[64px] text-right')}>{t('PT')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={18} className="px-4 py-12 text-center text-sm text-muted-foreground">
                      {t('No employees match the filters.')}
                    </td>
                  </tr>
                ) : rows.map((row, idx) => {
                  const hasPf = row.pf_challan_total > 0;
                  return (
                    <tr
                      key={row.sr}
                      className={cn(
                        'group',
                        idx % 2 === 1 && 'bg-slate-50/50',
                        hasPf ? 'hover:bg-orange-50/50' : 'text-slate-500 hover:bg-slate-50/80',
                      )}
                    >
                      <td className={cn(td, 'text-right', idx % 2 === 1 ? stickyCellAlt : stickyCell, 'left-0')}>{row.sr}</td>
                      <td className={cn(td, 'font-mono text-[10px]', idx % 2 === 1 ? stickyCellAlt : stickyCell, 'left-[36px]')}>{row.employee_code}</td>
                      <td className={cn(td, 'text-left font-semibold', idx % 2 === 1 ? stickyCellAlt : stickyCell, 'left-[108px]')}>{row.name}</td>
                      <td className={cn(td, 'text-left text-slate-600')}>{row.department || '—'}</td>
                      <td className={cn(td, 'text-left font-mono text-[10px]')}>{row.uan_number || '—'}</td>
                      <td className={cn(td, 'text-right')}>{formatNum(row.paid_days)}</td>
                      <td className={cn(td, 'text-right font-medium')}>{formatCell(row.total_earnings)}</td>
                      <td className={cn(td, 'text-right bg-sky-50/30')}>{formatCell(row.pf_wages)}</td>
                      <td className={cn(td, 'text-right font-semibold text-red-700 bg-red-50/20')}>{formatCell(row.pf_employee)}</td>
                      <td className={cn(td, 'text-right')}>{formatCell(row.pf_eps_employer)}</td>
                      <td className={cn(td, 'text-right')}>{formatCell(row.pf_epf_employer)}</td>
                      <td className={cn(td, 'text-right')}>{formatCell(row.pf_admin_employer)}</td>
                      <td className={cn(td, 'text-right font-semibold text-orange-900 bg-orange-50/30')}>{formatCell(row.pf_challan_ac1)}</td>
                      <td className={cn(td, 'text-right font-semibold text-orange-900 bg-orange-50/30')}>{formatCell(row.pf_challan_ac2)}</td>
                      <td className={cn(td, 'text-right font-semibold text-orange-900 bg-orange-50/30')}>{formatCell(row.pf_challan_ac10)}</td>
                      <td className={cn(
                        td,
                        'right-0 text-right font-bold text-orange-950',
                        idx % 2 === 1 ? stickyRightCellAlt : stickyRightCell,
                      )}>
                        {formatCell(row.pf_challan_total)}
                      </td>
                      <td className={cn(td, 'text-right')}>{formatCell(row.esi_employee)}</td>
                      <td className={cn(td, 'text-right')}>{formatCell(row.pt_amount)}</td>
                    </tr>
                  );
                })}
              </tbody>
              {rows.length > 0 && (
                <tfoot>
                  <tr className="bg-amber-100 font-bold text-amber-950">
                    <td className={cn(td, stickyCell, 'left-0 text-center uppercase')} colSpan={3}>{t('Total')}</td>
                    <td className={td} colSpan={2} />
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.total_earnings ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_wages ?? 0)}</td>
                    <td className={cn(td, 'text-right text-red-800')}>{formatRupee(totals.pf_employee ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_eps_employer ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_epf_employer ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_admin_employer ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_challan_ac1 ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_challan_ac2 ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pf_challan_ac10 ?? 0)}</td>
                    <td className={cn(td, stickyRightTotal, 'right-0 text-right text-orange-950')}>{formatRupee(totals.pf_challan_total ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.esi_employee ?? 0)}</td>
                    <td className={cn(td, 'text-right')}>{formatRupee(totals.pt_amount ?? 0)}</td>
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
          <ScrollToolbar meta={scrollMeta} onScrollBy={scrollBy} onJump={jumpToSection} t={t} />
        </div>
      </div>

      <Dialog open={downloadOpen} onOpenChange={setDownloadOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('Export Statutory Challan Report')}</DialogTitle>
            <DialogDescription>
              {t('Excel file will be password-protected. Enter your login password and a file password.')}
            </DialogDescription>
          </DialogHeader>
          {downloadErrors.general && (
            <p className="text-sm text-red-600">{downloadErrors.general}</p>
          )}
          <div className="space-y-3">
            <div>
              <Label>{t('Your password')}</Label>
              <div className="relative">
                <Input
                  type={showCurrentPassword ? 'text' : 'password'}
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                />
                <button type="button" className="absolute right-2 top-2.5 text-slate-400" onClick={() => setShowCurrentPassword(!showCurrentPassword)}>
                  {showCurrentPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
              {downloadErrors.current_password && <p className="text-xs text-red-600">{downloadErrors.current_password}</p>}
            </div>
            <div>
              <Label>{t('File password')}</Label>
              <div className="relative">
                <Input
                  type={showFilePassword ? 'text' : 'password'}
                  value={filePassword}
                  onChange={(e) => setFilePassword(e.target.value)}
                />
                <button type="button" className="absolute right-2 top-2.5 text-slate-400" onClick={() => setShowFilePassword(!showFilePassword)}>
                  {showFilePassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
              {downloadErrors.file_password && <p className="text-xs text-red-600">{downloadErrors.file_password}</p>}
            </div>
            <div>
              <Label>{t('Confirm file password')}</Label>
              <div className="relative">
                <Input
                  type={showFilePasswordConfirmation ? 'text' : 'password'}
                  value={filePasswordConfirmation}
                  onChange={(e) => setFilePasswordConfirmation(e.target.value)}
                />
                <button type="button" className="absolute right-2 top-2.5 text-slate-400" onClick={() => setShowFilePasswordConfirmation(!showFilePasswordConfirmation)}>
                  {showFilePasswordConfirmation ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDownloadOpen(false)}>{t('Cancel')}</Button>
            <Button onClick={handleDownload} disabled={isDownloading}>
              {isDownloading ? t('Exporting…') : t('Download Excel')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
