// pages/hr/designations/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileUp, FileText, Copy, Copy as CopyIcon } from 'lucide-react';
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

export default function Designations() {
  const { t } = useTranslation();
  const { auth, designations, departments, branches = [], activeBranchId, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedDepartment, setSelectedDepartment] = useState(pageFilters.department || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [selectedBranch, setSelectedBranch] = useState(pageFilters.branch_id || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  // Checkbox selection state
  const [selectedDesignations, setSelectedDesignations] = useState<number[]>([]);

  // Copy modal state
  const [isCopyModalOpen, setIsCopyModalOpen] = useState(false);
  const [isBulkCopy, setIsBulkCopy] = useState(false);
  const [isCopying, setIsCopying] = useState(false);

  // Import State
  const [isImportModalOpen, setIsImportModalOpen] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importErrors, setImportErrors] = useState<string | null>(null);

  // Check if any filters are active
  const hasActiveFilters = () => {
    return searchTerm !== '' || selectedDepartment !== 'all' || selectedStatus !== 'all' || selectedBranch !== 'all';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (searchTerm ? 1 : 0) + (selectedDepartment !== 'all' ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedBranch !== 'all' ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSearchClear = () => {
    setSearchTerm('');
    router.get(route('hr.designations.index'), {
      page: 1,
      search: undefined,
      department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const applyFilters = () => {
    router.get(route('hr.designations.index'), {
      page: 1,
      search: searchTerm || undefined,
      department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch !== 'all' ? selectedBranch : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.designations.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
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

    router.post(route('hr.designations.import'), formData, {
      onStart: () => {
        toast.loading(t('Importing designations...'));
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
          toast.success(t('Designations imported successfully'));
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

  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating designation...'));

      router.post(route('hr.designations.store'), formData, {
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
            toast.error(t('Failed to create designation: {{errors}}', { errors: Object.values(errors).join(', ') }));
          }
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating designation...'));

      router.put(route('hr.designations.update', currentItem.id), formData, {
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
            toast.error(t('Failed to update designation: {{errors}}', { errors: Object.values(errors).join(', ') }));
          }
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting designation...'));

    router.delete(route('hr.designations.destroy', currentItem.id), {
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
          toast.error(t('Failed to delete designation: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleToggleStatus = (designation: any) => {
    const newStatus = designation.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} designation...`);

    router.put(route('hr.designations.toggle-status', designation.id), {}, {
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
          toast.error(t('Failed to update designation status: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleCopyConfirm = (branchIds: number[]) => {
    if (branchIds.length === 0) {
      toast.error(t('Please select at least one branch'));
      return;
    }
    setIsCopying(true);
    toast.loading(t('Copying designations...'));

    if (isBulkCopy) {
      router.post(route('hr.designations.bulk-copy'), {
        designation_ids: selectedDesignations,
        branch_ids: branchIds,
      }, {
        onSuccess: (page: any) => {
          setIsCopying(false);
          setIsCopyModalOpen(false);
          setSelectedDesignations([]);
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
          toast.error(t('Failed to copy designations'));
        },
      });
    } else {
      router.post(route('hr.designations.copy-to-branches', currentItem.id), {
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
          toast.error(t('Failed to copy designation'));
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
    setSelectedDepartment('all');
    setSelectedStatus('all');
    setSelectedBranch('all');
    setShowFilters(false);

    router.get(route('hr.designations.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  // Define page actions
  const pageActions: any[] = [];

  // Add the "Add New Designation" button if user has permission
  if (hasPermission(permissions, 'create-designations')) {
    pageActions.push({
      label: t('Add Designation'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default' as const,
      onClick: () => handleAddNew()
    });

    if (selectedDesignations.length > 0) {
      pageActions.push({
        label: `${t('Copy Selected')} (${selectedDesignations.length})`,
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
      label: t('Import Designations'),
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
      window.open(route('hr.reports.master_listing', { type: 'DSG', branch_id: activeBranchId }), '_blank');
    },
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Designations') }
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
          checked={designations?.data?.length > 0 && selectedDesignations.length === designations.data.length}
          onChange={(e) => {
            e.target.checked
              ? setSelectedDesignations(designations.data.map((d: any) => d.id))
              : setSelectedDesignations([]);
          }}
        />
      ),
      render: (_: any, record: any) => (
        <input
          type="checkbox"
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={selectedDesignations.includes(record.id)}
          onChange={(e) => {
            e.target.checked
              ? setSelectedDesignations(prev => [...prev, record.id])
              : setSelectedDesignations(prev => prev.filter(id => id !== record.id));
          }}
        />
      ),
    },
    {
      key: 'name',
      label: t('Name'),
      sortable: true,
      render: (value: string) => (
        <div className="flex items-center gap-1.5 group">
          <span>{value}</span>
          <button
            onClick={(e) => {
              e.stopPropagation();
              copyToClipboardText(value, t('Name copied to clipboard'));
            }}
            className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-slate-600 cursor-pointer"
            title={t('Copy to Clipboard')}
          >
            <CopyIcon className="h-3 w-3" />
          </button>
        </div>
      )
    },
    {
      key: 'code',
      label: t('Short Code'),
      sortable: true,
      render: (value: string) => (
        <div className="flex items-center gap-1.5 group">
          <span className="font-mono text-xs font-semibold bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-300">{value}</span>
          <button
            onClick={(e) => {
              e.stopPropagation();
              copyToClipboardText(value, t('Short code copied to clipboard'));
            }}
            className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-slate-600 cursor-pointer"
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
      render: (value: number) => {
        return value ? `₹${value}` : '-';
      }
    },
    {
      key: 'department',
      label: t('Department'),
      render: (value: any, row: any) => {
        return value?.name || '-';
      }
    },
    // Branch badge
    {
      key: 'branch',
      label: t('Branch'),
      render: (_: any, record: any) => (
        <Badge variant="outline" className="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
          {record.branch?.name || t('N/A')}
        </Badge>
      ),
    },
    // Toggle-switch Status column
    {
      key: 'status',
      label: t('Status'),
      render: (_: any, record: any) => {
        const isActive = record.status === 'active';
        return (
          <button
            onClick={() => { const canToggle = hasPermission(permissions, 'toggle-status-designations') || hasPermission(permissions, 'edit-designations'); if(canToggle) handleAction('toggle-status', record); }}
            title={hasPermission(permissions, 'toggle-status-designations') || hasPermission(permissions, 'edit-designations') ? (isActive ? t('Click to Deactivate') : t('Click to Activate')) : t('No permission to change status')}
            disabled={!(hasPermission(permissions, 'toggle-status-designations') || hasPermission(permissions, 'edit-designations'))}
            className={`flex items-center gap-1.5 select-none ${hasPermission(permissions, 'toggle-status-designations') || hasPermission(permissions, 'edit-designations') ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'}`}
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

  // Define table actions
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-designations'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-designations'
    },
    {
      label: t('Copy to Branches'),
      icon: 'Copy',
      action: 'copy',
      className: 'text-purple-500',
      requiredPermission: 'create-designations'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-designations',
      isDisabled: (row: any) => (row.employees_count > 0),
      disabledTitle: t('This designation is in use and cannot be deleted')
    }
  ];

  // Prepare department options for filter
  const departmentOptions = [
    { value: 'all', label: t('All Departments') },
    ...(departments || []).map((department: any) => ({
      value: department.id.toString(),
      label: department.name
    }))
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
      title={t("Designation Management")}
      url="/designations"
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
              name: 'department',
              label: t('Department'),
              type: 'combobox',
              value: selectedDepartment,
              onChange: setSelectedDepartment,
              options: departmentOptions
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
            router.get(route('hr.designations.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              branch_id: selectedBranch !== 'all' ? selectedBranch : undefined
            }, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      {/* Content section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={designations?.data || []}
          from={designations?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'view-designations',
            edit: 'edit-designations',
            delete: 'delete-designations'
          }}
        />

        {/* Pagination section */}
        <Pagination
          from={designations?.from || 0}
          to={designations?.to || 0}
          total={designations?.total || 0}
          links={designations?.links}
          entityName={t("designations")}
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
            { name: 'name', label: t('Designation Name'), type: 'text', required: true },
            { name: 'code', label: t('Short Code'), type: 'text', required: true },
            { name: 'rate', label: t('Rate'), type: 'number', required: false },
            {
              name: 'department_id',
              label: t('Department'),
              type: 'combobox',
              options: departments ? departments.map((department: any) => ({
                value: department.id.toString(),
                label: department.name + (department.branch ? ` (${department.branch.name})` : '')
              })) : [],
              required: true
            },
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
          modalSize: 'lg'
        }}
        initialData={currentItem}
        title={
          formMode === 'create'
            ? t('Add New Designation')
            : formMode === 'edit'
              ? t('Edit Designation')
              : t('View Designation')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="designation"
      />

      {/* Copy to Branches Modal — shared themed component */}
      <CopyToBranchesModal
        open={isCopyModalOpen}
        onClose={() => setIsCopyModalOpen(false)}
        onConfirm={handleCopyConfirm}
        branches={branches}
        excludeBranchId={isBulkCopy ? null : currentItem?.branch_id}
        title={
          isBulkCopy
            ? t('Copy {{count}} Designations to Branches', { count: selectedDesignations.length })
            : t('Copy "{{name}}" to Branches', { name: currentItem?.name })
        }
        isLoading={isCopying}
      />

      {/* Import Modal */}
      <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>{t('Import Designations')}</DialogTitle>
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
                    window.location.href = route('hr.designations.import.template');
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
    </PageTemplate>
  );
}
