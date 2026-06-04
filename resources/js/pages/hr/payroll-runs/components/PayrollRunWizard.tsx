import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { Modal } from '@/components/ui/modal';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PayrollScopeFilters } from './PayrollScopeFilters';
import { PayrollEmployeePicker } from './PayrollEmployeePicker';
import {
    buildPreviewParams,
    buildRunPayload,
    daysInPeriod,
    defaultPayrollScope,
    parseYmd,
    PayrollPreviewResponse,
    PayrollScopeFilters as ScopeType,
    periodDatesFromMonthYear,
    scopeFromRun,
} from '@/lib/payroll-scope';
import { AlertTriangle, Calendar, ChevronLeft, ChevronRight, Info, Save } from 'lucide-react';

type Option = { id: number; name: string };

interface PeriodForm {
    month_year: string;
    title: string;
    pay_period_start: string;
    pay_period_end: string;
    pay_date: string;
    salary_calculation_type: string;
    notes: string;
}

type OverlapRun = {
    id: number;
    title: string;
    pay_period_start: string;
    pay_period_end: string;
    status: string;
    period_days: number;
    scope_summary: string;
};

interface Props {
    isOpen: boolean;
    onClose: () => void;
    mode: 'create' | 'edit';
    initialRun?: any;
    activeBranchId?: number | null;
    branches: Option[];
    departments: Option[];
    shifts: Option[];
    categories: Option[];
    designations: Option[];
    skills: Option[];
    onSubmit: (payload: Record<string, unknown>) => void;
}

const financialYearLabel = (endYmd: string): string => {
    if (!endYmd) {
        return '';
    }
    const d = parseYmd(endYmd);
    const y = d.getFullYear();
    const startYear = d.getMonth() >= 3 ? y : y - 1;
    return `${startYear}-${startYear + 1}`;
};

const formatPeriodDisplay = (ymd: string): string => {
    const d = parseYmd(ymd);
    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
};

export function PayrollRunWizard({
    isOpen,
    onClose,
    mode,
    initialRun,
    activeBranchId,
    branches,
    departments,
    shifts,
    categories,
    designations,
    skills,
    onSubmit,
}: Props) {
    const { t } = useTranslation();
    const [step, setStep] = useState(1);

    const buildPeriodDefaults = useCallback((): PeriodForm => {
        const now = new Date();
        const defaultMonthYear = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

        if (mode === 'edit' && initialRun) {
            const startStr =
                initialRun.pay_period_start?.split?.('T')?.[0] || initialRun.pay_period_start || '';
            const d = startStr ? parseYmd(startStr) : now;
            return {
                month_year: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
                title: initialRun.title || '',
                pay_period_start: startStr,
                pay_period_end:
                    initialRun.pay_period_end?.split?.('T')?.[0] || initialRun.pay_period_end || '',
                pay_date: initialRun.pay_date?.split?.('T')?.[0] || initialRun.pay_date || '',
                salary_calculation_type: initialRun.salary_calculation_type || 'basic_pay',
                notes: initialRun.notes || '',
            };
        }

        const defaults = periodDatesFromMonthYear(defaultMonthYear);
        return {
            month_year: defaultMonthYear,
            title: defaults.title,
            pay_period_start: defaults.pay_period_start,
            pay_period_end: defaults.pay_period_end,
            pay_date: defaults.pay_date,
            salary_calculation_type: 'basic_pay',
            notes: '',
        };
    }, [mode, initialRun]);

    const [period, setPeriod] = useState<PeriodForm>(buildPeriodDefaults);
    const [scope, setScope] = useState<ScopeType>(defaultPayrollScope(activeBranchId));
    const [preview, setPreview] = useState<PayrollPreviewResponse | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [overlapping, setOverlapping] = useState<OverlapRun[]>([]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }
        setStep(1);
        setPeriod(buildPeriodDefaults());
        const nextScope = scopeFromRun(initialRun || {}, activeBranchId);
        if (activeBranchId && !nextScope.branch_id) {
            nextScope.branch_id = activeBranchId;
        }
        setScope(nextScope);
        setPreview(null);
        setOverlapping([]);
    }, [isOpen, initialRun, activeBranchId, buildPeriodDefaults]);

    const monthOptions = () => {
        const options = [];
        const now = new Date();
        for (let i = 0; i < 12; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
            options.push({ value, label });
        }
        return options;
    };

    const onMonthChange = (value: string) => {
        const defaults = periodDatesFromMonthYear(value);
        setPeriod((p) => ({
            ...p,
            month_year: value,
            title: defaults.title,
            pay_period_start: defaults.pay_period_start,
            pay_period_end: defaults.pay_period_end,
            pay_date: defaults.pay_date,
        }));
    };

    const loadPreview = async () => {
        setPreviewLoading(true);
        try {
            const { data } = await axios.get(route('hr.payroll-runs.preview-employees'), {
                params: buildPreviewParams(
                    {
                        pay_period_start: period.pay_period_start,
                        pay_period_end: period.pay_period_end,
                        salary_calculation_type: period.salary_calculation_type,
                    },
                    scope
                ),
            });
            setPreview(data);
            if (scope.employee_mode === 'selected' && scope.selected_employee_ids.length === 0) {
                setScope((s) => ({
                    ...s,
                    selected_employee_ids: data.valid_employee_ids || [],
                }));
            }
        } catch {
            setPreview(null);
        } finally {
            setPreviewLoading(false);
        }
    };

    const loadOverlapping = async () => {
        try {
            const branchId = scope.branch_id ?? activeBranchId ?? undefined;
            const { data } = await axios.get(route('hr.payroll-runs.check-overlapping'), {
                params: {
                    pay_period_start: period.pay_period_start,
                    pay_period_end: period.pay_period_end,
                    branch_id: branchId,
                    exclude_run_id: mode === 'edit' && initialRun?.id ? initialRun.id : undefined,
                },
            });
            setOverlapping(data.overlapping || []);
        } catch {
            setOverlapping([]);
        }
    };

    const goNext = async () => {
        if (step === 1) {
            setStep(2);
            return;
        }
        if (step === 2) {
            setStep(3);
            await Promise.all([loadPreview(), loadOverlapping()]);
        }
    };

    const handleSave = () => {
        const payload = buildRunPayload(
            {
                ...period,
                ...(mode === 'edit' && initialRun ? { id: initialRun.id } : {}),
            },
            scope.employee_mode === 'selected'
                ? scope
                : { ...scope, employee_mode: 'all', selected_employee_ids: [] }
        );
        onSubmit(payload);
    };

    const periodDays = period.pay_period_start && period.pay_period_end
        ? daysInPeriod(period.pay_period_start, period.pay_period_end)
        : 0;
    const fyLabel = financialYearLabel(period.pay_period_end);
    const isFullCalendarMonth =
        period.month_year &&
        period.pay_period_start === periodDatesFromMonthYear(period.month_year).pay_period_start &&
        period.pay_period_end === periodDatesFromMonthYear(period.month_year).pay_period_end;

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="xl" title="">
            <div className="p-2">
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h2 className="text-lg font-bold">
                            {mode === 'create' ? t('Create Payroll Run') : t('Edit Payroll Run')}
                        </h2>
                        <p className="text-[10px] text-slate-500 uppercase tracking-wider">
                            {t('Step')} {step} / 3
                            {step === 1 && ` — ${t('Pay Period')}`}
                            {step === 2 && ` — ${t('Who is included?')}`}
                            {step === 3 && ` — ${t('Review Employees')}`}
                        </p>
                    </div>
                    <div className="flex gap-1">
                        {[1, 2, 3].map((s) => (
                            <div
                                key={s}
                                className={`h-2 w-8 rounded-full ${s <= step ? 'bg-primary' : 'bg-slate-200'}`}
                            />
                        ))}
                    </div>
                </div>

                {step === 1 && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Select Month')}</Label>
                                <Select value={period.month_year} onValueChange={onMonthChange}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monthOptions().map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-[10px] text-slate-500">
                                    {t('Sets pay period to the 1st through last day of the selected month.')}
                                </p>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Title')}</Label>
                                <Input
                                    className="h-9 text-xs"
                                    value={period.title}
                                    onChange={(e) => setPeriod((p) => ({ ...p, title: e.target.value }))}
                                />
                            </div>
                        </div>

                        {period.pay_period_start && period.pay_period_end && (
                            <div
                                className={`rounded-lg border px-3 py-2 text-xs ${
                                    isFullCalendarMonth
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                                        : 'border-amber-200 bg-amber-50 text-amber-900'
                                }`}
                            >
                                <div className="flex items-center gap-2 font-semibold">
                                    <Calendar className="h-3.5 w-3.5 shrink-0" />
                                    {formatPeriodDisplay(period.pay_period_start)} —{' '}
                                    {formatPeriodDisplay(period.pay_period_end)}
                                    <span className="font-normal text-slate-600">
                                        ({periodDays} {t('days')})
                                    </span>
                                </div>
                                {!isFullCalendarMonth && (
                                    <p className="mt-1 text-[10px]">
                                        {t('Custom date range — not a full calendar month.')}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Pay Period Start')}</Label>
                                <Input
                                    type="date"
                                    className="h-9 text-xs"
                                    value={period.pay_period_start}
                                    onChange={(e) =>
                                        setPeriod((p) => ({ ...p, pay_period_start: e.target.value }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Pay Period End')}</Label>
                                <Input
                                    type="date"
                                    className="h-9 text-xs"
                                    value={period.pay_period_end}
                                    onChange={(e) =>
                                        setPeriod((p) => ({ ...p, pay_period_end: e.target.value }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Pay Date')}</Label>
                                <Input
                                    type="date"
                                    className="h-9 text-xs"
                                    value={period.pay_date}
                                    onChange={(e) => setPeriod((p) => ({ ...p, pay_date: e.target.value }))}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Salary Basis')}</Label>
                                <Select
                                    value={period.salary_calculation_type}
                                    onValueChange={(v) =>
                                        setPeriod((p) => ({ ...p, salary_calculation_type: v }))
                                    }
                                >
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="basic_pay">{t('Basic Pay')}</SelectItem>
                                        <SelectItem value="minimum_wages">{t('Minimum Wages')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {fyLabel && (
                                <div className="flex items-end">
                                    <p className="text-[10px] text-slate-500 flex items-center gap-1 pb-2">
                                        <Calendar className="h-3 w-3" />
                                        {t('Financial Year')}: {fyLabel}
                                    </p>
                                </div>
                            )}
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Notes')}</Label>
                            <Input
                                className="h-9 text-xs"
                                value={period.notes}
                                onChange={(e) => setPeriod((p) => ({ ...p, notes: e.target.value }))}
                            />
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <PayrollScopeFilters
                        scope={scope}
                        onChange={setScope}
                        branches={branches}
                        departments={departments}
                        shifts={shifts}
                        categories={categories}
                        designations={designations}
                        skills={skills}
                        activeBranchId={activeBranchId}
                    />
                )}

                {step === 3 && (
                    <div className="space-y-3">
                        {overlapping.length > 0 && (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950">
                                <div className="flex items-start gap-2 font-semibold">
                                    <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                                    {t('Overlapping payroll runs for this period')}
                                </div>
                                <ul className="mt-2 space-y-1.5 list-disc list-inside">
                                    {overlapping.map((run) => (
                                        <li key={run.id}>
                                            <span className="font-medium">{run.title}</span>
                                            {' — '}
                                            {formatPeriodDisplay(run.pay_period_start)} to{' '}
                                            {formatPeriodDisplay(run.pay_period_end)} ({run.period_days}{' '}
                                            {t('days')}, {run.scope_summary})
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-2 flex items-start gap-1.5 text-[10px]">
                                    <Info className="h-3 w-3 shrink-0 mt-0.5" />
                                    {t(
                                        'Each payroll run creates separate payslips. If the same employee is processed in two overlapping runs, they will have two payslips for that month.'
                                    )}
                                </p>
                            </div>
                        )}
                        <PayrollEmployeePicker
                            employees={preview?.employees || []}
                            counts={
                                preview?.counts || {
                                    eligible: 0,
                                    ready: 0,
                                    mispunch: 0,
                                    no_salary: 0,
                                    no_branch: 0,
                                }
                            }
                            scope={scope}
                            onScopeChange={setScope}
                            loading={previewLoading}
                        />
                    </div>
                )}

                <div className="flex justify-between mt-6 pt-4 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => (step > 1 ? setStep(step - 1) : onClose())}
                    >
                        <ChevronLeft className="h-4 w-4 mr-1" />
                        {step > 1 ? t('Back') : t('Cancel')}
                    </Button>
                    {step < 3 ? (
                        <Button type="button" size="sm" onClick={goNext}>
                            {t('Next')}
                            <ChevronRight className="h-4 w-4 ml-1" />
                        </Button>
                    ) : (
                        <Button type="button" size="sm" onClick={handleSave} disabled={previewLoading}>
                            <Save className="h-4 w-4 mr-1" />
                            {t('Save Draft')}
                        </Button>
                    )}
                </div>
            </div>
        </Modal>
    );
}
