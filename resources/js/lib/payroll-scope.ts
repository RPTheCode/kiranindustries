export type PayrollEmployeeMode = 'all' | 'selected';

export type PayrollEmployeeStatus = 'ready' | 'mispunch' | 'no_salary' | 'no_branch';

export interface PayrollScopeFilters {
    branch_id: number | null;
    department_ids: number[];
    shift_ids: number[];
    category_ids: number[];
    designation_ids: number[];
    skill_ids: number[];
    employee_mode: PayrollEmployeeMode;
    selected_employee_ids: number[];
}

export interface PayrollPreviewEmployee {
    id: number;
    code: string;
    name: string;
    department: string;
    shift: string;
    status: PayrollEmployeeStatus;
}

export interface PayrollPreviewResponse {
    counts: {
        eligible: number;
        ready: number;
        mispunch: number;
        no_salary: number;
        no_branch: number;
        selected: number;
    };
    employees: PayrollPreviewEmployee[];
    ready_employees: PayrollPreviewEmployee[];
    mispunch_employees: PayrollPreviewEmployee[];
    skipped_employees: PayrollPreviewEmployee[];
    valid_employee_ids: number[];
}

export const defaultPayrollScope = (branchId?: number | null): PayrollScopeFilters => ({
    branch_id: branchId ?? null,
    department_ids: [],
    shift_ids: [],
    category_ids: [],
    designation_ids: [],
    skill_ids: [],
    employee_mode: 'all',
    selected_employee_ids: [],
});

export const buildPreviewParams = (
    period: { pay_period_start: string; pay_period_end: string; salary_calculation_type: string },
    scope: PayrollScopeFilters
) => ({
    pay_period_start: period.pay_period_start,
    pay_period_end: period.pay_period_end,
    salary_calculation_type: period.salary_calculation_type,
    scope_filters: scope,
    branch_id: scope.branch_id,
    department_ids: scope.department_ids,
    shift_ids: scope.shift_ids,
    category_ids: scope.category_ids,
    designation_ids: scope.designation_ids,
    skill_ids: scope.skill_ids,
    employee_mode: scope.employee_mode,
    selected_employee_ids: scope.selected_employee_ids,
});

export const scopeFromRun = (item: any, activeBranchId?: number | null): PayrollScopeFilters => {
    if (item?.scope_filters && typeof item.scope_filters === 'object') {
        return {
            ...defaultPayrollScope(activeBranchId),
            ...item.scope_filters,
            branch_id: item.scope_filters.branch_id ?? item.branch_id ?? activeBranchId ?? null,
            department_ids: item.scope_filters.department_ids?.length
                ? item.scope_filters.department_ids
                : item.department_id
                  ? [item.department_id]
                  : [],
        };
    }

    return {
        ...defaultPayrollScope(item?.branch_id ?? activeBranchId ?? null),
        department_ids: item?.department_id ? [item.department_id] : [],
    };
};

/** Local calendar date as YYYY-MM-DD (avoids UTC shift from toISOString). */
export const formatLocalDate = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

export const parseYmd = (ymd: string): Date => {
    const [y, m, d] = ymd.split('T')[0].split('-').map((n) => parseInt(n, 10));
    return new Date(y, m - 1, d);
};

/** Full calendar month: 1st to last day; pay date = 7th of next month. */
export const periodDatesFromMonthYear = (monthYear: string) => {
    const [year, month] = monthYear.split('-').map((n) => parseInt(n, 10));
    const startDate = new Date(year, month - 1, 1);
    const endDate = new Date(year, month, 0);
    const payDate = new Date(year, month, 6);
    const monthName = startDate.toLocaleString('default', { month: 'long' });

    return {
        pay_period_start: formatLocalDate(startDate),
        pay_period_end: formatLocalDate(endDate),
        pay_date: formatLocalDate(payDate),
        title: `Payroll - ${monthName} ${year}`,
        monthName,
        year: String(year),
    };
};

export const daysInPeriod = (start: string, end: string): number => {
    const s = parseYmd(start);
    const e = parseYmd(end);
    return Math.round((e.getTime() - s.getTime()) / (1000 * 60 * 60 * 24)) + 1;
};

export const buildRunPayload = (form: any, scope: PayrollScopeFilters) => {
    const payload = { ...form };
    delete payload.month_year;
    payload.scope_filters = scope;
    payload.branch_id = scope.branch_id;
    payload.department_id = scope.department_ids[0] ?? null;
    payload.payroll_frequency = 'monthly';
    return payload;
};
