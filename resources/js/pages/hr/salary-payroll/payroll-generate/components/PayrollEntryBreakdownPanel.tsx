import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronUp, Loader2 } from 'lucide-react';
import { StatutoryFlagBadge } from './StatutoryIndicators';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

export interface PayrollEntryBreakdown {
  monthly_gross: number;
  gross_input_mode?: string;
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
  attendance_extra_days?: number;
  attendance_extra_amount?: number;
  apply_attendance_extra?: boolean;
  attendance_extra_applied?: boolean;
  run_apply_attendance_extra?: boolean;
  can_toggle_attendance_extra?: boolean;
  is_locked?: boolean;
  id?: number;
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
  working_days_source?: string | null;
  use_government_wage_rules?: boolean;
  govt_min_wage_per_day?: number | null;
  govt_min_wage_used?: number | null;
  govt_wage_missing_reason?: string | null;
  govt_wage_rate_for_salary?: number | null;
  govt_wage_salary_applied?: boolean;
  actual_paid_days?: number;
  govt_wage_equiv_days_raw?: number | null;
  govt_wage_paid_days?: number | null;
  contract_regular_earnings?: number | null;
  govt_wage_computed_earnings?: number | null;
  govt_wage_adjustment_amount?: number;
  govt_wage_adjustment_type?: 'incentive' | 'deduction' | null;
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
    admin_pct?: number;
    admin?: number;
    employer_total?: number;
    challan_ac1?: number;
    challan_ac2?: number;
    challan_ac10?: number;
    challan_total?: number;
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

export function formatDays(value: number) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  return n % 1 === 0 ? String(n) : n.toFixed(1);
}

/** When govt wage conversion changes paid days (e.g. attendance 23 → govt 22). */
export function resolveActualPaidDays(entry: {
  govt_wage_salary_applied?: boolean;
  actual_paid_days?: number;
  govt_wage_paid_days?: number | null;
  paid_days?: number;
  contract_regular_earnings?: number | null;
  incentive_per_day_rate?: number;
  monthly_gross?: number;
  working_days?: number;
  present_days?: number;
  week_off_worked_days?: number;
  ot_enabled?: boolean;
  gross_input_mode?: string;
  daily_option?: boolean;
}): number {
  const govt = Number(entry.govt_wage_paid_days ?? entry.paid_days ?? 0);
  const stored = Number(entry.actual_paid_days ?? 0);

  if (!entry.govt_wage_salary_applied) {
    return stored > 0 ? stored : govt;
  }

  if (stored > govt + 0.009) {
    return stored;
  }

  const contract = Number(entry.contract_regular_earnings ?? 0);
  const workingDays = Math.max(1, Number(entry.working_days ?? 26) || 26);
  const perDay = Number(entry.incentive_per_day_rate ?? 0)
    || (entry.gross_input_mode === 'day' || entry.daily_option
      ? Number(entry.monthly_gross ?? 0)
      : Number(entry.monthly_gross ?? 0) / workingDays);
  if (contract > 0 && perDay > 0) {
    const derived = Math.round((contract / perDay) * 100) / 100;
    if (derived > govt + 0.009) {
      return derived;
    }
  }

  const present = Number(entry.present_days ?? 0);
  const weekOff = Number(entry.week_off_worked_days ?? 0);
  const otEnabled = Boolean(entry.ot_enabled);
  const attendanceOnly = Math.min(present, workingDays) + weekOff;
  const attendancePaid = otEnabled ? Math.min(attendanceOnly, workingDays) : (attendanceOnly > workingDays ? workingDays : attendanceOnly);
  if (attendancePaid > govt + 0.009) {
    return attendancePaid;
  }

  return stored > 0 ? stored : govt;
}

export function resolveGovtAttendanceDayChange(entry: {
  govt_wage_salary_applied?: boolean;
  actual_paid_days?: number;
  govt_wage_paid_days?: number | null;
  paid_days?: number;
  contract_regular_earnings?: number | null;
  incentive_per_day_rate?: number;
  monthly_gross?: number;
  working_days?: number;
  present_days?: number;
  week_off_worked_days?: number;
  ot_enabled?: boolean;
  gross_input_mode?: string;
  daily_option?: boolean;
}): { actual: number; govt: number } | null {
  if (!entry.govt_wage_salary_applied) return null;
  const actual = resolveActualPaidDays(entry);
  const govt = Number(entry.govt_wage_paid_days ?? entry.paid_days ?? 0);
  if (actual <= 0 || Math.abs(actual - govt) < 0.01) return null;
  return { actual, govt };
}

export function GovtAttendanceDayChangeBadge({
  change,
  t,
  size = 'sm',
  showDescription = false,
}: {
  change: { actual: number; govt: number };
  t: (key: string, opts?: Record<string, unknown>) => string;
  size?: 'sm' | 'md';
  showDescription?: boolean;
}) {
  const isMd = size === 'md';
  const description = t('Govt min wage — attendance {{actual}} days converted to {{govt}} paid days for salary & PF', {
    actual: formatDays(change.actual),
    govt: formatDays(change.govt),
  });

  return (
    <span className={cn('inline-flex flex-col gap-0.5', showDescription && 'items-start')}>
      <span
        className={cn(
          'inline-flex items-center gap-0.5 rounded font-bold tabular-nums',
          isMd
            ? 'border border-indigo-300 bg-indigo-100 px-2 py-0.5 text-[10px] text-indigo-950'
            : 'bg-indigo-600 px-1.5 py-px text-[8px] text-white',
        )}
        title={description}
      >
        <span className={cn(isMd && 'text-indigo-700/80')}>{t('Att')}</span>
        <span>{formatDays(change.actual)}</span>
        <span className={cn(isMd ? 'text-indigo-500' : 'opacity-90')}>→</span>
        <span>{formatDays(change.govt)}</span>
        {!isMd && <span className="opacity-90">{t('govt')}</span>}
      </span>
      {showDescription && (
        <span className="text-[9px] font-medium leading-tight text-indigo-900/90">{description}</span>
      )}
    </span>
  );
}

export type AttendanceDaysBadgeTone = 'empty' | 'partial' | 'full' | 'govt_adjusted';

export function getAttendanceDaysBadgeTone(
  entry: { paid_days?: number; govt_wage_salary_applied?: boolean; actual_paid_days?: number; govt_wage_paid_days?: number | null },
  working: number,
): AttendanceDaysBadgeTone {
  if (resolveGovtAttendanceDayChange(entry)) return 'govt_adjusted';
  const paid = Number(entry.paid_days ?? 0);
  if (paid <= 0) return 'empty';
  if (paid >= working) return 'full';
  return 'partial';
}

export function attendanceDaysBadgeDescription(
  tone: AttendanceDaysBadgeTone,
  t: (key: string, opts?: Record<string, unknown>) => string,
  opts?: { actual?: string; govt?: string; paid?: string; working?: number },
): string | null {
  switch (tone) {
    case 'govt_adjusted':
      return t('Govt min wage: {{actual}} attendance → {{govt}} paid days', {
        actual: opts?.actual ?? '',
        govt: opts?.govt ?? '',
      });
    case 'partial':
      return t('Partial month — {{paid}} of {{working}} working days', {
        paid: opts?.paid ?? '',
        working: opts?.working ?? 26,
      });
    case 'full':
      return t('Full month — all working days paid');
    case 'empty':
      return t('No paid days this month');
    default:
      return null;
  }
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

function scaleEarningLinesToTarget(lines: [string, number][], targetTotal: number): [string, number][] {
  if (lines.length === 0 || targetTotal <= 0) return lines;
  const currentTotal = lines.reduce((sum, [, amount]) => sum + Number(amount), 0);
  if (currentTotal <= 0) return lines;

  const scaled = lines.map(([name, amount]) => [
    name,
    Math.round(Number(amount) / currentTotal * targetTotal),
  ] as [string, number]);
  const scaledSum = scaled.reduce((sum, [, amount]) => sum + amount, 0);
  const diff = Math.round(targetTotal - scaledSum);
  if (diff !== 0) {
    const adjustIndex = scaled.findIndex(([name]) => name.toUpperCase().includes('BASIC'));
    const idx = adjustIndex >= 0 ? adjustIndex : 0;
    scaled[idx] = [scaled[idx][0], Math.max(0, scaled[idx][1] + diff)];
  }

  return scaled;
}

function breakdownLines(breakdown?: Record<string, number>) {
  if (!breakdown) return [];
  return Object.entries(breakdown)
    .filter(([, amount]) => Number(amount) > 0)
    .sort(([a], [b]) => a.localeCompare(b));
}

function salaryDisplayMeta(entry: {
  monthly_gross: number;
  gross_input_mode?: string;
  daily_option?: boolean;
  working_days?: number;
}) {
  const monthlyGross = Number(entry.monthly_gross ?? 0);
  const dailyOption = entry.gross_input_mode === 'day' || Boolean(entry.daily_option);
  const salaryDays = Math.max(1, Number(entry.working_days ?? 26) || 26);

  if (dailyOption) {
    return {
      mode: 'day' as const,
      rate: monthlyGross,
      ctc: Math.round(monthlyGross * salaryDays),
    };
  }

  return {
    mode: 'month' as const,
    rate: monthlyGross,
    ctc: monthlyGross,
  };
}

function workingDaysSourceLabel(source: string | null | undefined, t: (key: string) => string) {
  switch (source) {
    case 'branch':
      return t('Branch setting');
    case 'zone':
      return t('Wage zone');
    case 'company':
      return t('Company default');
    default:
      return t('Default (26)');
  }
}

function govtWageMissingLabel(reason: string | null | undefined, t: (key: string) => string) {
  switch (reason) {
    case 'no_wage_zone':
      return t('Branch has no wage zone linked');
    case 'no_skill':
      return t('Employee skill not set');
    case 'no_zone_rate':
      return t('No govt rate for skill + zone');
    default:
      return null;
  }
}

interface PayrollEntryBreakdownPanelProps {
  entry: PayrollEntryBreakdown;
  runUsesAttendance?: boolean;
  togglingAdjust?: boolean;
  onToggleAdjust?: (apply: boolean) => void;
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
  togglingAdjust = false,
  onToggleAdjust,
}: {
  entry: PayrollEntryBreakdown;
  formatRupee: (v: number) => string;
  t: (key: string, opts?: Record<string, unknown>) => string;
  togglingAdjust?: boolean;
  onToggleAdjust?: (apply: boolean) => void;
}) {
  const componentLines = componentEarningLines(entry.earnings_breakdown);
  const incentiveDays = Number(entry.incentive_days ?? 0);
  const incentiveAmount = Number(entry.incentive_amount ?? 0);
  const otEnabled = Boolean(entry.ot_enabled);
  const attendanceExtraDays = Number(entry.attendance_extra_days ?? 0);
  const attendanceExtraAmount = Number(entry.attendance_extra_amount ?? 0);
  const attendanceExtraApplied = Boolean(entry.attendance_extra_applied);
  const workingDays = Math.max(1, Number(entry.working_days ?? 26) || 26);
  const monthlyGross = Number(entry.monthly_gross ?? 0);
  const perDayRate = Number(entry.incentive_per_day_rate ?? 0) || (workingDays > 0 ? Math.round((monthlyGross / workingDays) * 100) / 100 : 0);
  const govtApplied = Boolean(entry.govt_wage_salary_applied);
  const contractEarnings = Number(entry.contract_regular_earnings ?? entry.regular_earnings ?? 0);
  const govtSalary = Number(entry.govt_wage_computed_earnings ?? entry.total_earnings ?? 0);
  const contractDays = govtApplied ? resolveActualPaidDays(entry) : Number(entry.paid_days ?? 0);
  const govtDays = Number(entry.govt_wage_paid_days ?? entry.paid_days ?? 0);
  const govtRate = Number(entry.govt_wage_rate_for_salary ?? Math.round(Number(entry.govt_min_wage_per_day ?? 0)));
  const contractPerDay = entry.gross_input_mode === 'day' || entry.daily_option
    ? monthlyGross
    : perDayRate;
  const regularEarnings = govtApplied
    ? contractEarnings
    : (Number(entry.regular_earnings ?? 0) || componentLines.reduce((sum, [, amt]) => sum + Number(amt), 0));
  const displayComponentLines = govtApplied && contractEarnings > 0 && govtSalary > 0
    ? scaleEarningLinesToTarget(componentLines, contractEarnings)
    : componentLines;
  const hasIncentive = otEnabled && incentiveDays > 0 && incentiveAmount > 0;
  const hasAttendanceExtra = !otEnabled && attendanceExtraDays > 0 && attendanceExtraAmount > 0;
  const regularSalaryLabel = govtApplied
    ? t('Contract salary ({{days}} days × ₹{{rate}})', { days: formatDays(contractDays), rate: formatRupee(contractPerDay) })
    : t('Regular Salary ({{days}} days)', { days: formatDays(Number(entry.paid_days ?? 0)) });

  return (
    <div className="min-w-0 rounded-md border border-slate-200 bg-white">
      <div className="bg-green-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green-800">{t('Earnings')}</div>
      <div className="divide-y divide-slate-100">
        {displayComponentLines.length === 0 ? (
          <p className="px-2.5 py-2 text-[10px] text-slate-400">—</p>
        ) : displayComponentLines.map(([name, amount]) => (
          <div key={name} className="flex items-center justify-between gap-2 px-2.5 py-1 text-[11px]">
            <span className="truncate text-slate-700">{name}</span>
            <span className="shrink-0 tabular-nums font-medium text-slate-900">₹{fmt(Number(amount))}</span>
          </div>
        ))}
      </div>
      <div className={cn(
        'flex items-center justify-between border-t border-slate-200 px-2.5 py-1 text-[11px] font-semibold',
        govtApplied ? 'bg-indigo-50/80 text-indigo-950' : 'bg-slate-50/80 text-slate-800',
      )}>
        <span>{regularSalaryLabel}</span>
        <span className="tabular-nums">₹{fmt(regularEarnings)}</span>
      </div>

      {govtApplied && govtSalary > 0 && (
        <div className="flex items-center justify-between border-t border-indigo-200 bg-indigo-50/60 px-2.5 py-1 text-[11px] font-semibold text-indigo-950">
          <span>{t('Govt salary ({{days}} days × ₹{{rate}}) — PF base', { days: formatDays(govtDays), rate: formatRupee(govtRate) })}</span>
          <span className="tabular-nums">₹{fmt(govtSalary)}</span>
        </div>
      )}

      {hasAttendanceExtra && (
        <div className="border-t border-sky-200 bg-sky-50/50 px-2.5 py-2 text-[10px] text-sky-950">
          <div className="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <p className="font-bold uppercase tracking-wide text-sky-900">{t('Adjust (OT No — extra days)')}</p>
            <span className={cn(
              'rounded px-1.5 py-px text-[9px] font-bold uppercase',
              attendanceExtraApplied ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900',
            )}>
              {attendanceExtraApplied ? t('Applied to net') : t('Pending — not in net')}
            </span>
          </div>
          <div className="grid gap-1 sm:grid-cols-2">
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Extra days')}</span>
              <strong className="tabular-nums">{attendanceExtraDays}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Per day')} (÷ {workingDays})</span>
              <strong className="tabular-nums">₹{fmt(perDayRate)}</strong>
            </div>
          </div>
          <div className="mt-1.5 flex items-center justify-between rounded bg-white/80 px-2 py-1 font-semibold">
            <span>{t('Adjust amount')}</span>
            <strong className="tabular-nums text-sky-900">+ ₹{fmt(attendanceExtraAmount)}</strong>
          </div>
          <p className="mt-1.5 text-[9px] text-sky-800/90">
            {attendanceExtraApplied
              ? t('Included in Total Salary and Net. PF is still calculated on {{days}} days only.', { days: workingDays })
              : t('Not in net yet. Turn on the option below to add this amount for this employee only.')}
          </p>
          {entry.can_toggle_attendance_extra && onToggleAdjust && (
            <label className="mt-2 flex cursor-pointer items-start gap-2 rounded-md border border-sky-300 bg-white px-2 py-1.5">
              {togglingAdjust ? (
                <Loader2 className="mt-0.5 h-3.5 w-3.5 shrink-0 animate-spin text-sky-700" />
              ) : (
                <Checkbox
                  checked={Boolean(entry.apply_attendance_extra)}
                  onCheckedChange={(v) => onToggleAdjust(Boolean(v))}
                  className="mt-0.5"
                  onClick={(e) => e.stopPropagation()}
                />
              )}
              <span className="text-[10px] font-semibold leading-snug text-sky-950">
                {t('Add ₹{{amount}} adjust to net salary (this employee only)', { amount: fmt(attendanceExtraAmount) })}
              </span>
            </label>
          )}
        </div>
      )}

      {hasIncentive && (
        <div className="border-t border-amber-200 bg-amber-50/50 px-2.5 py-2 text-[10px] text-amber-950">
          <p className="mb-1.5 font-bold uppercase tracking-wide text-amber-900">{t('Incentive')}</p>
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

export function PayrollEntryBreakdownPanel({ entry, runUsesAttendance = true, togglingAdjust = false, onToggleAdjust, onClose }: PayrollEntryBreakdownPanelProps) {
  const { t } = useTranslation();
  const deductions = breakdownLines(entry.deductions_breakdown);
  const componentLines = componentEarningLines(entry.earnings_breakdown);
  const hasBreakdown = componentLines.length > 0 || deductions.length > 0 || Number(entry.incentive_amount ?? 0) > 0;
  const workingDays = Math.max(1, Number(entry.working_days ?? 26) || 26);
  const presentDays = Number(entry.present_days ?? 0);
  const halfDays = Number(entry.half_days ?? 0);
  const weekOffWorkedDays = Number(entry.week_off_worked_days ?? 0);
  const paidDays = Number(entry.paid_days ?? 0);
  const incentiveDays = Number(entry.incentive_days ?? 0);
  const incentiveAmount = Number(entry.incentive_amount ?? 0);
  const attendanceExtraDays = Number(entry.attendance_extra_days ?? 0);
  const attendanceExtraAmount = Number(entry.attendance_extra_amount ?? 0);
  const attendanceExtraApplied = Boolean(entry.attendance_extra_applied);
  const otEnabled = Boolean(entry.ot_enabled);
  const isProRated = runUsesAttendance && paidDays > 0 && paidDays < workingDays;
  const hasIncentive = otEnabled && incentiveDays > 0 && incentiveAmount > 0;
  const hasAttendanceExtra = !otEnabled && attendanceExtraDays > 0 && attendanceExtraAmount > 0;
  const hasHalfDays = halfDays > 0;
  const hasWeekOffWorked = weekOffWorkedDays > 0;
  const govtDayChange = resolveGovtAttendanceDayChange(entry);
  const showAttendanceNote = runUsesAttendance && (entry.has_mispunch || isProRated || paidDays === 0 || hasIncentive || otEnabled || hasHalfDays || hasWeekOffWorked || govtDayChange);
  const salaryMeta = salaryDisplayMeta(entry);

  const showGovtRules = Boolean(entry.use_government_wage_rules);
  const govtMissing = govtWageMissingLabel(entry.govt_wage_missing_reason, t);

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

      {(entry.working_days_source || showGovtRules) && (
        <div className="mb-2 flex flex-wrap items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] text-slate-700">
          {entry.working_days_source && (
            <span>
              {t('Working days')}: <strong>{formatDays(workingDays)}</strong>
              {' · '}{workingDaysSourceLabel(entry.working_days_source, t)}
            </span>
          )}
          {showGovtRules && entry.govt_min_wage_per_day != null && (
            <span className="text-indigo-800">
              · {t('Govt min/day for PF')}: <strong>₹{formatRupee(entry.govt_min_wage_per_day)}</strong>
              {entry.govt_min_wage_used != null && entry.govt_min_wage_used > 0 && (
                <> · {t('PF basic used')}: <strong>₹{formatRupee(entry.govt_min_wage_used)}</strong></>
              )}
            </span>
          )}
          {showGovtRules && govtMissing && (
            <span className="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900">{govtMissing}</span>
          )}
        </div>
      )}

      {govtDayChange && (
        <div className="mb-2 flex flex-col gap-1 rounded-md border-2 border-indigo-300 bg-indigo-100/90 px-2.5 py-1.5 text-[10px] text-indigo-950">
          <div className="flex flex-wrap items-center gap-2">
            <GovtAttendanceDayChangeBadge change={govtDayChange} t={t} size="md" />
            <span className="rounded bg-indigo-600 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">{t('Color: Indigo')}</span>
          </div>
          <p className="font-semibold text-indigo-950">
            {t('Why indigo? Contract rate is below govt minimum — paid days changed from {{actual}} (attendance) to {{govt}} (govt rate days). Salary & PF use {{govt}} days.', {
              actual: formatDays(govtDayChange.actual),
              govt: formatDays(govtDayChange.govt),
            })}
          </p>
        </div>
      )}

      {entry.govt_wage_salary_applied && entry.govt_min_wage_per_day != null && (
        <div className="mb-2 rounded-md border border-indigo-200 bg-indigo-50/70 px-2.5 py-2 text-[10px] text-indigo-950">
          <p className="mb-1.5 font-bold uppercase tracking-wide text-indigo-900">{t('Government wage conversion')}</p>
          <div className="grid gap-1 sm:grid-cols-2">
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Contract rate')}</span>
              <strong className="tabular-nums">₹{formatRupee(
                (entry.gross_input_mode === 'day' || entry.daily_option)
                  ? Number(entry.monthly_gross ?? 0)
                  : Number(entry.monthly_gross ?? 0) / Math.max(1, workingDays)
              )}/day</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Actual paid days')}</span>
              <strong className="tabular-nums">{formatDays(Number(entry.actual_paid_days ?? paidDays))}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Contract salary')}</span>
              <strong className="tabular-nums">₹{formatRupee(entry.contract_regular_earnings ?? 0)}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Govt rate')} ({t('salary')})</span>
              <strong className="tabular-nums">₹{formatRupee(entry.govt_wage_rate_for_salary ?? Math.round(Number(entry.govt_min_wage_per_day ?? 0)))}/day</strong>
            </div>
            {entry.govt_min_wage_per_day != null
              && entry.govt_wage_rate_for_salary != null
              && Math.abs(Number(entry.govt_min_wage_per_day) - Number(entry.govt_wage_rate_for_salary)) >= 0.01 && (
              <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1 text-indigo-800/80">
                <span>{t('Govt rate')} ({t('PF exact')})</span>
                <strong className="tabular-nums">₹{formatRupee(entry.govt_min_wage_per_day)}/day</strong>
              </div>
            )}
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1 sm:col-span-2">
              <span>{t('Equivalent days')} (₹{formatRupee(entry.contract_regular_earnings ?? 0)} ÷ ₹{formatRupee(entry.govt_wage_rate_for_salary ?? entry.govt_min_wage_per_day ?? 0)})</span>
              <strong className="tabular-nums">{entry.govt_wage_equiv_days_raw != null ? entry.govt_wage_equiv_days_raw.toFixed(2) : '—'} → {formatDays(Number(entry.govt_wage_paid_days ?? paidDays))} {t('days')}</strong>
            </div>
            <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
              <span>{t('Govt salary')}</span>
              <strong className="tabular-nums">₹{formatRupee(entry.govt_wage_computed_earnings ?? 0)}</strong>
            </div>
            {Number(entry.govt_wage_adjustment_amount ?? 0) > 0 && (
              <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1 font-semibold">
                <span>{entry.govt_wage_adjustment_type === 'deduction' ? t('Other deduction (adjust)') : t('Incentive (adjust)')}</span>
                <strong className={cn('tabular-nums', entry.govt_wage_adjustment_type === 'deduction' ? 'text-red-700' : 'text-green-700')}>
                  {entry.govt_wage_adjustment_type === 'deduction' ? '−' : '+'} ₹{formatRupee(entry.govt_wage_adjustment_amount ?? 0)}
                </strong>
              </div>
            )}
          </div>
          <p className="mt-1.5 text-[9px] text-indigo-800/90">
            {t('Paid days shown as {{govtDays}} at govt rate. Contract target ₹{{contract}} preserved via adjust. PF wages use govt salary ₹{{govtSalary}} (same as Excel).', {
              govtDays: formatDays(Number(entry.govt_wage_paid_days ?? paidDays)),
              contract: formatRupee(entry.contract_regular_earnings ?? 0),
              govtSalary: formatRupee(entry.govt_wage_computed_earnings ?? 0),
            })}
          </p>
        </div>
      )}

      {showAttendanceNote && (
        <div className={cn(
          'mb-2 flex flex-wrap items-center justify-between gap-2 rounded-md px-2.5 py-2 text-xs',
          entry.has_mispunch
            ? 'border border-amber-200 bg-amber-50 text-amber-900'
            : govtDayChange
              ? 'border border-indigo-200 bg-indigo-50/80 text-indigo-950'
              : 'border border-sky-100 bg-sky-50/80 text-sky-900',
        )}>
          <span>
            {(entry.has_mispunch || govtDayChange) && (
              <span className={cn(
                'mr-1.5 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase',
                entry.has_mispunch ? 'bg-amber-500 text-white' : 'bg-indigo-600 text-white',
              )}>
                {entry.has_mispunch ? t('Warning') : t('Indigo')}
              </span>
            )}
            {govtDayChange && (
              <span className="mr-1 font-semibold text-indigo-900">
                {t('Govt wage days: {{actual}} → {{govt}} · ', {
                  actual: formatDays(govtDayChange.actual),
                  govt: formatDays(govtDayChange.govt),
                })}
              </span>
            )}
            {entry.has_mispunch && (
              <span className="mr-1 font-semibold text-amber-900">{t('Mispunch — fix attendance · ')}</span>
            )}
            {t('Working')} <strong>{workingDays}</strong>
            {' → '}{t('Present')} <strong>{formatDays(presentDays)}</strong>
            {hasHalfDays && (
              <> · <strong>{halfDays}</strong> {t('half days')} (×0.5 = <strong>{formatDays(halfDays * 0.5)}</strong>)</>
            )}
            {hasWeekOffWorked && (
              <> · {t('Week-off worked')} <strong>{formatDays(weekOffWorkedDays)}</strong></>
            )}
            {' → '}{t('Paid')}{' '}
            {govtDayChange ? (
              <>
                <strong className="text-indigo-800">{formatDays(govtDayChange.actual)}</strong>
                <span className="mx-0.5 font-bold text-indigo-600">→</span>
                <strong className="rounded bg-indigo-200 px-1 text-indigo-950">{formatDays(govtDayChange.govt)}</strong>
                <span className="ml-1 rounded bg-indigo-600 px-1 py-px text-[9px] font-bold uppercase text-white">{t('govt')}</span>
              </>
            ) : (
              <strong>{formatDays(paidDays)}</strong>
            )}
            {' · '}{t('OT')} <strong>{otEnabled ? t('Yes') : t('No')}</strong>
            {hasIncentive && (
              <> · {t('Incentive Days')} <strong>{incentiveDays}</strong> · {t('Incentive Amount')} <strong>₹{formatRupee(incentiveAmount)}</strong></>
            )}
            {hasAttendanceExtra && (
              <> · {t('Adjust')} <strong>{formatDays(attendanceExtraDays)}</strong> · <strong>+₹{formatRupee(attendanceExtraAmount)}</strong>
                {!attendanceExtraApplied && <> ({t('pending')})</>}
              </>
            )}
            {isProRated && paidDays > 0 && !hasIncentive && !hasAttendanceExtra && (
              <> · {t('Total Salary')} ₹{formatRupee(entry.total_earnings)} ({formatDays(paidDays)}/{workingDays} {t('days')})</>
            )}
            {!isProRated && paidDays > 0 && !hasIncentive && !hasAttendanceExtra && (
              <> · {t('Full month pay')}</>
            )}
            {hasIncentive && (
              <> · {t('Regular')} ₹{formatRupee(entry.total_earnings - incentiveAmount)} + {t('PI')} ₹{formatRupee(incentiveAmount)} = ₹{formatRupee(entry.total_earnings)}</>
            )}
            {hasAttendanceExtra && !hasIncentive && attendanceExtraApplied && (
              <> · {t('Regular')} ₹{formatRupee(entry.total_earnings - attendanceExtraAmount)} + {t('Adjust')} ₹{formatRupee(attendanceExtraAmount)} = ₹{formatRupee(entry.total_earnings)}</>
            )}
            {hasAttendanceExtra && !hasIncentive && !attendanceExtraApplied && (
              <> · {t('Regular')} ₹{formatRupee(entry.total_earnings)} · {t('Adjust pending')} ₹{formatRupee(attendanceExtraAmount)}</>
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
        <EarningsBreakdownPanel entry={entry} formatRupee={formatRupee} t={t} togglingAdjust={togglingAdjust} onToggleAdjust={onToggleAdjust} />
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
            {(entry.pf_breakdown.admin ?? 0) > 0 && (
              <div className="flex justify-between gap-2 rounded bg-white/80 px-2 py-1">
                <span>{t('PF Admin')} ({entry.pf_breakdown.admin_pct ?? 1}%)</span>
                <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.admin ?? 0)}</strong>
              </div>
            )}
            {(entry.pf_breakdown.employer_total ?? 0) > 0 && (
              <div className="flex justify-between gap-2 rounded bg-orange-100/80 px-2 py-1 sm:col-span-2">
                <span>{t('Total employer PF (not deducted from salary)')}</span>
                <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.employer_total ?? 0)}</strong>
              </div>
            )}
          </div>
          {(entry.pf_breakdown.challan_total ?? 0) > 0 && (
            <div className="mt-2 rounded border border-orange-200 bg-white/60 px-2 py-1.5">
              <p className="mb-1 text-[9px] font-bold uppercase text-orange-900">{t('PF challan (this employee)')}</p>
              <div className="grid gap-0.5 sm:grid-cols-2">
                <div className="flex justify-between gap-2 text-[9px]">
                  <span>{t('A/C 1 (Emp PF + Empr EPF)')}</span>
                  <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.challan_ac1 ?? 0)}</strong>
                </div>
                <div className="flex justify-between gap-2 text-[9px]">
                  <span>{t('A/C 2 (EPS)')}</span>
                  <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.challan_ac2 ?? 0)}</strong>
                </div>
                <div className="flex justify-between gap-2 text-[9px]">
                  <span>{t('A/C 10 (Admin)')}</span>
                  <strong className="tabular-nums">₹{formatRupee(entry.pf_breakdown.challan_ac10 ?? 0)}</strong>
                </div>
                <div className="flex justify-between gap-2 text-[9px] font-semibold">
                  <span>{t('Challan total')}</span>
                  <strong className="tabular-nums text-orange-900">₹{formatRupee(entry.pf_breakdown.challan_total ?? 0)}</strong>
                </div>
              </div>
            </div>
          )}
          <p className="mt-1.5 text-[9px] text-orange-800/80">
            {entry.govt_wage_salary_applied
              ? t('PF wages = govt salary ({{days}} × ₹{{rate}} = ₹{{wages}}). Employee {{empPct}}% = ₹{{empPf}}; EPS {{eps}}% + EPF {{epf}}%.', {
                  days: formatDays(Number(entry.govt_wage_paid_days ?? paidDays)),
                  rate: formatRupee(entry.govt_wage_rate_for_salary ?? Math.round(Number(entry.govt_min_wage_per_day ?? 0))),
                  wages: formatRupee(entry.pf_breakdown.wages),
                  empPct: entry.pf_breakdown.employee_pct,
                  empPf: formatRupee(entry.pf_breakdown.employee),
                  eps: entry.pf_breakdown.eps_pct,
                  epf: entry.pf_breakdown.epf_employer_pct,
                })
              : t('Employee 12% deducted from salary. Employer share split: {{eps}}% EPS + {{epf}}% EPF.', {
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
