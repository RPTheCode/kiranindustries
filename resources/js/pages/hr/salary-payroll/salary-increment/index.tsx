import { useCallback, useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router, Link } from '@inertiajs/react';
import axios from 'axios';
import {
  Search,
  X,
  Loader2,
  Info,
  TrendingUp,
  Eye,
  CheckCircle2,
} from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { hasPermission } from '@/utils/authorization';
import { Combobox } from '@/components/ui/combobox';

interface PreviewRow {
  id: number;
  name: string;
  employee_code?: string;
  category?: string;
  department?: string;
  shift?: string;
  current_gross: number;
  new_gross: number;
  increment_amount: number;
  increment_percentage?: number;
}

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export default function SalaryIncrementBulk() {
  const { t } = useTranslation();
  const {
    auth,
    categories = [],
    departments = [],
    shifts = [],
    activeBranchId,
    activeBranchName,
    defaultEffectiveFrom,
    flash,
  } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const canApply = hasPermission(permissions, 'create-employee-salaries')
    || hasPermission(permissions, 'edit-employee-salaries')
    || hasPermission(permissions, 'manage-employee-salaries');

  const [searchTerm, setSearchTerm] = useState('');
  const [categoryId, setCategoryId] = useState('all');
  const [departmentId, setDepartmentId] = useState('all');
  const [shiftId, setShiftId] = useState('all');
  const [incrementMode, setIncrementMode] = useState<'percentage' | 'fixed'>('percentage');
  const [incrementValue, setIncrementValue] = useState('10');
  const [effectiveFrom, setEffectiveFrom] = useState(defaultEffectiveFrom || '');
  const [notes, setNotes] = useState('');
  const [previewRows, setPreviewRows] = useState<PreviewRow[]>([]);
  const [previewCount, setPreviewCount] = useState(0);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [isApplying, setIsApplying] = useState(false);
  const [showPreview, setShowPreview] = useState(false);

  const branchLabel = activeBranchName || t('Selected Branch');

  const filterPayload = useCallback(() => ({
    increment_mode: incrementMode,
    increment_value: incrementValue,
    category_id: categoryId !== 'all' ? categoryId : undefined,
    department_id: departmentId !== 'all' ? departmentId : undefined,
    shift_id: shiftId !== 'all' ? shiftId : undefined,
    search: searchTerm || undefined,
  }), [incrementMode, incrementValue, categoryId, departmentId, shiftId, searchTerm]);

  const loadPreview = async () => {
    if (!incrementValue || Number(incrementValue) <= 0) {
      toast.error(t('Enter a valid increment value'));
      return;
    }
    setIsPreviewing(true);
    try {
      const { data } = await axios.post(route('hr.salary-payroll.salary-increment.preview'), filterPayload());
      setPreviewRows(data.employees || []);
      setPreviewCount(data.count || 0);
      setShowPreview(true);
      if ((data.count || 0) === 0) {
        toast.info(t('No employees with existing salary match these filters.'));
      }
    } catch (err: any) {
      toast.error(err?.response?.data?.error || err?.response?.data?.message || t('Preview failed'));
    } finally {
      setIsPreviewing(false);
    }
  };

  const applyIncrement = () => {
    if (!canApply || previewCount === 0) return;
    if (!effectiveFrom) {
      toast.error(t('Select effective from date'));
      return;
    }
    setIsApplying(true);
    router.post(route('hr.salary-payroll.salary-increment.apply'), {
      ...filterPayload(),
      effective_from: effectiveFrom,
      notes: notes || undefined,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(t('Bulk increment applied'));
        setShowPreview(false);
        setPreviewRows([]);
        setPreviewCount(0);
      },
      onError: (errors) => toast.error(Object.values(errors).join(', ')),
      onFinish: () => setIsApplying(false),
    });
  };

  const hasFilters = categoryId !== 'all' || departmentId !== 'all' || shiftId !== 'all' || searchTerm;

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

  return (
    <PageTemplate
      title={t('Bulk Salary Increment')}
      description={
        activeBranchId && activeBranchId !== 'all'
          ? `${branchLabel} · ${t('Apply percentage or fixed increment by category, department or shift')}`
          : t('Apply salary increment in bulk with filters')
      }
      url={route('hr.salary-payroll.salary-increment.index')}
      noPadding
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Salary Payroll') },
        { title: t('Bulk Salary Increment') },
      ]}
    >
      <div className="mx-auto max-w-6xl space-y-3 px-1 pb-6">
        {flash?.success && (
          <div className="rounded-lg border border-primary/20 bg-primary/5 px-4 py-2 text-sm text-primary">
            {flash.success}
          </div>
        )}

        <div className="overflow-hidden rounded-xl border border-primary/15 bg-primary/5 px-4 py-3">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex items-start gap-2">
              <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <p className="text-xs text-foreground/80">
                {t('Select filters, choose increment type, set the date from which new salary applies, preview, then apply. History tracks joining date to each increment date.')}
              </p>
            </div>
            <Link
              href={route('hr.salary-payroll.employee-salary.index')}
              className="text-xs font-medium text-primary hover:text-primary/80 hover:underline"
            >
              {t('← Back to Employee Salary')}
            </Link>
          </div>
        </div>

        <div className="overflow-hidden rounded-xl border border-border/90 bg-card shadow-sm">
          <div className="border-b border-border bg-muted/60 px-4 py-4">
            <h3 className="text-sm font-semibold text-foreground">{t('Increment Settings')}</h3>
            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div className="space-y-1.5">
                <Label className="text-xs">{t('Increment Type')}</Label>
                <Select value={incrementMode} onValueChange={(v) => setIncrementMode(v as 'percentage' | 'fixed')}>
                  <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percentage">{t('Percentage (%)')}</SelectItem>
                    <SelectItem value="fixed">{t('Fixed Amount (₹)')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">
                  {incrementMode === 'percentage' ? t('Increment %') : t('Add Amount (₹)')}
                </Label>
                <Input
                  type="number"
                  min="0.01"
                  step={incrementMode === 'percentage' ? '0.1' : '1'}
                  value={incrementValue}
                  onChange={(e) => setIncrementValue(e.target.value)}
                  placeholder={incrementMode === 'percentage' ? '10' : '2000'}
                  className="h-9"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">{t('Increment Effective Date')}</Label>
                <Input
                  type="date"
                  value={effectiveFrom}
                  onChange={(e) => setEffectiveFrom(e.target.value)}
                  className="h-9"
                />
                <p className="text-[10px] text-muted-foreground">{t('Actual date from which incremented salary applies for selected employees.')}</p>
              </div>
              <div className="space-y-1.5 sm:col-span-2 lg:col-span-3">
                <Label className="text-xs">{t('Notes (optional)')}</Label>
                <Input
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder={t('e.g. Annual increment from 1 May 2026')}
                  className="h-9"
                />
              </div>
            </div>
          </div>

          <div className="border-b border-border px-4 py-3">
            <h3 className="mb-2 text-sm font-semibold text-foreground">{t('Filter Employees')}</h3>
            <div className="flex flex-wrap items-center gap-2">
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
                onChange={(v) => setCategoryId(v || 'all')}
                options={categoryOptions}
                placeholder={t('All Categories')}
                searchPlaceholder={t('Search category...')}
                emptyText={t('No category found.')}
                className="h-8 w-[140px] text-xs"
              />
              <Combobox
                value={departmentId}
                onChange={(v) => setDepartmentId(v || 'all')}
                options={departmentOptions}
                placeholder={t('All Departments')}
                searchPlaceholder={t('Search department...')}
                emptyText={t('No department found.')}
                className="h-8 w-[140px] text-xs"
              />
              <Combobox
                value={shiftId}
                onChange={(v) => setShiftId(v || 'all')}
                options={shiftOptions}
                placeholder={t('All Shifts')}
                searchPlaceholder={t('Search shift...')}
                emptyText={t('No shift found.')}
                className="h-8 w-[130px] text-xs"
              />
              {hasFilters && <Badge variant="outline" className="text-[10px]">{t('Filtered')}</Badge>}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 px-4 py-3">
            <Button type="button" variant="outline" size="sm" disabled={isPreviewing} onClick={loadPreview}>
              {isPreviewing ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Eye className="mr-1.5 h-4 w-4" />}
              {t('Preview Increment')}
            </Button>
            {showPreview && (
              <span className="text-sm text-muted-foreground">
                <TrendingUp className="mr-1 inline h-4 w-4 text-primary" />
                {previewCount} {t('employees will be updated')}
              </span>
            )}
          </div>

          {showPreview && previewRows.length > 0 && (
            <div className="border-t border-border">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border bg-muted/60 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                      <th className="px-3 py-2">#</th>
                      <th className="px-3 py-2">{t('Employee')}</th>
                      <th className="px-3 py-2">{t('Category')}</th>
                      <th className="px-3 py-2">{t('Department')}</th>
                      <th className="px-3 py-2">{t('Shift')}</th>
                      <th className="px-3 py-2 text-right">{t('Current Gross')}</th>
                      <th className="px-3 py-2 text-right">{t('New Gross')}</th>
                      <th className="px-3 py-2 text-right">{t('Increase')}</th>
                      <th className="px-3 py-2 text-right">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    {previewRows.map((row, index) => (
                      <tr key={row.id} className="border-b border-border/60 hover:bg-muted/40">
                        <td className="px-3 py-2 text-xs text-muted-foreground">{index + 1}</td>
                        <td className="px-3 py-2">
                          <div className="font-medium text-foreground">{row.name}</div>
                          <div className="text-[11px] text-muted-foreground">{row.employee_code || '—'}</div>
                        </td>
                        <td className="px-3 py-2 text-xs">{row.category || '—'}</td>
                        <td className="px-3 py-2 text-xs">{row.department || '—'}</td>
                        <td className="px-3 py-2 text-xs">{row.shift || '—'}</td>
                        <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">₹{formatRupee(row.current_gross)}</td>
                        <td className="px-3 py-2 text-right font-semibold tabular-nums text-primary">₹{formatRupee(row.new_gross)}</td>
                        <td className="px-3 py-2 text-right font-semibold tabular-nums text-primary">+₹{formatRupee(row.increment_amount)}</td>
                        <td className="px-3 py-2 text-right text-xs tabular-nums text-muted-foreground">
                          {row.increment_percentage != null ? `${row.increment_percentage}%` : '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {canApply && (
                <div className="border-t border-border bg-muted/40 px-4 py-3">
                  <Button type="button" disabled={isApplying || previewCount === 0} onClick={applyIncrement}>
                    {isApplying ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-1.5 h-4 w-4" />}
                    {isApplying ? t('Applying...') : t('Apply Increment to :count employees', { count: previewCount })}
                  </Button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </PageTemplate>
  );
}
