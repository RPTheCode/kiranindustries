// pages/hr/skills/index.tsx
import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileUp, FileText, Copy, Users, Layers, MapPin, IndianRupee } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { CopyToBranchesModal } from '@/components/CopyToBranchesModal';
import { WageZonesTab } from './components/WageZonesTab';
import { ZoneRatesTab } from './components/ZoneRatesTab';

type SkillsTab = 'skills' | 'zones' | 'rates';

export default function Skills() {
    const { t } = useTranslation();
    const {
        auth,
        skills,
        branches = [],
        stats = {},
        activeBranchId,
        activeBranchName,
        filters: pageFilters = {},
        tab: activeTab = 'skills',
        wageZones = [],
        selectedWageZoneId = null,
        selectedWageZone = null,
        zoneRates = [],
    } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const sessionActiveBranchId = activeBranchId ?? auth?.active_branch_id ?? null;
    const defaultStatus = 'all';

    const branchFilterFromUrl = pageFilters.branch_id
        ? String(pageFilters.branch_id)
        : sessionActiveBranchId
            ? String(sessionActiveBranchId)
            : 'all';

    // State
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedBranch, setSelectedBranch] = useState(branchFilterFromUrl);
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status ?? defaultStatus);
    const [selectedSkills, setSelectedSkills] = useState<number[]>([]);

    // Modal state
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

    // CopyToBranchesModal state
    const [isCopyModalOpen, setIsCopyModalOpen] = useState(false);
    const [isBulkCopy, setIsBulkCopy] = useState(false);
    const [isCopying, setIsCopying] = useState(false);

    // Import State
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importErrors, setImportErrors] = useState<string | null>(null);

    useEffect(() => {
        setSelectedBranch(branchFilterFromUrl);
        if (pageFilters.status) {
            setSelectedStatus(pageFilters.status);
        }
    }, [pageFilters.branch_id, pageFilters.status]);

    const listQueryParams = (extra: Record<string, unknown> = {}) => ({
        page: 1,
        search: searchTerm || undefined,
        branch_id: selectedBranch,
        status: selectedStatus,
        per_page: pageFilters.per_page,
        sort_field: pageFilters.sort_field,
        sort_direction: pageFilters.sort_direction,
        tab: activeTab,
        wage_zone_id: selectedWageZoneId || undefined,
        ...extra,
    });

    const switchTab = (tab: SkillsTab) => {
        router.get(route('hr.skills.index'), listQueryParams({ tab, page: 1 }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const defaultBranchFilter = sessionActiveBranchId ? String(sessionActiveBranchId) : 'all';

    const showBranchColumn =
        !sessionActiveBranchId || selectedBranch === 'all' || String(selectedBranch) !== String(sessionActiveBranchId);

    const hasActiveFilters = () =>
        searchTerm !== '' ||
        selectedStatus !== defaultStatus ||
        selectedBranch !== defaultBranchFilter;

    const activeFilterCount = () =>
        (searchTerm ? 1 : 0) +
        (selectedStatus !== defaultStatus ? 1 : 0) +
        (selectedBranch !== defaultBranchFilter ? 1 : 0);

    const filteredBranchName =
        selectedBranch !== 'all' ? branches.find((b: any) => String(b.id) === selectedBranch)?.name : null;

    const resolvedActiveBranchName = activeBranchName ?? (sessionActiveBranchId
        ? branches.find((b: any) => String(b.id) === String(sessionActiveBranchId))?.name
        : null);

    const isViewingOtherBranch =
        selectedBranch !== 'all' &&
        sessionActiveBranchId &&
        String(selectedBranch) !== String(sessionActiveBranchId);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleSearchClear = () => {
        setSearchTerm('');
        router.get(route('hr.skills.index'), listQueryParams({ search: undefined }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const applyFilters = () => {
        router.get(route('hr.skills.index'), listQueryParams(), { preserveState: true, preserveScroll: true });
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
        router.get(
            route('hr.skills.index'),
            listQueryParams({ sort_field: field, sort_direction: direction }),
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedBranch(defaultBranchFilter);
        setSelectedStatus(defaultStatus);
        setShowFilters(false);

        router.get(
            route('hr.skills.index'),
            {
                page: 1,
                per_page: pageFilters.per_page,
                status: defaultStatus,
                branch_id: defaultBranchFilter,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleAction = (action: string, item: any) => {
        setCurrentItem(item);

        switch (action) {
            case 'view':
                setFormMode('view');
                setIsFormModalOpen(true);
                break;
            case 'edit':
                setFormMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'copy':
                setIsBulkCopy(false);
                setIsCopyModalOpen(true);
                break;
            case 'delete':
                if (!item.can_delete) {
                    toast.error(
                        item.delete_block_reason ||
                            t('This skill is assigned to employees in this branch and cannot be deleted.')
                    );
                    return;
                }
                setIsDeleteModalOpen(true);
                break;
            case 'toggle-status':
                handleToggleStatus(item);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentItem(null);
        setFormMode('create');
        setIsFormModalOpen(true);
    };

    const handleFormSubmit = (formData: any) => {
        const dataToSend = {
            ...formData,
            status: formData.status === 'active' || formData.status === true ? 1 : 0
        };

        if (formMode === 'create') {
            toast.loading(t('Creating skill...'));

            router.post(route('hr.skills.store'), dataToSend, {
                onSuccess: (page: any) => {
                    setIsFormModalOpen(false);
                    toast.dismiss();
                    if (page.props.flash.success) {
                        toast.success(t(page.props.flash.success));
                    } else if (page.props.flash.error) {
                        toast.error(t(page.props.flash.error));
                    }
                },
                onError: (errors) => {
                    toast.dismiss();
                    if (typeof errors === 'string') {
                        toast.error(t(errors));
                    } else {
                        toast.error(t('Failed to create skill: {{errors}}', { errors: Object.values(errors).join(', ') }));
                    }
                }
            });
        } else if (formMode === 'edit') {
            toast.loading(t('Updating skill...'));

            router.put(route('hr.skills.update', currentItem.id), dataToSend, {
                onSuccess: (page: any) => {
                    setIsFormModalOpen(false);
                    toast.dismiss();
                    if (page.props.flash.success) {
                        toast.success(t(page.props.flash.success));
                    } else if (page.props.flash.error) {
                        toast.error(t(page.props.flash.error));
                    }
                },
                onError: (errors) => {
                    toast.dismiss();
                    if (typeof errors === 'string') {
                        toast.error(t(errors));
                    } else {
                        toast.error(t('Failed to update skill: {{errors}}', { errors: Object.values(errors).join(', ') }));
                    }
                }
            });
        }
    };

    const handleDeleteConfirm = () => {
        toast.loading(t('Deleting skill...'));

        router.delete(route('hr.skills.destroy', currentItem.id), {
            onSuccess: (page: any) => {
                setIsDeleteModalOpen(false);
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(t(errors));
                } else {
                    toast.error(t('Failed to delete skill: {{errors}}', { errors: Object.values(errors).join(', ') }));
                }
            }
        });
    };

    const handleToggleStatus = (skill: any) => {
        const newStatus = !skill.status;
        toast.loading(`${newStatus ? t('Activating') : t('Deactivating')} skill...`);

        router.put(route('hr.skills.toggle-status', skill.id), {}, {
            onSuccess: (page: any) => {
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(t(errors));
                } else {
                    toast.error(t('Failed to update skill status: {{errors}}', { errors: Object.values(errors).join(', ') }));
                }
            }
        });
    };

    const handleCopyConfirm = (branchIds: number[]) => {
        setIsCopying(true);
        const url = isBulkCopy
            ? route('hr.skills.bulk-copy')
            : route('hr.skills.copy-to-branches', currentItem.id);

        const data = isBulkCopy
            ? { skill_ids: selectedSkills, branch_ids: branchIds }
            : { branch_ids: branchIds };

        toast.loading(t('Copying skills...'));

        router.post(url, data, {
            onSuccess: (page: any) => {
                setIsCopyModalOpen(false);
                setIsCopying(false);
                setSelectedSkills([]);
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                setIsCopying(false);
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(t(errors));
                } else {
                    toast.error(t('Failed to copy skills: {{errors}}', { errors: Object.values(errors).join(', ') }));
                }
            }
        });
    };

    const handleImportModalOpenChange = (open: boolean) => {
        setIsImportModalOpen(open);
        if (!open) {
            setTimeout(() => {
                setImportErrors(null);
                setImportFile(null);
            }, 300);
        }
    };

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importFile) {
            toast.error(t('Please select a file to import'));
            return;
        }

        setImportErrors(null);

        const formData = new FormData();
        formData.append('file', importFile);

        router.post(route('hr.skills.import'), formData, {
            onStart: () => {
                toast.loading(t('Importing skills...'));
            },
            onSuccess: (page: any) => {
                toast.dismiss();
                if (page.props.flash?.success) {
                    handleImportModalOpenChange(false);
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash?.error) {
                    const errorMsg = Array.isArray(page.props.flash.error)
                        ? page.props.flash.error.join('<br>')
                        : page.props.flash.error;
                    setImportErrors(errorMsg);
                } else {
                    handleImportModalOpenChange(false);
                    toast.success(t('Skills imported successfully'));
                }
            },
            onError: (errors: any) => {
                toast.dismiss();
                let errorMessage = '';
                if (errors?.error) {
                    errorMessage = t(errors.error);
                } else if (typeof errors === 'string') {
                    errorMessage = t(errors);
                } else {
                    const errorMessages = Object.values(errors).flat();
                    if (errorMessages.length > 0) {
                        errorMessage = errorMessages.join('<br>');
                    } else {
                        errorMessage = t('Import failed. Please check for errors.');
                    }
                }
                setImportErrors(errorMessage);
            },
        });
    };

    // Define page actions (context-aware per tab)
    const pageActions = [];

    if (activeTab === 'skills') {
        if (selectedSkills.length > 0 && hasPermission(permissions, 'create-skills')) {
            pageActions.push({
                label: `${t('Copy Selected to Branches')} (${selectedSkills.length})`,
                icon: <Copy className="h-4 w-4 mr-2" />,
                variant: 'secondary' as const,
                className: 'theme-bg hover:opacity-90 text-white border-none',
                onClick: () => {
                    setIsBulkCopy(true);
                    setIsCopyModalOpen(true);
                }
            });
        }

        if (hasPermission(permissions, 'create-skills')) {
            pageActions.push({
                label: t('Add Skill'),
                icon: <Plus className="h-4 w-4 mr-2" />,
                variant: 'default' as const,
                onClick: () => handleAddNew()
            });

            pageActions.push({
                label: t('Import Skills'),
                icon: <FileUp className="h-4 w-4 mr-2" />,
                variant: 'outline' as const,
                className: 'border-primary text-primary hover:bg-primary/5',
                onClick: () => setIsImportModalOpen(true),
            });
        }

        pageActions.push({
            label: t('Download Report'),
            icon: <FileText className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
            onClick: () => {
                window.open(route('hr.reports.master_listing', { type: 'SKL', branch_id: activeBranchId }), '_blank');
            },
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Skill Management'), href: route('hr.skills.index') },
        { title: t('Skills') },
    ];

    const checkboxClass =
        'rounded border-slate-300 h-4 w-4 cursor-pointer accent-[var(--theme-color)] focus:ring-[color-mix(in_srgb,var(--theme-color)_40%,transparent)]';

    const isSkillActive = (record: any) =>
        record.status === true || record.status === 1 || record.status === 'active';

    const tableColumns = [
        {
            key: 'select',
            label: (
                <input
                    type="checkbox"
                    className={checkboxClass}
                    checked={(skills?.data || []).length > 0 && selectedSkills.length === (skills?.data || []).length}
                    onChange={(e) => {
                        e.target.checked
                            ? setSelectedSkills((skills?.data || []).map((s: any) => s.id))
                            : setSelectedSkills([]);
                    }}
                />
            ),
            render: (_: any, record: any) => (
                <input
                    type="checkbox"
                    className={checkboxClass}
                    checked={selectedSkills.includes(record.id)}
                    onChange={(e) => {
                        e.target.checked
                            ? setSelectedSkills(prev => [...prev, record.id])
                            : setSelectedSkills(prev => prev.filter(id => id !== record.id));
                    }}
                />
            ),
        },
        {
            key: 'name',
            label: t('Skill'),
            sortable: true,
            render: (_: string, record: any) => (
                <div className="min-w-[7.5rem] max-w-[12rem]">
                    <p className="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-tight truncate" title={record.name}>
                        {record.name}
                    </p>
                    {record.code ? (
                        <p className="font-mono text-[11px] text-slate-500 mt-0.5">{record.code}</p>
                    ) : (
                        <p className="text-[11px] text-slate-400 mt-0.5">—</p>
                    )}
                </div>
            ),
        },
        {
            key: 'employees_count',
            label: t('Employees'),
            render: (_: number, record: any) => {
                const count = record.employees_count ?? 0;
                const branchId = record.branch_id ?? record.branch?.id;
                if (!hasPermission(permissions, 'view-employees') || !branchId) {
                    return <span className="text-sm tabular-nums text-slate-600">{count}</span>;
                }
                return (
                    <button
                        type="button"
                        title={t('View employees with this skill')}
                        onClick={() =>
                            router.get(route('hr.employees.index'), {
                                branch: branchId,
                                skill: record.id,
                                status: 'active',
                            })
                        }
                        className="flex items-center gap-1 text-sm font-medium tabular-nums theme-color hover:opacity-80 hover:underline border-none bg-transparent p-0 cursor-pointer"
                    >
                        <Users className="h-3.5 w-3.5 shrink-0" style={{ color: 'var(--theme-color)' }} />
                        {count}
                    </button>
                );
            },
        },
        ...(showBranchColumn
            ? [
                {
                    key: 'branch',
                    label: t('Branch'),
                    render: (_: any, record: any) => (
                        <Badge
                            variant="outline"
                            className="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-50 text-slate-600 border-slate-200"
                        >
                            {record.branch?.name || t('N/A')}
                        </Badge>
                    ),
                },
            ]
            : []),
        {
            key: 'status',
            label: t('Status'),
            render: (_: boolean, record: any) => {
                const isActive = isSkillActive(record);
                const canToggle =
                    hasPermission(permissions, 'toggle-status-skills') || hasPermission(permissions, 'edit-skills');

                return (
                    <button
                        type="button"
                        onClick={() => canToggle && handleAction('toggle-status', record)}
                        title={
                            canToggle
                                ? isActive
                                    ? t('Click to Deactivate')
                                    : t('Click to Activate')
                                : t('No permission to change status')
                        }
                        disabled={!canToggle}
                        className={cn(
                            'flex items-center gap-1 select-none border-none bg-transparent',
                            canToggle ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'
                        )}
                    >
                        <span
                            className={cn(
                                'relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors',
                                isActive ? 'bg-emerald-500' : 'bg-slate-300'
                            )}
                        >
                            <span
                                className={cn(
                                    'inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform',
                                    isActive ? 'translate-x-3.5' : 'translate-x-0.5'
                                )}
                            />
                        </span>
                        <span
                            className={cn(
                                'text-[10px] font-semibold uppercase tracking-wide',
                                isActive ? 'text-emerald-600' : 'text-slate-400'
                            )}
                        >
                            {isActive ? t('Active') : t('Inactive')}
                        </span>
                    </button>
                );
            },
        },
    ];

    const columns = tableColumns;

    const actions = [
        {
            label: t('View'),
            icon: 'Eye',
            action: 'view',
            className: 'text-blue-500',
            requiredPermission: 'view-skills',
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500',
            requiredPermission: 'edit-skills',
        },
        {
            label: t('Copy to Branches'),
            icon: 'Copy',
            action: 'copy',
            className: 'text-purple-500',
            requiredPermission: 'create-skills',
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500',
            requiredPermission: 'delete-skills',
            isDisabled: (item: any) => !item.can_delete,
            disabledTitle: (item: any) =>
                item.delete_block_reason ||
                t('This skill is assigned to employees in this branch and cannot be deleted.'),
        },
    ];

    const statusOptions = [
        { value: 'all', label: t('All Statuses') },
        { value: 'active', label: t('Active') },
        { value: 'inactive', label: t('Inactive') }
    ];

    const branchOptions = [
        { value: 'all', label: t('All Branches') },
        ...(branches || []).map((branch: any) => ({
            value: branch.id.toString(),
            label: branch.name
        }))
    ];

    const tabItems: { id: SkillsTab; step: number; label: string; icon: typeof Layers; hint: string }[] = [
        { id: 'skills', step: 1, label: t('Skill Levels'), icon: Layers, hint: t('Unskilled, Semi Skilled, Skilled...') },
        { id: 'zones', step: 2, label: t('Wage Zones'), icon: MapPin, hint: t('State / city wise zones') },
        { id: 'rates', step: 3, label: t('Set Rates'), icon: IndianRupee, hint: t('Min wage per zone & skill') },
    ];

    return (
        <PageTemplate
            title={t("Skill Management")}
            url="/skills"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {filteredBranchName && isViewingOtherBranch && (
                <div
                    className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm"
                    style={{
                        borderColor: 'color-mix(in srgb, var(--theme-color) 28%, transparent)',
                        backgroundColor: 'color-mix(in srgb, var(--theme-color) 8%, transparent)',
                        color: 'var(--theme-color)',
                    }}
                >
                    <span>
                        {t('Showing skills for')}: <strong>{filteredBranchName}</strong>
                    </span>
                    <button
                        type="button"
                        onClick={() => {
                            const activeId = String(sessionActiveBranchId);
                            setSelectedBranch(activeId);
                            router.get(route('hr.skills.index'), listQueryParams({ branch_id: activeId }), {
                                preserveState: true,
                                preserveScroll: true,
                            });
                        }}
                        className="text-xs font-semibold underline border-none bg-transparent cursor-pointer hover:opacity-80"
                        style={{ color: 'var(--theme-color)' }}
                    >
                        {t('Show active branch')}
                        {resolvedActiveBranchName ? ` (${resolvedActiveBranchName})` : ''}
                    </button>
                </div>
            )}

            <div className="mb-3 rounded-lg border border-blue-100 bg-blue-50/60 px-4 py-2.5 text-xs text-slate-600">
                <span className="font-semibold text-slate-800">{t('How it works:')}</span>{' '}
                {t('Step 1 — Add skill levels → Step 2 — Create wage zones (Surat, Ahmedabad, any state) → Step 3 — Set minimum wage rates for each skill in each zone.')}
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabItems.map(({ id, step, label, icon: Icon, hint }) => (
                    <button
                        key={id}
                        type="button"
                        onClick={() => switchTab(id)}
                        className={cn(
                            'flex min-w-[140px] flex-1 flex-col items-start rounded-xl border px-4 py-3 text-left transition-all sm:max-w-[220px]',
                            activeTab === id
                                ? 'border-primary bg-primary/5 shadow-sm ring-1 ring-primary/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50',
                        )}
                    >
                        <span className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <span className={cn(
                                'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                activeTab === id ? 'bg-primary text-white' : 'bg-slate-200 text-slate-600',
                            )}>
                                {step}
                            </span>
                            <Icon className={cn('h-4 w-4', activeTab === id ? 'text-primary' : 'text-slate-500')} />
                            {label}
                        </span>
                        <span className="mt-0.5 pl-7 text-[10px] text-slate-500">{hint}</span>
                    </button>
                ))}
            </div>

            {activeTab === 'zones' && (
                <WageZonesTab
                    wageZones={wageZones}
                    permissions={permissions}
                    listQueryParams={listQueryParams}
                />
            )}

            {activeTab === 'rates' && (
                <ZoneRatesTab
                    wageZones={wageZones}
                    selectedWageZoneId={selectedWageZoneId}
                    selectedWageZone={selectedWageZone}
                    zoneRates={zoneRates}
                    permissions={permissions}
                    listQueryParams={listQueryParams}
                    activeBranchName={resolvedActiveBranchName}
                />
            )}

            {activeTab === 'skills' && (
            <>
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    {filteredBranchName && selectedBranch !== 'all' && (
                        <>
                            <span className="font-semibold text-slate-700 dark:text-slate-300">{filteredBranchName}</span>
                            <span className="text-slate-300">·</span>
                        </>
                    )}
                    <span>
                        {t('Skills')}{' '}
                        <span className="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{stats.total ?? 0}</span>
                    </span>
                    <span className="text-slate-300">·</span>
                    <span>
                        {t('Active')}{' '}
                        <span className="font-semibold text-emerald-600 tabular-nums">{stats.active ?? 0}</span>
                    </span>
                    <span className="text-slate-300">·</span>
                    <span>
                        {t('Inactive')}{' '}
                        <span className="font-semibold text-slate-600 tabular-nums">{stats.inactive ?? 0}</span>
                    </span>
                    <span className="text-slate-300">·</span>
                    <span>
                        {t('Employees')}{' '}
                        <span className="font-semibold tabular-nums theme-color">{stats.total_employees ?? 0}</span>
                    </span>
                </div>
                <SearchAndFilterBar
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    onSearch={handleSearch}
                    onSearchClear={handleSearchClear}
                    filters={[
                        {
                            name: 'branch_id',
                            label: t('Branch'),
                            type: 'select',
                            value: selectedBranch,
                            onChange: setSelectedBranch,
                            options: branchOptions
                        },
                        {
                            name: 'status',
                            label: t('Status'),
                            type: 'select',
                            value: selectedStatus,
                            onChange: setSelectedStatus,
                            options: statusOptions
                        }
                    ]}
                    showFilters={showFilters}
                    setShowFilters={setShowFilters}
                    hasActiveFilters={hasActiveFilters}
                    activeFilterCount={activeFilterCount}
                    onResetFilters={handleResetFilters}
                    onApplyFilters={applyFilters}
                    currentPerPage={pageFilters.per_page?.toString() || "10"}
                    onPerPageChange={(value) => {
                        router.get(
                            route('hr.skills.index'),
                            listQueryParams({ per_page: parseInt(value, 10) }),
                            { preserveState: true, preserveScroll: true }
                        );
                    }}
                />
            </div>

            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <div className="w-full overflow-x-auto">
                    <CrudTable
                        columns={columns}
                        actions={actions}
                        data={skills?.data || []}
                        from={skills?.from || 1}
                        onAction={handleAction}
                        sortField={pageFilters.sort_field}
                        sortDirection={pageFilters.sort_direction}
                        onSort={handleSort}
                        permissions={permissions}
                        dense
                        stickyActions
                        entityPermissions={{
                            view: 'view-skills',
                            edit: 'edit-skills',
                            delete: 'delete-skills',
                        }}
                    />
                </div>

                {/* Pagination section */}
                <Pagination
                    from={skills?.from || 0}
                    to={skills?.to || 0}
                    total={skills?.total || 0}
                    links={skills?.links}
                    entityName={t("skills")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

            <CrudFormModal
                isOpen={isFormModalOpen}
                onClose={() => setIsFormModalOpen(false)}
                onSubmit={handleFormSubmit}
                formConfig={{
                    fields: [
                        {
                            name: 'branch_id',
                            label: t('Branch'),
                            type: 'select',
                            options: branches ? branches.map((b: any) => ({
                                value: b.id.toString(),
                                label: b.name
                            })) : [],
                            required: true
                        },
                        { name: 'name', label: t('Skill Name'), type: 'text', required: true, placeholder: t('e.g. Unskilled, Semi Skilled, Skilled') },
                        { name: 'code', label: t('Short Code'), type: 'text', required: true },
                        {
                            name: 'status',
                            label: t('Status'),
                            type: 'select',
                            options: [
                                { value: 'active', label: t('Active') },
                                { value: 'inactive', label: t('Inactive') }
                            ],
                            defaultValue: 'active'
                        }
                    ],
                    modalSize: 'md'
                }}
                initialData={currentItem ? {
                    ...currentItem,
                    status: currentItem.status ? 'active' : 'inactive',
                    branch_id: currentItem.branch_id?.toString()
                } : {
                    branch_id: activeBranchId?.toString(),
                    status: 'active'
                }}
                description={t('Skill levels are used for employees. Set government minimum wages in the "Set Rates" tab by zone.')}
                title={
                    formMode === 'create'
                        ? t('Add New Skill')
                        : formMode === 'edit'
                            ? t('Edit Skill')
                            : t('View Skill')
                }
                mode={formMode}
            />

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.name || ''}
                entityName="skill"
            />

            {/* Reusable Copy Modal */}
            <CopyToBranchesModal
                open={isCopyModalOpen}
                onClose={() => {
                    setIsCopyModalOpen(false);
                    setSelectedSkills([]);
                }}
                onConfirm={handleCopyConfirm}
                branches={branches}
                excludeBranchId={isBulkCopy ? (activeBranchId ? Number(activeBranchId) : null) : currentItem?.branch_id}
                title={isBulkCopy ? t('Copy Selected Skills to Branches') : t("Copy '{{name}}' to Branches", { name: currentItem?.name })}
                isLoading={isCopying}
            />

            <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{t('Import Skills')}</DialogTitle>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto pr-2 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-slate-100/50 [&::-webkit-scrollbar-thumb]:bg-slate-300 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-400">
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="file">{t('Select Excel File')}</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".xlsx, .xls, .csv"
                                    onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                                />
                            </div>
                            <div className="text-sm text-muted-foreground">
                                <a
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        window.location.href = route('hr.skills.import.template');
                                    }}
                                    className="text-primary hover:underline"
                                >
                                    {t('Download Sample File')}
                                </a>
                            </div>
                            {importErrors && (
                                <div className="bg-slate-50 border border-slate-200 px-4 py-3 rounded relative text-sm h-50 overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-slate-100/50 [&::-webkit-scrollbar-thumb]:bg-slate-300 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-400 font-medium" role="alert">
                                    <div dangerouslySetInnerHTML={{ __html: importErrors }} />
                                </div>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => handleImportModalOpenChange(false)}>{t('Cancel')}</Button>
                        <Button onClick={handleImport} disabled={!importFile}>{t('Import')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            </>
            )}
        </PageTemplate>
    );
}
