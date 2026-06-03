import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText, Copy, Copy as CopyIcon } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
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

export default function Categories() {
  const { t } = useTranslation();
  const { auth, categories, branches = [], activeBranchId, filters: pageFilters = {} } = usePage().props as any;
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
  const [selectedCategories, setSelectedCategories] = useState<number[]>([]);

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
    router.get(route('hr.categories.index'), {
      page: 1,
      search: undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const applyFilters = () => {
    router.get(route('hr.categories.index'), {
      page: 1,
      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.categories.index'), {
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
      toast.loading(t('Creating category...'));

      router.post(route('hr.categories.store'), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash?.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash?.error) {
            toast.error(t(page.props.flash.error));
          } else {
            toast.success(t('Category created successfully'));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(Object.values(errors).join(', '));
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating category...'));

      router.put(route('hr.categories.update', currentItem.id), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash?.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash?.error) {
            toast.error(t(page.props.flash.error));
          } else {
            toast.success(t('Category updated successfully'));
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
    toast.loading(t('Deleting category...'));

    router.delete(route('hr.categories.destroy', currentItem.id), {
      onSuccess: (page: any) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash?.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash?.error) {
          toast.error(t(page.props.flash.error));
        } else {
          toast.success(t('Category deleted successfully'));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(Object.values(errors).join(', '));
      }
    });
  };

  const handleToggleStatus = (category: any) => {
    const newStatus = category.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} category...`);

    router.put(route('hr.categories.toggle-status', category.id), {}, {
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash?.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash?.error) {
          toast.error(t(page.props.flash.error));
        } else {
          toast.success(t('Category status updated successfully'));
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
    toast.loading(t('Copying categories...'));

    if (isBulkCopy) {
      router.post(route('hr.categories.bulk-copy'), {
        category_ids: selectedCategories,
        branch_ids: branchIds,
      }, {
        onSuccess: (page: any) => {
          setIsCopying(false);
          setIsCopyModalOpen(false);
          setSelectedCategories([]);
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
          toast.error(t('Failed to copy categories'));
        },
      });
    } else {
      router.post(route('hr.categories.copy-to-branches', currentItem.id), {
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
          toast.error(t('Failed to copy category'));
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

    router.get(route('hr.categories.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  // Page Actions
  const pageActions: any[] = [];

  pageActions.push({
    label: t('Add Category'),
    icon: <Plus className="h-4 w-4 mr-2" />,
    variant: 'default' as const,
    onClick: () => handleAddNew()
  });

  if (selectedCategories.length > 0) {
    pageActions.push({
      label: `${t('Copy Selected')} (${selectedCategories.length})`,
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
      window.open(route('hr.reports.master_listing', { type: 'CNT', branch_id: activeBranchId }), '_blank');
    },
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Categories') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'select',
      label: (
        <input
          type="checkbox"
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={categories?.data?.length > 0 && selectedCategories.length === categories.data.length}
          onChange={(e) => {
            e.target.checked
              ? setSelectedCategories(categories.data.map((c: any) => c.id))
              : setSelectedCategories([]);
          }}
        />
      ),
      render: (_: any, record: any) => (
        <input
          type="checkbox"
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={selectedCategories.includes(record.id)}
          onChange={(e) => {
            e.target.checked
              ? setSelectedCategories(prev => [...prev, record.id])
              : setSelectedCategories(prev => prev.filter(id => id !== record.id));
          }}
        />
      ),
    },
    {
      key: 'name',
      label: t('Category Name'),
      sortable: true,
      render: (value: string) => (
        <div className="flex items-center gap-1.5 group">
          <span>{value}</span>
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
      label: t('Code'),
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
      className: 'text-purple-500'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      isDisabled: (row: any) => (row.employees_count > 0),
      disabledTitle: t('This category is in use and cannot be deleted')
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
      title={t("Employee Category Management")}
      url="/categories"
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
            router.get(route('hr.categories.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              branch_id: selectedBranch !== 'all' ? selectedBranch : undefined
            }, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      {/* Content table section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={categories?.data || []}
          from={categories?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
        />

        {/* Pagination */}
        <Pagination
          from={categories?.from || 0}
          to={categories?.to || 0}
          total={categories?.total || 0}
          links={categories?.links}
          entityName={t("categories")}
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
            { name: 'name', label: t('Category Name'), type: 'text', required: true },
            { name: 'code', label: t('Code'), type: 'text', required: true },
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
        title={formMode === 'create' ? t('Add Category') : formMode === 'edit' ? t('Edit Category') : t('View Category')}
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="category"
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
            ? t('Copy {{count}} Categories to Branches', { count: selectedCategories.length })
            : t('Copy "{{name}}" to Branches', { name: currentItem?.name })
        }
        isLoading={isCopying}
      />
    </PageTemplate>
  );
}
