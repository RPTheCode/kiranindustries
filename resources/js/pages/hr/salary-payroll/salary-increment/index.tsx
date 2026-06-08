import { useCallback, useEffect, useMemo, useState } from 'react';
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
  AlertTriangle,
} from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { canApplySalaryPayrollIncrement } from '@/utils/authorization';
import { Combobox } from '@/components/ui/combobox';
import { Pagination } from '@/components/ui/pagination';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

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
  increment_percentage?: number | null;
  actual_increment_percentage?: number | null;
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

  const canApply = canApplySalaryPayrollIncrement(permissions);

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
  const [previewPage, setPreviewPage] = useState(1);
  const [previewPerPage, setPreviewPerPage] = useState(50);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [isApplying, setIsApplying] = useState(false);
  const [showPreview, setShowPreview] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const branchLabel = activeBranchName || t('Selected Branch');
  const minEffectiveDate = defaultEffectiveFrom || new Date().toISOString().slice(0, 10);

  const handleEffectiveFromChange = (value: string) => {
    if (value && value < minEffectiveDate) {
      toast.error(t('Increment date must be today or a future date'));
      return;
    }
    setEffectiveFrom(value);
  };

  const filterPayload = useCallback(() => ({
    increment_mode: incrementMode,
    increment_value: incrementValue,
    category_id: categoryId !== 'all' ? categoryId : undefined,
    department_id: departmentId !== 'all' ? departmentId : undefined,
    shift_id: shiftId !== 'all' ? shiftId : undefined,
    search: searchTerm || undefined,
  }), [incrementMode, incrementValue, categoryId, departmentId, shiftId, searchTerm]);

  // Clear stale preview when increment settings or filters change
  useEffect(() => {
    setShowPreview(false);
    setPreviewRows([]);
    setPreviewCount(0);
    setPreviewPage(1);
  }, [incrementMode, incrementValue, categoryId, departmentId, shiftId, searchTerm]);

  const previewLastPage = Math.max(1, Math.ceil(previewRows.length / previewPerPage));

  const paginatedPreviewRows = useMemo(() => {
    const start = (previewPage - 1) * previewPerPage;
    return previewRows.slice(start, start + previewPerPage);
  }, [previewRows, previewPage, previewPerPage]);

  const previewFrom = previewRows.length === 0 ? 0 : (previewPage - 1) * previewPerPage + 1;
  const previewTo = Math.min(previewPage * previewPerPage, previewRows.length);

  useEffect(() => {
    if (previewPage > previewLastPage) {
      setPreviewPage(previewLastPage);
    }
  }, [previewPage, previewLastPage]);

  const handlePreviewPageChange = (url: string) => {
    const match = url.match(/page=(\d+)/);
    if (match) {
      setPreviewPage(Number(match[1]));
    }
  };

  const handlePreviewPerPageChange = (value: string) => {
    setPreviewPerPage(Number(value));
    setPreviewPage(1);
  };

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
      setPreviewPage(1);
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
    setConfirmOpen(false);
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

  const openApplyConfirm = () => {
    if (!canApply || previewCount === 0) return;
    if (!effectiveFrom) {
      toast.error(t('Select effective from date'));
      return;
    }
    setConfirmOpen(true);
  };

  const incrementSummary = incrementMode === 'percentage'
    ? `${incrementValue}%`
    : `₹${formatRupee(Number(incrementValue))}`;

  const hasFilters = categoryId !== 'all' || departmentId !== 'all' || shiftId !== 'all' || searchTerm.trim().length > 0;

  const searchTermCount = useMemo(() => {
    if (!searchTerm.trim()) return 0;
    return searchTerm.split(/[\s,;]+/).filter((t) => t.trim()).length;
  }, [searchTerm]);

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
        {flash?.error && (
          <div className="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-2 text-sm text-destructive">
            {flash.error}
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
                  min={minEffectiveDate}
                  value={effectiveFrom}
                  onChange={(e) => handleEffectiveFromChange(e.target.value)}
                  className="h-9"
                />
                <p className="text-[10px] text-muted-foreground">{t('Only today or future dates. Past dates are not allowed.')}</p>
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
            <div className="space-y-2">
              <div className="relative">
                <Search className="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                <Textarea
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder={t('Name or employee code — one or many (comma, space, or new line)\ne.g. 83024, 83025, 83026')}
                  rows={2}
                  className="min-h-[60px] resize-y border-input bg-background pl-8 pr-8 text-sm shadow-none"
                />
                {searchTerm && (
                  <button
                    type="button"
                    onClick={() => setSearchTerm('')}
                    className="absolute right-2 top-2 text-muted-foreground hover:text-foreground"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {searchTermCount > 1 && (
                  <Badge variant="secondary" className="text-[10px]">
                    {searchTermCount} {t('entries')}
                  </Badge>
                )}
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
              <p className="text-[10px] text-muted-foreground">
                {t('Paste multiple employee codes or names to increment only selected employees. Leave empty for all (with category/dept/shift filters).')}
              </p>
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

          {showPreview && previewRows.length === 0 && (
            <div className="border-t border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
              {t('No employees with existing salary match these filters. Set gross salary on Employee Salary page first, then preview again.')}
            </div>
          )}

          {showPreview && previewRows.length > 0 && (
            <div className="border-t border-border">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/30 px-4 py-2">
                <span className="text-xs text-muted-foreground">
                  {t('Preview results')} · {previewCount} {t('employees')}
                </span>
                <div className="flex items-center gap-2">
                  <span className="text-[11px] text-muted-foreground">{t('Per page')}</span>
                  <Select value={String(previewPerPage)} onValueChange={handlePreviewPerPageChange}>
                    <SelectTrigger className="h-8 w-[72px] text-xs"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {[25, 50, 100].map((n) => (
                        <SelectItem key={n} value={String(n)}>{n}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
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
                      {incrementMode === 'percentage' && (
                        <th className="px-3 py-2 text-right">{t('Given %')}</th>
                      )}
                      <th className="px-3 py-2 text-right">{t('Actual %')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedPreviewRows.map((row, index) => (
                      <tr key={row.id} className="border-b border-border/60 hover:bg-muted/40">
                        <td className="px-3 py-2 text-xs text-muted-foreground">{previewFrom + index}</td>
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
                        {incrementMode === 'percentage' && (
                          <td className="px-3 py-2 text-right text-xs tabular-nums text-muted-foreground">
                            {row.increment_percentage != null ? `${row.increment_percentage}%` : '—'}
                          </td>
                        )}
                        <td className="px-3 py-2 text-right text-xs tabular-nums text-foreground">
                          {row.actual_increment_percentage != null ? `${row.actual_increment_percentage}%` : '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {previewRows.length > previewPerPage && (
                <Pagination
                  from={previewFrom}
                  to={previewTo}
                  total={previewRows.length}
                  currentPage={previewPage}
                  lastPage={previewLastPage}
                  entityName={t('employees')}
                  onPageChange={handlePreviewPageChange}
                  className="border-t border-border bg-muted/40 px-3 py-2"
                />
              )}
              {canApply && (
                <div className="border-t border-border bg-muted/40 px-4 py-3">
                  <Button type="button" disabled={isApplying || previewCount === 0} onClick={openApplyConfirm}>
                    {isApplying ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-1.5 h-4 w-4" />}
                    {isApplying ? t('Applying...') : t('Apply Increment to {{count}} employees', { count: previewCount })}
                  </Button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
              {t('Confirm Salary Increment')}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3 text-sm text-muted-foreground">
            <p className="font-medium text-foreground">
              {t('Are you sure you want to apply this increment to {{count}} employees?', { count: previewCount })}
            </p>
            <ul className="space-y-1.5 rounded-lg border border-border bg-muted/40 p-3 text-xs">
              <li><span className="text-muted-foreground">{t('Branch')}:</span> <span className="font-medium text-foreground">{branchLabel}</span></li>
              <li><span className="text-muted-foreground">{t('Increment')}:</span> <span className="font-medium text-foreground">{incrementSummary}</span></li>
              <li><span className="text-muted-foreground">{t('Effective from')}:</span> <span className="font-medium text-foreground">{effectiveFrom}</span></li>
              {notes && (
                <li><span className="text-muted-foreground">{t('Notes')}:</span> <span className="font-medium text-foreground">{notes}</span></li>
              )}
            </ul>
            <p className="text-xs">{t('This will update gross salary and salary history for all matched employees. This action cannot be undone easily.')}</p>
          </div>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="outline" disabled={isApplying} onClick={() => setConfirmOpen(false)}>
              {t('Cancel')}
            </Button>
            <Button type="button" disabled={isApplying} onClick={applyIncrement}>
              {isApplying ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : null}
              {isApplying ? t('Applying...') : t('Yes, Apply to {{count}} employees', { count: previewCount })}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
