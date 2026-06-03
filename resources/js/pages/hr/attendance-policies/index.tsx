// pages/hr/attendance-policies/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function AttendancePolicies() {
  const { t } = useTranslation();
  const { auth, attendancePolicies, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [selectedOvertimeCalculation, setSelectedOvertimeCalculation] = useState(pageFilters.overtime_calculation || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  const pageActions = [
    {
      label: t('Add Policy'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      onClick: () => handleAddNew(),
      variant: 'default' as const,
      requiredPermission: 'create-attendance-policies'
    }
  ];

  // Check if any filters are active
  const hasActiveFilters = () => {
    return searchTerm !== '' || selectedStatus !== 'all';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const applyFilters = () => {
    router.get(route('hr.attendance-policies.index'), {
      page: 1,
      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.attendance-policies.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      // branch: selectedBranch !== 'all' ? selectedBranch : undefined,
      overtime_calculation: selectedOvertimeCalculation !== 'all' ? selectedOvertimeCalculation : undefined,
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
    }
  };

  const handleAddNew = () => {
    setCurrentItem(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating attendance policy...'));

      router.post(route('hr.attendance-policies.store'), formData, {
        onSuccess: (page) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if ((page.props as any).flash.success) {
            toast.success(t((page.props as any).flash.success));
          } else if ((page.props as any).flash.error) {
            toast.error(t((page.props as any).flash.error));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(`Failed to create attendance policy: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating attendance policy...'));

      router.put(route('hr.attendance-policies.update', currentItem.id), formData, {
        onSuccess: (page) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if ((page.props as any).flash.success) {
            toast.success(t((page.props as any).flash.success));
          } else if ((page.props as any).flash.error) {
            toast.error(t((page.props as any).flash.error));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(`Failed to update attendance policy: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting attendance policy...'));

    router.delete(route('hr.attendance-policies.destroy', currentItem.id), {
      onSuccess: (page) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if ((page.props as any).flash.success) {
          toast.success(t((page.props as any).flash.success));
        } else if ((page.props as any).flash.error) {
          toast.error(t((page.props as any).flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(`Failed to delete attendance policy: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  const handleToggleStatus = (policy: any) => {
    const newStatus = policy.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} attendance policy...`);

    router.put(route('hr.attendance-policies.toggle-status', policy.id), {}, {
      onSuccess: (page) => {
        toast.dismiss();
        if ((page.props as any).flash.success) {
          toast.success(t((page.props as any).flash.success));
        } else if ((page.props as any).flash.error) {
          toast.error(t((page.props as any).flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(`Failed to update attendance policy status: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedStatus('all');
    setShowFilters(false);

    router.get(route('hr.attendance-policies.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };


  // Define table actions
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-attendance-policies'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-attendance-policies'
    },
    {
      label: t('Toggle Status'),
      icon: 'Lock',
      action: 'toggle-status',
      className: 'text-amber-500',
      requiredPermission: 'edit-attendance-policies'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-attendance-policies'
    }
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Shift Management'), href: route('hr.attendance-policies.index') },
    { title: t('Attendance Policies') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'name',
      label: t('Policy Name'),
      sortable: true
    },
    // Branch column removed as it is implied by context
    {
      key: 'late_arrival_grace',
      label: t('Late Grace (mins)'),
      render: (value: number) => (
        <span className="font-mono text-orange-600">{value}</span>
      )
    },
    {
      key: 'early_departure_grace',
      label: t('Early Grace (mins)'),
      render: (value: number) => (
        <span className="font-mono text-blue-600">{value}</span>
      )
    },
    {
      key: 'overtime_rate_per_hour',
      label: t('Overtime Rate'),
      render: (value: number) => (
        <span className="font-mono text-green-600">{window.appSettings?.formatCurrency(value)}/hr</span>
      )
    },
    {
      key: 'status',
      label: t('Status'),
      render: (value: string) => {
        return (
          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${value === 'active'
            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
            : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
            }`}>
            {value === 'active' ? t('Active') : t('Inactive')}
          </span>
        );
      }
    }
  ];
  // Prepare options for filters
  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') }
  ];



  return (
    <PageTemplate
      title={t("Attendance Policy Management")}
      url="/attendance-policies"
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
          filters={[
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
            router.get(route('hr.attendance-policies.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              overtime_calculation: selectedOvertimeCalculation !== 'all' ? selectedOvertimeCalculation : undefined
            }, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      {/* Content section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={attendancePolicies?.data || []}
          from={attendancePolicies?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'view-attendance-policies',
            edit: 'edit-attendance-policies',
            delete: 'delete-attendance-policies'
          }}
        />

        {/* Pagination section */}
        <Pagination
          from={attendancePolicies?.from || 0}
          to={attendancePolicies?.to || 0}
          total={attendancePolicies?.total || 0}
          links={attendancePolicies?.links}
          entityName={t("attendance policies")}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      {/* Form Modal */}
      <AttendancePolicyForm
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        attendancePolicy={currentItem}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="attendance policy"
      />
    </PageTemplate>
  );
}

function AttendancePolicyForm({ isOpen, onClose, attendancePolicy }: { isOpen: boolean; onClose: () => void; attendancePolicy?: any }) {
  const { t } = useTranslation();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: '',
    description: '',
    late_arrival_grace: 0,
    early_departure_grace: 0,
    overtime_type: 'fixed',
    overtime_rate_per_hour: 0,
    weekoff_full_day_hours: 6,
    status: 'active',
    branch_id: ''
  });

  useEffect(() => {
    if (attendancePolicy) {
      setData({
        name: attendancePolicy.name || '',
        description: attendancePolicy.description || '',
        late_arrival_grace: attendancePolicy.late_arrival_grace || 0,
        early_departure_grace: attendancePolicy.early_departure_grace || 0,
        overtime_type: attendancePolicy.overtime_type || 'fixed',
        overtime_rate_per_hour: attendancePolicy.overtime_rate_per_hour || 0,
        weekoff_full_day_hours: attendancePolicy.weekoff_full_day_hours || 6,
        status: attendancePolicy.status || 'active',
        branch_id: attendancePolicy.branch_id || ''
      });
    } else {
      reset();
    }
  }, [attendancePolicy, isOpen]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (attendancePolicy) {
      put(route('hr.attendance-policies.update', attendancePolicy.id), {
        onSuccess: () => onClose()
      });
    } else {
      post(route('hr.attendance-policies.store'), {
        onSuccess: () => onClose()
      });
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>{attendancePolicy ? t('Edit Attendance Policy') : t('Add Attendance Policy')}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">{t('Policy Name')}</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              required
            />
            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">{t('Description')}</Label>
            <Textarea
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
            />
            {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="late_arrival_grace">{t('Late Arrival Grace (mins)')}</Label>
              <Input
                id="late_arrival_grace"
                type="number"
                min="0"
                value={data.late_arrival_grace}
                onChange={(e) => {
                  const val = e.target.value;
                  if (val === '') {
                    setData('late_arrival_grace', '' as any);
                    return;
                  }
                  const num = parseInt(val) || 0;
                  setData('late_arrival_grace', Math.max(0, num));
                }}
              />
              {errors.late_arrival_grace && <p className="text-sm text-destructive">{errors.late_arrival_grace}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="early_departure_grace">{t('Early Departure Grace (mins)')}</Label>
              <Input
                id="early_departure_grace"
                type="number"
                min="0"
                value={data.early_departure_grace}
                onChange={(e) => {
                  const val = e.target.value;
                  if (val === '') {
                    setData('early_departure_grace', '' as any);
                    return;
                  }
                  const num = parseInt(val) || 0;
                  setData('early_departure_grace', Math.max(0, num));
                }}
              />
              {errors.early_departure_grace && <p className="text-sm text-destructive">{errors.early_departure_grace}</p>}
            </div>
          </div>

          <div className="space-y-2">
            <Label>{t('Overtime Type')}</Label>
            <RadioGroup
              value={data.overtime_type}
              onValueChange={(value) => setData('overtime_type', value)}
              className="flex items-center space-x-4"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="fixed" id="ot_fixed" />
                <Label htmlFor="ot_fixed">{t('Fixed Amount')}</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="salary_based" id="ot_salary" />
                <Label htmlFor="ot_salary">{t('Salary Based')}</Label>
              </div>
            </RadioGroup>
            {errors.overtime_type && <p className="text-sm text-destructive">{errors.overtime_type}</p>}
          </div>

          {data.overtime_type === 'fixed' && (
            <div className="space-y-2">
              <Label htmlFor="overtime_rate_per_hour">{t('Overtime Rate Per Hour')}</Label>
              <Input
                id="overtime_rate_per_hour"
                type="number"
                min="0"
                step="0.01"
                value={data.overtime_rate_per_hour}
                onChange={(e) => {
                  const val = e.target.value;
                  if (val === '') {
                    setData('overtime_rate_per_hour', '' as any);
                    return;
                  }
                  const num = parseFloat(val) || 0;
                  setData('overtime_rate_per_hour', Math.max(0, num));
                }}
                required
              />
              {errors.overtime_rate_per_hour && <p className="text-sm text-destructive">{errors.overtime_rate_per_hour}</p>}
            </div>
          )}
          <div className="bg-red-50 p-3 rounded-md text-sm text-red-600 mt-2 border border-red-100">
            <p><strong>{t('Note')}:</strong> {t("If 'Fixed Amount' is selected, everyone gets the same overtime amount. If 'Salary Based' is selected, overtime is calculated based on each employee's salary per hour.")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="weekoff_full_day_hours">{t('Week Off Full Day Hours')}</Label>
            <Input
              id="weekoff_full_day_hours"
              type="number"
              min="0"
              value={data.weekoff_full_day_hours}
              onChange={(e) => {
                const val = e.target.value;
                if (val === '') {
                  setData('weekoff_full_day_hours', '' as any);
                  return;
                }
                const num = parseInt(val) || 0;
                setData('weekoff_full_day_hours', Math.max(0, num));
              }}
              required
            />
            {errors.weekoff_full_day_hours && <p className="text-sm text-destructive">{errors.weekoff_full_day_hours}</p>}
          </div>

          <div className="space-y-2">
            <Label>{t('Status')}</Label>
            <RadioGroup
              value={data.status}
              onValueChange={(value) => setData('status', value)}
              className="flex items-center space-x-4"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="active" id="active" />
                <Label htmlFor="active">{t('Active')}</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="inactive" id="inactive" />
                <Label htmlFor="inactive">{t('Inactive')}</Label>
              </div>
            </RadioGroup>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              {t('Cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? t('Saving...') : t('Save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}