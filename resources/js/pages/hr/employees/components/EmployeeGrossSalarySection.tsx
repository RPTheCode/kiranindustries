import { useEffect, useMemo } from 'react';
import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
  buildSalaryFormPayload,
  formatRupee,
  grossDisplayAfterModeToggle,
  type GrossInputMode,
} from '@/utils/salary-gross-split';

export interface EmployeeGrossSalarySectionProps {
  grossAmount: string;
  grossInputMode: GrossInputMode;
  workingDays: number;
  workingDaysSource?: string | null;
  salaryComponents: any[];
  extraComponentIds: number[];
  pfApplicable?: boolean;
  esiApplicable?: boolean;
  onGrossAmountChange: (value: string) => void;
  onGrossInputModeChange: (mode: GrossInputMode) => void;
  onComputedChange: (payload: {
    gross_salary: string;
    basic_salary: string;
    salary_components: Record<string, string>;
    gross_input_mode: GrossInputMode;
  }) => void;
}

function workingDaysSourceLabel(source: string | null | undefined, t: (k: string) => string) {
  switch (source) {
    case 'branch':
      return t('Branch setting');
    case 'zone':
      return t('Wage zone');
    case 'company':
      return t('Company default');
    default:
      return t('Default');
  }
}

export function EmployeeGrossSalarySection({
  grossAmount,
  grossInputMode,
  workingDays,
  workingDaysSource,
  salaryComponents,
  extraComponentIds,
  pfApplicable = false,
  esiApplicable = false,
  onGrossAmountChange,
  onGrossInputModeChange,
  onComputedChange,
}: EmployeeGrossSalarySectionProps) {
  const { t } = useTranslation();
  const isDayWise = grossInputMode === 'day';

  const payload = useMemo(
    () => buildSalaryFormPayload(
      grossAmount,
      grossInputMode,
      workingDays,
      salaryComponents,
      extraComponentIds,
      { applyPf: pfApplicable, applyEsi: esiApplicable },
    ),
    [grossAmount, grossInputMode, workingDays, salaryComponents, extraComponentIds, pfApplicable, esiApplicable],
  );

  useEffect(() => {
    onComputedChange({
      gross_salary: payload.gross_salary,
      basic_salary: payload.basic_salary,
      salary_components: payload.salary_components,
      gross_input_mode: grossInputMode,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [payload.gross_salary, payload.basic_salary, JSON.stringify(payload.salary_components), grossInputMode]);

  const earnings = payload.split?.breakdown.filter((r) => r.type === 'earning' && r.amount > 0) ?? [];
  const deductions = payload.split?.breakdown.filter((r) => r.type === 'deduction' && r.amount > 0) ?? [];

  const handleModeChange = (nextMode: GrossInputMode) => {
    if (nextMode === grossInputMode) return;
    const converted = grossDisplayAfterModeToggle(grossAmount, grossInputMode, nextMode, workingDays);
    onGrossAmountChange(converted);
    onGrossInputModeChange(nextMode);
  };

  return (
    <div className="space-y-4 rounded-2xl border border-blue-100 bg-blue-50/30 p-5 shadow-sm">
      <div>
        <h3 className="text-sm font-bold text-slate-800">{t('Salary amount & breakdown')}</h3>
        <p className="mt-0.5 text-[10px] text-slate-500">
          {isDayWise
            ? t('Enter daily gross — components split on this amount. Working days ({{days}}) apply only for monthly estimate.', {
                days: workingDays,
              })
            : t('Enter monthly gross — components auto-split by master percentages. Working days: {{days}} ({{source}}).', {
                days: workingDays,
                source: workingDaysSourceLabel(workingDaysSource, t),
              })}
        </p>
      </div>

      <div className="flex flex-wrap items-end gap-4">
        <div className="min-w-[200px] flex-1 space-y-1">
          <Label className="text-[11px] font-semibold">{t('Gross Salary')}</Label>
          <div
            className={cn(
              'relative flex h-10 items-stretch rounded-lg border bg-white shadow-sm',
              isDayWise
                ? 'border-amber-200 focus-within:border-amber-400 focus-within:ring-2 focus-within:ring-amber-400/20'
                : 'border-blue-200 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-400/20',
            )}
          >
            <span className="flex w-7 shrink-0 items-center justify-center text-sm text-muted-foreground">₹</span>
            <Input
              type="number"
              min="0"
              step={isDayWise ? '0.01' : '1'}
              value={grossAmount}
              onChange={(e) => onGrossAmountChange(e.target.value)}
              placeholder="0"
              className="h-10 flex-1 rounded-none border-0 bg-transparent px-0 text-sm font-semibold shadow-none focus-visible:ring-0"
            />
            <div className={cn('relative shrink-0 border-l', isDayWise ? 'border-amber-200 bg-amber-50/60' : 'border-blue-200 bg-blue-50/60')}>
              <select
                value={grossInputMode}
                onChange={(e) => handleModeChange(e.target.value as GrossInputMode)}
                className={cn(
                  'h-10 w-[54px] cursor-pointer appearance-none border-0 bg-transparent py-0 pl-1.5 pr-5 text-[10px] font-semibold outline-none',
                  isDayWise ? 'text-amber-800' : 'text-blue-800',
                )}
              >
                <option value="day">{t('/day')}</option>
                <option value="month">{t('/mo')}</option>
              </select>
              <ChevronDown className="pointer-events-none absolute right-0.5 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground/60" />
            </div>
          </div>
          {isDayWise && payload.monthlyGross > 0 && (
            <p className="text-[10px] text-amber-800">
              {t('≈ ₹{{total}}/month ({{days}} days)', { total: formatRupee(payload.monthlyGross), days: workingDays })}
            </p>
          )}
        </div>

        {!isDayWise && payload.monthlyGross > 0 && (
          <div className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-center">
            <p className="text-[9px] font-bold uppercase tracking-wide text-slate-400">{t('Monthly gross')}</p>
            <p className="text-lg font-black tabular-nums text-slate-900">₹{formatRupee(payload.monthlyGross)}</p>
          </div>
        )}
      </div>

      {payload.splitBase > 0 && (earnings.length > 0 || deductions.length > 0) && (
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <div className="rounded-xl border border-green-100 bg-white/90 p-3">
            <p className="mb-2 text-[10px] font-bold uppercase tracking-wide text-green-800">
              {isDayWise ? t('Earnings (auto, per day)') : t('Earnings (auto)')}
            </p>
            <div className="space-y-1">
              {earnings.map((row) => (
                <div key={row.id} className="flex items-center justify-between text-[11px]">
                  <span className="text-slate-700">
                    {row.name}
                    <span className="ml-1 text-[9px] text-muted-foreground">({row.rate}%)</span>
                  </span>
                  <span className="font-semibold tabular-nums text-green-800">₹{formatRupee(row.amount)}</span>
                </div>
              ))}
            </div>
          </div>
          <div className="rounded-xl border border-red-100 bg-white/90 p-3">
            <p className="mb-2 text-[10px] font-bold uppercase tracking-wide text-red-800">
              {isDayWise ? t('Deductions (auto, per day)') : t('Deductions (auto)')}
            </p>
            {deductions.length === 0 ? (
              <p className="text-[10px] text-slate-400">{t('No deductions for this employee.')}</p>
            ) : (
              <div className="space-y-1">
                {deductions.map((row) => (
                  <div key={row.id} className="flex items-center justify-between text-[11px]">
                    <span className="text-slate-700">
                      {row.name}
                      <span className="ml-1 text-[9px] text-muted-foreground">({row.rate}%)</span>
                    </span>
                    <span className="font-semibold tabular-nums text-red-700">₹{formatRupee(row.amount)}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {payload.splitBase > 0 && payload.split && (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-white">
          <span className="text-[10px] uppercase tracking-wide text-slate-400">
            {isDayWise ? t('Net per day (before attendance)') : t('Net (before attendance)')}
          </span>
          <span className="text-lg font-black tabular-nums">₹{formatRupee(payload.split.netSalary)}</span>
        </div>
      )}
    </div>
  );
}
