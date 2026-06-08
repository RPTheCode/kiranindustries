import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router, Link } from '@inertiajs/react';
import axios from 'axios';
import {
  Search,
  X,
  Loader2,
  Info,
  History,
  TrendingUp,
  Layers,
} from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { canCreateSalaryPayrollEmployee, canEditSalaryPayrollEmployee } from '@/utils/authorization';
import { Pagination } from '@/components/ui/pagination';
import { Combobox } from '@/components/ui/combobox';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import {
  componentAppliesToEmployee,
  customComponents as getCustomGroupComponents,
  hasCustomAssignment,
  isPrimaryComponent,
  primaryComponents as getPrimaryGroupComponents,
  resolveAssignedComponentIds,
  resolveComponentsForEmployee,
  storedCustomComponentIds,
} from '@/utils/salary-component-assignment';

interface SkippedRow {
  id: number;
  name: string;
  type: string;
  reason: string;
  skipped: true;
}

function isPfComponent(comp: any): boolean {
  const name = String(comp.name || '').toUpperCase().trim();
  if (['PF', 'PF DEDUCTION', 'PROVIDENT FUND', 'EPF'].includes(name)) return true;
  return comp.type === 'deduction' && name.includes('PF');
}

function isEsiComponent(comp: any): boolean {
  const name = String(comp.name || '').toUpperCase().trim();
  if (['ESI', 'ESIC', 'ESI DEDUCTION'].includes(name)) return true;
  return comp.type === 'deduction' && (name.includes('ESI') || name.includes('ESIC'));
}

function calcComponentAmount(comp: any, basic: number, gross: number): number {
  let amount = 0;
  if (comp.calculation_type === 'percentage_of_gross') {
    amount = (gross * Number(comp.percentage_of_gross_pay || 0)) / 100;
  } else if (comp.calculation_type === 'percentage') {
    amount = (basic * Number(comp.percentage_of_basic || 0)) / 100;
  }
  switch (comp.rounding_method) {
    case 'round':
      return Math.round(amount);
    case 'ceil':
      return Math.ceil(amount);
    case 'floor':
      return Math.floor(amount);
    default:
      return Math.round(amount * 100) / 100;
  }
}

function splitGrossFromComponents(
  gross: number,
  components: any[],
  opts: { applyPf?: boolean; applyEsi?: boolean } = {},
) {
  const applyPf = opts.applyPf ?? true;
  const applyEsi = opts.applyEsi ?? true;
  const active = components.filter((c) => {
    if (c.status && c.status !== 'active') return false;
    return true;
  });

  const breakdown: BreakdownRow[] = [];
  const skipped: SkippedRow[] = [];
  const amounts: Record<number, number> = {};
  let basicAmount = 0;

  const eligible = active.filter((comp) => {
    if (!applyPf && isPfComponent(comp)) {
      skipped.push({ id: comp.id, name: comp.name, type: comp.type, reason: 'PF not applicable', skipped: true });
      return false;
    }
    if (!applyEsi && isEsiComponent(comp)) {
      skipped.push({ id: comp.id, name: comp.name, type: comp.type, reason: 'ESI not applicable', skipped: true });
      return false;
    }
    return true;
  });

  const grossComps = eligible.filter((c) => c.calculation_type === 'percentage_of_gross');
  for (const comp of grossComps) {
    const amount = calcComponentAmount(comp, 0, gross);
    amounts[comp.id] = amount;
    breakdown.push({
      id: comp.id,
      name: comp.name,
      type: comp.type,
      calculation_type: comp.calculation_type,
      rate: Number(comp.percentage_of_gross_pay || 0),
      base: 'gross',
      base_amount: gross,
      amount,
    });
    if (comp.name?.toUpperCase() === 'BASIC') {
      basicAmount = amount;
    }
  }

  if (basicAmount <= 0) {
    const basicComp = grossComps.find((c) => c.name?.toUpperCase() === 'BASIC')
      ?? grossComps.find((c) => c.type === 'earning');
    basicAmount = basicComp ? amounts[basicComp.id] : gross;
  }

  const basicComps = eligible.filter((c) => c.calculation_type === 'percentage');
  for (const comp of basicComps) {
    const amount = calcComponentAmount(comp, basicAmount, gross);
    amounts[comp.id] = amount;
    breakdown.push({
      id: comp.id,
      name: comp.name,
      type: comp.type,
      calculation_type: comp.calculation_type,
      rate: Number(comp.percentage_of_basic || 0),
      base: 'basic',
      base_amount: basicAmount,
      amount,
    });
  }

  const totalEarnings = breakdown.filter((r) => r.type === 'earning').reduce((s, r) => s + r.amount, 0);
  const totalDeductions = breakdown.filter((r) => r.type === 'deduction').reduce((s, r) => s + r.amount, 0);

  return {
    breakdown,
    skipped,
    amounts,
    totalEarnings,
    totalDeductions,
    netSalary: totalEarnings - totalDeductions,
    basicAmount,
    applyPf,
    applyEsi,
  };
}

interface BreakdownRow {
  id: number;
  name: string;
  type: 'earning' | 'deduction';
  calculation_type: string;
  rate: number;
  base: string;
  base_amount: number;
  amount: number;
}

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

export default function SalaryPayrollEmployeeSalaries() {
  const { t } = useTranslation();
  const {
    auth,
    employees,
    salaryComponents = [],
    primaryComponents = [],
    customComponents = [],
    categories = [],
    departments = [],
    shifts = [],
    activeBranchId,
    activeBranchName,
    filters: pageFilters = {},
    defaultEffectiveFrom,
  } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const canSave = canCreateSalaryPayrollEmployee(permissions) || canEditSalaryPayrollEmployee(permissions);

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [categoryId, setCategoryId] = useState(pageFilters.category_id || 'all');
  const [departmentId, setDepartmentId] = useState(pageFilters.department_id || 'all');
  const [shiftId, setShiftId] = useState(pageFilters.shift_id || 'all');
  const [perPage, setPerPage] = useState(String(pageFilters.per_page || 50));
  const [items, setItems] = useState<any[]>([]);
  const [grossInputs, setGrossInputs] = useState<Record<number, string>>({});
  const [savingIds, setSavingIds] = useState<Set<number>>(new Set());
  const [isSearching, setIsSearching] = useState(false);
  const skipDebounce = useRef(true);

  const [historyOpen, setHistoryOpen] = useState(false);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyRows, setHistoryRows] = useState<any[]>([]);
  const [historyEmployee, setHistoryEmployee] = useState<any>(null);

  const [incrementOpen, setIncrementOpen] = useState(false);
  const [incrementEmployee, setIncrementEmployee] = useState<any>(null);
  const [incrementMode, setIncrementMode] = useState<'percentage' | 'fixed' | 'set_gross'>('percentage');
  const [incrementValue, setIncrementValue] = useState('');
  const [effectiveFrom, setEffectiveFrom] = useState(defaultEffectiveFrom || '');
  const [incrementNotes, setIncrementNotes] = useState('');
  const [isIncrementSaving, setIsIncrementSaving] = useState(false);
  const [componentsOpen, setComponentsOpen] = useState(false);
  const [componentsEmployee, setComponentsEmployee] = useState<any>(null);
  const [selectedComponentIds, setSelectedComponentIds] = useState<number[]>([]);
  const [componentsCustomize, setComponentsCustomize] = useState(false);
  const [isSavingComponents, setIsSavingComponents] = useState(false);

  const minEffectiveDate = defaultEffectiveFrom || new Date().toISOString().slice(0, 10);

  const handleEffectiveFromChange = (value: string) => {
    if (value && value < minEffectiveDate) {
      toast.error(t('Increment date must be today or a future date'));
      return;
    }
    setEffectiveFrom(value);
  };

  useEffect(() => {
    const data = employees?.data || [];
    setItems(data);
    setIsSearching(false);
    const drafts: Record<number, string> = {};
    data.forEach((emp: any) => {
      drafts[emp.id] = emp.salary_record?.monthly_gross
        ? String(emp.salary_record.monthly_gross)
        : '';
    });
    setGrossInputs(drafts);
  }, [employees]);

  const branchLabel = activeBranchName || t('Selected Branch');

  const fetchList = useCallback((params: Record<string, string | undefined>, resetPage = false) => {
    router.get(route('hr.salary-payroll.employee-salary.index'), {
      ...(resetPage ? { page: '1' } : {}),
      ...params,
      per_page: params.per_page ?? perPage,
    }, {
      preserveState: true,
      preserveScroll: !resetPage,
      onStart: () => setIsSearching(true),
      onFinish: () => setIsSearching(false),
    });
  }, [perPage]);

  const filterParams = useCallback(() => ({
    search: searchTerm || undefined,
    category_id: categoryId !== 'all' ? categoryId : undefined,
    department_id: departmentId !== 'all' ? departmentId : undefined,
    shift_id: shiftId !== 'all' ? shiftId : undefined,
  }), [searchTerm, categoryId, departmentId, shiftId]);

  useEffect(() => {
    if (skipDebounce.current) {
      skipDebounce.current = false;
      return;
    }
    const timer = setTimeout(() => fetchList(filterParams(), true), 300);
    return () => clearTimeout(timer);
  }, [searchTerm, fetchList, filterParams]);

  const applyFilter = (key: string, value: string) => {
    const next = {
      search: searchTerm || undefined,
      category_id: key === 'category_id' ? (value !== 'all' ? value : undefined) : (categoryId !== 'all' ? categoryId : undefined),
      department_id: key === 'department_id' ? (value !== 'all' ? value : undefined) : (departmentId !== 'all' ? departmentId : undefined),
      shift_id: key === 'shift_id' ? (value !== 'all' ? value : undefined) : (shiftId !== 'all' ? shiftId : undefined),
    };
    if (key === 'category_id') setCategoryId(value);
    if (key === 'department_id') setDepartmentId(value);
    if (key === 'shift_id') setShiftId(value);
    fetchList(next, true);
  };

  const handlePageChange = (url: string) => {
    router.get(url, {}, {
      preserveState: true,
      preserveScroll: true,
      onStart: () => setIsSearching(true),
      onFinish: () => setIsSearching(false),
    });
  };

  const handlePerPageChange = (value: string) => {
    setPerPage(value);
    fetchList({ ...filterParams(), per_page: value }, true);
  };

  const getSplitForEmployee = useCallback((emp: any, grossValue: string) => {
    const gross = Number(grossValue);
    if (!gross || gross <= 0 || salaryComponents.length === 0) return null;
    const employeeComponents = resolveComponentsForEmployee(salaryComponents, emp.extra_salary_component_ids);
    return splitGrossFromComponents(gross, employeeComponents, {
      applyPf: Boolean(emp.pf_applicable),
      applyEsi: Boolean(emp.esi_applicable),
    });
  }, [salaryComponents]);

  const saveEmployeeGross = (emp: any, grossValue: string) => {
    const gross = Number(grossValue);
    if (!gross || gross <= 0) return;

    const savedGross = emp.salary_record?.monthly_gross;
    if (savedGross && Number(savedGross) === gross) return;

    setSavingIds((prev) => new Set(prev).add(emp.id));

    const salaryId = emp.salary_record?.id;
    const method = salaryId ? 'put' : 'post';
    const routeName = salaryId
      ? 'hr.salary-payroll.employee-salary.update'
      : 'hr.salary-payroll.employee-salary.store';
    const routeParams = salaryId ? [salaryId] : [];

    router[method](route(routeName, routeParams), {
      employee_id: emp.id,
      monthly_gross: grossValue,
    }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success(t('Salary saved')),
      onError: (errors) => toast.error(Object.values(errors).join(', ')),
      onFinish: () => {
        setSavingIds((prev) => {
          const next = new Set(prev);
          next.delete(emp.id);
          return next;
        });
      },
    });
  };

  const handleGrossChange = (empId: number, value: string) => {
    setGrossInputs((prev) => ({ ...prev, [empId]: value }));
  };

  const handleGrossBlur = (emp: any) => {
    if (!canSave) return;
    const value = grossInputs[emp.id] ?? '';
    if (!value.trim()) return;
    saveEmployeeGross(emp, value);
  };

  const handleGrossKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      (e.currentTarget as HTMLInputElement).blur();
    }
  };

  const openHistory = async (emp: any) => {
    setHistoryEmployee(emp);
    setHistoryOpen(true);
    setHistoryLoading(true);
    try {
      const { data } = await axios.get(route('hr.salary-payroll.employee-salary.history', emp.id));
      setHistoryRows(data.history || []);
      setHistoryEmployee(data.employee || emp);
    } catch {
      toast.error(t('Failed to load salary history'));
      setHistoryRows([]);
    } finally {
      setHistoryLoading(false);
    }
  };

  const openIncrement = (emp: any) => {
    setIncrementEmployee(emp);
    setIncrementMode('percentage');
    setIncrementValue('10');
    setEffectiveFrom(minEffectiveDate);
    setIncrementNotes('');
    setIncrementOpen(true);
  };

  const incrementPreviewGross = useMemo(() => {
    if (!incrementEmployee || !incrementValue) return null;
    const current = Number(grossInputs[incrementEmployee.id] ?? incrementEmployee.salary_record?.monthly_gross ?? 0);
    const value = Number(incrementValue);
    if (!value || value <= 0) return null;
    if (incrementMode === 'percentage') return Math.round(current * (1 + value / 100));
    if (incrementMode === 'fixed') return Math.round(current + value);
    return Math.round(value);
  }, [incrementEmployee, incrementValue, incrementMode, grossInputs]);

  const submitIncrement = () => {
    if (!incrementEmployee || !incrementValue || !effectiveFrom) return;
    setIsIncrementSaving(true);
    router.post(route('hr.salary-payroll.employee-salary.increment', incrementEmployee.id), {
      increment_mode: incrementMode,
      increment_value: incrementValue,
      effective_from: effectiveFrom,
      notes: incrementNotes || undefined,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setIncrementOpen(false);
        toast.success(t('Salary increment saved'));
      },
      onError: (errors) => toast.error(Object.values(errors).join(', ')),
      onFinish: () => setIsIncrementSaving(false),
    });
  };

  const openComponentsDialog = (emp: any) => {
    setComponentsEmployee(emp);
    const assigned = (emp.extra_salary_component_ids || []).map(Number);
    setComponentsCustomize(hasCustomAssignment(assigned, salaryComponents as any[]));
    setSelectedComponentIds(
      hasCustomAssignment(assigned, salaryComponents as any[])
        ? storedCustomComponentIds(assigned, salaryComponents as any[])
        : [],
    );
    setComponentsOpen(true);
  };

  const handleComponentsCustomizeToggle = (on: boolean) => {
    setComponentsCustomize(on);
    if (on) {
      setSelectedComponentIds(storedCustomComponentIds(selectedComponentIds, salaryComponents as any[]));
    } else {
      setSelectedComponentIds([]);
    }
  };

  const toggleComponentSelection = (id: number, checked: boolean) => {
    const comp = (salaryComponents as any[]).find((c) => Number(c.id) === id);
    if (comp && isPrimaryComponent(comp)) {
      return;
    }
    setSelectedComponentIds((prev) => {
      if (checked) return [...prev, id];
      return prev.filter((x) => x !== id);
    });
  };

  const saveComponentAssignment = () => {
    if (!componentsEmployee) return;
    setIsSavingComponents(true);
    router.post(route('hr.salary-payroll.employee-salary.components', componentsEmployee.id), {
      extra_salary_component_ids: componentsCustomize ? selectedComponentIds : [],
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(t('Salary components updated'));
        setComponentsOpen(false);
      },
      onError: (errors) => toast.error(Object.values(errors).join(', ')),
      onFinish: () => setIsSavingComponents(false),
    });
  };

  const revisionTypeLabel = (type: string) => {
    const map: Record<string, string> = {
      joining: t('Joining'),
      increment: t('Increment'),
      promotion: t('Promotion'),
      correction: t('Correction'),
    };
    return map[type] || type;
  };

  const primaryCols = useMemo(
    () => (primaryComponents as any[]).length > 0
      ? (primaryComponents as any[])
      : getPrimaryGroupComponents(salaryComponents as any[]),
    [primaryComponents, salaryComponents],
  );
  const customCols = useMemo(
    () => (customComponents as any[]).length > 0
      ? (customComponents as any[])
      : getCustomGroupComponents(salaryComponents as any[]),
    [customComponents, salaryComponents],
  );
  const tableComponents = useMemo(() => [...primaryCols, ...customCols], [primaryCols, customCols]);
  const tableColSpan = 6 + tableComponents.length;

  const categoryOptions = useMemo(() => [
    { label: t('All Categories'), value: 'all' },
    ...(categories as any[]).map((c) => ({ label: c.name, value: String(c.id) })),
  ], [categories, t]);

  const departmentOptions = useMemo(() => [
    { label: t('All Departments'), value: 'all' },
    ...(departments as any[]).map((d) => ({ label: d.name, value: String(d.id) })),
  ], [departments, t]);

  const shiftOptions = useMemo(() => [
    { label: t('All Shifts'), value: 'all' },
    ...(shifts as any[]).map((s) => ({ label: s.name, value: String(s.id) })),
  ], [shifts, t]);

  const hasFilters = categoryId !== 'all' || departmentId !== 'all' || shiftId !== 'all';

  return (
    <PageTemplate
      title={t('Employee Salary')}
      description={
        activeBranchId && activeBranchId !== 'all'
          ? `${branchLabel} · ${t('Each employee has their own gross salary')}`
          : t('Set individual gross salary per employee.')
      }
      url={route('hr.salary-payroll.employee-salary.index')}
      noPadding
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Salary Payroll') },
        { title: t('Employee Salary') },
      ]}
    >
      <div className="mx-auto max-w-[100%] space-y-3 px-1 pb-6">
        <div className="overflow-hidden rounded-xl border border-primary/15 bg-primary/5 px-4 py-3">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex items-start gap-2">
              <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <p className="text-xs text-foreground/80">
                {t('Default = Primary group. Customize to add custom components on top of primary.')}
              </p>
            </div>
            {canSave && (
              <Link
                href={route('hr.salary-payroll.salary-increment.index')}
                className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary/80 hover:underline"
              >
                <TrendingUp className="h-3.5 w-3.5" />
                {t('Bulk Increment')}
              </Link>
            )}
          </div>
        </div>

        <div className="overflow-hidden rounded-xl border border-border/90 bg-card shadow-sm">
          <div className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/60 px-3 py-2.5 sm:px-4">
            <div className="relative min-w-[180px] flex-1 sm:max-w-xs">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder={t('Search name or code...')}
                className="h-8 border-input bg-background pl-8 pr-8 text-sm shadow-none"
              />
              {searchTerm && (
                <button type="button" onClick={() => setSearchTerm('')} className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground">
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            <Combobox
              value={categoryId}
              onChange={(v) => applyFilter('category_id', v || 'all')}
              options={categoryOptions}
              placeholder={t('All Categories')}
              searchPlaceholder={t('Search category...')}
              emptyText={t('No category found.')}
              className="h-8 w-[140px] text-xs"
            />

            <Combobox
              value={departmentId}
              onChange={(v) => applyFilter('department_id', v || 'all')}
              options={departmentOptions}
              placeholder={t('All Departments')}
              searchPlaceholder={t('Search department...')}
              emptyText={t('No department found.')}
              className="h-8 w-[140px] text-xs"
            />

            <Combobox
              value={shiftId}
              onChange={(v) => applyFilter('shift_id', v || 'all')}
              options={shiftOptions}
              placeholder={t('All Shifts')}
              searchPlaceholder={t('Search shift...')}
              emptyText={t('No shift found.')}
              className="h-8 w-[130px] text-xs"
            />

            <Select value={perPage} onValueChange={handlePerPageChange}>
              <SelectTrigger className="h-8 w-[72px] text-xs"><SelectValue /></SelectTrigger>
              <SelectContent>
                {[25, 50, 100].map((n) => (
                  <SelectItem key={n} value={String(n)}>{n}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <div className="ml-auto flex items-center gap-2 text-[11px] text-muted-foreground">
              {isSearching && <Loader2 className="h-3 w-3 animate-spin text-primary" />}
              {(employees?.total ?? 0) > 0 ? (
                <span>
                  {employees.from}–{employees.to} {t('of')} {employees.total} {t('employees')}
                </span>
              ) : (
                <span>0 {t('employees')}</span>
              )}
              {hasFilters && <Badge variant="outline" className="text-[10px]">{t('Filtered')}</Badge>}
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                  <th className="px-3 py-2">#</th>
                  <th className="px-3 py-2">{t('Employee')}</th>
                  <th className="hidden px-3 py-2 md:table-cell">{t('Category')}</th>
                  <th className="hidden px-3 py-2 lg:table-cell">{t('Department')}</th>
                  <th className="hidden px-3 py-2 sm:table-cell">{t('Shift')}</th>
                  <th className="min-w-[120px] px-3 py-2">{t('Gross (₹)')}</th>
                  {tableComponents.map((comp) => (
                    <th
                      key={comp.id}
                      className={cn(
                        'min-w-[90px] whitespace-nowrap px-3 py-2 text-right',
                        comp.type === 'deduction' ? 'text-destructive' : comp.component_group === 'primary' || comp.assign_to_all ? 'text-primary' : 'text-amber-600',
                      )}
                      title={comp.calculation_type === 'percentage_of_gross'
                        ? `${comp.percentage_of_gross_pay}% ${t('on gross')}`
                        : `${comp.percentage_of_basic}% ${t('on basic')}`}
                    >
                      {comp.name}
                      {!(comp.component_group === 'primary' || comp.assign_to_all) && (
                        <span className="ml-1 text-[9px] font-normal text-amber-600">*</span>
                      )}
                    </th>
                  ))}
                  <th className="min-w-[90px] px-3 py-2 text-right">{t('Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={tableColSpan} className="px-4 py-12 text-center text-sm text-muted-foreground">
                      {t('No employees found for selected filters.')}
                    </td>
                  </tr>
                ) : items.map((emp, index) => {
                  const grossValue = grossInputs[emp.id] ?? '';
                  const split = getSplitForEmployee(emp, grossValue);
                  const isSaving = savingIds.has(emp.id);

                  return (
                  <tr key={emp.id} className="border-b border-border/60 hover:bg-muted/40">
                    <td className="px-3 py-2 text-xs text-muted-foreground">{(employees?.from || 1) + index}</td>
                    <td className="px-3 py-2">
                      <div className="font-semibold text-foreground">{emp.name}</div>
                      <div className="text-[11px] text-muted-foreground tabular-nums">
                        {emp.employee?.employee_id || '—'}
                      </div>
                    </td>
                    <td className="hidden px-3 py-2 md:table-cell text-xs text-muted-foreground">
                      {emp.employee?.category?.name || '—'}
                    </td>
                    <td className="hidden px-3 py-2 lg:table-cell text-xs text-muted-foreground">
                      {emp.employee?.department?.name || '—'}
                    </td>
                    <td className="hidden px-3 py-2 sm:table-cell text-xs text-muted-foreground">
                      {emp.employee?.shift?.name || '—'}
                    </td>
                    <td className="px-3 py-2">
                      <div className="relative flex items-center gap-1">
                        <Input
                          type="number"
                          min="0"
                          step="1"
                          disabled={!canSave || isSaving}
                          value={grossValue}
                          onChange={(e) => handleGrossChange(emp.id, e.target.value)}
                          onBlur={() => handleGrossBlur(emp)}
                          onKeyDown={handleGrossKeyDown}
                          placeholder={t('Enter gross')}
                          className="h-8 w-[110px] border-input bg-background text-sm font-semibold tabular-nums shadow-none"
                        />
                        {isSaving && <Loader2 className="h-3.5 w-3.5 shrink-0 animate-spin text-primary" />}
                      </div>
                    </td>
                    {tableComponents.map((comp) => {
                      const applies = componentAppliesToEmployee(comp, salaryComponents, emp.extra_salary_component_ids);
                      const amount = applies ? split?.amounts[comp.id] : null;
                      return (
                        <td
                          key={comp.id}
                          className={cn(
                            'px-3 py-2 text-right text-xs font-semibold tabular-nums',
                            !applies && 'text-muted-foreground/30',
                            applies && comp.type === 'deduction' ? 'text-destructive' : applies ? 'text-primary' : '',
                          )}
                        >
                          {amount != null && amount > 0
                            ? `₹${formatRupee(amount)}`
                            : <span className="text-muted-foreground/40">{applies ? '—' : '·'}</span>}
                        </td>
                      );
                    })}
                    <td className="px-3 py-2 text-right">
                      <div className="flex justify-end gap-1">
                        {canSave && salaryComponents.length > 0 && (
                          <Button type="button" size="sm" variant="ghost" className="h-7 w-7 p-0" title={t('Assign Components')} onClick={() => openComponentsDialog(emp)}>
                            <Layers className="h-3.5 w-3.5 text-amber-600" />
                          </Button>
                        )}
                        <Button type="button" size="sm" variant="ghost" className="h-7 w-7 p-0" title={t('Salary History')} onClick={() => openHistory(emp)}>
                          <History className="h-3.5 w-3.5 text-muted-foreground" />
                        </Button>
                        {canSave && emp.salary_record?.monthly_gross > 0 && (
                          <Button type="button" size="sm" variant="ghost" className="h-7 w-7 p-0" title={t('Add Increment')} onClick={() => openIncrement(emp)}>
                            <TrendingUp className="h-3.5 w-3.5 text-primary" />
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {(employees?.total ?? 0) > 0 && (
            <Pagination
              from={employees?.from || 0}
              to={employees?.to || 0}
              total={employees?.total || 0}
              links={employees?.links}
              entityName={t('employees')}
              onPageChange={handlePageChange}
              className="border-t border-border bg-muted/40 px-3 py-2"
            />
          )}
        </div>
      </div>

      <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('Salary History')}</DialogTitle>
          </DialogHeader>
          {historyEmployee && (
            <div className="space-y-1">
              <p className="text-sm text-muted-foreground">
                {historyEmployee.name}
                {historyEmployee.employee_code && (
                  <span className="ml-2 text-xs text-muted-foreground/70">#{historyEmployee.employee_code}</span>
                )}
              </p>
              {historyEmployee.date_of_joining && (
                <p className="text-[11px] text-muted-foreground">
                  {t('Joining date')}: {historyEmployee.date_of_joining}
                </p>
              )}
            </div>
          )}
          {historyLoading ? (
            <div className="flex justify-center py-8"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
          ) : historyRows.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('No salary history yet.')}</p>
          ) : (
            <div className="space-y-2">
              {historyRows.map((row) => (
                <div
                  key={row.id}
                  className={cn(
                    'rounded-lg border px-3 py-2.5',
                    row.is_active ? 'border-primary/20 bg-primary/5' : 'border-border bg-card',
                  )}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="font-semibold tabular-nums text-foreground">₹{formatRupee(row.monthly_gross)}</p>
                      <p className="mt-0.5 text-[11px] text-muted-foreground">
                        {row.revision_type === 'joining' ? t('From joining') : t('From')}
                        {' '}{row.effective_from}
                        {row.is_active
                          ? ` · ${t('Current salary')}`
                          : row.effective_to
                            ? ` — ${row.effective_to}`
                            : ''}
                      </p>
                    </div>
                    <Badge variant="outline" className="text-[10px]">
                      {revisionTypeLabel(row.revision_type)}
                    </Badge>
                  </div>
                  <div className="mt-1.5 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
                    {row.previous_gross != null && (
                      <span>{t('From')} ₹{formatRupee(row.previous_gross)}</span>
                    )}
                    {row.increment_percentage != null && (
                      <span className="text-primary">+{row.increment_percentage}%</span>
                    )}
                    {row.increment_amount != null && row.increment_amount > 0 && (
                      <span className="text-primary">+₹{formatRupee(row.increment_amount)}</span>
                    )}
                  </div>
                  {row.notes && <p className="mt-1 text-[11px] text-muted-foreground/70">{row.notes}</p>}
                </div>
              ))}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={incrementOpen} onOpenChange={setIncrementOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('Salary Increment')}</DialogTitle>
          </DialogHeader>
          {incrementEmployee && (
            <div className="space-y-3">
              <div className="rounded-lg bg-muted/60 px-3 py-2 text-sm">
                <p className="font-semibold text-foreground">{incrementEmployee.name}</p>
                <p className="text-xs text-muted-foreground">
                  {t('Current gross')}: ₹{formatRupee(Number(grossInputs[incrementEmployee.id] ?? incrementEmployee.salary_record?.monthly_gross ?? 0))}
                </p>
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">{t('Increment Type')}</Label>
                <Select value={incrementMode} onValueChange={(v) => setIncrementMode(v as typeof incrementMode)}>
                  <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percentage">{t('Percentage (%)')}</SelectItem>
                    <SelectItem value="fixed">{t('Fixed Amount (₹)')}</SelectItem>
                    <SelectItem value="set_gross">{t('Set New Gross (₹)')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">
                  {incrementMode === 'percentage' ? t('Increment %') : incrementMode === 'fixed' ? t('Add Amount (₹)') : t('New Gross (₹)')}
                </Label>
                <Input
                  type="number"
                  min="0.01"
                  value={incrementValue}
                  onChange={(e) => setIncrementValue(e.target.value)}
                  className="h-9"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">{t('Increment Effective Date')}</Label>
                <Input
                  type="date"
                  min={minEffectiveDate}
                  value={effectiveFrom}
                  onChange={(e) => handleEffectiveFromChange(e.target.value)}
                  className="h-9"
                />
                <p className="text-[10px] text-muted-foreground">
                  {t('Only today or future dates. Past dates are not allowed.')}
                </p>
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">{t('Notes')}</Label>
                <Input value={incrementNotes} onChange={(e) => setIncrementNotes(e.target.value)} placeholder={t('e.g. Annual increment May 2026')} className="h-9" />
              </div>
              {incrementPreviewGross != null && (
                <div className="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm">
                  <span className="text-muted-foreground">{t('New gross')}: </span>
                  <span className="font-bold tabular-nums text-primary">₹{formatRupee(incrementPreviewGross)}</span>
                </div>
              )}
            </div>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setIncrementOpen(false)}>{t('Cancel')}</Button>
            <Button type="button" disabled={isIncrementSaving || !incrementValue || !effectiveFrom} onClick={submitIncrement}>
              {isIncrementSaving ? t('Saving...') : t('Apply Increment')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={componentsOpen} onOpenChange={setComponentsOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('Assign Salary Components')}</DialogTitle>
          </DialogHeader>
          {componentsEmployee && (
            <div className="space-y-4">
              <div className="rounded-lg bg-muted/60 px-3 py-2 text-sm">
                <p className="font-semibold text-foreground">{componentsEmployee.name}</p>
                <p className="text-xs text-muted-foreground tabular-nums">
                  {componentsEmployee.employee?.employee_id || '—'}
                </p>
              </div>

              <div className="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                <Label className="text-xs font-semibold">{t('Customize for this employee')}</Label>
                <Switch checked={componentsCustomize} onCheckedChange={handleComponentsCustomizeToggle} />
              </div>

              {!componentsCustomize ? (
                <div className="rounded-lg border border-border bg-muted/30 p-3">
                  <p className="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{t('Default — Primary group')}</p>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {primaryCols.map((comp) => (
                      <Badge key={comp.id} variant="secondary" className="text-[11px]">
                        {comp.name}
                      </Badge>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="max-h-[280px] space-y-3 overflow-y-auto">
                  {primaryCols.length > 0 && (
                    <div className="space-y-2">
                      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Primary group')}</p>
                      <p className="text-[10px] text-muted-foreground">{t('Always applied from master — cannot be changed per employee.')}</p>
                      <div className="space-y-1 rounded-lg border border-border bg-muted/40 p-3">
                        {primaryCols.map((comp) => (
                          <label
                            key={comp.id}
                            className="flex cursor-not-allowed items-center gap-2 rounded-md px-1 py-1 opacity-80"
                          >
                            <Checkbox checked disabled />
                            <span className="text-sm">{comp.name}</span>
                            <span className="ml-auto text-[10px] text-muted-foreground">
                              {comp.calculation_type === 'percentage_of_gross'
                                ? `${comp.percentage_of_gross_pay}% ${t('on gross')}`
                                : `${comp.percentage_of_basic}% ${t('on basic')}`}
                            </span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}
                  {customCols.length > 0 && (
                    <div className="space-y-2">
                      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Custom group')}</p>
                      <p className="text-[10px] text-muted-foreground">{t('Select additional components for this employee.')}</p>
                      <div className="space-y-1 rounded-lg border border-border p-3">
                        {customCols.map((comp) => {
                          const checked = selectedComponentIds.includes(Number(comp.id));
                          return (
                            <label
                              key={comp.id}
                              className="flex cursor-pointer items-center gap-2 rounded-md px-1 py-1 hover:bg-muted/50"
                            >
                              <Checkbox
                                checked={checked}
                                onCheckedChange={(v) => toggleComponentSelection(Number(comp.id), Boolean(v))}
                              />
                              <span className="text-sm">{comp.name}</span>
                              <span className="ml-auto text-[10px] text-muted-foreground">
                                {comp.calculation_type === 'percentage_of_gross'
                                  ? `${comp.percentage_of_gross_pay}% ${t('on gross')}`
                                  : `${comp.percentage_of_basic}% ${t('on basic')}`}
                              </span>
                            </label>
                          );
                        })}
                      </div>
                    </div>
                  )}
                  {customCols.length === 0 && (
                    <p className="rounded-lg border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground">
                      {t('No custom components in master. Add custom components in Salary Component Master first.')}
                    </p>
                  )}
                </div>
              )}
            </div>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setComponentsOpen(false)}>{t('Cancel')}</Button>
            <Button type="button" disabled={isSavingComponents} onClick={saveComponentAssignment}>
              {isSavingComponents ? t('Saving...') : t('Save Assignment')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
