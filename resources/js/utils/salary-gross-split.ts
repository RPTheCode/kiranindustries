import { resolveComponentsForEmployee } from '@/utils/salary-component-assignment';

export type GrossInputMode = 'day' | 'month';

export interface SalaryBreakdownRow {
  id: number;
  name: string;
  type: 'earning' | 'deduction';
  calculation_type: string;
  rate: number;
  base: string;
  base_amount: number;
  amount: number;
}

function isPfComponent(comp: { name?: string; type?: string }): boolean {
  const name = String(comp.name || '').toUpperCase().trim();
  if (['PF', 'PF DEDUCTION', 'PROVIDENT FUND', 'EPF'].includes(name)) return true;
  return comp.type === 'deduction' && name.includes('PF');
}

function isEsiComponent(comp: { name?: string; type?: string }): boolean {
  const name = String(comp.name || '').toUpperCase().trim();
  if (['ESI', 'ESIC', 'ESI DEDUCTION'].includes(name)) return true;
  return comp.type === 'deduction' && (name.includes('ESI') || name.includes('ESIC'));
}

function calcComponentAmount(comp: any, basic: number, gross: number): number {
  let amount = 0;
  if (comp.calculation_type === 'percentage_of_gross') {
    amount = (gross * Number(comp.percentage_of_gross_pay || 0)) / 100;
  } else if (comp.calculation_type === 'percentage') {
    amount = (basic * Number(comp.percentage_of_basic || 0)) / 100;
  }
  switch (comp.rounding_method) {
    case 'round':
      return Math.round(amount);
    case 'ceil':
      return Math.ceil(amount);
    case 'floor':
      return Math.floor(amount);
    default:
      return Math.round(amount * 100) / 100;
  }
}

export function resolveMonthlyGross(
  amount: number,
  mode: GrossInputMode,
  workingDays: number,
): number {
  if (!amount || amount <= 0) return 0;
  if (mode === 'day') {
    return Math.round(amount * Math.max(1, workingDays) * 100) / 100;
  }
  return Math.round(amount * 100) / 100;
}

/** Gross base used for percentage split — always the entered amount (per day or per month). */
export function resolveGrossForSplit(amount: number, mode: GrossInputMode): number {
  if (!amount || amount <= 0) return 0;
  return amount;
}

export function grossDisplayAfterModeToggle(
  currentInput: string,
  fromMode: GrossInputMode,
  toMode: GrossInputMode,
  workingDays: number,
): string {
  const amount = Number(currentInput);
  if (!amount || amount <= 0) return currentInput;
  const monthly = resolveMonthlyGross(amount, fromMode, workingDays);
  if (toMode === 'day') {
    return String(Math.round((monthly / Math.max(1, workingDays)) * 100) / 100);
  }
  return String(Math.round(monthly));
}

export function splitGrossFromComponents(
  gross: number,
  components: any[],
  opts: { applyPf?: boolean; applyEsi?: boolean } = {},
) {
  const applyPf = opts.applyPf ?? true;
  const applyEsi = opts.applyEsi ?? true;
  const active = components.filter((c) => !c.status || c.status === 'active');

  const breakdown: SalaryBreakdownRow[] = [];
  const amounts: Record<number, number> = {};
  let basicAmount = 0;

  const eligible = active.filter((comp) => {
    if (!applyPf && isPfComponent(comp)) return false;
    if (!applyEsi && isEsiComponent(comp)) return false;
    return true;
  });

  const grossComps = eligible.filter((c) => c.calculation_type === 'percentage_of_gross');
  for (const comp of grossComps) {
    const amount = calcComponentAmount(comp, 0, gross);
    amounts[comp.id] = amount;
    breakdown.push({
      id: comp.id,
      name: comp.name,
      type: comp.type,
      calculation_type: comp.calculation_type,
      rate: Number(comp.percentage_of_gross_pay || 0),
      base: 'gross',
      base_amount: gross,
      amount,
    });
    if (comp.name?.toUpperCase() === 'BASIC') {
      basicAmount = amount;
    }
  }

  if (basicAmount <= 0) {
    const basicComp = grossComps.find((c) => c.name?.toUpperCase() === 'BASIC')
      ?? grossComps.find((c) => c.type === 'earning');
    basicAmount = basicComp ? amounts[basicComp.id] : gross;
  }

  const basicComps = eligible.filter((c) => c.calculation_type === 'percentage');
  for (const comp of basicComps) {
    const amount = calcComponentAmount(comp, basicAmount, gross);
    amounts[comp.id] = amount;
    breakdown.push({
      id: comp.id,
      name: comp.name,
      type: comp.type,
      calculation_type: comp.calculation_type,
      rate: Number(comp.percentage_of_basic || 0),
      base: 'basic',
      base_amount: basicAmount,
      amount,
    });
  }

  const totalEarnings = breakdown.filter((r) => r.type === 'earning').reduce((s, r) => s + r.amount, 0);
  const totalDeductions = breakdown.filter((r) => r.type === 'deduction').reduce((s, r) => s + r.amount, 0);

  return {
    breakdown,
    amounts,
    totalEarnings,
    totalDeductions,
    netSalary: totalEarnings - totalDeductions,
    basicAmount,
  };
}

export function buildSalaryFormPayload(
  grossAmount: string,
  mode: GrossInputMode,
  workingDays: number,
  salaryComponents: any[],
  extraComponentIds: number[],
  opts: { applyPf?: boolean; applyEsi?: boolean } = {},
) {
  const inputAmount = Number(grossAmount);
  const monthlyGross = resolveMonthlyGross(inputAmount, mode, workingDays);
  if (monthlyGross <= 0 || inputAmount <= 0) {
    return {
      gross_salary: '',
      basic_salary: '',
      salary_components: {} as Record<string, string>,
      monthlyGross: 0,
      split: null,
      splitBase: 0,
    };
  }

  const splitBase = resolveGrossForSplit(inputAmount, mode);
  const employeeComponents = resolveComponentsForEmployee(salaryComponents, extraComponentIds);
  const split = splitGrossFromComponents(splitBase, employeeComponents, opts);

  const storageMultiplier = mode === 'day' ? Math.max(1, workingDays) : 1;
  const salaryComponentsMap: Record<string, string> = {};
  Object.entries(split.amounts).forEach(([id, amt]) => {
    const stored = Number(amt) * storageMultiplier;
    if (stored > 0) {
      salaryComponentsMap[id] = String(Math.round(stored));
    }
  });

  return {
    gross_salary: String(Math.round(monthlyGross)),
    basic_salary: String(Math.round(split.basicAmount * storageMultiplier)),
    salary_components: salaryComponentsMap,
    monthlyGross,
    split,
    splitBase,
  };
}

export function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
