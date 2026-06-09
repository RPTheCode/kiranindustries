import { useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft,
  FileDown,
  LayoutGrid,
  Search,
  FilterX,
  Lock,
  Eye,
  EyeOff,
  Landmark,
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
import { cn } from '@/lib/utils';

interface RegisterRow {
  sr: number;
  employee_code: string;
  name: string;
  category: string;
  department: string;
  shift: string;
  day_rate: number;
  monthly_gross: number;
  ot_enabled: boolean;
  working_days: number;
  present_days: number;
  paid_days: number;
  week_off_worked_days: number;
  half_days: number;
  incentive_days: number;
  incentive_amount: number;
  attendance_extra_days?: number;
  attendance_extra_amount?: number;
  attendance_extra_applied?: boolean;
  regular_earnings: number;
  total_earnings: number;
  total_deductions: number;
  net_salary: number;
  pf_wages: number;
  bank_name: string;
  account_number: string;
  earnings: Record<string, number>;
  deductions: Record<string, number>;
}

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatNum(value: number) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  return n % 1 === 0 ? String(n) : n.toFixed(1);
}

const stickyHead = 'sticky z-20 bg-slate-100 shadow-[2px_0_0_0_#e2e8f0]';
const stickyCell = 'sticky z-10 bg-white shadow-[2px_0_0_0_#f1f5f9] group-hover:bg-slate-50';
const stickyCellAlt = 'sticky z-10 bg-slate-50/80 shadow-[2px_0_0_0_#f1f5f9]';

export default function PayrollGenerateRegister() {
  const { t } = useTranslation();
  const {
    run,
    register,
    filters = {},
    categories = [],
    departments = [],
    shifts = [],
  } = usePage().props as any;

  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [downloadOpen, setDownloadOpen] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [filePassword, setFilePassword] = useState('');
  const [filePasswordConfirmation, setFilePasswordConfirmation] = useState('');
  const [downloadErrors, setDownloadErrors] = useState<Record<string, string>>({});
  const [isDownloading, setIsDownloading] = useState(false);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showFilePassword, setShowFilePassword] = useState(false);
  const [showFilePasswordConfirmation, setShowFilePasswordConfirmation] = useState(false);

  const earningColumns: string[] = register?.earning_columns ?? [];
  const deductionColumns: string[] = register?.deduction_columns ?? [];
  const rows: RegisterRow[] = register?.rows ?? [];
  const totals = register?.totals ?? {};

  const categoryFilter = filters.category_id ? String(filters.category_id) : 'all';
  const shiftFilter = filters.shift_id ? String(filters.shift_id) : 'all';
  const departmentFilter = filters.department_id ? String(filters.department_id) : 'all';
  const lockFilter = filters.lock_status || 'all';

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

  const lockStatusOptions = useMemo(() => [
    { label: t('All Lock Status'), value: 'all' },
    { label: t('Locked only'), value: 'locked' },
    { label: t('Unlocked only'), value: 'unlocked' },
  ], [t]);

  const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
    const nextCategory = overrides.category_id !== undefined ? overrides.category_id : (categoryFilter !== 'all' ? categoryFilter : undefined);
    const nextShift = overrides.shift_id !== undefined ? overrides.shift_id : (shiftFilter !== 'all' ? shiftFilter : undefined);
    const nextDepartment = overrides.department_id !== undefined ? overrides.department_id : (departmentFilter !== 'all' ? departmentFilter : undefined);
    const nextLock = overrides.lock_status !== undefined ? overrides.lock_status : (lockFilter !== 'all' ? lockFilter : undefined);

    router.get(route('hr.salary-payroll.generate.register', run.id), {
      search: overrides.search !== undefined ? overrides.search || undefined : searchTerm || undefined,
      category_id: nextCategory && nextCategory !== 'all' ? nextCategory : undefined,
      shift_id: nextShift && nextShift !== 'all' ? nextShift : undefined,
      department_id: nextDepartment && nextDepartment !== 'all' ? nextDepartment : undefined,
      lock_status: nextLock && nextLock !== 'all' ? nextLock : undefined,
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

  const openDownloadDialog = () => {
    resetDownloadForm();
    setDownloadOpen(true);
  };

  const handleDownload = async () => {
    setDownloadErrors({});
    setIsDownloading(true);

    try {
      const response = await fetch(route('hr.salary-payroll.generate.register.export', run.id), {
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
        } else {
          setDownloadErrors({ general: t('Failed to download Excel file.') });
        }
        return;
      }

      const blob = await response.blob();
      const disposition = response.headers.get('content-disposition') || '';
      const filenameMatch = disposition.match(/filename="?([^"]+)"?/i);
      const filename = filenameMatch?.[1] || `Salary_Register_${run.id}.xlsx`;

      const url = window.URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = filename;
      document.body.appendChild(anchor);
      anchor.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(anchor);

      setDownloadOpen(false);
      resetDownloadForm();
    } catch {
      setDownloadErrors({ general: t('Failed to download Excel file.') });
    } finally {
      setIsDownloading(false);
    }
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Payroll'), href: route('hr.salary-payroll.generate.index') },
    { title: run?.title || t('Payroll Run'), href: route('hr.salary-payroll.generate.show', run.id) },
    { title: t('Excel View') },
  ];

  const th = 'whitespace-nowrap border border-slate-300 px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-700';
  const td = 'whitespace-nowrap border border-slate-200 px-2 py-1 text-[11px] tabular-nums text-slate-800';
  const tdText = cn(td, 'text-left');
  const tdNum = cn(td, 'text-right font-medium');

  return (
    <PageTemplate
      title={t('Excel View')}
      description={run?.title}
      url={route('hr.salary-payroll.generate.register', run.id)}
      breadcrumbs={breadcrumbs}
      noPadding
    >
      <div className="mx-auto max-w-full space-y-3 px-1 pb-6">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
          <div>
            <div className="flex items-center gap-2">
              <LayoutGrid className="h-4 w-4 text-primary" />
              <p className="text-sm font-semibold text-slate-800">{t('Excel View')}</p>
            </div>
            <p className="mt-0.5 text-[11px] text-slate-500">
              {run?.title} · {run?.pay_period_start} → {run?.pay_period_end} · {rows.length} {t('employees')}
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
              <Link href={route('hr.salary-payroll.generate.challan-report', run.id)}>
                <Landmark className="mr-1.5 h-4 w-4" />
                {t('Challan Report')}
              </Link>
            </Button>
            <Button size="sm" onClick={openDownloadDialog}>
              <FileDown className="mr-1.5 h-4 w-4" />
              {t('Download Excel')}
            </Button>
          </div>
        </div>

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
            <Button variant="ghost" size="sm" className="h-8" onClick={() => { setSearchTerm(''); router.get(route('hr.salary-payroll.generate.register', run.id)); }}>
              <FilterX className="mr-1 h-3.5 w-3.5" />
              {t('Clear')}
            </Button>
          )}
        </div>

        <div className="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
          <div className="overflow-auto max-h-[calc(100vh-280px)]">
            <table className="min-w-max w-full border-collapse">
              <thead className="sticky top-0 z-30 bg-slate-100">
                <tr>
                  <th className={cn(th, stickyHead, 'left-0 min-w-[36px]')}>#</th>
                  <th className={cn(th, stickyHead, 'left-[36px] min-w-[72px]')}>{t('Code')}</th>
                  <th className={cn(th, stickyHead, 'left-[108px] min-w-[180px]')}>{t('Name')}</th>
                  <th className={cn(th, 'min-w-[80px]')}>{t('Category')}</th>
                  <th className={cn(th, 'min-w-[100px]')}>{t('Department')}</th>
                  <th className={cn(th, 'min-w-[90px]')}>{t('Shift')}</th>
                  <th className={cn(th, 'min-w-[72px]')}>{t('Day Rate')}</th>
                  <th className={cn(th, 'min-w-[80px]')}>{t('CTC')}</th>
                  <th className={cn(th, 'min-w-[40px]')}>{t('OT')}</th>
                  <th className={cn(th, 'min-w-[48px]')}>{t('Work')}</th>
                  <th className={cn(th, 'min-w-[48px]')}>{t('Present')}</th>
                  <th className={cn(th, 'min-w-[48px]')}>{t('Paid')}</th>
                  <th className={cn(th, 'min-w-[48px]')}>{t('WO')}</th>
                  <th className={cn(th, 'min-w-[48px]')}>{t('HD')}</th>
                  <th className={cn(th, 'min-w-[48px]')}>{t('PI')}</th>
                  <th className={cn(th, 'min-w-[72px]')}>{t('PI Amt')}</th>
                  <th className={cn(th, 'min-w-[48px] bg-sky-50 text-sky-900')}>{t('Adj')}</th>
                  <th className={cn(th, 'min-w-[72px] bg-sky-50 text-sky-900')}>{t('Adjust')}</th>
                  {earningColumns.map((col) => (
                    <th key={col} className={cn(th, 'min-w-[80px] bg-emerald-50 text-emerald-900')}>{col}</th>
                  ))}
                  <th className={cn(th, 'min-w-[88px] bg-emerald-50 text-emerald-900')}>{t('Regular')}</th>
                  <th className={cn(th, 'min-w-[88px] bg-emerald-100 text-emerald-900')}>{t('Total')}</th>
                  {deductionColumns.map((col) => (
                    <th key={col} className={cn(th, 'min-w-[80px] bg-rose-50 text-rose-900')}>{col}</th>
                  ))}
                  <th className={cn(th, 'min-w-[80px] bg-rose-100 text-rose-900')}>{t('Deductions')}</th>
                  <th className={cn(th, 'min-w-[88px] bg-green-100 text-green-900')}>{t('Net')}</th>
                  <th className={cn(th, 'min-w-[72px]')}>{t('PF Wages')}</th>
                  <th className={cn(th, 'min-w-[100px]')}>{t('Bank')}</th>
                  <th className={cn(th, 'min-w-[120px]')}>{t('Account')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={20 + earningColumns.length + deductionColumns.length} className="px-4 py-12 text-center text-sm text-muted-foreground">
                      {t('No employees found.')}
                    </td>
                  </tr>
                ) : rows.map((row, idx) => (
                  <tr key={`${row.employee_code}-${row.sr}`} className={cn('group hover:bg-slate-50/80', idx % 2 === 1 && 'bg-slate-50/40')}>
                    <td className={cn(tdNum, idx % 2 === 1 ? stickyCellAlt : stickyCell, 'left-0')}>{row.sr}</td>
                    <td className={cn(tdNum, idx % 2 === 1 ? stickyCellAlt : stickyCell, 'left-[36px]')}>{row.employee_code}</td>
                    <td className={cn(tdText, 'font-semibold', idx % 2 === 1 ? stickyCellAlt : stickyCell, 'left-[108px]')}>{row.name}</td>
                    <td className={tdText}>{row.category}</td>
                    <td className={tdText}>{row.department}</td>
                    <td className={tdText}>{row.shift}</td>
                    <td className={tdNum}>{formatRupee(row.day_rate)}</td>
                    <td className={tdNum}>{formatRupee(row.monthly_gross)}</td>
                    <td className={cn(td, 'text-center')}>{row.ot_enabled ? t('Y') : t('N')}</td>
                    <td className={tdNum}>{formatNum(row.working_days)}</td>
                    <td className={tdNum}>{formatNum(row.present_days)}</td>
                    <td className={tdNum}>{formatNum(row.paid_days)}</td>
                    <td className={tdNum}>{formatNum(row.week_off_worked_days)}</td>
                    <td className={tdNum}>{formatNum(row.half_days)}</td>
                    <td className={tdNum}>{formatNum(row.incentive_days)}</td>
                    <td className={tdNum}>{formatRupee(row.incentive_amount)}</td>
                    <td className={cn(tdNum, 'bg-sky-50/30')}>{formatNum(row.attendance_extra_days ?? 0)}</td>
                    <td className={cn(tdNum, 'bg-sky-50/30', row.attendance_extra_amount && !row.attendance_extra_applied ? 'text-amber-800' : '')}>
                      {formatRupee(row.attendance_extra_amount ?? 0)}
                      {Number(row.attendance_extra_amount ?? 0) > 0 && !row.attendance_extra_applied ? '*' : ''}
                    </td>
                    {earningColumns.map((col) => (
                      <td key={col} className={cn(tdNum, 'bg-emerald-50/30')}>{formatRupee(row.earnings[col] ?? 0)}</td>
                    ))}
                    <td className={cn(tdNum, 'bg-emerald-50/30')}>{formatRupee(row.regular_earnings)}</td>
                    <td className={cn(tdNum, 'bg-emerald-50/50 font-semibold')}>{formatRupee(row.total_earnings)}</td>
                    {deductionColumns.map((col) => (
                      <td key={col} className={cn(tdNum, 'bg-rose-50/30')}>{formatRupee(row.deductions[col] ?? 0)}</td>
                    ))}
                    <td className={cn(tdNum, 'bg-rose-50/50 font-semibold text-rose-800')}>{formatRupee(row.total_deductions)}</td>
                    <td className={cn(tdNum, 'bg-green-50 font-bold text-green-800')}>{formatRupee(row.net_salary)}</td>
                    <td className={tdNum}>{formatRupee(row.pf_wages)}</td>
                    <td className={tdText}>{row.bank_name || '—'}</td>
                    <td className={tdText}>{row.account_number || '—'}</td>
                  </tr>
                ))}
                {rows.length > 0 && (
                  <tr className="bg-slate-200 font-bold">
                    <td className={cn(td, stickyCell, 'left-0 text-center')} colSpan={3}>{t('TOTAL')}</td>
                    <td className={td} colSpan={12} />
                    <td className={tdNum}>{formatRupee(totals.incentive_amount ?? 0)}</td>
                    <td className={td} />
                    <td className={tdNum}>{formatRupee(totals.attendance_extra_amount ?? 0)}</td>
                    {earningColumns.map((col) => (
                      <td key={col} className={tdNum}>{formatRupee(totals.earnings?.[col] ?? 0)}</td>
                    ))}
                    <td className={td} />
                    <td className={tdNum}>{formatRupee(totals.total_earnings ?? 0)}</td>
                    {deductionColumns.map((col) => (
                      <td key={col} className={tdNum}>{formatRupee(totals.deductions?.[col] ?? 0)}</td>
                    ))}
                    <td className={tdNum}>{formatRupee(totals.total_deductions ?? 0)}</td>
                    <td className={cn(tdNum, 'text-green-800')}>{formatRupee(totals.net_salary ?? 0)}</td>
                    <td className={td} colSpan={3} />
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        <p className="text-center text-[10px] text-muted-foreground">
          {t('Scroll horizontally for all columns · Sticky name columns · Download exports password-protected Excel')}
        </p>
      </div>

      <Dialog open={downloadOpen} onOpenChange={(open) => { setDownloadOpen(open); if (!open) resetDownloadForm(); }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Lock className="h-4 w-4 text-primary" />
              {t('Download Protected Excel')}
            </DialogTitle>
            <DialogDescription>
              {t('Verify your login password and set a password for the Excel file. The file will only open after entering that password.')}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="register-current-password" className="text-xs">{t('Your Login Password')}</Label>
              <div className="relative">
                <Input
                  id="register-current-password"
                  type={showCurrentPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  className="h-9 pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="absolute right-0 top-0 h-9 w-9 text-muted-foreground"
                  onClick={() => setShowCurrentPassword((v) => !v)}
                  tabIndex={-1}
                  aria-label={showCurrentPassword ? t('Hide password') : t('Show password')}
                >
                  {showCurrentPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </Button>
              </div>
              {downloadErrors.current_password && (
                <p className="text-xs text-destructive">{downloadErrors.current_password}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="register-file-password" className="text-xs">{t('Excel File Password')}</Label>
              <div className="relative">
                <Input
                  id="register-file-password"
                  type={showFilePassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={filePassword}
                  onChange={(e) => setFilePassword(e.target.value)}
                  className="h-9 pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="absolute right-0 top-0 h-9 w-9 text-muted-foreground"
                  onClick={() => setShowFilePassword((v) => !v)}
                  tabIndex={-1}
                  aria-label={showFilePassword ? t('Hide password') : t('Show password')}
                >
                  {showFilePassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </Button>
              </div>
              {downloadErrors.file_password && (
                <p className="text-xs text-destructive">{downloadErrors.file_password}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="register-file-password-confirm" className="text-xs">{t('Confirm Excel Password')}</Label>
              <div className="relative">
                <Input
                  id="register-file-password-confirm"
                  type={showFilePasswordConfirmation ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={filePasswordConfirmation}
                  onChange={(e) => setFilePasswordConfirmation(e.target.value)}
                  className="h-9 pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="absolute right-0 top-0 h-9 w-9 text-muted-foreground"
                  onClick={() => setShowFilePasswordConfirmation((v) => !v)}
                  tabIndex={-1}
                  aria-label={showFilePasswordConfirmation ? t('Hide password') : t('Show password')}
                >
                  {showFilePasswordConfirmation ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </Button>
              </div>
              {downloadErrors.file_password_confirmation && (
                <p className="text-xs text-destructive">{downloadErrors.file_password_confirmation}</p>
              )}
            </div>

            {downloadErrors.general && (
              <p className="text-xs text-destructive">{downloadErrors.general}</p>
            )}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDownloadOpen(false)} disabled={isDownloading}>
              {t('Cancel')}
            </Button>
            <Button
              type="button"
              onClick={handleDownload}
              disabled={isDownloading || !currentPassword || !filePassword || !filePasswordConfirmation}
            >
              {isDownloading ? t('Downloading...') : t('Download Excel')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
