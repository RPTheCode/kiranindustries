import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText, Copy, Copy as CopyIcon } from 'lucide-react';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { CopyToBranchesModal } from '@/components/CopyToBranchesModal';

export default function MaterialItems() {
  const { t } = useTranslation();
  const { auth, materialItems, branches = [], activeBranchId, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedBranch, setSelectedBranch] = useState(pageFilters.branch_id || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  // Checkbox selection state
  const [selectedItems, setSelectedItems] = useState<number[]>([]);

  // Copy modal state
  const [isCopyModalOpen, setIsCopyModalOpen] = useState(false);
  const [isBulkCopy, setIsBulkCopy] = useState(false);
  const [isCopying, setIsCopying] = useState(false);

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
    router.get(route('hr.material-items.index'), {
      page: 1,
      search: undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const applyFilters = () => {
    router.get(route('hr.material-items.index'), {
      page: 1,
      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.material-items.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
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
      case 'delete':
        setIsDeleteModalOpen(true);
        break;
      case 'toggle-status':
        handleToggleStatus(item);
        break;
      case 'copy':
        setIsBulkCopy(false);
        setIsCopyModalOpen(true);
        break;
    }
  };

  const handleAddNew = () => {
    setCurrentItem(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating material item...'));

      router.post(route('hr.material-items.store'), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash?.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash?.error) {
            toast.error(t(page.props.flash.error));
          } else {
            toast.success(t('Material item created successfully'));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(Object.values(errors).join(', '));
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating material item...'));

      router.put(route('hr.material-items.update', currentItem.id), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash?.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash?.error) {
            toast.error(t(page.props.flash.error));
          } else {
            toast.success(t('Material item updated successfully'));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(Object.values(errors).join(', '));
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting material item...'));

    router.delete(route('hr.material-items.destroy', currentItem.id), {
      onSuccess: (page: any) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash?.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash?.error) {
          toast.error(t(page.props.flash.error));
        } else {
          toast.success(t('Material item deleted successfully'));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(Object.values(errors).join(', '));
      }
    });
  };

  const handleToggleStatus = (item: any) => {
    const newStatus = item.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} material item...`);

    router.put(route('hr.material-items.toggle-status', item.id), {}, {
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash?.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash?.error) {
          toast.error(t(page.props.flash.error));
        } else {
          toast.success(t('Material item status updated successfully'));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(Object.values(errors).join(', '));
      }
    });
  };

  const handleCopyConfirm = (branchIds: number[]) => {
    if (branchIds.length === 0) {
      toast.error(t('Please select at least one branch'));
      return;
    }
    setIsCopying(true);
    toast.loading(t('Copying material items...'));

    if (isBulkCopy) {
      router.post(route('hr.material-items.bulk-copy'), {
        item_ids: selectedItems,
        branch_ids: branchIds,
      }, {
        onSuccess: (page: any) => {
          setIsCopying(false);
          setIsCopyModalOpen(false);
          setSelectedItems([]);
          toast.dismiss();
          if (page.props.flash?.success) {
            toast.success(page.props.flash.success);
          } else if (page.props.flash?.error) {
            toast.error(page.props.flash.error);
          }
        },
        onError: () => {
          setIsCopying(false);
          toast.dismiss();
          toast.error(t('Failed to copy material items'));
        },
      });
    } else {
      router.post(route('hr.material-items.copy-to-branches', currentItem.id), {
        branch_ids: branchIds,
      }, {
        onSuccess: (page: any) => {
          setIsCopying(false);
          setIsCopyModalOpen(false);
          toast.dismiss();
          if (page.props.flash?.success) {
            toast.success(page.props.flash.success);
          } else if (page.props.flash?.error) {
            toast.error(page.props.flash.error);
          }
        },
        onError: () => {
          setIsCopying(false);
          toast.dismiss();
          toast.error(t('Failed to copy material item'));
        },
      });
    }
  };

  const copyToClipboardText = (text: string, message: string) => {
    navigator.clipboard.writeText(text);
    toast.success(message);
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedBranch('all');
    setSelectedStatus('all');
    setShowFilters(false);

    router.get(route('hr.material-items.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  // Page Actions
  const pageActions: any[] = [];

  pageActions.push({
    label: t('Add Material Item'),
    icon: <Plus className="h-4 w-4 mr-2" />,
    variant: 'default' as const,
    onClick: () => handleAddNew()
  });

  if (selectedItems.length > 0) {
    pageActions.push({
      label: `${t('Copy Selected')} (${selectedItems.length})`,
      icon: <Copy className="h-4 w-4 mr-2" />,
      variant: 'secondary' as const,
      className: 'bg-purple-600 hover:bg-purple-700 text-white border-none',
      onClick: () => {
        setIsBulkCopy(true);
        setIsCopyModalOpen(true);
      },
    });
  }

  pageActions.push({
    label: t('Download Report'),
    icon: <FileText className="h-4 w-4 mr-2" />,
    variant: 'outline' as const,
    className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
    onClick: () => {
      window.open(route('hr.reports.master_listing', { type: 'MAT', branch_id: activeBranchId }), '_blank');
    },
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Material Items') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'select',
      label: (
        <input
          type="checkbox"
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={materialItems?.data?.length > 0 && selectedItems.length === materialItems.data.length}
          onChange={(e) => {
            e.target.checked
              ? setSelectedItems(materialItems.data.map((c: any) => c.id))
              : setSelectedItems([]);
          }}
        />
      ),
      render: (_: any, record: any) => (
        <input
          type="checkbox"
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={selectedItems.includes(record.id)}
          onChange={(e) => {
            e.target.checked
              ? setSelectedItems(prev => [...prev, record.id])
              : setSelectedItems(prev => prev.filter(id => id !== record.id));
          }}
        />
      ),
    },
    {
      key: 'name',
      label: t('Material Name'),
      sortable: true,
      render: (value: string) => (
        <div className="flex items-center gap-1.5 group">
          <span className="font-medium text-slate-900 dark:text-slate-100">{value}</span>
          <button
            onClick={(e) => {
              e.stopPropagation();
              copyToClipboardText(value, t('Name copied to clipboard'));
            }}
            className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-slate-600 cursor-pointer border-none bg-transparent"
            title={t('Copy to Clipboard')}
          >
            <CopyIcon className="h-3 w-3" />
          </button>
        </div>
      )
    },
    {
      key: 'code',
      label: t('Item Code'),
      sortable: true,
      render: (value: string) => (
        <div className="flex items-center gap-1.5 group">
          <span className="font-mono text-xs font-semibold bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-300">{value}</span>
          <button
            onClick={(e) => {
              e.stopPropagation();
              copyToClipboardText(value, t('Code copied to clipboard'));
            }}
            className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-slate-600 cursor-pointer border-none bg-transparent"
            title={t('Copy to Clipboard')}
          >
            <CopyIcon className="h-3 w-3" />
          </button>
        </div>
      )
    },
    {
      key: 'rate',
      label: t('Rate'),
      sortable: true,
      render: (v: number) => (
        <span className="font-semibold text-slate-700 dark:text-slate-300">₹{Number(v).toFixed(2)}</span>
      )
    },
    {
      key: 'branch',
      label: t('Branch'),
      render: (_: any, record: any) => (
        <Badge variant="outline" className="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
          {record.branch?.name || t('N/A')}
        </Badge>
      ),
    },
    {
      key: 'status',
      label: t('Status'),
      render: (_: any, record: any) => {
        const isActive = record.status === 'active';
        return (
          <button
            onClick={() => handleAction('toggle-status', record)}
            title={isActive ? t('Click to Deactivate') : t('Click to Activate')}
            className="flex items-center gap-1.5 cursor-pointer select-none border-none bg-transparent"
          >
            <span className={cn(
              'relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors duration-200 ease-in-out',
              isActive ? 'bg-emerald-500' : 'bg-slate-300'
            )}>
              <span className={cn(
                'inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform duration-200 ease-in-out',
                isActive ? 'translate-x-3.5' : 'translate-x-0.5'
              )} />
            </span>
            <span className={cn(
              'text-[10px] font-semibold uppercase tracking-wide',
              isActive ? 'text-emerald-600' : 'text-slate-400'
            )}>
              {isActive ? t('Active') : t('Inactive')}
            </span>
          </button>
        );
      },
    },
    {
      key: 'created_at',
      label: t('Created At'),
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    }
  ];

  // Table row actions
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500'
    },
    {
      label: t('Copy to Branches'),
      icon: 'Copy',
      action: 'copy',
      className: 'text-purple-500 hover:text-purple-600'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500'
    }
  ];

  return (
    <PageTemplate
      title={t("Material Item Master")}
      breadcrumbs={breadcrumbs}
      actions={pageActions}
    >
      <div className="space-y-4">
        {/* Search and Filters Bar */}
        <SearchAndFilterBar
          searchTerm={searchTerm}
          setSearchTerm={setSearchTerm}
          selectedBranch={selectedBranch}
          setSelectedBranch={setSelectedBranch}
          selectedStatus={selectedStatus}
          setSelectedStatus={setSelectedStatus}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          branches={branches}
          activeFilterCount={activeFilterCount()}
          hasActiveFilters={hasActiveFilters()}
          onSearch={handleSearch}
          onSearchClear={handleSearchClear}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          t={t}
        />

        {/* Material Items Table Grid */}
        <div className="bg-white dark:bg-gray-900 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
          <CrudTable
            columns={columns}
            actions={actions}
            data={materialItems?.data || []}
            onAction={handleAction}
            permissions={permissions}
            sortField={pageFilters.sort_field}
            sortDirection={pageFilters.sort_direction}
            onSort={handleSort}
          />

          {/* Pagination Controls */}
          {materialItems?.total > 0 && (
            <div className="border-t border-slate-100 dark:border-slate-800 px-6 py-4 flex items-center justify-between">
              <div className="text-sm text-slate-500">
                {t('Showing')} <span className="font-semibold text-slate-700 dark:text-slate-300">{materialItems.from || 0}</span> {t('to')}{' '}
                <span className="font-semibold text-slate-700 dark:text-slate-300">{materialItems.to || 0}</span> {t('of')}{' '}
                <span className="font-semibold text-slate-700 dark:text-slate-300">{materialItems.total || 0}</span> {t('entries')}
              </div>
              <Pagination
                links={materialItems.links}
                onPageChange={(url) => {
                  if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
                }}
              />
            </div>
          )}
        </div>
      </div>

      {/* Add / Edit Form Modal */}
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
              options: branches.map((b: any) => ({ value: b.id.toString(), label: b.name })),
              required: true
            },
            { name: 'name', label: t('Material Name'), type: 'text', required: true },
            { name: 'code', label: t('Item Code'), type: 'text', required: true },
            { name: 'rate', label: t('Rate'), type: 'number', required: true, step: '0.01' },
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
        title={formMode === 'create' ? t('Add Material Item') : formMode === 'edit' ? t('Edit Material Item') : t('View Material Item')}
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="material item"
      />

      {/* Copy to Branches Modal */}
      <CopyToBranchesModal
        open={isCopyModalOpen}
        onClose={() => setIsCopyModalOpen(false)}
        onConfirm={handleCopyConfirm}
        branches={branches}
        excludeBranchId={isBulkCopy ? (activeBranchId ? Number(activeBranchId) : null) : currentItem?.branch_id}
        title={
          isBulkCopy
            ? t('Copy {{count}} Material Items to Branches', { count: selectedItems.length })
            : t('Copy "{{name}}" to Branches', { name: currentItem?.name })
        }
        isLoading={isCopying}
      />
    </PageTemplate>
  );
}
