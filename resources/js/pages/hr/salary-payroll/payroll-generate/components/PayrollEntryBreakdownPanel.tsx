import { useTranslation } from 'react-i18next';
import { ArrowRight, TrendingDown, TrendingUp } from 'lucide-react';
import { StatutorySummaryCard } from './StatutoryIndicators';

export interface PayrollEntryBreakdown {
  monthly_gross: number;
  basic: number;
  total_earnings: number;
  total_deductions: number;
  net_salary: number;
  pf_enabled?: boolean;
  esi_enabled?: boolean;
  pf_basic_salary?: number;
  pf_employee: number;
  pf_employer?: number;
  esi_employee: number;
  esi_employer?: number;
  pt_amount: number;
  earnings_breakdown?: Record<string, number>;
  deductions_breakdown?: Record<string, number>;
}

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function breakdownLines(breakdown?: Record<string, number>) {
  if (!breakdown) return [];
  return Object.entries(breakdown)
    .filter(([, amount]) => Number(amount) > 0)
    .sort(([a], [b]) => a.localeCompare(b));
}

interface PayrollEntryBreakdownPanelProps {
  entry: PayrollEntryBreakdown;
}

export function PayrollEntryBreakdownPanel({ entry }: PayrollEntryBreakdownPanelProps) {
  const { t } = useTranslation();
  const earnings = breakdownLines(entry.earnings_breakdown);
  const deductions = breakdownLines(entry.deductions_breakdown);
  const hasBreakdown = earnings.length > 0 || deductions.length > 0;

  return (
    <div className="space-y-3 bg-slate-50/90 px-4 py-3">
      {!hasBreakdown && (
        <p className="text-xs text-amber-700">
          {t('Component breakdown not available for this entry. Regenerate payroll to refresh detailed earnings and deductions.')}
        </p>
      )}

      <div className="grid gap-3 sm:grid-cols-3">
        <StatutorySummaryCard
          title={t('Provident Fund (PF)')}
          enabled={!!entry.pf_enabled}
          employeeAmount={entry.pf_employee}
          employerAmount={Number(entry.pf_employer ?? 0)}
          baseLabel={entry.pf_basic_salary ? t('PF Basic') : undefined}
          baseAmount={entry.pf_basic_salary ? Number(entry.pf_basic_salary) : undefined}
          formatRupee={formatRupee}
        />
        <StatutorySummaryCard
          title={t('ESIC (ESI)')}
          enabled={!!entry.esi_enabled}
          employeeAmount={entry.esi_employee}
          employerAmount={Number(entry.esi_employer ?? 0)}
          formatRupee={formatRupee}
        />
        <div className="rounded-lg border border-violet-200 bg-violet-50/50 px-3 py-2">
          <div className="mb-1 flex items-center justify-between gap-2">
            <span className="text-xs font-semibold text-slate-800">{t('Professional Tax')}</span>
            <span className="rounded bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-violet-700">{t('Auto')}</span>
          </div>
          <p className="text-[11px] text-slate-600">
            {entry.pt_amount > 0
              ? <>{t('Deducted')}: <strong className="text-slate-800">₹{formatRupee(entry.pt_amount)}</strong></>
              : t('No PT for this gross slab')}
          </p>
        </div>
      </div>

      <div className="grid gap-3 lg:grid-cols-2">
        <div className="overflow-hidden rounded-lg border border-green-100 bg-white shadow-sm">
          <div className="flex items-center gap-2 border-b border-green-100 bg-green-50/80 px-3 py-2">
            <TrendingUp className="h-3.5 w-3.5 text-green-600" />
            <h4 className="text-xs font-bold uppercase tracking-wide text-green-800">{t('Earnings')}</h4>
          </div>
          <div className="divide-y divide-slate-100">
            {earnings.length === 0 ? (
              <p className="px-3 py-4 text-xs text-slate-400">{t('No earning components')}</p>
            ) : earnings.map(([name, amount]) => (
              <div key={name} className="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                <span className="text-slate-700">{name}</span>
                <span className="font-medium tabular-nums text-slate-900">₹{formatRupee(Number(amount))}</span>
              </div>
            ))}
          </div>
          <div className="flex items-center justify-between border-t border-green-100 bg-green-50/50 px-3 py-2 text-xs font-semibold text-green-900">
            <span>{t('Total Earnings')}</span>
            <span className="tabular-nums">₹{formatRupee(entry.total_earnings)}</span>
          </div>
        </div>

        <div className="overflow-hidden rounded-lg border border-red-100 bg-white shadow-sm">
          <div className="flex items-center gap-2 border-b border-red-100 bg-red-50/80 px-3 py-2">
            <TrendingDown className="h-3.5 w-3.5 text-red-600" />
            <h4 className="text-xs font-bold uppercase tracking-wide text-red-800">{t('Deductions')}</h4>
          </div>
          <div className="divide-y divide-slate-100">
            {deductions.length === 0 ? (
              <p className="px-3 py-4 text-xs text-slate-400">{t('No deduction components')}</p>
            ) : deductions.map(([name, amount]) => (
              <div key={name} className="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                <span className="text-slate-700">{name}</span>
                <span className="font-medium tabular-nums text-red-700">₹{formatRupee(Number(amount))}</span>
              </div>
            ))}
          </div>
          <div className="flex items-center justify-between border-t border-red-100 bg-red-50/50 px-3 py-2 text-xs font-semibold text-red-900">
            <span>{t('Total Deductions')}</span>
            <span className="tabular-nums">₹{formatRupee(entry.total_deductions)}</span>
          </div>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
        <div className="flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
          <span>{t('Gross')}: <strong className="text-slate-800">₹{formatRupee(entry.monthly_gross)}</strong></span>
          <ArrowRight className="h-3 w-3 text-slate-400" />
          <span>{t('Earnings')}: <strong className="text-green-700">₹{formatRupee(entry.total_earnings)}</strong></span>
          <ArrowRight className="h-3 w-3 text-slate-400" />
          <span>{t('Deductions')}: <strong className="text-red-700">₹{formatRupee(entry.total_deductions)}</strong></span>
          <ArrowRight className="h-3 w-3 text-slate-400" />
          <span>{t('Net')}: <strong className="text-primary">₹{formatRupee(entry.net_salary)}</strong></span>
        </div>
      </div>
    </div>
  );
}
