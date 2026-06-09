import { useCallback, useEffect, useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import {
  Users,
  Tags,
  Clock,
  UserCheck,
  Loader2,
  ChevronRight,
  AlertTriangle,
  CheckCircle2,
  ArrowLeft,
  Search,
  X,
  Banknote,
} from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Pagination } from '@/components/ui/pagination';
import { ConfirmActionDialog } from './components/ConfirmActionDialog';
import { cn } from '@/lib/utils';
import { canApplySalaryPayrollAttendanceExtra } from '@/utils/authorization';

type ScopeMode = 'all' | 'category' | 'shift' | 'employee';
type PeriodMode = 'month' | 'custom';

type PreviewStatusFilter = 'all' | 'ready' | 'missing';

interface PreviewPagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface PreviewRow {
  id: number;
  name: string;
  employee_code?: string;
  category?: string;
  shift?: string;
  department?: string;
  monthly_gross: number;
  ready: boolean;
  status: string;
}

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function periodLabel(monthYear: string) {
  if (!monthYear) return '';
  const [y, m] = monthYear.split('-');
  const date = new Date(Number(y), Number(m) - 1, 1);
  const start = `01 ${date.toLocaleString('en-IN', { month: 'short', year: 'numeric' })}`;
  const lastDay = new Date(Number(y), Number(m), 0).getDate();
  const end = `${String(lastDay).padStart(2, '0')} ${date.toLocaleString('en-IN', { month: 'short', year: 'numeric' })}`;
  return `${start} – ${end}`;
}

function formatDateLabel(value: string) {
  if (!value) return '';
  const date = new Date(`${value}T00:00:00`);
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function monthBounds(monthYear: string) {
  const [y, m] = monthYear.split('-').map(Number);
  const lastDay = new Date(y, m, 0).getDate();
  return {
    start: `${monthYear}-01`,
    end: `${monthYear}-${String(lastDay).padStart(2, '0')}`,
  };
}

const scopeCards: { mode: ScopeMode; icon: typeof Users; title: string; desc: string }[] = [
  { mode: 'all', icon: Users, title: 'All Employees', desc: 'Everyone in this branch with salary setup' },
  { mode: 'category', icon: Tags, title: 'By Category', desc: 'Staff, Worker, or other categories' },
  { mode: 'shift', icon: Clock, title: 'By Shift', desc: 'Day shift, night shift, etc.' },
  { mode: 'employee', icon: UserCheck, title: 'Select Employees', desc: 'Pick specific employees' },
];

export default function PayrollGenerateCreate() {
  const { t } = useTranslation();
  const {
    mode = 'create',
    existingRun = null,
    financialYearOptions = [],
    defaultFinancialYear,
    monthsByFinancialYear = {},
    defaultMonthYear,
    categories = [],
    departments = [],
    shifts = [],
    employees = [],
    activeBranchName,
    flash,
    auth,
  } = usePage().props as any;

  const permissions = auth?.permissions || [];
  const canApplyAttendanceExtra = canApplySalaryPayrollAttendanceExtra(permissions);

  const isEdit = mode === 'edit' && existingRun;
  const initialFilters = existingRun?.scope_filters ?? {};
  const initialPeriodMode: PeriodMode = existingRun?.period_mode === 'custom' ? 'custom' : 'month';

  const [step, setStep] = useState(isEdit ? 2 : 1);
  const [periodMode, setPeriodMode] = useState<PeriodMode>(initialPeriodMode);
  const [financialYear, setFinancialYear] = useState(existingRun?.financial_year || defaultFinancialYear || '');
  const [monthYear, setMonthYear] = useState(existingRun?.month_year || defaultMonthYear || '');
  const [customStart, setCustomStart] = useState(existingRun?.pay_period_start || '');
  const [customEnd, setCustomEnd] = useState(existingRun?.pay_period_end || '');
  const [useAttendance, setUseAttendance] = useState(
    existingRun?.use_attendance !== undefined ? Boolean(existingRun.use_attendance) : true
  );
  const [applyAttendanceExtra, setApplyAttendanceExtra] = useState(
    existingRun?.apply_attendance_extra !== undefined ? Boolean(existingRun.apply_attendance_extra) : false
  );
  const [scopeMode, setScopeMode] = useState<ScopeMode>(existingRun?.scope_mode || 'all');
  const [categoryIds, setCategoryIds] = useState<number[]>(initialFilters.category_ids || []);
  const [shiftIds, setShiftIds] = useState<number[]>(initialFilters.shift_ids || []);
  const [employeeIds, setEmployeeIds] = useState<number[]>(initialFilters.employee_ids || []);
  const [departmentId, setDepartmentId] = useState<string>(
    initialFilters.department_ids?.[0] ? String(initialFilters.department_ids[0]) : 'all'
  );
  const [searchTerm, setSearchTerm] = useState('');
  const [showMoreFilters, setShowMoreFilters] = useState(false);
  const [previewRows, setPreviewRows] = useState<PreviewRow[]>([]);
  const [previewSummary, setPreviewSummary] = useState({ ready_count: 0, missing_count: 0, total_count: 0, total_gross: 0, filtered_count: 0 });
  const [previewPagination, setPreviewPagination] = useState<PreviewPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
    from: 0,
    to: 0,
  });
  const [previewSearchInput, setPreviewSearchInput] = useState('');
  const [previewSearch, setPreviewSearch] = useState('');
  const [previewStatus, setPreviewStatus] = useState<PreviewStatusFilter>('all');
  const [previewCategoryId, setPreviewCategoryId] = useState('all');
  const [previewShiftId, setPreviewShiftId] = useState('all');
  const [previewDepartmentId, setPreviewDepartmentId] = useState('all');
  const [previewPerPage, setPreviewPerPage] = useState(50);
  const [generateConfirmOpen, setGenerateConfirmOpen] = useState(false);
  const [existingRunPreview, setExistingRunPreview] = useState<{ id: number; title: string; status: string; is_locked: boolean } | null>(null);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);

  const monthOptions = useMemo(
    () => monthsByFinancialYear[financialYear] || [],
    [monthsByFinancialYear, financialYear]
  );

  useEffect(() => {
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  useEffect(() => {
    if (monthOptions.length && !monthOptions.some((m: { value: string }) => m.value === monthYear)) {
      setMonthYear(monthOptions[0]?.value || '');
    }
  }, [financialYear, monthOptions, monthYear]);

  const handlePeriodModeChange = (mode: PeriodMode) => {
    setPeriodMode(mode);
    if (mode === 'custom' && monthYear) {
      const bounds = monthBounds(monthYear);
      setCustomStart(bounds.start);
      setCustomEnd(bounds.end);
    }
  };

  const payPeriodDisplay = useMemo(() => {
    if (periodMode === 'custom') {
      if (customStart && customEnd) {
        return `${formatDateLabel(customStart)} – ${formatDateLabel(customEnd)}`;
      }
      return '';
    }
    return periodLabel(monthYear);
  }, [periodMode, monthYear, customStart, customEnd]);

  const isPeriodValid = useMemo(() => {
    if (periodMode === 'month') return !!monthYear;
    if (!customStart || !customEnd) return false;
    return customEnd >= customStart;
  }, [periodMode, monthYear, customStart, customEnd]);

  useEffect(() => {
    if (!useAttendance && applyAttendanceExtra) {
      setApplyAttendanceExtra(false);
    }
  }, [useAttendance, applyAttendanceExtra]);

  const payload = useCallback(() => ({
    period_mode: periodMode,
    financial_year: financialYear,
    month_year: periodMode === 'month' ? monthYear : undefined,
    pay_period_start: periodMode === 'custom' ? customStart : undefined,
    pay_period_end: periodMode === 'custom' ? customEnd : undefined,
    scope_mode: scopeMode,
    category_ids: scopeMode === 'category' ? categoryIds : [],
    shift_ids: scopeMode === 'shift' ? shiftIds : [],
    employee_ids: scopeMode === 'employee' ? employeeIds : [],
    department_ids: departmentId !== 'all' ? [Number(departmentId)] : [],
    search: searchTerm || undefined,
    use_attendance: useAttendance,
    apply_attendance_extra: useAttendance && canApplyAttendanceExtra ? applyAttendanceExtra : false,
  }), [periodMode, financialYear, monthYear, customStart, customEnd, scopeMode, categoryIds, shiftIds, employeeIds, departmentId, searchTerm, useAttendance, applyAttendanceExtra, canApplyAttendanceExtra]);

  const toggleId = (list: number[], id: number, checked: boolean) =>
    checked ? [...list, id] : list.filter((x) => x !== id);

  const validateScope = () => {
    if (scopeMode === 'category' && categoryIds.length === 0) {
      toast.error(t('Select at least one category'));
      return false;
    }
    if (scopeMode === 'shift' && shiftIds.length === 0) {
      toast.error(t('Select at least one shift'));
      return false;
    }
    if (scopeMode === 'employee' && employeeIds.length === 0) {
      toast.error(t('Select at least one employee'));
      return false;
    }
    return true;
  };

  const resetPreviewFilters = () => {
    setPreviewSearchInput('');
    setPreviewSearch('');
    setPreviewStatus('all');
    setPreviewCategoryId('all');
    setPreviewShiftId('all');
    setPreviewDepartmentId('all');
    setPreviewPerPage(50);
  };

  const buildPreviewRequest = useCallback((
    page = 1,
    overrides: {
      search?: string;
      status?: PreviewStatusFilter;
      categoryId?: string;
      shiftId?: string;
      departmentId?: string;
      perPage?: number;
    } = {}
  ) => ({
    ...payload(),
    preview_search: (overrides.search !== undefined ? overrides.search : previewSearch) || undefined,
    preview_status: overrides.status ?? previewStatus,
    preview_category_id: (overrides.categoryId ?? previewCategoryId) !== 'all'
      ? Number(overrides.categoryId ?? previewCategoryId)
      : undefined,
    preview_shift_id: (overrides.shiftId ?? previewShiftId) !== 'all'
      ? Number(overrides.shiftId ?? previewShiftId)
      : undefined,
    preview_department_id: (overrides.departmentId ?? previewDepartmentId) !== 'all'
      ? Number(overrides.departmentId ?? previewDepartmentId)
      : undefined,
    page,
    per_page: overrides.perPage ?? previewPerPage,
  }), [payload, previewSearch, previewStatus, previewCategoryId, previewShiftId, previewDepartmentId, previewPerPage]);

  const loadPreview = async (options?: {
    page?: number;
    resetFilters?: boolean;
    fromStep2?: boolean;
    filters?: {
      search?: string;
      status?: PreviewStatusFilter;
      categoryId?: string;
      shiftId?: string;
      departmentId?: string;
      perPage?: number;
    };
  }) => {
    if (!isPeriodValid) {
      toast.error(periodMode === 'custom' ? t('Select a valid custom date range') : t('Select a month'));
      return;
    }
    if (options?.fromStep2 && !validateScope()) return;

    const page = options?.page ?? 1;
    const filterOverrides = options?.fromStep2 || options?.resetFilters
      ? { search: '', status: 'all' as PreviewStatusFilter, categoryId: 'all', shiftId: 'all', departmentId: 'all', perPage: 50 }
      : (options?.filters ?? {});

    if (options?.fromStep2 || options?.resetFilters) {
      resetPreviewFilters();
    }

    setIsPreviewing(true);
    try {
      const requestPayload = buildPreviewRequest(page, filterOverrides);
      const { data } = await axios.post(route('hr.salary-payroll.generate.preview'), requestPayload);
      setPreviewRows(data.rows || []);
      setPreviewSummary({
        ready_count: data.ready_count || 0,
        missing_count: data.missing_count || 0,
        total_count: data.total_count || 0,
        total_gross: data.total_gross || 0,
        filtered_count: data.filtered_count ?? data.pagination?.total ?? 0,
      });
      setPreviewPagination(data.pagination || {
        current_page: 1,
        last_page: 1,
        per_page: filterOverrides.perPage ?? previewPerPage,
        total: data.rows?.length || 0,
        from: data.rows?.length ? 1 : 0,
        to: data.rows?.length || 0,
      });
      setExistingRunPreview(data.existing_run || null);
      setStep(3);
      if (data.existing_run?.is_locked) {
        toast.error(t('Payroll for this period and scope is already locked.'));
      } else if (data.existing_run) {
        toast.info(t('An existing payroll run matches this period and scope — it will be updated, not duplicated.'));
      }
      if ((data.ready_count || 0) === 0) {
        toast.info(t('No employees with salary match these filters.'));
      }
    } catch (err: any) {
      toast.error(err?.response?.data?.message || t('Preview failed'));
    } finally {
      setIsPreviewing(false);
    }
  };

  const handlePreviewSearch = () => {
    const term = previewSearchInput.trim();
    setPreviewSearch(term);
    loadPreview({ page: 1, filters: { search: term } });
  };

  const handleClearPreviewSearch = () => {
    setPreviewSearchInput('');
    setPreviewSearch('');
    loadPreview({ page: 1, filters: { search: '' } });
  };

  const handlePreviewPageChange = (url: string) => {
    const match = url.match(/[?&]page=(\d+)/);
    const page = match ? Number(match[1]) : 1;
    loadPreview({ page });
  };

  const handleGenerate = () => {
    setGenerateConfirmOpen(false);
    if (existingRunPreview?.is_locked) {
      toast.error(t('Payroll for this period and scope is already locked.'));
      return;
    }
    if (previewSummary.ready_count === 0) {
      toast.error(t('No employees ready for payroll generation'));
      return;
    }
    setIsGenerating(true);
    const options = {
      onError: (errors: Record<string, string>) => {
        const msg = Object.values(errors)[0];
        toast.error(typeof msg === 'string' ? msg : t('Generation failed'));
        setIsGenerating(false);
      },
      onFinish: () => setIsGenerating(false),
    };

    if (isEdit) {
      router.put(route('hr.salary-payroll.generate.update', existingRun.id), payload(), options);
    } else {
      router.post(route('hr.salary-payroll.generate.store'), payload(), options);
    }
  };

  const openGenerateConfirm = () => {
    if (existingRunPreview?.is_locked) {
      toast.error(t('Payroll for this period and scope is already locked.'));
      return;
    }
    if (previewSummary.ready_count === 0) {
      toast.error(t('No employees ready for payroll generation'));
      return;
    }
    setGenerateConfirmOpen(true);
  };

  const filteredEmployees = useMemo(() => {
    if (!searchTerm) return employees;
    const q = searchTerm.toLowerCase();
    return employees.filter(
      (e: { name: string; employee_code?: string }) =>
        e.name.toLowerCase().includes(q) || (e.employee_code || '').toLowerCase().includes(q)
    );
  }, [employees, searchTerm]);

  const previewStatusOptions = useMemo(() => [
    { label: t('All Status'), value: 'all' },
    { label: t('Ready'), value: 'ready' },
    { label: t('Missing Salary'), value: 'missing' },
  ], [t]);

  const previewCategoryOptions = useMemo(() => [
    { label: t('All Categories'), value: 'all' },
    ...(categories as { id: number; name: string }[]).map((c) => ({ label: c.name, value: String(c.id) })),
  ], [categories, t]);

  const previewShiftOptions = useMemo(() => [
    { label: t('All Shifts'), value: 'all' },
    ...(shifts as { id: number; name: string }[]).map((s) => ({ label: s.name, value: String(s.id) })),
  ], [shifts, t]);

  const previewDepartmentOptions = useMemo(() => [
    { label: t('All Departments'), value: 'all' },
    ...(departments as { id: number; name: string }[]).map((d) => ({ label: d.name, value: String(d.id) })),
  ], [departments, t]);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Payroll'), href: route('hr.salary-payroll.generate.index') },
    { title: isEdit ? t('Customize Payroll') : t('Generate Payroll') },
  ];

  const pageUrl = isEdit
    ? route('hr.salary-payroll.generate.edit', existingRun.id)
    : route('hr.salary-payroll.generate.create');

  const steps = [
    { n: 1, label: t('Period') },
    { n: 2, label: t('Scope') },
    { n: 3, label: t('Preview') },
  ];

  return (
    <PageTemplate
      title={isEdit ? t('Customize Payroll') : t('Generate Payroll')}
      url={pageUrl}
      breadcrumbs={breadcrumbs}
    >
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/80 p-3 shadow-sm">
        <div>
          <p className="text-sm font-semibold text-slate-800">
            {isEdit ? t('Customize & Regenerate') : t('New Payroll Run')}
          </p>
          <p className="text-[11px] text-slate-500">
            {isEdit
              ? t('Change period, scope, or filters — then regenerate. Lock only when final.')
              : `${t('Branch')}: ${activeBranchName || '—'}`}
          </p>
        </div>
        <Button variant="outline" size="sm" asChild>
          <Link href={isEdit ? route('hr.salary-payroll.generate.show', existingRun.id) : route('hr.salary-payroll.generate.index')}>
            <ArrowLeft className="mr-1.5 h-4 w-4" />
            {t('Back')}
          </Link>
        </Button>
      </div>

      <div className="mb-4 flex items-center gap-2">
        {steps.map((s, i) => (
          <div key={s.n} className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => s.n < step && setStep(s.n)}
              className={cn(
                'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors',
                step >= s.n ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500',
                s.n < step && 'cursor-pointer hover:bg-primary/80'
              )}
            >
              {s.n}
            </button>
            <span className={cn('text-xs font-medium', step >= s.n ? 'text-slate-800' : 'text-slate-400')}>{s.label}</span>
            {i < steps.length - 1 && <ChevronRight className="h-4 w-4 text-slate-300" />}
          </div>
        ))}
      </div>

      {/* Step 1: Period */}
      <div className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 className="mb-3 text-sm font-bold text-slate-800">{t('Step 1 — Select Period')}</h3>

        <div className="mb-4 flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => handlePeriodModeChange('month')}
            className={cn(
              'rounded-full border px-4 py-1.5 text-xs font-semibold transition-all',
              periodMode === 'month'
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-slate-200 text-slate-600 hover:border-slate-300'
            )}
          >
            {t('Full Month')}
          </button>
          <button
            type="button"
            onClick={() => handlePeriodModeChange('custom')}
            className={cn(
              'rounded-full border px-4 py-1.5 text-xs font-semibold transition-all',
              periodMode === 'custom'
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-slate-200 text-slate-600 hover:border-slate-300'
            )}
          >
            {t('Custom Date Range')}
          </button>
        </div>

        {periodMode === 'month' ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1">
              <Label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{t('Financial Year')}</Label>
              <Select value={financialYear} onValueChange={setFinancialYear}>
                <SelectTrigger className="h-9 text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {financialYearOptions.map((fy: string) => (
                    <SelectItem key={fy} value={fy}>{fy}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{t('Month')}</Label>
              <Select value={monthYear} onValueChange={setMonthYear}>
                <SelectTrigger className="h-9 text-sm"><SelectValue placeholder={t('Select month')} /></SelectTrigger>
                <SelectContent>
                  {monthOptions.map((m: { value: string; label: string }) => (
                    <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-end">
              <p className="text-xs text-slate-500">
                {t('Pay period')}: <span className="font-medium text-slate-700">{payPeriodDisplay}</span>
              </p>
            </div>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1">
              <Label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{t('Financial Year')}</Label>
              <Select value={financialYear} onValueChange={setFinancialYear}>
                <SelectTrigger className="h-9 text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {financialYearOptions.map((fy: string) => (
                    <SelectItem key={fy} value={fy}>{fy}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{t('From Date')}</Label>
              <Input
                type="date"
                className="h-9 text-sm"
                value={customStart}
                onChange={(e) => setCustomStart(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{t('To Date')}</Label>
              <Input
                type="date"
                className="h-9 text-sm"
                value={customEnd}
                min={customStart || undefined}
                onChange={(e) => setCustomEnd(e.target.value)}
              />
            </div>
            <div className="flex items-end">
              <p className="text-xs text-slate-500">
                {t('Pay period')}: <span className="font-medium text-slate-700">{payPeriodDisplay || '—'}</span>
              </p>
            </div>
          </div>
        )}

        {periodMode === 'custom' && customStart && customEnd && customEnd < customStart && (
          <p className="mt-2 text-xs text-red-600">{t('End date must be on or after start date')}</p>
        )}

        <div className="mt-4 rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-3">
          <label className="flex cursor-pointer items-start gap-3">
            <Checkbox
              checked={useAttendance}
              onCheckedChange={(v) => setUseAttendance(Boolean(v))}
              className="mt-0.5"
            />
            <div>
              <p className="text-xs font-bold text-slate-800">{t('Use attendance for salary')}</p>
              <p className="mt-0.5 text-[11px] leading-relaxed text-slate-600">
                {t('When ON: pay is calculated from biometric present days (working days → present → paid). Mispunch (MIS) days are flagged — fix in Attendance Sync before locking payroll.')}
              </p>
            </div>
          </label>

          {useAttendance && canApplyAttendanceExtra && (
            <label className="mt-3 flex cursor-pointer items-start gap-3 rounded-lg border border-sky-200 bg-sky-50/60 p-3">
              <Checkbox
                checked={applyAttendanceExtra}
                onCheckedChange={(v) => setApplyAttendanceExtra(Boolean(v))}
                className="mt-0.5"
              />
              <div>
                <p className="text-xs font-bold text-sky-950">{t('Add extra days salary to net (OT No only)')}</p>
                <p className="mt-0.5 text-[11px] leading-relaxed text-sky-900/80">
                  {t('When ON: all OT No employees with extra days get adjust added to net on generate. You can still turn ON/OFF per employee later in payroll breakdown. When OFF: adjust is pending until you enable it per employee or here.')}
                </p>
              </div>
            </label>
          )}
        </div>

        {step === 1 && (
          <div className="mt-4 flex justify-end">
            <Button size="sm" onClick={() => setStep(2)} disabled={!isPeriodValid}>
              {t('Next — Choose Scope')}
              <ChevronRight className="ml-1 h-4 w-4" />
            </Button>
          </div>
        )}
      </div>

      {/* Step 2: Scope */}
      {step >= 2 && (
        <div className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-sm font-bold text-slate-800">{t('Step 2 — Who to include?')}</h3>
          <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {scopeCards.map(({ mode, icon: Icon, title, desc }) => (
              <button
                key={mode}
                type="button"
                onClick={() => setScopeMode(mode)}
                className={cn(
                  'rounded-lg border-2 p-3 text-left transition-all',
                  scopeMode === mode
                    ? 'border-primary bg-primary/5 shadow-sm'
                    : 'border-slate-200 hover:border-slate-300'
                )}
              >
                <Icon className={cn('mb-2 h-5 w-5', scopeMode === mode ? 'text-primary' : 'text-slate-400')} />
                <p className="text-xs font-bold text-slate-800">{t(title)}</p>
                <p className="mt-0.5 text-[10px] text-slate-500">{t(desc)}</p>
              </button>
            ))}
          </div>

          {scopeMode === 'category' && (
            <div className="mb-3 flex flex-wrap gap-2">
              {categories.map((cat: { id: number; name: string }) => (
                <label
                  key={cat.id}
                  className={cn(
                    'flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs',
                    categoryIds.includes(cat.id) ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200'
                  )}
                >
                  <Checkbox
                    checked={categoryIds.includes(cat.id)}
                    onCheckedChange={(c) => setCategoryIds(toggleId(categoryIds, cat.id, !!c))}
                  />
                  {cat.name}
                </label>
              ))}
            </div>
          )}

          {scopeMode === 'shift' && (
            <div className="mb-3 flex flex-wrap gap-2">
              {shifts.map((sh: { id: number; name: string }) => (
                <label
                  key={sh.id}
                  className={cn(
                    'flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs',
                    shiftIds.includes(sh.id) ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200'
                  )}
                >
                  <Checkbox
                    checked={shiftIds.includes(sh.id)}
                    onCheckedChange={(c) => setShiftIds(toggleId(shiftIds, sh.id, !!c))}
                  />
                  {sh.name}
                </label>
              ))}
            </div>
          )}

          {scopeMode === 'employee' && (
            <div className="mb-3 space-y-2">
              <Input
                placeholder={t('Search by name or code...')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="h-9 max-w-sm text-sm"
              />
              <div className="max-h-48 overflow-y-auto rounded-lg border border-slate-200 p-2">
                {filteredEmployees.map((emp: { id: number; name: string; employee_code?: string }) => (
                  <label key={emp.id} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs hover:bg-slate-50">
                    <Checkbox
                      checked={employeeIds.includes(emp.id)}
                      onCheckedChange={(c) => setEmployeeIds(toggleId(employeeIds, emp.id, !!c))}
                    />
                    <span className="font-medium">{emp.name}</span>
                    {emp.employee_code && <span className="text-slate-400">({emp.employee_code})</span>}
                  </label>
                ))}
              </div>
              {employeeIds.length > 0 && (
                <p className="text-[11px] text-slate-500">{employeeIds.length} {t('selected')}</p>
              )}
            </div>
          )}

          <button
            type="button"
            className="text-[11px] text-primary underline"
            onClick={() => setShowMoreFilters(!showMoreFilters)}
          >
            {showMoreFilters ? t('Hide filters') : t('More filters (Department)')}
          </button>
          {showMoreFilters && (
            <div className="mt-2 max-w-xs">
              <Label className="text-[10px] uppercase text-slate-500">{t('Department')}</Label>
              <Select value={departmentId} onValueChange={setDepartmentId}>
                <SelectTrigger className="mt-1 h-9 text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t('All Departments')}</SelectItem>
                  {departments.map((d: { id: number; name: string }) => (
                    <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {step === 2 && (
            <div className="mt-4 flex justify-end gap-2">
              <Button variant="outline" size="sm" onClick={() => setStep(1)}>{t('Back')}</Button>
              <Button size="sm" onClick={() => loadPreview({ fromStep2: true })} disabled={isPreviewing}>
                {isPreviewing ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : null}
                {t('Preview Employees')}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Step 3: Preview */}
      {step >= 3 && (
        <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
            <div>
              <h3 className="text-sm font-bold text-slate-800">{t('Step 3 — Preview')}</h3>
              <div className="mt-1 flex flex-wrap gap-2">
                <Badge className="border-0 bg-green-100 text-green-700">
                  <CheckCircle2 className="mr-1 h-3 w-3" />
                  {previewSummary.ready_count} {t('ready')}
                </Badge>
                {previewSummary.missing_count > 0 && (
                  <Badge className="border-0 bg-amber-100 text-amber-700">
                    <AlertTriangle className="mr-1 h-3 w-3" />
                    {previewSummary.missing_count} {t('missing salary')}
                  </Badge>
                )}
                <Badge variant="outline" className="text-xs">
                  {t('Total gross')}: ₹{formatRupee(previewSummary.total_gross)}
                </Badge>
              </div>
            </div>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => setStep(2)}>{t('Change Scope')}</Button>
              <Button
                size="sm"
                onClick={openGenerateConfirm}
                disabled={isGenerating || previewSummary.ready_count === 0 || existingRunPreview?.is_locked}
              >
                {isGenerating ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : null}
                {isEdit
                  ? t('Update & Regenerate')
                  : existingRunPreview
                    ? t('Update Existing & Regenerate')
                    : t('Generate Payroll')}
              </Button>
            </div>
          </div>
          {existingRunPreview && !existingRunPreview.is_locked && (
            <div className="border-b border-amber-100 bg-amber-50 px-4 py-2 text-xs text-amber-800">
              {t('Matching run already exists')}: <strong>{existingRunPreview.title}</strong>.
              {' '}{t('Generating will update this run — no duplicate will be created.')}
            </div>
          )}
          {existingRunPreview?.is_locked && (
            <div className="border-b border-red-100 bg-red-50 px-4 py-2 text-xs text-red-800">
              {t('This period and scope is already locked. Choose a different scope or date range.')}
            </div>
          )}

          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <p className="text-[11px] text-slate-500">
              {t('Review employees before generating. Use filters to check missing salary or search by name/code.')}
            </p>
            <div className="flex flex-wrap items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <Input
                  value={previewSearchInput}
                  onChange={(e) => setPreviewSearchInput(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && handlePreviewSearch()}
                  placeholder={t('Search name or code...')}
                  className="h-8 w-[170px] pl-8 text-xs"
                />
                {previewSearchInput && (
                  <button type="button" onClick={handleClearPreviewSearch} className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <X className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>
              <Button variant="outline" size="sm" className="h-8 text-xs" onClick={handlePreviewSearch} disabled={isPreviewing}>
                {t('Search')}
              </Button>
              <Combobox
                value={previewStatus}
                onChange={(v) => {
                  const status = (v || 'all') as PreviewStatusFilter;
                  setPreviewStatus(status);
                  loadPreview({ page: 1, filters: { status } });
                }}
                options={previewStatusOptions}
                placeholder={t('All Status')}
                searchPlaceholder={t('Search status...')}
                emptyText={t('No status found.')}
                className="h-8 w-[130px] text-xs"
              />
              <Combobox
                value={previewCategoryId}
                onChange={(v) => {
                  const categoryId = v || 'all';
                  setPreviewCategoryId(categoryId);
                  loadPreview({ page: 1, filters: { categoryId } });
                }}
                options={previewCategoryOptions}
                placeholder={t('All Categories')}
                searchPlaceholder={t('Search category...')}
                emptyText={t('No category found.')}
                className="h-8 w-[140px] text-xs"
              />
              <Combobox
                value={previewShiftId}
                onChange={(v) => {
                  const shiftId = v || 'all';
                  setPreviewShiftId(shiftId);
                  loadPreview({ page: 1, filters: { shiftId } });
                }}
                options={previewShiftOptions}
                placeholder={t('All Shifts')}
                searchPlaceholder={t('Search shift...')}
                emptyText={t('No shift found.')}
                className="h-8 w-[130px] text-xs"
              />
              <Combobox
                value={previewDepartmentId}
                onChange={(v) => {
                  const departmentId = v || 'all';
                  setPreviewDepartmentId(departmentId);
                  loadPreview({ page: 1, filters: { departmentId } });
                }}
                options={previewDepartmentOptions}
                placeholder={t('All Departments')}
                searchPlaceholder={t('Search department...')}
                emptyText={t('No department found.')}
                className="h-8 w-[150px] text-xs"
              />
              <Select
                value={String(previewPerPage)}
                onValueChange={(v) => {
                  const perPage = Number(v);
                  setPreviewPerPage(perPage);
                  loadPreview({ page: 1, filters: { perPage } });
                }}
              >
                <SelectTrigger className="h-8 w-[90px] text-xs"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {[25, 50, 100, 200].map((n) => (
                    <SelectItem key={n} value={String(n)}>{n} / {t('page')}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="overflow-x-auto">
            <Table>
              <TableHeader className="sticky top-0 bg-slate-50">
                <TableRow>
                  <TableHead className="text-[10px]">{t('Code')}</TableHead>
                  <TableHead className="text-[10px]">{t('Name')}</TableHead>
                  <TableHead className="text-[10px]">{t('Category')}</TableHead>
                  <TableHead className="text-[10px]">{t('Shift')}</TableHead>
                  <TableHead className="text-[10px]">{t('Department')}</TableHead>
                  <TableHead className="text-right text-[10px]">{t('Gross')}</TableHead>
                  <TableHead className="text-[10px]">{t('Status')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isPreviewing && previewRows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="py-10 text-center text-sm text-slate-400">
                      <Loader2 className="mx-auto mb-2 h-5 w-5 animate-spin" />
                      {t('Loading preview...')}
                    </TableCell>
                  </TableRow>
                ) : previewRows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="py-10 text-center text-sm text-slate-400">
                      {previewSearch || previewStatus !== 'all' || previewCategoryId !== 'all' || previewShiftId !== 'all' || previewDepartmentId !== 'all'
                        ? t('No employees match your filters.')
                        : t('No employees in preview.')}
                    </TableCell>
                  </TableRow>
                ) : previewRows.map((row) => (
                  <TableRow key={row.id} className={!row.ready ? 'bg-amber-50/50' : ''}>
                    <TableCell className="text-xs">{row.employee_code || '—'}</TableCell>
                    <TableCell className="text-xs font-medium">{row.name}</TableCell>
                    <TableCell className="text-xs">{row.category || '—'}</TableCell>
                    <TableCell className="text-xs">{row.shift || '—'}</TableCell>
                    <TableCell className="text-xs">{row.department || '—'}</TableCell>
                    <TableCell className="text-right text-xs">
                      {row.ready ? `₹${formatRupee(row.monthly_gross)}` : '—'}
                    </TableCell>
                    <TableCell>
                      {row.ready ? (
                        <Badge className="border-0 bg-green-100 text-[10px] text-green-700">{t('Ready')}</Badge>
                      ) : (
                        <Badge className="border-0 bg-amber-100 text-[10px] text-amber-700">{t('Missing')}</Badge>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          {previewPagination.total > 0 && (
            <Pagination
              from={previewPagination.from}
              to={previewPagination.to}
              total={previewPagination.total}
              currentPage={previewPagination.current_page}
              lastPage={previewPagination.last_page}
              entityName={t('employees')}
              onPageChange={handlePreviewPageChange}
              className="border-t border-slate-100 bg-slate-50/50 px-3 py-2"
            />
          )}
        </div>
      )}

      <ConfirmActionDialog
        open={generateConfirmOpen}
        onOpenChange={setGenerateConfirmOpen}
        title={isEdit ? t('Update & Regenerate Payroll?') : t('Generate Payroll?')}
        description={
          applyAttendanceExtra
            ? t('You are about to generate payroll for {{count}} employees with total gross ₹{{amount}}. Extra days salary (OT No) will be added to net where applicable. Continue?', {
                count: previewSummary.ready_count,
                amount: formatRupee(previewSummary.total_gross),
              })
            : t('You are about to generate payroll for {{count}} employees with total gross ₹{{amount}}. Extra days (OT No) will appear in Adjust column but will NOT be added to net unless you enable that option above. Continue?', {
                count: previewSummary.ready_count,
                amount: formatRupee(previewSummary.total_gross),
              })
        }
        confirmLabel={isEdit ? t('Update & Regenerate') : existingRunPreview ? t('Update Existing & Regenerate') : t('Generate Payroll')}
        cancelLabel={t('Cancel')}
        variant="primary"
        icon={<Banknote className="h-6 w-6" />}
        loading={isGenerating}
        onConfirm={handleGenerate}
      />
    </PageTemplate>
  );
}
