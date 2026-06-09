// pages/hr/branches/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileUp, FileText, Users, Layers, MapPin, Phone, Mail, UserCircle } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { cn } from '@/lib/utils';

type BranchStats = {
  total: number;
  active: number;
  inactive: number;
  total_employees: number;
};

export default function Branches() {
  const { t } = useTranslation();
  const { auth, branches, stats = {} as BranchStats, activeBranchId, wageZones = [], filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status ?? 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
  const [isImportModalOpen, setIsImportModalOpen] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importErrors, setImportErrors] = useState<string | null>(null);

  const hasActiveFilters = () =>
    searchTerm !== '' || selectedStatus !== 'all';

  const activeFilterCount = () =>
    (searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0);

  const filterParams = (extra: Record<string, unknown> = {}) => ({
    page: 1,
    search: searchTerm || undefined,
    status: selectedStatus,
    per_page: pageFilters.per_page,
    sort_field: pageFilters.sort_field,
    sort_direction: pageFilters.sort_direction,
    ...extra,
  });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSearchClear = () => {
    setSearchTerm('');
    router.get(route('hr.branches.index'), filterParams({ search: undefined, page: 1 }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const applyFilters = () => {
    router.get(route('hr.branches.index'), filterParams(), { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction =
      pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    router.get(
      route('hr.branches.index'),
      filterParams({ sort_field: field, sort_direction: direction }),
      { preserveState: true, preserveScroll: true }
    );
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedStatus('all');
    setShowFilters(false);
    router.get(route('hr.branches.index'), { page: 1, status: 'all', per_page: pageFilters.per_page }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSetCurrentBranch = (branch: any) => {
    if (branch.status !== 'active') {
      toast.error(t('Only active branches can be set as current.'));
      return;
    }
    toast.loading(t('Switching branch...'));
    router.post(
      route('hr.branches.set-active'),
      { branch_id: branch.id },
      {
        onSuccess: (page: any) => {
          toast.dismiss();
          if (page.props.flash?.success) toast.success(t(page.props.flash.success));
          else if (page.props.flash?.error) toast.error(t(page.props.flash.error));
        },
        onError: () => {
          toast.dismiss();
          toast.error(t('Failed to switch branch.'));
        },
      }
    );
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem({
      ...item,
      wage_zone_id: item?.wage_zone_id ? String(item.wage_zone_id) : '',
    });
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
        if (!item.can_delete) {
          toast.error(
            item.delete_block_reason || t('This branch is in use and cannot be deleted.')
          );
          return;
        }
        setIsDeleteModalOpen(true);
        break;
      case 'toggle-status':
        handleToggleStatus(item);
        break;
      case 'set-current':
        handleSetCurrentBranch(item);
        break;
    }
  };

  const handleAddNew = () => {
    setCurrentItem(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    const payload = {
      ...formData,
      wage_zone_id: formData.wage_zone_id ? Number(formData.wage_zone_id) : null,
    };
    if (formMode === 'create') {
      toast.loading(t('Creating branch...'));
      router.post(route('hr.branches.store'), payload, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          page.props.flash?.success
            ? toast.success(t(page.props.flash.success))
            : page.props.flash?.error && toast.error(t(page.props.flash.error));
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(
            typeof errors === 'string'
              ? t(errors)
              : t('Failed to create branch: {{errors}}', { errors: Object.values(errors).join(', ') })
          );
        },
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating branch...'));
      router.put(route('hr.branches.update', currentItem.id), payload, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          page.props.flash?.success
            ? toast.success(t(page.props.flash.success))
            : page.props.flash?.error && toast.error(t(page.props.flash.error));
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(
            typeof errors === 'string'
              ? t(errors)
              : t('Failed to update branch: {{errors}}', { errors: Object.values(errors).join(', ') })
          );
        },
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting branch...'));
    router.delete(route('hr.branches.destroy', currentItem.id), {
      onSuccess: (page: any) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        page.props.flash?.success
          ? toast.success(t(page.props.flash.success))
          : page.props.flash?.error && toast.error(t(page.props.flash.error));
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to delete branch'));
      },
    });
  };

  const handleToggleStatus = (branch: any) => {
    const newStatus = branch.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} ${t('branch')}...`);
    router.put(route('hr.branches.toggle-status', branch.id), {}, {
      onSuccess: (page: any) => {
        toast.dismiss();
        page.props.flash?.success
          ? toast.success(t(page.props.flash.success))
          : page.props.flash?.error && toast.error(t(page.props.flash.error));
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to update branch status'));
      },
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
    router.post(route('hr.branches.import'), formData, {
      onStart: () => toast.loading(t('Importing branches...')),
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash?.success) {
          handleImportModalOpenChange(false);
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash?.error) {
          setImportErrors(page.props.flash.error);
        } else {
          handleImportModalOpenChange(false);
          toast.success(t('Branches imported successfully'));
        }
      },
      onError: (errors: any) => {
        toast.dismiss();
        const msgs = Object.values(errors || {}).flat();
        setImportErrors(msgs.length > 0 ? msgs.join('<br>') : t('Import failed. Please check for errors.'));
      },
    });
  };

  const formatLocation = (row: any) => {
    const parts = [row.city, row.state, row.country].filter(Boolean);
    if (parts.length) return parts.join(', ');
    if (row.address) return row.address.length > 48 ? `${row.address.slice(0, 48)}…` : row.address;
    return '—';
  };

  const pageActions = [];
  if (hasPermission(permissions, 'create-branches')) {
    pageActions.push({
      label: t('Add Branch'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default' as const,
      onClick: () => handleAddNew(),
    });
    pageActions.push({
      label: t('Import Branches'),
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
    onClick: () => window.open(route('hr.reports.master_listing', { type: 'PLC' }), '_blank'),
  });

  const columns = [
    {
      key: 'name',
      label: t('Branch'),
      sortable: true,
      render: (_: string, record: any) => (
        <div className="min-w-[140px]">
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="font-semibold text-slate-900 dark:text-slate-100">{record.name}</span>
            {record.is_current && (
              <Badge
                className="text-[9px] font-bold uppercase tracking-wide text-white border-0 px-1.5 py-0"
                style={{ backgroundColor: 'var(--theme-color)' }}
              >
                {t('Current')}
              </Badge>
            )}
          </div>
          {record.city && (
            <p className="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1">
              <MapPin className="h-3 w-3 shrink-0" />
              {formatLocation(record)}
            </p>
          )}
        </div>
      ),
    },
    {
      key: 'in_charge_name',
      label: t('In-charge'),
      render: (_: string, record: any) => {
        if (!record.in_charge_name && !record.in_charge_contact) {
          return <span className="text-slate-400 text-sm">—</span>;
        }
        return (
          <div className="text-sm">
            {record.in_charge_name && (
              <p className="font-medium text-slate-800 flex items-center gap-1">
                <UserCircle className="h-3.5 w-3.5 text-slate-400" />
                {record.in_charge_name}
              </p>
            )}
            {record.in_charge_contact && (
              <p className="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                <Phone className="h-3 w-3" />
                {record.in_charge_contact}
              </p>
            )}
          </div>
        );
      },
    },
    {
      key: 'contact',
      label: t('Contact'),
      render: (_: string, record: any) => (
        <div className="text-sm space-y-0.5">
          {record.phone && (
            <p className="text-slate-700 flex items-center gap-1">
              <Phone className="h-3 w-3 text-slate-400" />
              {record.phone}
            </p>
          )}
          {record.email && (
            <p className="text-slate-500 text-[11px] flex items-center gap-1 truncate max-w-[180px]">
              <Mail className="h-3 w-3 shrink-0" />
              {record.email}
            </p>
          )}
          {!record.phone && !record.email && <span className="text-slate-400">—</span>}
        </div>
      ),
    },
    {
      key: 'employees_count',
      label: t('Workforce'),
      render: (_: number, record: any) => (
        <div className="flex items-center gap-3 text-sm tabular-nums">
          <button
            type="button"
            title={t('View employees in this branch')}
            onClick={() =>
              router.get(route('hr.employees.index'), { branch: record.id, status: 'active' })
            }
            className="flex items-center gap-1 font-medium tabular-nums theme-color hover:opacity-80 hover:underline border-none bg-transparent p-0 cursor-pointer"
          >
            <Users className="h-3.5 w-3.5 shrink-0" style={{ color: 'var(--theme-color)' }} />
            {record.employees_count ?? 0}
          </button>
          <button
            type="button"
            title={t('View departments in this branch')}
            onClick={() =>
              router.get(route('hr.departments.index'), { branch_id: record.id, status: 'all' })
            }
            className="flex items-center gap-1 font-medium tabular-nums theme-color hover:opacity-80 hover:underline border-none bg-transparent p-0 cursor-pointer"
          >
            <Layers className="h-3.5 w-3.5 shrink-0" style={{ color: 'var(--theme-color)' }} />
            {record.departments_count ?? 0}
          </button>
        </div>
      ),
    },
    {
      key: 'status',
      label: t('Status'),
      render: (_: string, record: any) => {
        const isActive = record.status === 'active';
        const canToggle = hasPermission(permissions, 'edit-branches');
        return (
          <button
            type="button"
            onClick={() => canToggle && handleAction('toggle-status', record)}
            disabled={!canToggle}
            title={
              canToggle
                ? isActive
                  ? t('Click to Deactivate')
                  : t('Click to Activate')
                : t('No permission to change status')
            }
            className={cn(
              'flex items-center gap-1.5 select-none border-none bg-transparent p-0',
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
    {
      key: 'created_at',
      label: t('Created'),
      sortable: true,
      render: (value: string) => (
        <span className="text-sm text-slate-600 tabular-nums">
          {window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()}
        </span>
      ),
    },
  ];

  const actions = [
    {
      label: t('Set as current'),
      icon: 'Building2',
      action: 'set-current',
      className: 'text-primary',
      requiredPermission: 'manage-branches',
      condition: (row: any) => !row.is_current && row.status === 'active',
    },
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-branches',
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-branches',
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-branches',
      isDisabled: (row: any) => !row.can_delete,
      disabledTitle: (row: any) =>
        row.delete_block_reason || t('This branch is in use and cannot be deleted.'),
    },
  ];

  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
  ];

  const formFields = [
    { name: 'name', label: t('Branch Name'), type: 'text' as const, required: true, colSpan: 2, row: 1 },
    { name: 'address', label: t('Address'), type: 'textarea' as const, colSpan: 2, row: 2 },
    { name: 'city', label: t('City'), type: 'text' as const, row: 3 },
    { name: 'state', label: t('State/Province'), type: 'text' as const, row: 3 },
    {
      name: 'wage_zone_id',
      label: t('Wage Zone'),
      type: 'select' as const,
      options: [
        { value: '', label: t('None') },
        ...wageZones.map((zone: { id: number; display_label: string }) => ({
          value: String(zone.id),
          label: zone.display_label,
        })),
      ],
      row: 3,
      colSpan: 2,
    },
    { name: 'country', label: t('Country'), type: 'text' as const, row: 4 },
    { name: 'zip_code', label: t('ZIP/Postal Code'), type: 'text' as const, row: 4 },
    { name: 'phone', label: t('Phone'), type: 'text' as const, validation: { pattern: '^[0-9]*$' }, row: 5 },
    { name: 'email', label: t('Email'), type: 'email' as const, row: 5 },
    { name: 'in_charge_name', label: t('Branch In-charge Name'), type: 'text' as const, row: 6 },
    {
      name: 'in_charge_contact',
      label: t('Branch In-charge Contact'),
      type: 'text' as const,
      validation: { pattern: '^[0-9]*$' },
      row: 6,
    },
    {
      name: 'status',
      label: t('Status'),
      type: 'select' as const,
      options: [
        { value: 'active', label: t('Active') },
        { value: 'inactive', label: t('Inactive') },
      ],
      defaultValue: 'active',
      row: 7,
    },
  ];

  return (
    <PageTemplate
      title={t('Branch Management')}
      url="/branches"
      actions={pageActions}
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Branches') },
      ]}
      noPadding
    >
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          <span>
            {t('Branches')}{' '}
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
              name: 'status',
              label: t('Status'),
              type: 'select',
              value: selectedStatus,
              onChange: setSelectedStatus,
              options: statusOptions,
            },
          ]}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          currentPerPage={pageFilters.per_page?.toString() || '10'}
          onPerPageChange={(value) => {
            router.get(
              route('hr.branches.index'),
              filterParams({ per_page: parseInt(value, 10) }),
              { preserveState: true, preserveScroll: true }
            );
          }}
        />
      </div>

      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={branches?.data || []}
          from={branches?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'view-branches',
            edit: 'edit-branches',
            delete: 'delete-branches',
          }}
        />
        <Pagination
          from={branches?.from || 0}
          to={branches?.to || 0}
          total={branches?.total || 0}
          links={branches?.links}
          entityName={t('branches')}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          fields: formFields,
          modalSize: '2xl',
          columns: 2,
          layout: 'grid',
        }}
        initialData={currentItem}
        title={
          formMode === 'create'
            ? t('Add New Branch')
            : formMode === 'edit'
              ? t('Edit Branch')
              : t('View Branch')
        }
        mode={formMode}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="branch"
      />

      <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>{t('Import Branches')}</DialogTitle>
          </DialogHeader>
          <div className="max-h-[60vh] overflow-y-auto pr-2">
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
              <p className="text-sm text-muted-foreground">
                <a
                  href="#"
                  onClick={(e) => {
                    e.preventDefault();
                    window.location.href = route('hr.branches.import.template');
                  }}
                  className="text-primary hover:underline"
                >
                  {t('Download Sample File')}
                </a>
              </p>
              {importErrors && (
                <div
                  className="bg-slate-50 border border-slate-200 px-4 py-3 rounded text-sm max-h-48 overflow-y-auto font-medium"
                  role="alert"
                  dangerouslySetInnerHTML={{ __html: importErrors }}
                />
              )}
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => handleImportModalOpenChange(false)}>
              {t('Cancel')}
            </Button>
            <Button onClick={handleImport} disabled={!importFile}>
              {t('Import')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
