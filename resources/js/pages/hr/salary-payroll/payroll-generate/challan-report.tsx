import { useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft,
  FileDown,
  FilterX,
  Landmark,
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
import { StatutoryChallanPanel, type StatutoryChallanSummary } from './components/StatutoryChallanPanel';
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

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatNum(value: number) {
  const n = Number(value);
  if (!Number.isFinite(n) || n === 0) return '—';
  return n % 1 === 0 ? String(n) : n.toFixed(1);
}

const stickyHead = 'sticky left-0 z-20 bg-amber-100 shadow-[2px_0_0_0_#fde68a]';
const stickyCell = 'sticky left-0 z-10 bg-white shadow-[2px_0_0_0_#f1f5f9]';

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

  const rows = report?.rows ?? [];
  const totals = report?.totals ?? {};

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

  const periodLabel = run?.pay_period_start && run?.pay_period_end
    ? `${run.pay_period_start} — ${run.pay_period_end}`
    : run?.title;

  return (
    <PageTemplate
      title={t('Statutory Challan Report')}
      description={run?.title || periodLabel}
      url={route('hr.salary-payroll.generate.challan-report', run.id)}
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Generate Payroll'), href: route('hr.salary-payroll.generate.index') },
        { title: run?.title || t('Payroll'), href: route('hr.salary-payroll.generate.show', run.id) },
        { title: t('Challan Report') },
      ]}
      noPadding
    >
      <div className="mx-auto max-w-full space-y-3 px-1 pb-6">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
          <div>
            <div className="flex items-center gap-2">
              <Landmark className="h-4 w-4 text-amber-700" />
              <p className="text-sm font-semibold text-slate-800">{t('Statutory Challan Report')}</p>
            </div>
            <p className="mt-0.5 text-[11px] text-slate-500">
              {run?.title} · {run?.pay_period_start} → {run?.pay_period_end} · {rows.length} {t('employees')} · {report?.pf_employee_count ?? 0} {t('with PF')}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" asChild>
              <Link href={route('hr.salary-payroll.generate.show', run.id)}>
                <ArrowLeft className="mr-1.5 h-4 w-4" />
                {t('Back to Payroll')}
              </Link>
            </Button>
            <Button size="sm" onClick={() => { resetDownloadForm(); setDownloadOpen(true); }}>
              <FileDown className="mr-1.5 h-4 w-4" />
              {t('Export Excel')}
            </Button>
          </div>
        </div>

      <StatutoryChallanPanel challan={summary} t={t} defaultOpen />

      <div className="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div className="min-w-[180px] flex-1">
          <Label className="text-[10px] uppercase text-slate-500">{t('Search')}</Label>
          <div className="relative">
            <Search className="absolute left-2 top-2 h-3.5 w-3.5 text-slate-400" />
            <Input
              className="h-8 pl-7 text-xs"
              placeholder={t('Name or employee code')}
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search: searchTerm })}
            />
          </div>
        </div>
        <Combobox
          options={categoryOptions}
          value={categoryFilter}
          onChange={(v) => applyFilters({ category_id: v || 'all' })}
          placeholder={t('Category')}
          className="h-8 w-[130px] text-xs"
        />
        <Combobox
          options={departmentOptions}
          value={departmentFilter}
          onChange={(v) => applyFilters({ department_id: v || 'all' })}
          placeholder={t('Department')}
          className="h-8 w-[130px] text-xs"
        />
        <Combobox
          options={shiftOptions}
          value={shiftFilter}
          onChange={(v) => applyFilters({ shift_id: v || 'all' })}
          placeholder={t('Shift')}
          className="h-8 w-[120px] text-xs"
        />
        <div className="w-[130px]">
          <Label className="text-[10px] uppercase text-slate-500">{t('Lock')}</Label>
          <Select value={lockFilter} onValueChange={(v) => applyFilters({ lock_status: v })}>
            <SelectTrigger className="h-8 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {lockStatusOptions.map((o) => (
                <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <Button variant="outline" size="sm" className="h-8" onClick={() => applyFilters({ search: searchTerm })}>
          {t('Apply')}
        </Button>
        <Button
          variant="ghost"
          size="sm"
          className="h-8"
          onClick={() => {
            setSearchTerm('');
            applyFilters({ search: '', category_id: 'all', department_id: 'all', shift_id: 'all', lock_status: 'all' });
          }}
        >
          <FilterX className="mr-1 h-3.5 w-3.5" />
          {t('Reset')}
        </Button>
      </div>

      <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="w-full min-w-[1400px] border-collapse text-[11px]">
          <thead>
            <tr className="border-b border-amber-200 bg-amber-50 text-[10px] uppercase tracking-wide text-amber-950">
              <th className={cn('px-2 py-2 text-left', stickyHead)}>#</th>
              <th className="px-2 py-2 text-left">{t('Code')}</th>
              <th className="px-2 py-2 text-left min-w-[140px]">{t('Name')}</th>
              <th className="px-2 py-2 text-left">{t('Dept')}</th>
              <th className="px-2 py-2 text-left">{t('UAN')}</th>
              <th className="px-2 py-2 text-right">{t('Paid')}</th>
              <th className="px-2 py-2 text-right">{t('Gross')}</th>
              <th className="px-2 py-2 text-right">{t('PF Wages')}</th>
              <th className="px-2 py-2 text-right text-red-800">{t('Emp PF')}</th>
              <th className="px-2 py-2 text-right">{t('EPS')}</th>
              <th className="px-2 py-2 text-right">{t('EPF Empr')}</th>
              <th className="px-2 py-2 text-right">{t('Admin')}</th>
              <th className="px-2 py-2 text-right font-bold text-orange-900">{t('A/C 1')}</th>
              <th className="px-2 py-2 text-right font-bold text-orange-900">{t('A/C 2')}</th>
              <th className="px-2 py-2 text-right font-bold text-orange-900">{t('A/C 10')}</th>
              <th className="px-2 py-2 text-right font-bold text-orange-950">{t('Challan')}</th>
              <th className="px-2 py-2 text-right">{t('ESIC')}</th>
              <th className="px-2 py-2 text-right">{t('PT')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.sr} className="border-b border-slate-100 hover:bg-slate-50/80">
                <td className={cn('px-2 py-1.5 tabular-nums', stickyCell)}>{row.sr}</td>
                <td className="px-2 py-1.5 font-mono text-[10px]">{row.employee_code}</td>
                <td className="px-2 py-1.5 font-medium">{row.name}</td>
                <td className="px-2 py-1.5 text-slate-600">{row.department}</td>
                <td className="px-2 py-1.5 font-mono text-[10px]">{row.uan_number || '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{formatNum(row.paid_days)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{formatRupee(row.total_earnings)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{row.pf_wages > 0 ? formatRupee(row.pf_wages) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums text-red-700">{row.pf_employee > 0 ? formatRupee(row.pf_employee) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{row.pf_eps_employer > 0 ? formatRupee(row.pf_eps_employer) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{row.pf_epf_employer > 0 ? formatRupee(row.pf_epf_employer) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{row.pf_admin_employer > 0 ? formatRupee(row.pf_admin_employer) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums font-semibold text-orange-900">{row.pf_challan_ac1 > 0 ? formatRupee(row.pf_challan_ac1) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums font-semibold text-orange-900">{row.pf_challan_ac2 > 0 ? formatRupee(row.pf_challan_ac2) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums font-semibold text-orange-900">{row.pf_challan_ac10 > 0 ? formatRupee(row.pf_challan_ac10) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums font-bold text-orange-950">{row.pf_challan_total > 0 ? formatRupee(row.pf_challan_total) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{row.esi_employee > 0 ? formatRupee(row.esi_employee) : '—'}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{row.pt_amount > 0 ? formatRupee(row.pt_amount) : '—'}</td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan={18} className="px-4 py-8 text-center text-slate-500">{t('No employees match the filters.')}</td>
              </tr>
            )}
          </tbody>
          {rows.length > 0 && (
            <tfoot>
              <tr className="border-t-2 border-amber-300 bg-amber-50 font-bold text-amber-950">
                <td colSpan={6} className="px-2 py-2 text-right uppercase text-[10px]">{t('Total')}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.total_earnings ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_wages ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums text-red-800">{formatRupee(totals.pf_employee ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_eps_employer ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_epf_employer ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_admin_employer ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_challan_ac1 ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_challan_ac2 ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_challan_ac10 ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pf_challan_total ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.esi_employee ?? 0)}</td>
                <td className="px-2 py-2 text-right tabular-nums">{formatRupee(totals.pt_amount ?? 0)}</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      <Dialog open={downloadOpen} onOpenChange={setDownloadOpen}>
        <DialogContent>
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
      </div>
    </PageTemplate>
  );
}
