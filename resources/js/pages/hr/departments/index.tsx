// pages/hr/departments/index.tsx
import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileUp, FileText, Copy, Users, Layers } from 'lucide-react';
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

export default function Departments() {
  const { t } = useTranslation();
  const { auth, departments, branches = [], stats = {}, activeBranchId, filters: pageFilters = {} } = usePage().props as any;
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
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  // Checkbox selection state
  const [selectedDepts, setSelectedDepts] = useState<number[]>([]);
const [selectedBranchIds, setSelectedBranchIds] = useState<number[]>([]);
  // Copy modal state
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
    ...extra,
  });

  const showBranchColumn =
    !sessionActiveBranchId || selectedBranch === 'all' || String(selectedBranch) !== String(sessionActiveBranchId);

  const defaultBranchFilter = sessionActiveBranchId ? String(sessionActiveBranchId) : 'all';

  const hasActiveFilters = () =>
    searchTerm !== '' ||
    selectedStatus !== defaultStatus ||
    selectedBranch !== defaultBranchFilter;

  const activeFilterCount = () =>
    (searchTerm ? 1 : 0) +
    (selectedStatus !== defaultStatus ? 1 : 0) +
    (selectedBranch !== defaultBranchFilter ? 1 : 0);

  const filteredBranchName =
    selectedBranch !== 'all'
      ? branches.find((b: any) => String(b.id) === selectedBranch)?.name
      : null;

  const activeBranchName = sessionActiveBranchId
    ? branches.find((b: any) => String(b.id) === String(sessionActiveBranchId))?.name
    : null;

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
    router.get(route('hr.departments.index'), listQueryParams({ search: undefined }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const applyFilters = () => {
    router.get(route('hr.departments.index'), listQueryParams(), { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    router.get(
      route('hr.departments.index'),
      listQueryParams({ sort_field: field, sort_direction: direction }),
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
      case 'delete':
        if (!item.can_delete) {
          toast.error(
            item.delete_block_reason ||
              t('This department is assigned to employees in this branch and cannot be deleted.')
          );
          return;
        }
        setIsDeleteModalOpen(true);
        break;
      case 'toggle-status':
        handleToggleStatus(item);
        break;
      case 'copy':
        setIsBulkCopy(false);
        setSelectedBranchIds([]);
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
    if (!importFile) { toast.error(t('Please select a file to import')); return; }
    setImportErrors(null);
    const formData = new FormData();
    formData.append('file', importFile);
    router.post(route('hr.departments.import'), formData, {
      onStart: () => toast.loading(t('Importing departments...')),
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash?.success) { handleImportModalOpenChange(false); toast.success(t(page.props.flash.success)); }
        else if (page.props.flash?.error) { setImportErrors(page.props.flash.error); }
        else { handleImportModalOpenChange(false); toast.success(t('Departments imported successfully')); }
      },
      onError: (errors: any) => {
        toast.dismiss();
        const msgs = Object.values(errors).flat();
        setImportErrors(msgs.length > 0 ? msgs.join('<br>') : t('Import failed.'));
      },
    });
  };

  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating department...'));
      router.post(route('hr.departments.store'), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          page.props.flash?.success ? toast.success(t(page.props.flash.success)) : page.props.flash?.error && toast.error(t(page.props.flash.error));
        },
        onError: (errors) => { toast.dismiss(); toast.error(t('Failed to create department: {{errors}}', { errors: Object.values(errors).join(', ') })); },
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating department...'));
      router.put(route('hr.departments.update', currentItem.id), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          page.props.flash?.success ? toast.success(t(page.props.flash.success)) : page.props.flash?.error && toast.error(t(page.props.flash.error));
        },
        onError: (errors) => { toast.dismiss(); toast.error(t('Failed to update department: {{errors}}', { errors: Object.values(errors).join(', ') })); },
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting department...'));
    router.delete(route('hr.departments.destroy', currentItem.id), {
      onSuccess: (page: any) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        page.props.flash?.success ? toast.success(t(page.props.flash.success)) : page.props.flash?.error && toast.error(t(page.props.flash.error));
      },
      onError: () => { toast.dismiss(); toast.error(t('Failed to delete department')); },
    });
  };

  const handleToggleStatus = (dept: any) => {
    const newStatus = dept.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} ${t('department')}...`);
    router.put(route('hr.departments.toggle-status', dept.id), {}, {
      onSuccess: (page: any) => {
        toast.dismiss();
        page.props.flash?.success ? toast.success(t(page.props.flash.success)) : page.props.flash?.error && toast.error(t(page.props.flash.error));
      },
      onError: () => { toast.dismiss(); toast.error(t('Failed to update status')); },
    });
  };

  // ── Copy to Branches ──────────────────────────────────────────────
  const handleCopyConfirm = () => {
    if (selectedBranchIds.length === 0) { toast.error(t('Please select at least one branch')); return; }
    setIsCopying(true);
    toast.loading(t('Copying departments...'));

    if (isBulkCopy) {
      router.post(route('hr.departments.bulk-copy'), {
        department_ids: selectedDepts,
        branch_ids: selectedBranchIds,
      }, {
        onSuccess: (page: any) => {
          setIsCopying(false); setIsCopyModalOpen(false); setSelectedDepts([]); setSelectedBranchIds([]);
          toast.dismiss();
          page.props.flash?.success ? toast.success(page.props.flash.success) : page.props.flash?.error && toast.error(page.props.flash.error);
        },
        onError: () => { setIsCopying(false); toast.dismiss(); toast.error(t('Failed to copy departments')); },
      });
    } else {
      router.post(route('hr.departments.copy-to-branches', currentItem.id), {
        branch_ids: selectedBranchIds,
      }, {
        onSuccess: (page: any) => {
          setIsCopying(false); setIsCopyModalOpen(false); setSelectedBranchIds([]);
          toast.dismiss();
          page.props.flash?.success ? toast.success(page.props.flash.success) : page.props.flash?.error && toast.error(page.props.flash.error);
        },
        onError: () => { setIsCopying(false); toast.dismiss(); toast.error(t('Failed to copy department')); },
      });
    }
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedBranch(defaultBranchFilter);
    setSelectedStatus(defaultStatus);
    setShowFilters(false);
    router.get(
      route('hr.departments.index'),
      {
        page: 1,
        per_page: pageFilters.per_page,
        status: defaultStatus,
        branch_id: defaultBranchFilter,
      },
      { preserveState: true, preserveScroll: true }
    );
  };

  // ── Page Actions ──────────────────────────────────────────────────
  const pageActions: any[] = [];

  if (hasPermission(permissions, 'create-departments')) {
    pageActions.push({
      label: t('Add Department'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default' as const,
      onClick: () => handleAddNew(),
    });

    if (selectedDepts.length > 0) {
      pageActions.push({
        label: `${t('Copy Selected')} (${selectedDepts.length})`,
        icon: <Copy className="h-4 w-4 mr-2" />,
        variant: 'secondary' as const,
        className: 'theme-bg hover:opacity-90 text-white border-none',
        onClick: () => { setIsBulkCopy(true); setSelectedBranchIds([]); setIsCopyModalOpen(true); },
      });
    }

    pageActions.push({
      label: t('Import Departments'),
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
    onClick: () => window.open(route('hr.reports.master_listing', { type: 'DPT' }), '_blank'),
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Department Management'), href: route('hr.departments.index') },
    { title: t('Departments') },
  ];

  const checkboxClass =
    'rounded border-slate-300 h-4 w-4 cursor-pointer accent-[var(--theme-color)] focus:ring-[color-mix(in_srgb,var(--theme-color)_40%,transparent)]';

  const tableColumns = [
    {
      key: 'select',
      label: (
        <input
          type="checkbox"
          className={checkboxClass}
          checked={departments?.data?.length > 0 && selectedDepts.length === departments.data.length}
          onChange={(e) => {
            e.target.checked
              ? setSelectedDepts(departments.data.map((d: any) => d.id))
              : setSelectedDepts([]);
          }}
        />
      ),
      render: (_: any, record: any) => (
        <input
          type="checkbox"
          className={checkboxClass}
          checked={selectedDepts.includes(record.id)}
          onChange={(e) => {
            e.target.checked
              ? setSelectedDepts(prev => [...prev, record.id])
              : setSelectedDepts(prev => prev.filter(id => id !== record.id));
          }}
        />
      ),
    },
    {
      key: 'name',
      label: t('Department'),
      sortable: true,
      render: (_: string, record: any) => (
        <div className="min-w-[8rem] max-w-[14rem]">
          <p className="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-tight truncate" title={record.name}>
            {record.name}
          </p>
          {record.short_code ? (
            <p className="font-mono text-[11px] text-slate-500 mt-0.5">{record.short_code}</p>
          ) : (
            <p className="text-[11px] text-slate-400 mt-0.5">—</p>
          )}
        </div>
      ),
    },
    {
      key: 'sanction_strength',
      label: t('Sanction'),
      sortable: true,
      render: (value: number | null, record: any) => {
        const sanction = value ?? record.sanction_strength;
        const current = record.employees_count ?? 0;
        if (sanction == null || sanction === '') {
          return <span className="text-slate-400 text-sm">—</span>;
        }
        const over = current > sanction;
        return (
          <div className="text-sm tabular-nums">
            <span className={cn('font-semibold', over && 'text-amber-600')}>{sanction}</span>
            <span className="block text-[10px] text-slate-500">
              {current} {t('present')}
            </span>
          </div>
        );
      },
    },
    {
      key: 'workforce',
      label: t('Workforce'),
      render: (_: unknown, record: any) => {
        const branchId = record.branch_id ?? record.branch?.id;
        const empCount = record.employees_count ?? 0;
        const desigCount = record.desginations_count ?? 0;

        return (
          <div className="flex items-center gap-3 text-sm tabular-nums">
            {hasPermission(permissions, 'view-employees') && branchId ? (
              <button
                type="button"
                title={t('View employees in this department')}
                onClick={() =>
                  router.get(route('hr.employees.index'), {
                    branch: branchId,
                    department: record.id,
                    status: 'active',
                  })
                }
                className="flex items-center gap-1 font-medium theme-color hover:opacity-80 hover:underline border-none bg-transparent p-0 cursor-pointer"
              >
                <Users className="h-3.5 w-3.5 shrink-0" style={{ color: 'var(--theme-color)' }} />
                {empCount}
              </button>
            ) : (
              <span className="text-slate-600">{empCount}</span>
            )}
            {hasPermission(permissions, 'view-designations') && branchId ? (
              <button
                type="button"
                title={t('View designations in this department')}
                onClick={() =>
                  router.get(route('hr.designations.index'), {
                    branch_id: branchId,
                    department: record.id,
                    status: 'all',
                  })
                }
                className="flex items-center gap-1 font-medium theme-color hover:opacity-80 hover:underline border-none bg-transparent p-0 cursor-pointer"
              >
                <Layers className="h-3.5 w-3.5 shrink-0" style={{ color: 'var(--theme-color)' }} />
                {desigCount}
              </button>
            ) : (
              <span className="text-slate-600">{desigCount}</span>
            )}
          </div>
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
      render: (_: any, record: any) => {
        const isActive = record.status === 'active';
        const canToggle =
          hasPermission(permissions, 'toggle-status-departments') || hasPermission(permissions, 'edit-departments');

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
              'flex items-center gap-1 select-none',
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

  // ── Table actions (NO toggle-status button — it's in the Status column) ──
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-departments',
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-departments',
    },
    {
      label: t('Copy to Branches'),
      icon: 'Copy',
      action: 'copy',
      className: 'text-purple-500',
      requiredPermission: 'create-departments',
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-departments',
      isDisabled: (row: any) => !row.can_delete,
      disabledTitle: (row: any) =>
        row.delete_block_reason ||
        t('This department is assigned to employees in this branch and cannot be deleted.'),
    },
  ];

  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
  ];

  const branchOptions = [
    { value: 'all', label: t('All Branches') },
    ...(branches || []).map((b: any) => ({
      value: b.id.toString(),
      label: b.name,
    })),
  ];

  // Branches available for copy (exclude the department's own branch)
  const availableBranches = isBulkCopy
    ? branches
    : branches.filter((b: any) => b.id !== currentItem?.branch_id);

  return (
    <PageTemplate
      title={t('Department Management')}
      url="/departments"
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
            {t('Showing departments for')}: <strong>{filteredBranchName}</strong>
          </span>
          <button
            type="button"
            onClick={() => {
              const activeId = String(sessionActiveBranchId);
              setSelectedBranch(activeId);
              router.get(route('hr.departments.index'), listQueryParams({ branch_id: activeId }), {
                preserveState: true,
                preserveScroll: true,
              });
            }}
            className="text-xs font-semibold underline border-none bg-transparent cursor-pointer hover:opacity-80"
            style={{ color: 'var(--theme-color)' }}
          >
            {t('Show active branch')}
            {activeBranchName ? ` (${activeBranchName})` : ''}
          </button>
        </div>
      )}

      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          {filteredBranchName && selectedBranch !== 'all' && (
            <>
              <span className="font-semibold text-slate-700 dark:text-slate-300">{filteredBranchName}</span>
              <span className="text-slate-300">·</span>
            </>
          )}
          <span>
            {t('Departments')}{' '}
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
          <span className="text-slate-300">·</span>
          <span>
            {t('Designations')}{' '}
            <span className="font-semibold tabular-nums text-slate-700">{stats.total_designations ?? 0}</span>
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
              options: branchOptions,
            },
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
              route('hr.departments.index'),
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
            data={departments?.data || []}
            from={departments?.from || 1}
            onAction={handleAction}
            sortField={pageFilters.sort_field}
            sortDirection={pageFilters.sort_direction}
            onSort={handleSort}
            permissions={permissions}
            dense
            stickyActions
            entityPermissions={{
              view: 'view-departments',
              edit: 'edit-departments',
              delete: 'delete-departments',
            }}
          />
        </div>
        <Pagination
          from={departments?.from || 0}
          to={departments?.to || 0}
          total={departments?.total || 0}
          links={departments?.links}
          entityName={t('departments')}
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
            { name: 'name', label: t('Department Name'), type: 'text', required: true },
            { name: 'short_code', label: t('Short Code'), type: 'text', required: true },
            {
              name: 'sanction_strength',
              label: t('Sanction Strength'),
              type: 'number',
              required: false,
              placeholder: t('Approved manpower for this department'),
              min: 0,
            },
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
          modalSize: 'lg',
        }}
        initialData={currentItem}
        title={
          formMode === 'create' ? t('Add New Department')
            : formMode === 'edit' ? t('Edit Department')
              : t('View Department')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="department"
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
            ? t('Copy {{count}} Departments to Branches', { count: selectedDepts.length })
            : t('Copy "{{name}}" to Branches', { name: currentItem?.name })
        }
        isLoading={isCopying}
      />

      {/* Import Modal */}
      <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>{t('Import Departments')}</DialogTitle>
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
              <div className="text-sm text-muted-foreground">
                <a
                  href="#"
                  onClick={(e) => { e.preventDefault(); window.location.href = route('hr.departments.import.template'); }}
                  className="text-primary hover:underline"
                >
                  {t('Download Sample File')}
                </a>
              </div>
              {importErrors && (
                <div className="bg-slate-50 border border-slate-200 px-4 py-3 rounded text-sm max-h-48 overflow-y-auto font-medium" role="alert">
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
