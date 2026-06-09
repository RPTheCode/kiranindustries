import { useState } from 'react';
import { ChevronDown, ChevronRight, Landmark } from 'lucide-react';
import { cn } from '@/lib/utils';

export type StatutoryChallanSummary = {
  pf: {
    employee_count: number;
    wages: number;
    employee_contribution: number;
    employer_eps: number;
    employer_epf: number;
    admin_charges: number;
    employer_total: number;
    challan: {
      ac1_employees_pf: number;
      ac2_pension_eps: number;
      ac10_admin: number;
      total_deposit: number;
    };
    rates: {
      employee_pct: number;
      eps_pct: number;
      epf_employer_pct: number;
      admin_pct: number;
    };
  };
  esi: {
    employee: number;
    employer: number;
    total: number;
  };
  pt: {
    total: number;
  };
};

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function ChallanRow({
  label,
  sublabel,
  amount,
  highlight,
}: {
  label: string;
  sublabel?: string;
  amount: number;
  highlight?: boolean;
}) {
  return (
    <div
      className={cn(
        'flex items-start justify-between gap-3 rounded px-2 py-1.5',
        highlight ? 'bg-orange-100/80 font-semibold' : 'bg-white/70',
      )}
    >
      <div>
        <div className="text-[10px] text-slate-800">{label}</div>
        {sublabel && <div className="text-[9px] text-slate-500">{sublabel}</div>}
      </div>
      <strong className="shrink-0 tabular-nums text-[10px] text-slate-900">₹{formatRupee(amount)}</strong>
    </div>
  );
}

export function StatutoryChallanPanel({
  challan,
  t,
  defaultOpen = false,
}: {
  challan: StatutoryChallanSummary | null | undefined;
  t: (key: string, opts?: Record<string, unknown>) => string;
  defaultOpen?: boolean;
}) {
  const [open, setOpen] = useState(defaultOpen);

  if (!challan) return null;

  const { pf, esi, pt } = challan;
  const hasPf = pf.challan.total_deposit > 0;
  const hasEsi = esi.total > 0;
  const hasPt = pt.total > 0;

  if (!hasPf && !hasEsi && !hasPt) return null;

  return (
    <div className="mb-3 overflow-hidden rounded-lg border border-orange-200 bg-orange-50/40 shadow-sm">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left hover:bg-orange-50/80"
      >
        <span className="inline-flex items-center gap-2 text-xs font-semibold text-orange-950">
          <Landmark className="h-4 w-4 shrink-0 text-orange-700" />
          {t('Statutory Challan Summary')}
          {hasPf && (
            <span className="rounded bg-orange-200/80 px-1.5 py-0.5 text-[10px] font-bold text-orange-950">
              {t('PF deposit')} ₹{formatRupee(pf.challan.total_deposit)}
            </span>
          )}
        </span>
        {open ? (
          <ChevronDown className="h-4 w-4 shrink-0 text-orange-800" />
        ) : (
          <ChevronRight className="h-4 w-4 shrink-0 text-orange-800" />
        )}
      </button>

      {open && (
        <div className="space-y-3 border-t border-orange-200 px-3 pb-3 pt-2">
          {hasPf && (
            <div>
              <p className="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-orange-900">
                {t('EPFO PF Challan (month total — {{count}} employees)', { count: pf.employee_count })}
              </p>
              <p className="mb-2 text-[9px] leading-snug text-orange-900/80">
                {t('Use these amounts when filling EPFO challan / ECR. A/C numbers match standard EPFO deposit heads.')}
              </p>
              <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-4">
                <ChallanRow
                  label={t('A/C 1 — Employees PF')}
                  sublabel={t('Employee {{pct}}% + Employer EPF {{epf}}%', {
                    pct: pf.rates.employee_pct,
                    epf: pf.rates.epf_employer_pct,
                  })}
                  amount={pf.challan.ac1_employees_pf}
                />
                <ChallanRow
                  label={t('A/C 2 — Pension (EPS)')}
                  sublabel={t('Employer {{pct}}%', { pct: pf.rates.eps_pct })}
                  amount={pf.challan.ac2_pension_eps}
                />
                <ChallanRow
                  label={t('A/C 10 — Admin charges')}
                  sublabel={t('{{pct}}% on PF wages', { pct: pf.rates.admin_pct })}
                  amount={pf.challan.ac10_admin}
                />
                <ChallanRow
                  label={t('Total PF deposit')}
                  sublabel={t('Sum of A/C 1 + 2 + 10')}
                  amount={pf.challan.total_deposit}
                  highlight
                />
              </div>
              <div className="mt-2 grid gap-1 text-[9px] text-slate-600 sm:grid-cols-3">
                <span>{t('PF wages total')}: <strong className="text-slate-800">₹{formatRupee(pf.wages)}</strong></span>
                <span>{t('Employee share')}: <strong className="text-red-700">₹{formatRupee(pf.employee_contribution)}</strong></span>
                <span>{t('Employer share')}: <strong className="text-slate-800">₹{formatRupee(pf.employer_total)}</strong></span>
              </div>
            </div>
          )}

          {(hasEsi || hasPt) && (
            <div className="flex flex-wrap gap-x-4 gap-y-1 border-t border-orange-100 pt-2 text-[10px] text-slate-700">
              {hasEsi && (
                <span>
                  {t('ESIC challan')}: {t('Emp')} ₹{formatRupee(esi.employee)} + {t('Empr')} ₹{formatRupee(esi.employer)} = <strong>₹{formatRupee(esi.total)}</strong>
                </span>
              )}
              {hasPt && (
                <span>
                  {t('Professional Tax (PT) total')}: <strong>₹{formatRupee(pt.total)}</strong>
                </span>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
