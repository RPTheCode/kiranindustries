import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Building2, Copy, IndianRupee, MapPin, Save } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { hasPermission } from '@/utils/authorization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface ZoneRateRow {
    skill_id: number;
    skill_name: string;
    skill_code: string;
    wage_per_day: number | string | null;
    wage_per_month: number | string | null;
    effective_from: string | null;
}

interface ZoneRatesTabProps {
    wageZones: any[];
    selectedWageZoneId: number | null;
    selectedWageZone: any;
    zoneRates: ZoneRateRow[];
    permissions: string[];
    listQueryParams: (extra?: Record<string, unknown>) => Record<string, unknown>;
    activeBranchName?: string | null;
}

function syncWage(row: ZoneRateRow, field: 'wage_per_day' | 'wage_per_month', value: string, workingDays: number): ZoneRateRow {
    const next = { ...row, [field]: value };
    const day = Number(next.wage_per_day);
    const month = Number(next.wage_per_month);
    const days = Math.max(1, workingDays);

    if (field === 'wage_per_day' && value && day > 0) {
        next.wage_per_month = String(Math.round(day * days * 100) / 100);
    } else if (field === 'wage_per_month' && value && month > 0) {
        next.wage_per_day = String(Math.round((month / days) * 100) / 100);
    }

    return next;
}

export function ZoneRatesTab({
    wageZones,
    selectedWageZoneId,
    selectedWageZone,
    zoneRates,
    permissions,
    listQueryParams,
    activeBranchName,
}: ZoneRatesTabProps) {
    const { t } = useTranslation();
    const canSave = hasPermission(permissions, 'edit-skills');
    const workingDays = selectedWageZone?.working_days ?? 26;

    const [rows, setRows] = useState<ZoneRateRow[]>(zoneRates);
    const [isSaving, setIsSaving] = useState(false);
    const [copyToAllBranches, setCopyToAllBranches] = useState(false);

    useEffect(() => {
        setRows(zoneRates);
    }, [zoneRates, selectedWageZoneId]);

    const zoneOptions = useMemo(
        () => wageZones.filter((z) => z.status).map((z) => ({ value: String(z.id), label: z.display_label || z.name })),
        [wageZones],
    );

    const handleZoneChange = (zoneId: string) => {
        router.get(route('hr.skills.index'), listQueryParams({ tab: 'rates', wage_zone_id: zoneId }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const updateRow = (skillId: number, field: keyof ZoneRateRow, value: string) => {
        setRows((prev) => prev.map((row) => {
            if (row.skill_id !== skillId) return row;
            if (field === 'wage_per_day' || field === 'wage_per_month') {
                return syncWage(row, field, value, workingDays);
            }
            return { ...row, [field]: value };
        }));
    };

    const handleSave = () => {
        if (!selectedWageZoneId) return;
        setIsSaving(true);
        toast.loading(t('Saving wage rates...'));

        router.post(route('hr.skills.wage-rates.save'), {
            wage_zone_id: selectedWageZoneId,
            copy_to_all_branches: copyToAllBranches ? 1 : 0,
            rates: rows.map((row) => ({
                skill_id: row.skill_id,
                wage_per_day: row.wage_per_day || null,
                wage_per_month: row.wage_per_month || null,
                effective_from: row.effective_from || null,
            })),
        }, {
            preserveScroll: true,
            onSuccess: () => { toast.dismiss(); toast.success(t('Wage rates saved.')); },
            onError: (errors) => { toast.dismiss(); toast.error(Object.values(errors).flat().join(', ')); },
            onFinish: () => setIsSaving(false),
        });
    };

    if (wageZones.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                <MapPin className="mx-auto h-8 w-8 text-slate-400" />
                <p className="mt-2 text-sm font-medium text-slate-700">{t('Create a wage zone first')}</p>
                <Button
                    className="mt-4"
                    size="sm"
                    onClick={() => router.get(route('hr.skills.index'), listQueryParams({ tab: 'zones' }))}
                >
                    {t('Go to Wage Zones')}
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-slate-200 bg-gradient-to-r from-blue-50/80 to-white p-4">
                <div className="flex flex-wrap items-end gap-4">
                    <div className="min-w-[220px] flex-1 space-y-1.5">
                        <Label className="text-xs font-semibold text-slate-700">{t('Wage Zone')}</Label>
                        <Select
                            value={selectedWageZoneId ? String(selectedWageZoneId) : undefined}
                            onValueChange={handleZoneChange}
                        >
                            <SelectTrigger className="h-10 bg-white">
                                <SelectValue placeholder={t('Select zone')} />
                            </SelectTrigger>
                            <SelectContent>
                                {zoneOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {activeBranchName && (
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold text-slate-700">{t('Active Branch')}</Label>
                            <Badge variant="outline" className="flex h-10 items-center gap-1.5 px-3 text-sm font-semibold text-slate-800">
                                <Building2 className="h-4 w-4 text-primary" />
                                {activeBranchName}
                            </Badge>
                            <p className="text-[10px] text-slate-500">{t('Rates apply to skill levels of this branch')}</p>
                        </div>
                    )}

                    {selectedWageZone && (
                        <div className="rounded-lg border border-blue-100 bg-white px-3 py-2 text-xs text-slate-600">
                            <span className="font-semibold text-slate-800">{selectedWageZone.name}</span>
                            <span className="mx-1.5 text-slate-300">·</span>
                            {t('{{days}} working days', { days: workingDays })}
                            <span className="mx-1.5 text-slate-300">·</span>
                            {t('Day × {{days}} = Month', { days: workingDays })}
                        </div>
                    )}
                </div>
            </div>

            {!activeBranchName ? (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-6 py-10 text-center text-sm text-amber-800">
                    {t('Please select an active branch from the top header to set wage rates.')}
                </div>
            ) : rows.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                    {t('No active skills found for {{branch}}. Add skills in the "Skill Levels" tab first.', { branch: activeBranchName })}
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">{t('Skill Level')}</th>
                                    <th className="px-4 py-3 text-right font-semibold">{t('Per Day (₹)')}</th>
                                    <th className="px-4 py-3 text-right font-semibold">{t('Per Month (₹)')}</th>
                                    <th className="px-4 py-3 text-left font-semibold">{t('Effective From')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row) => (
                                    <tr key={row.skill_id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-slate-800">{row.skill_name}</p>
                                            <p className="font-mono text-[10px] text-slate-500">{row.skill_code}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={row.wage_per_day ?? ''}
                                                onChange={(e) => updateRow(row.skill_id, 'wage_per_day', e.target.value)}
                                                className="h-9 ml-auto max-w-[120px] text-right tabular-nums"
                                                disabled={!canSave}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={row.wage_per_month ?? ''}
                                                onChange={(e) => updateRow(row.skill_id, 'wage_per_month', e.target.value)}
                                                className="h-9 ml-auto max-w-[128px] text-right tabular-nums"
                                                disabled={!canSave}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Input
                                                type="date"
                                                value={row.effective_from ?? ''}
                                                onChange={(e) => updateRow(row.skill_id, 'effective_from', e.target.value)}
                                                className="h-9 max-w-[160px]"
                                                disabled={!canSave}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {canSave && (
                        <div className="space-y-3 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
                            <label className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2.5 hover:bg-slate-50/80">
                                <Checkbox
                                    checked={copyToAllBranches}
                                    onCheckedChange={(checked) => setCopyToAllBranches(checked === true)}
                                    className="mt-0.5"
                                />
                                <span className="min-w-0">
                                    <span className="flex items-center gap-1.5 text-sm font-medium text-slate-800">
                                        <Copy className="h-3.5 w-3.5 text-primary" />
                                        {t('Copy same rates to all branches')}
                                    </span>
                                    <span className="mt-0.5 block text-[11px] text-slate-500">
                                        {t('Matches skills by code (e.g. USK, SSK, SKL) in every branch for this wage zone.')}
                                    </span>
                                </span>
                            </label>

                            <div className="flex items-center justify-between gap-3">
                                <p className="flex items-center gap-1.5 text-[11px] text-slate-500">
                                    <IndianRupee className="h-3.5 w-3.5" />
                                    {t('Enter per day or per month — the other value calculates automatically.')}
                                </p>
                                <Button onClick={handleSave} disabled={isSaving}>
                                    <Save className="mr-1.5 h-4 w-4" />
                                    {isSaving
                                        ? t('Saving...')
                                        : copyToAllBranches
                                            ? t('Save & Copy to All Branches')
                                            : t('Save All Rates')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
