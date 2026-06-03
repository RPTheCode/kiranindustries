import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { PayrollPreviewEmployee, PayrollScopeFilters } from '@/lib/payroll-scope';
import { Loader2 } from 'lucide-react';

interface Props {
    employees: PayrollPreviewEmployee[];
    counts: {
        eligible: number;
        ready: number;
        mispunch: number;
        no_salary: number;
        no_branch: number;
    };
    scope: PayrollScopeFilters;
    onScopeChange: (scope: PayrollScopeFilters) => void;
    loading?: boolean;
}

const statusClass: Record<string, string> = {
    ready: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    mispunch: 'bg-amber-50 text-amber-700 border-amber-200',
    no_salary: 'bg-red-50 text-red-700 border-red-200',
    no_branch: 'bg-slate-50 text-slate-600 border-slate-200',
};

export function PayrollEmployeePicker({ employees, counts, scope, onScopeChange, loading }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return employees.filter(
            (e) =>
                e.name.toLowerCase().includes(q) ||
                e.code.toLowerCase().includes(q) ||
                e.department.toLowerCase().includes(q)
        );
    }, [employees, search]);

    const readyIds = useMemo(
        () => employees.filter((e) => e.status === 'ready').map((e) => e.id),
        [employees]
    );

    const toggleEmployee = (id: number, checked: boolean) => {
        const set = new Set(scope.selected_employee_ids);
        if (checked) {
            set.add(id);
        } else {
            set.delete(id);
        }
        onScopeChange({
            ...scope,
            employee_mode: 'selected',
            selected_employee_ids: Array.from(set),
        });
    };

    const selectAllReady = () => {
        onScopeChange({
            ...scope,
            employee_mode: 'selected',
            selected_employee_ids: [...readyIds],
        });
    };

    const selectAllMode = () => {
        onScopeChange({
            ...scope,
            employee_mode: 'all',
            selected_employee_ids: [],
        });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider">
                <span className="px-2 py-1 rounded-lg bg-slate-100">{t('Eligible')}: {counts.eligible}</span>
                <span className="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700">{t('Ready')}: {counts.ready}</span>
                <span className="px-2 py-1 rounded-lg bg-amber-50 text-amber-700">{t('MIS')}: {counts.mispunch}</span>
                <span className="px-2 py-1 rounded-lg bg-red-50 text-red-700">{t('No Salary')}: {counts.no_salary}</span>
            </div>

            <div className="flex flex-wrap items-center gap-4">
                <label className="flex items-center gap-2 text-xs cursor-pointer">
                    <input
                        type="radio"
                        checked={scope.employee_mode === 'all'}
                        onChange={selectAllMode}
                    />
                    {t('All matching employees')}
                </label>
                <label className="flex items-center gap-2 text-xs cursor-pointer">
                    <input
                        type="radio"
                        checked={scope.employee_mode === 'selected'}
                        onChange={() =>
                            onScopeChange({ ...scope, employee_mode: 'selected', selected_employee_ids: readyIds })
                        }
                    />
                    {t('Select employees manually')}
                </label>
                {scope.employee_mode === 'selected' && (
                    <button type="button" onClick={selectAllReady} className="text-xs text-primary font-medium">
                        {t('Select all ready')}
                    </button>
                )}
            </div>

            <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t('Search by name, code, department...')}
                className="h-9 text-xs"
            />

            {loading ? (
                <div className="flex items-center justify-center py-12 text-slate-400">
                    <Loader2 className="h-6 w-6 animate-spin mr-2" />
                    {t('Loading employees...')}
                </div>
            ) : filtered.length === 0 ? (
                <p className="text-center text-xs text-slate-400 py-8">{t('No employees match these filters.')}</p>
            ) : (
                <div className="max-h-[320px] overflow-y-auto border rounded-lg divide-y">
                    {filtered.map((emp) => {
                        const canSelect = emp.status === 'ready';
                        const checked =
                            scope.employee_mode === 'all' ||
                            scope.selected_employee_ids.includes(emp.id);

                        return (
                            <div
                                key={emp.id}
                                className="flex items-center gap-3 px-3 py-2 text-xs hover:bg-slate-50"
                            >
                                {scope.employee_mode === 'selected' && (
                                    <Checkbox
                                        checked={checked && canSelect}
                                        disabled={!canSelect}
                                        onCheckedChange={(v) => toggleEmployee(emp.id, !!v)}
                                    />
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="font-semibold truncate">{emp.name}</div>
                                    <div className="text-[10px] text-slate-500">
                                        {emp.code} · {emp.department} · {emp.shift}
                                    </div>
                                </div>
                                <span
                                    className={`px-2 py-0.5 rounded border text-[9px] font-bold uppercase ${statusClass[emp.status] || ''}`}
                                >
                                    {emp.status === 'mispunch' ? 'MIS' : emp.status.replace('_', ' ')}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
