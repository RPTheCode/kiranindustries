import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronUp } from 'lucide-react';
import { StatutoryFlagBadge } from './StatutoryIndicators';
import { cn } from '@/lib/utils';

export interface PayrollEntryBreakdown {
  monthly_gross: number;
  daily_option?: boolean;
  employee_working_days?: number;
  total_earnings: number;
  working_days?: number;
  present_days?: number;
  half_days?: number;
  week_off_worked_days?: number;
  paid_days?: number;
  ot_enabled?: boolean;
  incentive_days?: number;
  incentive_amount?: number;
  incentive_per_day_rate?: number;
  regular_earnings?: number;
  mispunch_count?: number;
  has_mispunch?: boolean;
  use_attendance?: boolean;
  basic: number;
  total_deductions: number;
  net_salary: number;
  pf_enabled?: boolean;
  esi_enabled?: boolean;
  pf_basic_salary?: number;
  pf_employee: number;
  pf_wages?: number;
  pf_employer?: number;
  pf_eps_employer?: number;
  pf_epf_employer?: number;
  pf_breakdown?: {
    wages: number;
    employee_pct: number;
    employee: number;
    eps_pct: number;
    eps: number;
    epf_employer_pct: number;
    epf_employer: number;
  } | null;
  esi_employee: number;
  esi_employer?: number;
  pt_amount: number;
  pt_breakdown?: {
    gross: number;
    min_amt: number | null;
    max_amt: number | null;
    pt_amount: number;
  } | null;
  earnings_breakdown?: Record<string, number>;
  deductions_breakdown?: Record<string, number>;
}

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDays(value: number) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  return n % 1 === 0 ? String(n) : n.toFixed(1);
}

function formatPtSlabRange(min: number | null, max: number | null, formatRupee: (v: number) => string) {
  if (min === null && max === null) return '—';
  if (max === null) return `≥ ₹${formatRupee(min ?? 0)}`;
  return `₹${formatRupee(min ?? 0)} – ₹${formatRupee(max)}`;
}

const COMPONENT_ORDER = ['BASIC', 'HRA', 'LTA', 'ALLOWANCE', 'SPECIAL ALLOWANCE'];

function componentSortKey(name: string): string {
  const upper = name.toUpperCase();
  const idx = COMPONENT_ORDER.findIndex((key) => upper === key || upper.includes(key));
  return idx >= 0 ? `${idx}-${upper}` : `9-${upper}`;
}

function isIncentiveLine(name: string): boolean {
  const upper = name.toUpperCase();
  return upper.includes('INCENTIVE') || upper.includes('PI)') || upper.includes('OVERTIME SALARY') || upper.includes('EXTRA DAYS');
}

function componentEarningLines(breakdown?: Record<string, number>): [string, number][] {
  if (!breakdown) return [];
  return Object.entries(breakdown)
    .filter(([name, amount]) => !isIncentiveLine(name) && Number(amount) > 0)
    .sort(([a], [b]) => componentSortKey(a).localeCompare(componentSortKey(b)));
}

function breakdownLines(breakdown?: Record<string, number>) {
  if (!breakdown) return [];
  return Object.entries(breakdown)
    .filter(([, amount]) => Number(amount) > 0)
    .sort(([a], [b]) => a.localeCompare(b));
}

function salaryDisplayMeta(entry: {
  monthly_gross: number;
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
      ctc: Math.round(monthlyGross * 26),
    };
  }

  if (dailyOption && salaryDays > 1) {
    return {
      mode: 'day' as const,
      rate: Math.round((monthlyGross / salaryDays) * 100) / 100,
      ctc: monthlyGross,
    };
  }

  return {
    mode: 'month' as const,
    rate: monthlyGross,
    ctc: monthlyGross,
  };
}

interface PayrollEntryBreakdownPanelProps {
  entry: PayrollEntryBreakdown;
  runUsesAttendance?: boolean;
  onClose?: () => void;
}

function CompactLineList({
  title,
  lines,
  total,
  totalLabel,
  tone,
  formatRupee: fmt,
}: {
  title: string;
  lines: [string, number][];
  total: number;
  totalLabel: string;
  tone: 'green' | 'red';
  formatRupee: (v: number) => string;
}) {
  const header = tone === 'green' ? 'text-green-800 bg-green-50' : 'text-red-800 bg-red-50';
  const totalCls = tone === 'green' ? 'text-green-900 bg-green-50/80' : 'text-red-900 bg-red-50/80';

  return (
    <div className="min-w-0 rounded-md border border-slate-200 bg-white">
      <div className={cn('px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide', header)}>{title}</div>
      <div className="divide-y divide-slate-100">
        {lines.length === 0 ? (
          <p className="px-2.5 py-2 text-[10px] text-slate-400">—</p>
        ) : lines.map(([name, amount]) => (
          <div key={name} className="flex items-center justify-between gap-2 px-2.5 py-1 text-[11px]">
            <span className="truncate text-slate-700">{name}</span>
            <span className="shrink-0 tabular-nums font-medium text-slate-900">₹{fmt(Number(amount))}</span>
          </div>
        ))}
      </div>
      <div className={cn('flex items-center justify-between border-t border-slate-100 px-2.5 py-1 text-[11px] font-semibold', totalCls)}>
        <span>{totalLabel}</span>
        <span className="tabular-nums">₹{fmt(total)}</span>
      </div>
    </div>
  );
}

function EarningsBreakdownPanel({
  entry,
  formatRupee: fmt,
  t,
}: {
  entry: PayrollEntryBreakdown;
  formatRupee: (v: number) => string;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const componentLines = componentEarningLines(entry.earnings_breakdown);
  const incentiveDays = Number(entry.incentive_days ?? 0);
  const incentiveAmount = Number(entry.incentive_amount ?? 0);
  const workingDays = Math.max(26, Number(entry.working_days ?? 26) || 26);
  const monthlyGross = Number(entry.monthly_gross ?? 0);
  const perDayRate = Number(entry.incentive_per_day_rate ?? 0) || (workingDays > 0 ? Math.round((monthlyGross / workingDays) * 100) / 100 : 0);
  const regularEarnings = Number(entry.regular_earnings ?? 0) || componentLines.reduce((sum, [, amt]) => sum + Number(amt), 0);
  const hasIncentive = incentiveDays > 0 && incentiveAmount > 0;

  return (
    <div className="min-w-0 rounded-md border border-slate-200 bg-white">
      <div className="bg-green-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green-800">{t('Earnings')}</div>
      <div className="divide-y divide-slate-100">
        {componentLines.length === 0 ? (
          <p className="px-2.5 py-2 text-[10px] text-slate-400">—</p>
        ) : componentLines.map(([name, amount]) => (
          <div key={name} className="flex items-center justify-between gap-2 px-2.5 py-1 text-[11px]">
            <span className="truncate text-slate-700">{name}</span>
            <span className="shrink-0 tabular-nums font-medium text-slate-900">₹{fmt(Number(amount))}</span>
          </div>
        ))}
      </div>
      <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50/80 px-2.5 py-1 text-[11px] font-semibold text-slate-800">
        <span>{t('Regular Salary')}</span>
        <span className="tabular-nums">₹{fmt(regularEarnings)}</span>
      </div>

      {hasIncentive && (
        <div className="border-t border-amber-200 bg-amber-50/50 px-2.5 py-2 text-[10px] text-amber-950">
          <p className="mb-1.5 font-bold uppercase tracking-wide text-amber-900">{t('Production Incentive (PI)')}</p>
          <div className="grid gap-1 sm:grid-cols-2">
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Month salary')}</span>
              <strong className="tabular-nums">₹{fmt(monthlyGross)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Per day rate')} (÷ {workingDays})</span>
              <strong className="tabular-nums">₹{fmt(perDayRate)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('PI Days')}</span>
              <strong className="tabular-nums">{incentiveDays}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('PI Amount')}</span>
              <strong className="tabular-nums text-amber-900">₹{fmt(incentiveAmount)}</strong>
            </div>
          </div>
          <p className="mt-1.5 text-[9px] text-amber-800/90">
            {t('₹{{gross}} ÷ {{days}} days = ₹{{rate}}/day × {{piDays}} PI days = ₹{{amount}}', {
              gross: fmt(monthlyGross),
              days: workingDays,
              rate: fmt(perDayRate),
              piDays: incentiveDays,
              amount: fmt(incentiveAmount),
            })}
          </p>
        </div>
      )}

      <div className="flex items-center justify-between border-t border-green-200 bg-green-50/80 px-2.5 py-1 text-[11px] font-semibold text-green-900">
        <span>{t('Total Salary')}</span>
        <span className="tabular-nums">₹{fmt(entry.total_earnings)}</span>
      </div>
    </div>
  );
}

export function PayrollEntryBreakdownPanel({ entry, runUsesAttendance = true, onClose }: PayrollEntryBreakdownPanelProps) {
  const { t } = useTranslation();
  const deductions = breakdownLines(entry.deductions_breakdown);
  const componentLines = componentEarningLines(entry.earnings_breakdown);
  const hasBreakdown = componentLines.length > 0 || deductions.length > 0 || Number(entry.incentive_amount ?? 0) > 0;
  const workingDays = Math.max(26, Number(entry.working_days ?? 26) || 26);
  const presentDays = Number(entry.present_days ?? 0);
  const halfDays = Number(entry.half_days ?? 0);
  const weekOffWorkedDays = Number(entry.week_off_worked_days ?? 0);
  const paidDays = Number(entry.paid_days ?? 0);
  const incentiveDays = Number(entry.incentive_days ?? 0);
  const incentiveAmount = Number(entry.incentive_amount ?? 0);
  const otEnabled = Boolean(entry.ot_enabled);
  const isProRated = runUsesAttendance && paidDays > 0 && paidDays < workingDays;
  const hasIncentive = incentiveDays > 0 && incentiveAmount > 0;
  const hasHalfDays = halfDays > 0;
  const hasWeekOffWorked = weekOffWorkedDays > 0;
  const showAttendanceNote = runUsesAttendance && (entry.has_mispunch || isProRated || paidDays === 0 || hasIncentive || otEnabled || hasHalfDays || hasWeekOffWorked);
  const salaryMeta = salaryDisplayMeta(entry);

  return (
    <div className="w-full border-l-4 border-l-primary/40 bg-slate-50/80 px-3 py-2">
      <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
        <p className="text-[11px] font-semibold text-slate-700">
          {t('Salary breakdown')}
          <span className="ml-1.5 font-normal text-slate-500">· {t('PF, ESI, earnings & deductions')}</span>
        </p>
        {onClose && (
          <button
            type="button"
            onClick={(e) => { e.stopPropagation(); onClose(); }}
            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-slate-100"
          >
            <ChevronUp className="h-3 w-3" />
            {t('Close')}
          </button>
        )}
      </div>

      {showAttendanceNote && (
        <div className={cn(
          'mb-2 flex flex-wrap items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-[10px]',
          entry.has_mispunch ? 'border border-amber-200 bg-amber-50 text-amber-900' : 'border border-sky-100 bg-sky-50/80 text-sky-900',
        )}>
          <span>
            {t('Working')} <strong>{workingDays}</strong>
            {' → '}{t('Present')} <strong>{formatDays(presentDays)}</strong>
            {hasHalfDays && (
              <> · <strong>{halfDays}</strong> {t('half days')} (×0.5 = <strong>{formatDays(halfDays * 0.5)}</strong>)</>
            )}
            {hasWeekOffWorked && (
              <> · {t('Week-off worked')} <strong>{formatDays(weekOffWorkedDays)}</strong></>
            )}
            {' → '}{t('Paid')} <strong>{formatDays(paidDays)}</strong>
            {' · '}{t('OT')} <strong>{otEnabled ? t('Yes') : t('No')}</strong>
            {hasIncentive && (
              <> · {t('Incentive Days')} <strong>{incentiveDays}</strong> · {t('Incentive Amount')} <strong>₹{formatRupee(incentiveAmount)}</strong></>
            )}
            {isProRated && paidDays > 0 && !hasIncentive && (
              <> · {t('Total Salary')} ₹{formatRupee(entry.total_earnings)} ({formatDays(paidDays)}/{workingDays} {t('days')})</>
            )}
            {!isProRated && paidDays > 0 && !hasIncentive && (
              <> · {t('Full month pay')}</>
            )}
            {hasIncentive && (
              <> · {t('Regular')} ₹{formatRupee(entry.total_earnings - incentiveAmount)} + {t('PI')} ₹{formatRupee(incentiveAmount)} = ₹{formatRupee(entry.total_earnings)}</>
            )}
            {paidDays === 0 && <> · {t('No pay — 0 paid days')}</>}
          </span>
          {entry.has_mispunch && (
            <Link
              href={route('hr.attendance.sync')}
              className="inline-flex shrink-0 items-center gap-1 font-semibold text-amber-800 hover:underline"
              onClick={(e) => e.stopPropagation()}
            >
              <AlertTriangle className="h-3 w-3" />
              {t('Fix mispunch')} ({entry.mispunch_count})
            </Link>
          )}
        </div>
      )}

      {!hasBreakdown && (
        <p className="mb-2 text-[10px] text-amber-700">
          {t('Regenerate payroll to refresh component breakdown.')}
        </p>
      )}

      <div className="mb-2 grid w-full gap-2 lg:grid-cols-2">
        <EarningsBreakdownPanel entry={entry} formatRupee={formatRupee} t={t} />
        <CompactLineList
          title={t('Deductions')}
          lines={deductions}
          total={entry.total_deductions}
          totalLabel={t('Total')}
          tone="red"
          formatRupee={formatRupee}
        />
      </div>

      {entry.pf_breakdown && (
        <div className="mb-2 rounded-md border border-orange-200 bg-orange-50/60 px-2.5 py-2 text-[10px] text-slate-700">
          <p className="mb-1.5 font-bold uppercase tracking-wide text-orange-900">{t('Provident Fund (PF)')}</p>
          <div className="grid gap-1 sm:grid-cols-2">
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('PF Wages (Basic)')}</span>
              <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.wages)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Employee PF')} ({entry.pf_breakdown.employee_pct}%)</span>
              <strong className="tabular-nums text-red-700">₹{formatRupee(entry.pf_breakdown.employee)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Pension (EPS)')} ({entry.pf_breakdown.eps_pct}%)</span>
              <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.eps)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Employer EPF')} ({entry.pf_breakdown.epf_employer_pct}%)</span>
              <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.epf_employer)}</strong>
            </div>
          </div>
          <p className="mt-1.5 text-[9px] text-orange-800/80">
            {t('Employee 12% deducted from salary. Employer share split: {{eps}}% EPS + {{epf}}% EPF.', {
              eps: entry.pf_breakdown.eps_pct,
              epf: entry.pf_breakdown.epf_employer_pct,
            })}
          </p>
        </div>
      )}

      {entry.pt_breakdown && entry.pt_breakdown.pt_amount > 0 && (
        <div className="mb-2 rounded-md border border-violet-200 bg-violet-50/60 px-2.5 py-2 text-[10px] text-slate-700">
          <p className="mb-1.5 font-bold uppercase tracking-wide text-violet-900">{t('Professional Tax (P.Tax)')}</p>
          <div className="grid gap-1 sm:grid-cols-2">
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Earned gross (Total Salary)')}</span>
              <strong className="tabular-nums">₹{formatRupee(entry.pt_breakdown.gross)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('PT slab (monthly)')}</span>
              <strong className="tabular-nums text-violet-900">
                {formatPtSlabRange(entry.pt_breakdown.min_amt, entry.pt_breakdown.max_amt, formatRupee)}
              </strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1 sm:col-span-2">
              <span>{t('Professional Tax deducted')}</span>
              <strong className="tabular-nums text-red-700">₹{formatRupee(entry.pt_breakdown.pt_amount)}</strong>
            </div>
          </div>
          <p className="mt-1.5 text-[9px] text-violet-800/90">
            {entry.pt_breakdown.min_amt !== null ? (
              t('PT is a fixed monthly amount from Payroll Settings slabs — not a % of salary. Earned gross ₹{{gross}} falls in slab {{slab}} → PT ₹{{pt}}.', {
                gross: formatRupee(entry.pt_breakdown.gross),
                slab: formatPtSlabRange(entry.pt_breakdown.min_amt, entry.pt_breakdown.max_amt, formatRupee),
                pt: formatRupee(entry.pt_breakdown.pt_amount),
              })
            ) : (
              t('PT is deducted as per slabs configured in Payroll Settings based on this month\'s Total Salary.')
            )}
          </p>
        </div>
      )}

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] text-slate-600">
        <span className="font-medium text-slate-700">{t('Statutory')}:</span>
        <span className="inline-flex items-center gap-1">
          {t('PF')} <StatutoryFlagBadge enabled={!!entry.pf_enabled} />
          {entry.pf_enabled ? <strong className="text-slate-800">₹{formatRupee(entry.pf_employee)}</strong> : <span className="text-slate-400">—</span>}
        </span>
        <span className="text-slate-300">|</span>
        <span className="inline-flex items-center gap-1">
          {t('ESI')} <StatutoryFlagBadge enabled={!!entry.esi_enabled} />
          {entry.esi_enabled ? <strong className="text-slate-800">₹{formatRupee(entry.esi_employee)}</strong> : <span className="text-slate-400">—</span>}
        </span>
        <span className="text-slate-300">|</span>
        <span>
          {t('P.Tax')}: <strong className="text-slate-800">{entry.pt_amount > 0 ? `₹${formatRupee(entry.pt_amount)}` : '—'}</strong>
        </span>
      </div>

      <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-slate-600">
        <span>
          {salaryMeta.mode === 'day' ? t('Day rate') : t('Month salary')}
          {' '}<strong className="text-slate-800">₹{formatRupee(salaryMeta.rate)}</strong>
        </span>
        <span className="text-slate-300">·</span>
        <span>{t('CTC')} <strong className="text-slate-800">₹{formatRupee(salaryMeta.ctc)}</strong></span>
        <span className="text-slate-300">·</span>
        <span>{t('Total Salary')} <strong className="text-green-700">₹{formatRupee(entry.total_earnings)}</strong></span>
        <span className="text-slate-300">−</span>
        <span>{t('Deductions')} <strong className="text-red-700">₹{formatRupee(entry.total_deductions)}</strong></span>
        <span className="text-slate-300">=</span>
        <span>{t('Net Salary')} <strong className="text-primary">₹{formatRupee(entry.net_salary)}</strong></span>
      </div>
    </div>
  );
}
