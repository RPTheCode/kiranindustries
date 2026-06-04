import { useEffect, useState } from 'react';
import axios from 'axios';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MultiSelect } from '@/components/ui/multi-select';
import { PayrollScopeFilters as ScopeType } from '@/lib/payroll-scope';
import { useTranslation } from 'react-i18next';
import { Loader2 } from 'lucide-react';

type Option = { id: number; name: string };

interface Props {
    scope: ScopeType;
    onChange: (scope: ScopeType) => void;
    branches: Option[];
    departments: Option[];
    shifts: Option[];
    categories: Option[];
    designations: Option[];
    skills: Option[];
    activeBranchId?: number | null;
}

const toMultiOptions = (items: Option[]) =>
    items.map((item) => ({ value: String(item.id), label: item.name }));

const pruneIds = (selected: number[], allowed: Option[]): number[] => {
    const allowedSet = new Set(allowed.map((o) => o.id));
    return selected.filter((id) => allowedSet.has(id));
};

export function PayrollScopeFilters({
    scope,
    onChange,
    branches,
    departments: initialDepartments,
    shifts: initialShifts,
    categories: initialCategories,
    designations: initialDesignations,
    skills: initialSkills,
    activeBranchId,
}: Props) {
    const { t } = useTranslation();
    const [departments, setDepartments] = useState<Option[]>(initialDepartments);
    const [shifts, setShifts] = useState<Option[]>(initialShifts);
    const [categories, setCategories] = useState<Option[]>(initialCategories);
    const [designations, setDesignations] = useState<Option[]>(initialDesignations);
    const [skills, setSkills] = useState<Option[]>(initialSkills);
    const [loadingOptions, setLoadingOptions] = useState(false);

    const effectiveBranchId = scope.branch_id ?? activeBranchId ?? null;
    const branchLocked = Boolean(activeBranchId);

    useEffect(() => {
        if (!effectiveBranchId) {
            setDepartments(initialDepartments);
            setShifts(initialShifts);
            setCategories(initialCategories);
            setDesignations(initialDesignations);
            setSkills(initialSkills);
            return;
        }

        let cancelled = false;
        setLoadingOptions(true);

        axios
            .get(route('hr.payroll-runs.scope-filter-options'), {
                params: { branch_id: effectiveBranchId },
            })
            .then(({ data }) => {
                if (cancelled) {
                    return;
                }
                const nextDepartments = data.departments || [];
                const nextShifts = data.shifts || [];
                const nextCategories = data.categories || [];
                const nextDesignations = data.designations || [];
                const nextSkills = data.skills || [];

                setDepartments(nextDepartments);
                setShifts(nextShifts);
                setCategories(nextCategories);
                setDesignations(nextDesignations);
                setSkills(nextSkills);

                onChange({
                    ...scope,
                    branch_id: effectiveBranchId,
                    department_ids: pruneIds(scope.department_ids, nextDepartments),
                    shift_ids: pruneIds(scope.shift_ids, nextShifts),
                    category_ids: pruneIds(scope.category_ids, nextCategories),
                    designation_ids: pruneIds(scope.designation_ids, nextDesignations),
                    skill_ids: pruneIds(scope.skill_ids, nextSkills),
                });
            })
            .catch(() => {
                if (!cancelled) {
                    setDepartments([]);
                    setShifts([]);
                    setCategories([]);
                    setDesignations([]);
                    setSkills([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadingOptions(false);
                }
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reload only when branch changes
    }, [effectiveBranchId]);

    const patch = (partial: Partial<ScopeType>) => onChange({ ...scope, ...partial });

    const handleBranchChange = (v: string) => {
        const branch_id = v === 'all' ? null : parseInt(v, 10);
        onChange({
            ...scope,
            branch_id,
            department_ids: [],
            shift_ids: [],
            category_ids: [],
            designation_ids: [],
            skill_ids: [],
        });
    };

    const activeBranchName =
        branches.find((b) => b.id === activeBranchId)?.name ||
        branches.find((b) => b.id === effectiveBranchId)?.name;

    return (
        <div className="space-y-3">
            {effectiveBranchId && (
                <p className="text-[10px] text-slate-500 rounded-md bg-slate-50 border border-slate-200 px-2 py-1.5">
                    {t('Showing departments, shifts, categories, and other filters for')}{' '}
                    <span className="font-semibold text-slate-700">{activeBranchName || t('selected branch')}</span>
                    {loadingOptions && <Loader2 className="inline h-3 w-3 ml-1 animate-spin" />}
                </p>
            )}
            {!effectiveBranchId && (
                <p className="text-[10px] text-amber-700 rounded-md bg-amber-50 border border-amber-200 px-2 py-1.5">
                    {t('Select a branch to load branch-specific filter options.')}
                </p>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                    <Label className="text-xs font-medium">{t('Branch')}</Label>
                    {branchLocked ? (
                        <div className="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-xs font-medium">
                            {activeBranchName || t('Active branch')}
                        </div>
                    ) : (
                        <Select
                            value={scope.branch_id ? String(scope.branch_id) : 'all'}
                            onValueChange={handleBranchChange}
                        >
                            <SelectTrigger className="h-9 text-xs">
                                <SelectValue placeholder={t('All Branches')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All Branches')}</SelectItem>
                                {branches.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>
                                        {b.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <div
                    className={`space-y-1 md:col-span-2 ${!effectiveBranchId || loadingOptions ? 'pointer-events-none opacity-50' : ''}`}
                >
                    <Label className="text-xs font-medium">{t('Department')}</Label>
                    <MultiSelect
                        options={toMultiOptions(departments)}
                        selected={scope.department_ids.map(String)}
                        onChange={(vals) => patch({ department_ids: vals.map((v) => parseInt(v, 10)) })}
                        placeholder={
                            loadingOptions
                                ? t('Loading...')
                                : effectiveBranchId
                                  ? t('All Departments')
                                  : t('Select branch first')
                        }
                    />
                </div>

                <div
                    className={`space-y-1 ${!effectiveBranchId || loadingOptions ? 'pointer-events-none opacity-50' : ''}`}
                >
                    <Label className="text-xs font-medium">{t('Shift')}</Label>
                    <MultiSelect
                        options={toMultiOptions(shifts)}
                        selected={scope.shift_ids.map(String)}
                        onChange={(vals) => patch({ shift_ids: vals.map((v) => parseInt(v, 10)) })}
                        placeholder={loadingOptions ? t('Loading...') : t('All Shifts')}
                    />
                </div>

                <div
                    className={`space-y-1 ${!effectiveBranchId || loadingOptions ? 'pointer-events-none opacity-50' : ''}`}
                >
                    <Label className="text-xs font-medium">{t('Category')}</Label>
                    <MultiSelect
                        options={toMultiOptions(categories)}
                        selected={scope.category_ids.map(String)}
                        onChange={(vals) => patch({ category_ids: vals.map((v) => parseInt(v, 10)) })}
                        placeholder={loadingOptions ? t('Loading...') : t('All Categories')}
                    />
                </div>

                <div
                    className={`space-y-1 ${!effectiveBranchId || loadingOptions ? 'pointer-events-none opacity-50' : ''}`}
                >
                    <Label className="text-xs font-medium">{t('Designation')}</Label>
                    <MultiSelect
                        options={toMultiOptions(designations)}
                        selected={scope.designation_ids.map(String)}
                        onChange={(vals) => patch({ designation_ids: vals.map((v) => parseInt(v, 10)) })}
                        placeholder={loadingOptions ? t('Loading...') : t('All Designations')}
                    />
                </div>

                <div
                    className={`space-y-1 md:col-span-2 ${!effectiveBranchId || loadingOptions ? 'pointer-events-none opacity-50' : ''}`}
                >
                    <Label className="text-xs font-medium">{t('Skill')}</Label>
                    <MultiSelect
                        options={toMultiOptions(skills)}
                        selected={scope.skill_ids.map(String)}
                        onChange={(vals) => patch({ skill_ids: vals.map((v) => parseInt(v, 10)) })}
                        placeholder={loadingOptions ? t('Loading...') : t('All Skills')}
                    />
                </div>
            </div>
        </div>
    );
}
