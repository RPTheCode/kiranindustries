import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText, Copy, Edit, Trash2 } from 'lucide-react';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { CopyToBranchesModal } from '@/components/CopyToBranchesModal';
import { hasPermission } from '@/utils/authorization';

export default function Sections() {
    const { t } = useTranslation();
    const { auth, sections, branches = [], activeBranchId, filters: pageFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];

    // State
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedBranch, setSelectedBranch] = useState(pageFilters.branch_id || 'all');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedSections, setSelectedSections] = useState<number[]>([]);

    // Modals state
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

    // CopyToBranchesModal state
    const [isCopyModalOpen, setIsCopyModalOpen] = useState(false);
    const [isBulkCopy, setIsBulkCopy] = useState(false);
    const [isCopying, setIsCopying] = useState(false);

    // Clipboard utility
    const copyToClipboardText = (text: string, message: string) => {
        navigator.clipboard.writeText(text);
        toast.success(message);
    };

    // Check if any filters are active
    const hasActiveFilters = () => {
        return searchTerm !== '' || selectedStatus !== 'all' || selectedBranch !== 'all';
    };

    // Count active filters
    const activeFilterCount = () => {
        return (searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedBranch !== 'all' ? 1 : 0);
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleSearchClear = () => {
        setSearchTerm('');
        router.get(route('hr.sections.index'), {
            page: 1,
            search: undefined,
            status: selectedStatus !== 'all' ? selectedStatus : undefined,
            branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
    };

    const applyFilters = () => {
        router.get(route('hr.sections.index'), {
            page: 1,
            search: searchTerm || undefined,
            status: selectedStatus !== 'all' ? selectedStatus : undefined,
            branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedBranch('all');
        setSelectedStatus('all');
        setShowFilters(false);

        router.get(route('hr.sections.index'), {
            page: 1,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
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
            status: formData.status ?? 'active'
        };

        if (formMode === 'create') {
            toast.loading(t('Creating section...'));

            router.post(route('hr.sections.store'), dataToSend, {
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
                        toast.error(t('Failed to create section: {{errors}}', { errors: Object.values(errors).join(', ') }));
                    }
                }
            });
        } else if (formMode === 'edit') {
            toast.loading(t('Updating section...'));

            router.put(route('hr.sections.update', currentItem.id), dataToSend, {
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
                        toast.error(t('Failed to update section: {{errors}}', { errors: Object.values(errors).join(', ') }));
                    }
                }
            });
        }
    };

    const handleDeleteConfirm = () => {
        toast.loading(t('Deleting section...'));

        router.delete(route('hr.sections.destroy', currentItem.id), {
            onSuccess: (page: any) => {
                setIsDeleteModalOpen(false);
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors: any) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(t(errors));
                } else {
                    toast.error(t('Failed to delete section: {{errors}}', { errors: Object.values(errors).join(', ') }));
                }
            }
        });
    };

    const handleToggleStatus = (section: any) => {
        const newStatus = section.status === 'active' ? 'inactive' : 'active';
        toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} section...`);

        router.put(route('hr.sections.toggle-status', section.id), {}, {
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
                    toast.error(t('Failed to update section status: {{errors}}', { errors: Object.values(errors).join(', ') }));
                }
            }
        });
    };

    const handleCopyConfirm = (branchIds: number[]) => {
        setIsCopying(true);
        const url = isBulkCopy
            ? route('hr.sections.bulk-copy')
            : route('hr.sections.copy-to-branches', currentItem.id);

        const data = isBulkCopy
            ? { section_ids: selectedSections, branch_ids: branchIds }
            : { branch_ids: branchIds };

        toast.loading(t('Copying sections...'));

        router.post(url, data, {
            onSuccess: (page: any) => {
                setIsCopyModalOpen(false);
                setIsCopying(false);
                setSelectedSections([]);
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
                    toast.error(t('Failed to copy sections: {{errors}}', { errors: Object.values(errors).join(', ') }));
                }
            }
        });
    };

    // Define page actions
    const pageActions = [];

    // If user selected multiple sections, add bulk actions
    if (selectedSections.length > 0) {
        pageActions.push({
            label: `${t('Copy Selected to Branches')} (${selectedSections.length})`,
            icon: <Copy className="h-4 w-4 mr-2" />,
            variant: 'secondary' as const,
            className: 'bg-purple-600 hover:bg-purple-700 text-white border-none',
            onClick: () => {
                setIsBulkCopy(true);
                setIsCopyModalOpen(true);
            }
        });
    }

    pageActions.push({
        label: t('Add Section'),
        icon: <Plus className="h-4 w-4 mr-2" />,
        variant: 'default' as const,
        onClick: () => handleAddNew()
    });

    pageActions.push({
        label: t('Download Report'),
        icon: <FileText className="h-4 w-4 mr-2" />,
        variant: 'outline' as const,
        className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
        onClick: () => {
            window.open(route('hr.reports.master_listing', { type: 'SEC', branch_id: activeBranchId }), '_blank');
        },
    });

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Sections') }
    ];

    // Define table columns
    const columns = [
        // Checkbox column
        {
            key: 'select',
            label: (
                <input
                    type="checkbox"
                    className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
                    checked={(sections?.data || []).length > 0 && selectedSections.length === (sections?.data || []).length}
                    onChange={(e) => {
                        e.target.checked
                            ? setSelectedSections((sections?.data || []).map((s: any) => s.id))
                            : setSelectedSections([]);
                    }}
                />
            ),
            render: (_: any, record: any) => (
                <input
                    type="checkbox"
                    className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
                    checked={selectedSections.includes(record.id)}
                    onChange={(e) => {
                        e.target.checked
                            ? setSelectedSections(prev => [...prev, record.id])
                            : setSelectedSections(prev => prev.filter(id => id !== record.id));
                    }}
                />
            ),
        },
        {
            key: 'name',
            label: t('Section Name'),
            sortable: true,
            render: (value: string) => (
                <div className="flex items-center gap-1.5 group">
                    <span>{value}</span>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            copyToClipboardText(value, t('Section Name copied to clipboard'));
                        }}
                        className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-slate-600 cursor-pointer border-none bg-transparent"
                        title={t('Copy to Clipboard')}
                    >
                        <Copy className="h-3 w-3" />
                    </button>
                </div>
            )
        },
        {
            key: 'code',
            label: t('Section Code'),
            sortable: true,
            render: (value: string) => (
                <div className="flex items-center gap-1.5 group">
                    <span className="font-mono text-xs font-semibold bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-300">{value}</span>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            copyToClipboardText(value, t('Section Code copied to clipboard'));
                        }}
                        className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-slate-600 cursor-pointer border-none bg-transparent"
                        title={t('Copy to Clipboard')}
                    >
                        <Copy className="h-3 w-3" />
                    </button>
                </div>
            )
        },
        {
            key: 'branch',
            label: t('Branch'),
            render: (_: any, record: any) => record.branch ? (
                <span className="inline-flex items-center rounded-md bg-purple-50 dark:bg-purple-900/20 px-2 py-1 text-xs font-medium text-purple-700 dark:text-purple-300 ring-1 ring-inset ring-purple-700/10">
                    {record.branch.name}
                </span>
            ) : (
                <span className="text-slate-400 italic text-xs">{t('Global')}</span>
            )
        },
        {
            key: 'status',
            label: t('Status'),
            render: (value: string, record: any) => {
                const isActive = record.status === 'active';
                return (
                    <button
                        onClick={() => handleAction('toggle-status', record)}
                        title={isActive ? t('Click to Deactivate') : t('Click to Activate')}
                        className="flex items-center gap-1.5 cursor-pointer select-none border-none bg-transparent"
                    >
                        <span className={`relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors duration-200 ease-in-out ${
                            isActive ? 'bg-emerald-500' : 'bg-slate-300'
                        }`}>
                            <span className={`inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform duration-200 ease-in-out ${
                                isActive ? 'translate-x-3.5' : 'translate-x-0.5'
                            }`} />
                        </span>
                        <span className={`text-[10px] font-semibold uppercase tracking-wide ${
                            isActive ? 'text-emerald-600' : 'text-slate-400'
                        }`}>
                            {isActive ? t('Active') : t('Inactive')}
                        </span>
                    </button>
                );
            },
        },
        {
            key: 'created_at',
            label: t('Created At'),
            render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
        }
    ];

    // Define table actions
    const actions = [
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500',
        },
        {
            label: t('Copy to Branches'),
            icon: 'Copy',
            action: 'copy',
            className: 'text-purple-500',
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500',
            isDisabled: (item: any) => item.employees_count > 0,
            disabledTitle: t('Cannot delete section because it is used in employees')
        }
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

    return (
        <PageTemplate
            title={t("Section Management")}
            url="/sections"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters section */}
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
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
                        router.get(route('hr.sections.index'), {
                            page: 1,
                            per_page: parseInt(value),
                            search: searchTerm || undefined,
                            status: selectedStatus !== 'all' ? selectedStatus : undefined,
                            branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
                        }, { preserveState: true, preserveScroll: true });
                    }}
                />
            </div>

            {/* Content section */}
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <CrudTable
                    columns={columns}
                    actions={actions}
                    data={sections?.data || []}
                    from={sections?.from || 1}
                    onAction={handleAction}
                    permissions={permissions}
                />

                {/* Pagination section */}
                <Pagination
                    from={sections?.from || 0}
                    to={sections?.to || 0}
                    total={sections?.total || 0}
                    links={sections?.links}
                    entityName={t("sections")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

            {/* Form Modal */}
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
                        { name: 'name', label: t('Section Name'), type: 'text', required: true },
                        { name: 'code', label: t('Section Code'), type: 'text', required: true },
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
                    branch_id: currentItem.branch_id?.toString()
                } : {
                    branch_id: activeBranchId?.toString(),
                    status: 'active'
                }}
                title={
                    formMode === 'create'
                        ? t('Add New Section')
                        : formMode === 'edit'
                            ? t('Edit Section')
                            : t('View Section')
                }
                mode={formMode}
            />

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.name || ''}
                entityName="section"
            />

            {/* Reusable Copy Modal */}
            <CopyToBranchesModal
                open={isCopyModalOpen}
                onClose={() => {
                    setIsCopyModalOpen(false);
                    setSelectedSections([]);
                }}
                onConfirm={handleCopyConfirm}
                branches={branches}
                excludeBranchId={isBulkCopy ? (activeBranchId ? Number(activeBranchId) : null) : currentItem?.branch_id}
                title={isBulkCopy ? t('Copy Selected Sections to Branches') : t("Copy '{{name}}' to Branches", { name: currentItem?.name })}
                isLoading={isCopying}
            />
        </PageTemplate>
    );
}
