import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Plus, MapPin, Pencil, Trash2 } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { hasPermission } from '@/utils/authorization';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface WageZonesTabProps {
    wageZones: any[];
    permissions: string[];
    listQueryParams: (extra?: Record<string, unknown>) => Record<string, unknown>;
}

export function WageZonesTab({ wageZones, permissions, listQueryParams }: WageZonesTabProps) {
    const { t } = useTranslation();
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [currentZone, setCurrentZone] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

    const canCreate = hasPermission(permissions, 'create-skills');
    const canEdit = hasPermission(permissions, 'edit-skills');
    const canDelete = hasPermission(permissions, 'delete-skills');

    const openCreate = () => {
        setCurrentZone(null);
        setFormMode('create');
        setIsFormOpen(true);
    };

    const openEdit = (zone: any) => {
        setCurrentZone(zone);
        setFormMode('edit');
        setIsFormOpen(true);
    };

    const handleSubmit = (formData: any) => {
        const payload = {
            ...formData,
            status: formData.status === 'active' || formData.status === true ? 1 : 0,
            working_days: Number(formData.working_days || 26),
        };

        if (formMode === 'create') {
            toast.loading(t('Creating wage zone...'));
            router.post(route('hr.wage-zones.store'), payload, {
                onSuccess: () => { setIsFormOpen(false); toast.dismiss(); toast.success(t('Wage zone created.')); },
                onError: (errors) => { toast.dismiss(); toast.error(Object.values(errors).flat().join(', ')); },
            });
            return;
        }

        toast.loading(t('Updating wage zone...'));
        router.put(route('hr.wage-zones.update', currentZone.id), payload, {
            onSuccess: () => { setIsFormOpen(false); toast.dismiss(); toast.success(t('Wage zone updated.')); },
            onError: (errors) => { toast.dismiss(); toast.error(Object.values(errors).flat().join(', ')); },
        });
    };

    const handleDelete = () => {
        toast.loading(t('Deleting wage zone...'));
        router.delete(route('hr.wage-zones.destroy', currentZone.id), {
            onSuccess: () => { setIsDeleteOpen(false); toast.dismiss(); toast.success(t('Wage zone deleted.')); },
            onError: (errors) => { toast.dismiss(); toast.error(typeof errors === 'string' ? errors : t('Could not delete zone.')); },
        });
    };

    const toggleStatus = (zone: any) => {
        router.put(route('hr.wage-zones.toggle-status', zone.id), {}, { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 to-white p-4">
                <div>
                    <h3 className="text-sm font-semibold text-slate-800">{t('Wage Zones')}</h3>
                    <p className="mt-1 max-w-xl text-xs text-slate-500">
                        {t('Create zones for any state or region — e.g. Gujarat · Surat, Maharashtra · Mumbai. Each zone can have different minimum wage rates.')}
                    </p>
                </div>
                {canCreate && (
                    <Button size="sm" onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        {t('Add Wage Zone')}
                    </Button>
                )}
            </div>

            {wageZones.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                    <MapPin className="mx-auto h-8 w-8 text-slate-400" />
                    <p className="mt-2 text-sm font-medium text-slate-700">{t('No wage zones yet')}</p>
                    <p className="mt-1 text-xs text-slate-500">{t('Add your first zone, then set rates in the "Set Rates" tab.')}</p>
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {wageZones.map((zone) => (
                        <div
                            key={zone.id}
                            className={cn(
                                'rounded-xl border bg-white p-4 shadow-sm transition-shadow hover:shadow-md',
                                zone.status ? 'border-slate-200' : 'border-slate-200 opacity-75',
                            )}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-slate-900">{zone.name}</p>
                                    <p className="mt-0.5 text-[11px] text-slate-500">{zone.display_label}</p>
                                </div>
                                <Badge variant={zone.status ? 'default' : 'secondary'} className="shrink-0 text-[10px]">
                                    {zone.status ? t('Active') : t('Inactive')}
                                </Badge>
                            </div>

                            <div className="mt-3 flex flex-wrap gap-2 text-[11px] text-slate-600">
                                <span className="rounded-md bg-slate-100 px-2 py-0.5 font-mono">{zone.code}</span>
                                <span className="rounded-md bg-blue-50 px-2 py-0.5 text-blue-700">
                                    {zone.working_days} {t('days/month')}
                                </span>
                                {zone.branches_count > 0 && (
                                    <span className="rounded-md bg-amber-50 px-2 py-0.5 text-amber-700">
                                        {zone.branches_count} {t('branches')}
                                    </span>
                                )}
                            </div>

                            {zone.notes && (
                                <p className="mt-2 line-clamp-2 text-[11px] text-slate-500">{zone.notes}</p>
                            )}

                            <div className="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                                {canEdit && (
                                    <>
                                        <Button variant="outline" size="sm" className="h-7 text-xs" onClick={() => openEdit(zone)}>
                                            <Pencil className="mr-1 h-3 w-3" />
                                            {t('Edit')}
                                        </Button>
                                        <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => toggleStatus(zone)}>
                                            {zone.status ? t('Deactivate') : t('Activate')}
                                        </Button>
                                    </>
                                )}
                                {canDelete && zone.branches_count === 0 && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 text-xs text-destructive hover:text-destructive"
                                        onClick={() => { setCurrentZone(zone); setIsDeleteOpen(true); }}
                                    >
                                        <Trash2 className="mr-1 h-3 w-3" />
                                        {t('Delete')}
                                    </Button>
                                )}
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-7 text-xs ml-auto"
                                    onClick={() => router.get(route('hr.skills.index'), listQueryParams({ tab: 'rates', wage_zone_id: zone.id }))}
                                >
                                    {t('Set Rates')}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <CrudFormModal
                isOpen={isFormOpen}
                onClose={() => setIsFormOpen(false)}
                onSubmit={handleSubmit}
                formConfig={{
                    modalSize: 'md',
                    fields: [
                        { name: 'name', label: t('Zone Name'), type: 'text', required: true, placeholder: t('e.g. Surat Industrial Zone') },
                        { name: 'code', label: t('Short Code'), type: 'text', required: true, placeholder: t('e.g. SURAT') },
                        { name: 'state', label: t('State / Province'), type: 'text', placeholder: t('e.g. Gujarat') },
                        { name: 'region', label: t('City / District'), type: 'text', placeholder: t('e.g. Surat') },
                        { name: 'country', label: t('Country'), type: 'text', placeholder: t('India'), defaultValue: 'India' },
                        { name: 'working_days', label: t('Working Days per Month'), type: 'number', required: true, min: 1, max: 31, defaultValue: 26, step: 1 },
                        { name: 'notes', label: t('Notes'), type: 'textarea', placeholder: t('Govt notification reference, zone details...') },
                        {
                            name: 'status',
                            label: t('Status'),
                            type: 'select',
                            options: [
                                { value: 'active', label: t('Active') },
                                { value: 'inactive', label: t('Inactive') },
                            ],
                            defaultValue: 'active',
                        },
                    ],
                }}
                initialData={currentZone ? {
                    ...currentZone,
                    status: currentZone.status ? 'active' : 'inactive',
                    working_days: currentZone.working_days ?? 26,
                    country: currentZone.country || 'India',
                } : { status: 'active', working_days: 26, country: 'India' }}
                title={formMode === 'create' ? t('Add Wage Zone') : t('Edit Wage Zone')}
                mode={formMode}
                description={t('Zones are reusable across branches. Rates are set separately per skill level.')}
            />

            <CrudDeleteModal
                isOpen={isDeleteOpen}
                onClose={() => setIsDeleteOpen(false)}
                onConfirm={handleDelete}
                itemName={currentZone?.name || ''}
                entityName="wage zone"
            />
        </div>
    );
}
