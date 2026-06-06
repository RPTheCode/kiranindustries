import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

export function StatutoryFlagBadge({ enabled, className }: { enabled: boolean; className?: string }) {
  const { t } = useTranslation();

  return (
    <span
      className={cn(
        'inline-flex rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
        enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500',
        className
      )}
    >
      {enabled ? t('ON') : t('OFF')}
    </span>
  );
}

export function StatutoryAmountCell({
  enabled,
  amount,
  formatRupee,
}: {
  enabled: boolean;
  amount: number;
  formatRupee: (value: number) => string;
}) {
  return (
    <div className="flex flex-col items-end gap-1">
      <StatutoryFlagBadge enabled={enabled} />
      <span className={cn('text-xs tabular-nums', enabled ? 'text-slate-800' : 'text-slate-400')}>
        {enabled ? `₹${formatRupee(amount)}` : '—'}
      </span>
    </div>
  );
}

export function StatutoryLegend() {
  const { t } = useTranslation();

  return (
    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-100 bg-slate-50/80 px-4 py-2 text-[10px] text-slate-600">
      <span className="font-medium text-slate-700">{t('Statutory flags')}:</span>
      <span className="inline-flex items-center gap-1.5">
        <StatutoryFlagBadge enabled />
        <span>{t('PF / ESI applicable on this employee')}</span>
      </span>
      <span className="inline-flex items-center gap-1.5">
        <StatutoryFlagBadge enabled={false} />
        <span>{t('Not applicable — no deduction calculated')}</span>
      </span>
    </div>
  );
}

interface StatutorySummaryCardProps {
  title: string;
  enabled: boolean;
  employeeAmount: number;
  employerAmount?: number;
  baseLabel?: string;
  baseAmount?: number;
  formatRupee: (value: number) => string;
}

export function StatutorySummaryCard({
  title,
  enabled,
  employeeAmount,
  employerAmount = 0,
  baseLabel,
  baseAmount,
  formatRupee,
}: StatutorySummaryCardProps) {
  const { t } = useTranslation();

  return (
    <div className={cn(
      'rounded-lg border px-3 py-2',
      enabled ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 bg-slate-50'
    )}>
      <div className="mb-1 flex items-center justify-between gap-2">
        <span className="text-xs font-semibold text-slate-800">{title}</span>
        <StatutoryFlagBadge enabled={enabled} />
      </div>
      {enabled ? (
        <div className="space-y-0.5 text-[11px] text-slate-600">
          <p>{t('Employee')}: <strong className="text-slate-800">₹{formatRupee(employeeAmount)}</strong></p>
          {employerAmount > 0 && (
            <p>{t('Employer')}: <strong className="text-slate-800">₹{formatRupee(employerAmount)}</strong></p>
          )}
          {baseLabel && baseAmount !== undefined && baseAmount > 0 && (
            <p className="text-slate-500">{baseLabel}: ₹{formatRupee(baseAmount)}</p>
          )}
        </div>
      ) : (
        <p className="text-[11px] text-slate-500">{t('Disabled on employee profile — not deducted')}</p>
      )}
    </div>
  );
}
