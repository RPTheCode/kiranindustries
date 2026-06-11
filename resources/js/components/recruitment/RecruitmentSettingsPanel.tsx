import { RecruitmentEmptyState } from '@/components/recruitment/RecruitmentEmptyState';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Link, router } from '@inertiajs/react';
import { ExternalLink, Loader2, MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

type MasterRow = { id: number; name: string; status?: string; description?: string };

export function SettingsMasterPanel({
    title,
    description,
    icon: Icon,
    items,
    storeRoute,
    updateRoute,
    destroyRoute,
    canCreate,
    canEdit,
    canDelete,
    reloadKey,
    placeholder,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    items: MasterRow[];
    storeRoute: string;
    updateRoute: (id: number) => string;
    destroyRoute: (id: number) => string;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    reloadKey: string;
    placeholder?: string;
}) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState('');
    const [saving, setSaving] = useState(false);

    const reload = () => router.reload({ only: [reloadKey] });

    const add = () => {
        const trimmed = name.trim();
        if (!trimmed) return;
        setSaving(true);
        router.post(
            storeRoute,
            { name: trimmed, status: 'active' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    toast.success(t('Added successfully'));
                    reload();
                },
                onFinish: () => setSaving(false),
            }
        );
    };

    const save = (id: number) => {
        const trimmed = editName.trim();
        if (!trimmed) return;
        setSaving(true);
        router.put(
            updateRoute(id),
            { name: trimmed, status: 'active' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    toast.success(t('Updated successfully'));
                    reload();
                },
                onFinish: () => setSaving(false),
            }
        );
    };

    const remove = (id: number) => {
        if (!confirm(t('Delete this item?'))) return;
        router.delete(destroyRoute(id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('Deleted'));
                reload();
            },
        });
    };

    return (
        <div className="space-y-5">
            <div className="flex items-start gap-3 rounded-xl border border-slate-200/80 bg-gradient-to-r from-slate-50 to-white p-4 dark:border-slate-800 dark:from-slate-900/50 dark:to-slate-950">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100">{title}</h3>
                        <span className="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            {items.length} {t('items')}
                        </span>
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">{description}</p>
                </div>
            </div>

            {canCreate ? (
                <div className="flex gap-2 rounded-xl border border-dashed border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">
                    <Input
                        placeholder={placeholder ?? t('Enter name')}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), add())}
                        className="h-10 flex-1 bg-white dark:bg-slate-950"
                    />
                    <Button type="button" className="h-10 shrink-0 px-4" onClick={add} disabled={saving || !name.trim()}>
                        {saving ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Plus className="mr-1.5 h-4 w-4" />}
                        {t('Add')}
                    </Button>
                </div>
            ) : null}

            {items.length === 0 ? (
                <RecruitmentEmptyState
                    icon={Icon}
                    title={t('No items yet')}
                    description={canCreate ? t('Add your first item using the field above.') : t('No data available.')}
                />
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="group flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white px-4 py-3 transition-shadow hover:shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            {editingId === item.id ? (
                                <div className="flex flex-1 flex-wrap gap-2">
                                    <Input
                                        value={editName}
                                        onChange={(e) => setEditName(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), save(item.id))}
                                        className="h-9 min-w-[140px] flex-1"
                                        autoFocus
                                    />
                                    <Button type="button" size="sm" className="h-9" onClick={() => save(item.id)} disabled={saving}>
                                        {t('Save')}
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" className="h-9" onClick={() => setEditingId(null)}>
                                        {t('Cancel')}
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{item.name}</p>
                                        {item.description ? (
                                            <p className="mt-0.5 truncate text-[11px] text-slate-400">{item.description}</p>
                                        ) : null}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        {item.status ? <StatusBadge status={item.status} /> : null}
                                        {canEdit ? (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-8 w-8 opacity-60 group-hover:opacity-100"
                                                onClick={() => {
                                                    setEditingId(item.id);
                                                    setEditName(item.name);
                                                }}
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                        ) : null}
                                        {canDelete ? (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="h-8 w-8 text-rose-600 opacity-60 hover:text-rose-700 group-hover:opacity-100"
                                                onClick={() => remove(item.id)}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        ) : null}
                                    </div>
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

type BranchRow = { id: number; name: string; city?: string; address?: string; status?: string };

export function SettingsBranchPanel({
    branches,
    manageUrl,
    canManage,
}: {
    branches: BranchRow[];
    manageUrl: string;
    canManage: boolean;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-5">
            <div className="flex items-start gap-3 rounded-xl border border-slate-200/80 bg-gradient-to-r from-slate-50 to-white p-4 dark:border-slate-800 dark:from-slate-900/50 dark:to-slate-950">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100">{t('Company Branches')}</h3>
                        <span className="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            {branches.length} {t('branches')}
                        </span>
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {t('Job postings use your company branches. Add or edit branches from the Branches master.')}
                    </p>
                </div>
                {canManage ? (
                    <Button type="button" variant="outline" size="sm" className="shrink-0" asChild>
                        <Link href={manageUrl}>
                            <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                            {t('Manage Branches')}
                        </Link>
                    </Button>
                ) : null}
            </div>

            {branches.length === 0 ? (
                <RecruitmentEmptyState
                    icon={MapPin}
                    title={t('No branches found')}
                    description={t('Create branches in Administration → Branches first.')}
                    actionLabel={canManage ? t('Go to Branches') : undefined}
                    onAction={canManage ? () => router.visit(manageUrl) : undefined}
                />
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2">
                    {branches.map((branch) => (
                        <li
                            key={branch.id}
                            className="rounded-xl border border-slate-200/80 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-slate-800 dark:text-slate-200">{branch.name}</p>
                                    {branch.city ? (
                                        <p className="mt-0.5 text-[11px] text-slate-400">{branch.city}</p>
                                    ) : null}
                                    {branch.address ? (
                                        <p className="mt-0.5 truncate text-[11px] text-slate-400">{branch.address}</p>
                                    ) : null}
                                </div>
                                {branch.status ? <StatusBadge status={branch.status} /> : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
