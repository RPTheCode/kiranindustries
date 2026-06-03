// pages/hr/attendance-records/index.tsx
import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, Clock, LogIn, LogOut } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { type PageAction } from '@/components/page-template';

export default function AttendanceRecords() {
  const { t } = useTranslation();
  const { auth, attendanceRecords, employees, branches, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedEmployee, setSelectedEmployee] = useState(pageFilters.employee_id || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [selectedBranch, setSelectedBranch] = useState(pageFilters.branch_id || auth?.active_branch_id || 'all');
  const [dateFrom, setDateFrom] = useState(pageFilters.date_from || '');
  const [dateTo, setDateTo] = useState(pageFilters.date_to || '');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  // Sync local filter state with props when global branch or URL filters change
  useEffect(() => {
    setSelectedBranch(pageFilters.branch_id || auth?.active_branch_id || 'all');
  }, [pageFilters.branch_id, auth?.active_branch_id]);

  // Check if any filters are active
  const hasActiveFilters = () => {
    return searchTerm !== '' || selectedEmployee !== 'all' || selectedStatus !== 'all' || (selectedBranch !== 'all' && selectedBranch != auth?.active_branch_id) || dateFrom !== '' || dateTo !== '';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (searchTerm ? 1 : 0) +
      (selectedEmployee !== 'all' ? 1 : 0) +
      (selectedStatus !== 'all' ? 1 : 0) +
      (selectedBranch !== 'all' && selectedBranch != auth?.active_branch_id ? 1 : 0) +
      (dateFrom ? 1 : 0) +
      (dateTo ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const applyFilters = () => {
    router.get(route('hr.attendance-records.index'), {
      page: 1,
      search: searchTerm || undefined,
      employee_id: selectedEmployee !== 'all' ? selectedEmployee : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.attendance-records.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      employee_id: selectedEmployee !== 'all' ? selectedEmployee : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      branch_id: selectedBranch,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
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
    }
  };

  const handleAddNew = () => {
    setCurrentItem(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating attendance record...'));

      router.post(route('hr.attendance-records.store'), formData, {
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
            toast.error(errors);
          } else {
            toast.error(`Failed to create attendance record: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating attendance record...'));

      router.put(route('hr.attendance-records.update', currentItem.id), formData, {
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
            toast.error(errors);
          } else {
            toast.error(`Failed to update attendance record: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting attendance record...'));

    router.delete(route('hr.attendance-records.destroy', currentItem.id), {
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
          toast.error(errors);
        } else {
          toast.error(`Failed to delete attendance record: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedEmployee('all');
    setSelectedStatus('all');
    setSelectedBranch('all');
    setDateFrom('');
    setDateTo('');
    setShowFilters(false);

    router.get(route('hr.attendance-records.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  // Define page actions
  const pageActions: PageAction[] = [];

  // Add the "Add New Record" button if user has permission
  if (hasPermission(permissions, 'create-attendance-records')) {
    pageActions.push({
      label: t('Add Record'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: () => handleAddNew()
    });
  }

  // Add the "Daily Report" button
  pageActions.push({
    label: t('Attendance Report'),
    icon: <Clock className="h-4 w-4 mr-2" />,
    variant: 'outline',
    onClick: () => {
      const formatDate = (date: any) => {
        if (!date) return '';
        const d = typeof date === 'string' ? new Date(date) : date;
        if (d && !isNaN(d.getTime())) {
          return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
        }
        return date;
      };

      const params = new URLSearchParams();
      if (selectedBranch) params.append('branch_id', selectedBranch);
      if (selectedEmployee && selectedEmployee !== 'all') params.append('employee_id', selectedEmployee);
      if (dateFrom) params.append('date_from', formatDate(dateFrom));
      if (dateTo) params.append('date_to', formatDate(dateTo));

      window.location.href = route('hr.attendance-records.export-daily') + '?' + params.toString();
    }
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Shift Management'), href: route('hr.attendance-records.index') },
    { title: t('Attendance Records') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'employee',
      label: t('Employee'),
      render: (value: any, row: any) => (
        <div className="flex flex-col">
          <span className="font-medium">
            {row.employee?.name || row.employee_id || '-'}
            {row.employee?.employee?.employee_id ? ` (${row.employee.employee.employee_id})` : ''}
          </span>
          <span className="text-xs text-gray-500">{row.employee?.email}</span>
        </div>
      )
    },
    {
      key: 'branch.name',
      label: t('Branch'),
      sortable: false,
      render: (value: string, row: any) => (
        <span>{row.branch?.name || '-'}</span>
      )
    },
    {
      key: 'date',
      label: t('Date'),
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    },
    {
      key: 'shift',
      label: t('Shift'),
      render: (value: any, row: any) => row.shift?.name || '-'
    },
    {
      key: 'clock_in',
      label: t('Clock In'),
      render: (value: string) => (

        <span className="font-mono text-green-600">{window.appSettings.formatTime(value) || '-'}</span>
      )
    },
    {
      key: 'clock_out',
      label: t('Clock Out'),
      render: (value: string) => (
        <span className="font-mono text-red-600">{window.appSettings.formatTime(value) || '-'}</span>
      )
    },
    {
      key: 'total_hours',
      label: t('Total Hours'),
      render: (value: number) => (
        <span className="font-mono">{window.appSettings.formatDuration(value)}h</span>
      )
    },
    {
      key: 'overtime_hours',
      label: t('Overtime'),
      render: (value: number, row: any) => (
        <div className="text-sm">
          <span className={`font-mono ${value > 0 ? 'text-orange-600' : 'text-gray-500'}`}>
            {window.appSettings.formatDuration(value)}h
          </span>
          {value > 0 && (
            <div className="flex flex-col text-[10px] space-y-0.5 mt-1 leading-tight">
              {(row.attendancePolicy?.overtime_type === 'salary_based' || row.attendance_policy?.overtime_type === 'salary_based') ? (
                <>
                  {row.overtime_amount_basic > 0 && (
                    <span className="text-gray-500">
                      {t('Basic')}: {window.appSettings?.formatCurrency(row.overtime_amount_basic)}
                    </span>
                  )}
                  {row.overtime_amount_minimum > 0 && (
                    <span className="text-green-600 font-medium">
                      {t('Min. Wage')}: {window.appSettings?.formatCurrency(row.overtime_amount_minimum)}
                    </span>
                  )}
                </>
              ) : (
                row.overtime_amount > 0 && (
                  <span className="text-green-600">
                    {window.appSettings?.formatCurrency(row.overtime_amount)}
                  </span>
                )
              )}
            </div>
          )}
        </div>
      )
    },
    {
      key: 'shortfall_hours',
      label: t('Shortfall'),
      render: (value: number, row: any) => (
        <div className="text-sm">
          <span className={`font-mono ${value > 0 ? 'text-red-600' : 'text-gray-500'}`}>
            {window.appSettings.formatDuration(value)}h
          </span>
          {value > 0 && (
            <div className="flex flex-col text-[10px] space-y-0.5 mt-1 leading-tight">
              {(row.attendancePolicy?.overtime_type === 'salary_based' || row.attendance_policy?.overtime_type === 'salary_based') ? (
                <>
                  {row.shortfall_amount_basic > 0 && (
                    <span className="text-gray-500">
                      {t('Basic')}: {window.appSettings?.formatCurrency(row.shortfall_amount_basic)}
                    </span>
                  )}
                  {row.shortfall_amount_minimum > 0 && (
                    <span className="text-red-600 font-medium">
                      {t('Min. Wage')}: {window.appSettings?.formatCurrency(row.shortfall_amount_minimum)}
                    </span>
                  )}
                </>
              ) : (
                row.shortfall_amount > 0 && (
                  <span className="text-red-600">
                    {window.appSettings?.formatCurrency(row.shortfall_amount)}
                  </span>
                )
              )}
            </div>
          )}
        </div>
      )
    },
    {
      key: 'status',
      label: t('Status'),
      render: (value: string, row: any) => {
        const statusConfig = {
          present: {
            label: t('Present'),
            className: 'bg-green-50 text-green-700 ring-green-600/20'
          },
          absent: {
            label: t('Absent'),
            className: 'bg-red-50 text-red-700 ring-red-600/20'
          },
          half_day: {
            label: t('Half Day'),
            className: 'bg-yellow-50 text-yellow-700 ring-yellow-600/20'
          },
          on_leave: {
            label: row.leave_type ? `${t('On Leave')} (${row.leave_type.name})` : t('On Leave'),
            className: 'bg-blue-50 text-blue-700 ring-blue-600/20'
          },
          holiday: {
            label: t('Holiday'),
            className: 'bg-purple-50 text-purple-700 ring-purple-600/20'
          }
        };

        const config = statusConfig[value as keyof typeof statusConfig] || {
          label: value || '-',
          className: 'bg-gray-50 text-gray-700 ring-gray-600/20'
        };

        return (
          <div className="flex items-center gap-2">
            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${config.className}`}>
              {value === 'on_leave' ? t('On Leave') : config.label}
            </span>
            {value === 'on_leave' && row.leave_type && (
              <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                {row.leave_type.name}
              </span>
            )}
            {row.is_late && (
              <span className="inline-flex items-center rounded-md px-1 py-0.5 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">
                {t('Late')}
              </span>
            )}
            {row.is_early_departure && (
              <span className="inline-flex items-center rounded-md px-1 py-0.5 text-xs font-medium bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20">
                {t('Early')}
              </span>
            )}
          </div>
        );
      }
    }
  ];

  // Define table actions
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-attendance-records'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-attendance-records'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-attendance-records'
    }
  ];

  // Prepare options for filters and forms
  const employeeOptions = [
    { value: 'all', label: t('All Employees') },
    ...(employees || []).map((emp: any) => ({
      value: emp.id.toString(),
      label: emp.employee_id ? `${emp.name} (${emp.employee_id})` : emp.name
    }))
  ];

  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'present', label: t('Present') },
    // { value: 'absent', label: t('Absent') },
    { value: 'half_day', label: t('Half Day') },
    // { value: 'on_leave', label: t('On Leave') },
    { value: 'holiday', label: t('Holiday') }
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
      title={t("Attendance Record Management")}
      url="/attendance-records"
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
              name: 'employee_id',
              label: t('Employee'),
              type: 'combobox',
              value: selectedEmployee,
              onChange: setSelectedEmployee,
              options: employeeOptions
            },
            {
              name: 'status',
              label: t('Status'),
              type: 'combobox',
              value: selectedStatus,
              onChange: setSelectedStatus,
              options: statusOptions
            },
            {
              name: 'branch_id',
              label: t('Branch'),
              type: 'combobox',
              value: selectedBranch,
              onChange: setSelectedBranch,
              options: branchOptions
            },
            {
              name: 'date_from',
              label: t('Date From'),
              type: 'date',
              value: dateFrom,
              onChange: setDateFrom
            },
            {
              name: 'date_to',
              label: t('Date To'),
              type: 'date',
              value: dateTo,
              onChange: setDateTo
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
            router.get(route('hr.attendance-records.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              employee_id: selectedEmployee !== 'all' ? selectedEmployee : undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              branch_id: selectedBranch,
              date_from: dateFrom || undefined,
              date_to: dateTo || undefined
            }, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      {/* Content section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={attendanceRecords?.data || []}
          from={attendanceRecords?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'view-attendance-records',
            edit: 'edit-attendance-records',
            delete: 'delete-attendance-records'
          }}
        />

        {/* Pagination section */}
        <Pagination
          from={attendanceRecords?.from || 0}
          to={attendanceRecords?.to || 0}
          total={attendanceRecords?.total || 0}
          links={attendanceRecords?.links}
          entityName={t("attendance records")}
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
              name: 'employee_id',
              label: t('Employee'),
              type: 'combobox',
              options: employees?.map((e: any) => ({
                value: e.id,
                label: e.employee_id ? `${e.name} (${e.employee_id})` : e.name
              })) || [],
              required: true,
              placeholder: t('Select Employee')
            },
            { name: 'date', label: t('Date'), type: 'date', required: true, defaultValue: new Date().toLocaleDateString('en-CA') },
            { name: 'clock_in', label: t('Clock In'), type: 'time', width: '100%' },
            { name: 'clock_out', label: t('Clock Out Time'), type: 'time' },
            { name: 'break_hours', label: t('Break Hours'), type: 'number', min: 0, step: 0.5, defaultValue: 1 },
            {
              name: 'status',
              label: t('Status'),
              type: 'select',
              required: true,
              options: [
                { value: 'present', label: t('Present') },
                // { value: 'absent', label: t('Absent') },
                { value: 'half_day', label: t('Half Day') },
                // { value: 'on_leave', label: t('On Leave') },
                { value: 'holiday', label: t('Holiday') },
              ]
            },
            // { name: 'is_holiday', label: t('Holiday'), type: 'checkbox', defaultValue: false },
            { name: 'notes', label: t('Notes'), type: 'textarea' }
          ],
          modalSize: 'lg'
        }}
        initialData={currentItem}
        title={
          formMode === 'create'
            ? t('Add New Attendance Record')
            : formMode === 'edit'
              ? t('Edit Attendance Record')
              : t('View Attendance Record')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.employee?.name || ''}
        entityName="attendance record"
      />
    </PageTemplate>
  );
}
