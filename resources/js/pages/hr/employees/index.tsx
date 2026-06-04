// pages/hr/employees/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { FileUp, Plus, Eye, Edit, Trash2, Lock, Unlock, MoreHorizontal, Key, Building, Briefcase, FileText, Calculator } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { CrudFormModal } from '@/components/CrudFormModal';
import { getImagePath } from '@/utils/helpers';

export default function Employees() {
  const { t } = useTranslation();
  const { auth, employees, branches, planLimits, departments, designations, categories, skills, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const getInitials = useInitials();
  const defaultStatus = 'active';

  // State
  const [activeView, setActiveView] = useState<'list' | 'grid'>('list');
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedBranch, setSelectedBranch] = useState(pageFilters.branch || 'all');
  const [selectedDepartment, setSelectedDepartment] = useState(pageFilters.department || 'all');
  const [selectedDesignation, setSelectedDesignation] = useState(pageFilters.designation || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status ?? defaultStatus);
  const [selectedCategory, setSelectedCategory] = useState(pageFilters.category || 'all');
  const [selectedSkill, setSelectedSkill] = useState(pageFilters.skill || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);
  const [isImportModalOpen, setIsImportModalOpen] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [importErrors, setImportErrors] = useState<string | null>(null);

  // Check if any filters are active
  const hasActiveFilters = () => {
    return selectedBranch !== 'all' || selectedDepartment !== 'all' || selectedDesignation !== 'all' || selectedStatus !== defaultStatus || selectedCategory !== 'all' || selectedSkill !== 'all' || searchTerm !== '';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (selectedBranch !== 'all' ? 1 : 0) +
      (selectedDepartment !== 'all' ? 1 : 0) +
      (selectedDesignation !== 'all' ? 1 : 0) +
      (selectedStatus !== defaultStatus ? 1 : 0) +
      (selectedCategory !== 'all' ? 1 : 0) +
      (selectedSkill !== 'all' ? 1 : 0) +
      (searchTerm ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSearchClear = () => {
    setSearchTerm('');
    router.get(route('hr.employees.index'), {
      page: 1,
      search: undefined,
      branch: selectedBranch !== 'all' ? selectedBranch : undefined,
      department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
      designation: selectedDesignation !== 'all' ? selectedDesignation : undefined,
      status: selectedStatus,
      category: selectedCategory !== 'all' ? selectedCategory : undefined,
      skill: selectedSkill !== 'all' ? selectedSkill : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const applyFilters = () => {
    router.get(route('hr.employees.index'), {
      page: 1,
      search: searchTerm || undefined,
      branch: selectedBranch !== 'all' ? selectedBranch : undefined,
      department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
      designation: selectedDesignation !== 'all' ? selectedDesignation : undefined,
      status: selectedStatus,
      category: selectedCategory !== 'all' ? selectedCategory : undefined,
      skill: selectedSkill !== 'all' ? selectedSkill : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.employees.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
      designation: selectedDesignation !== 'all' ? selectedDesignation : undefined,
      status: selectedStatus,
      category: selectedCategory !== 'all' ? selectedCategory : undefined,
      skill: selectedSkill !== 'all' ? selectedSkill : undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);

    switch (action) {
      case 'view':
        router.get(route('hr.employees.show', item.employee?.id || item.id));
        break;
      case 'edit':
        router.get(route('hr.employees.edit', item.employee?.id || item.id));
        break;
      case 'delete':
        setIsDeleteModalOpen(true);
        break;
      case 'toggle-status':
        handleToggleStatus(item);
        break;
      case 'change-password':
        setIsPasswordModalOpen(true);
        break;
      case 'monthly-incentive':
        router.get(route('hr.monthly-incentives.index', { employee_id: item.employee?.id || item.id }));
        break;
      case 'export':
        window.open(route('hr.employees.export', item.employee?.user_id || item.id), '_blank');
        break;
    }
  };

  const handleAddNew = () => {
    router.get(route('hr.employees.create'));
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting employee...'));

    router.delete(route('hr.employees.destroy', currentItem.id), {
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
          toast.error(t('Failed to delete employee: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleToggleStatus = (employee: any) => {
    const currentStatus = employee.status || 'inactive';
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} employee...`);

    router.put(route('hr.employees.toggle-status', employee.employee?.id || employee.id), {}, {
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
          toast.error(t('Failed to update employee status: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handlePasswordChange = (formData: any) => {
    toast.loading(t('Changing password...'));

    router.put(route('hr.employees.change-password', currentItem.employee?.id || currentItem.id), formData, {
      onSuccess: (page: any) => {
        setIsPasswordModalOpen(false);
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
          toast.error(t('Failed to change password: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedBranch('all');
    setSelectedDepartment('all');
    setSelectedDesignation('all');
    setSelectedStatus(defaultStatus);
    setSelectedCategory('all');
    setSelectedSkill('all');
    setShowFilters(false);

    router.get(route('hr.employees.index'), {
      page: 1,
      status: defaultStatus,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const ALLOWED_IMPORT_EXTENSIONS = ['.xlsx', '.xls', '.csv'];

  const validateImportFile = (file: File): string | null => {
    const fileName = file.name.toLowerCase();
    const extension = fileName.includes('.') ? fileName.slice(fileName.lastIndexOf('.')) : '';

    if (!ALLOWED_IMPORT_EXTENSIONS.includes(extension)) {
      return t('Invalid file format. Please upload an Excel file (.xlsx, .xls) or CSV (.csv) only.');
    }

    if (file.size === 0) {
      return t('The selected file is empty. Please choose a valid Excel or CSV file.');
    }

    return null;
  };

  const handleImportModalOpenChange = (open: boolean) => {
    setIsImportModalOpen(open);
    if (!open) {
      setTimeout(() => {
        setImportErrors(null);
        setImportFile(null);
      }, 300); // Small delay to allow animation to finish
    }
  };

  const handleImportFileChange = (file: File | null) => {
    setImportFile(file);
    setImportErrors(null);

    if (!file) {
      return;
    }

    const validationError = validateImportFile(file);
    if (validationError) {
      setImportErrors(validationError);
    }
  };

  const handleImport = (e: React.FormEvent) => {
    e.preventDefault();
    if (!importFile) {
      toast.error(t('Please select a file to import'));
      return;
    }

    const validationError = validateImportFile(importFile);
    if (validationError) {
      setImportErrors(validationError);
      return;
    }

    setImportErrors(null); // Clear previous errors

    const formData = new FormData();
    formData.append('file', importFile);

    router.post(route('hr.employees.import'), formData, {
      onStart: () => {
        toast.loading(t('Importing employees...'));
      },
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash?.success) {
          handleImportModalOpenChange(false);
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash?.error) {
          // Show error INSIDE modal
          setImportErrors(page.props.flash.error);
        } else {
          handleImportModalOpenChange(false);
          toast.success(t('Employees imported successfully'));
        }
      },
      onError: (errors: Record<string, string | string[]>) => {
        toast.dismiss();
        let errorMessage = '';

        if (errors?.file) {
          errorMessage = Array.isArray(errors.file) ? errors.file.join('<br>') : errors.file;
        } else if (errors?.error) {
          errorMessage = Array.isArray(errors.error) ? errors.error.join('<br>') : String(errors.error);
        } else if (typeof errors === 'string') {
          errorMessage = errors;
        } else {
          const errorMessages = Object.values(errors).flat();
          if (errorMessages.length > 0) {
            errorMessage = errorMessages.map((message) => String(message)).join('<br>');
          } else {
            errorMessage = t('Import failed. Please check the file format and try again.');
          }
        }

        setImportErrors(errorMessage);
      },
    });
  };

  // Define page actions
  const pageActions = [];

  // Add the "Add New Employee" button if user has permission
  if (hasPermission(permissions, 'create-employees')) {
    const canCreate = !planLimits || planLimits.can_create;
    pageActions.push({
      label: planLimits && !canCreate ? t('Employee Create Limit Reached ({{current}}/{{max}})', { current: planLimits.current_users, max: planLimits.max_users }) : t('Add Employee'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: canCreate ? 'default' : 'outline',
      onClick: canCreate ? () => handleAddNew() : () => toast.error(t('Employee limit exceeded. Your plan allows maximum {{max}} users. Please upgrade your plan.', { max: planLimits.max_users })),
      disabled: !canCreate
    });
  }

  if (hasPermission(permissions, 'create-employees')) {
    pageActions.push({
      label: t('Import Employees'),
      icon: <FileUp className="h-4 w-4 mr-2" />,
      variant: 'outline',
      className: 'border-primary text-primary hover:bg-primary/5',
      onClick: () => setIsImportModalOpen(true),
    });
  }

  // Add the PDF Report Download button
  pageActions.push({
    label: t('Download PDF Report'),
    icon: <FileText className="h-4 w-4 mr-2" />,
    variant: 'outline',
    className: 'border-red-500 text-red-500 hover:bg-red-50',
    onClick: () => window.open(route('hr.employees.report.pdf', { branch_id: selectedBranch }), '_blank'),
  });


  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Employees') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'name',
      label: t('Name'),
      sortable: true,
      render: (value: any, row: any) => {
        return (
          <div className="flex items-center gap-3 py-1.5 min-w-[200px]">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-primary font-black text-xs overflow-hidden border-2 border-white shadow-sm ring-1 ring-slate-200/50 transition-transform group-hover:scale-105">
              {row.avatar ? (
                <img src={getImagePath(row.avatar)} alt={row.name} className="h-full w-full object-cover" />
              ) : (
                getInitials(row.name)
              )}
            </div>
            <div className="flex flex-col min-w-0">
              <div className="font-bold text-sm text-slate-800 leading-tight truncate group-hover:text-primary transition-colors">
                {row.name}
              </div>
              <div className="text-[10px] font-extrabold text-primary truncate uppercase tracking-widest mt-1 bg-primary/5 px-2 py-0.5 rounded-md border border-primary/10 w-fit">
                CODE: {row.employee?.employee_id || t('N/A')}
              </div>
            </div>
          </div>
        );
      }
    },
    {
      key: 'branch',
      label: t('Branch'),
      sortable: false,
      render: (value: any, row: any) => {
        return row.employee?.branch?.name || '-';
      }
    },
    {
      key: 'department',
      label: t('Department'),
      sortable: false,
      render: (value: any, row: any) => {
        return row.employee?.department?.name || '-';
      }
    },
    {
      key: 'category',
      label: t('Category'),
      sortable: false,
      render: (value: any, row: any) => {
        return row.employee?.category?.name || '-';
      }
    },
    {
      key: 'status',
      label: t('Status'),
      sortable: false,
      render: (value: any, row: any) => {
        const isActive = row.status === 'active';
        return (
          <button
            onClick={(e) => {
              e.stopPropagation();
              handleAction('toggle-status', row);
            }}
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
      }
    },
    {
      key: 'date_of_joining',
      label: t('Joined'),
      sortable: false,
      render: (value: any, row: any) => {
        const joinDate = row.employee?.date_of_joining;
        return joinDate ? (window.appSettings?.formatDateTime(joinDate, false) || new Date(joinDate).toLocaleDateString()) : '-';
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
      requiredPermission: 'view-employees'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-employees'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-employees'
    },
    {
      label: t('Monthly Incentive'),
      icon: 'Calculator',
      action: 'monthly-incentive',
      className: 'text-green-500',
      requiredPermission: 'view-employees'
    },
    {
      label: t('Export Profile (PDF)'),
      icon: 'FileText',
      action: 'export',
      className: 'text-red-500',
      requiredPermission: 'view-employees'
    }
  ];

  // Prepare filter options
  const departmentOptions = [
    { value: 'all', label: t('All Departments') },
    ...(departments || []).map((department: any) => ({
      value: department.id.toString(),
      label: department.name
    }))
  ];

  const designationOptions = [
    { value: 'all', label: t('All Designations') },
    ...(designations || []).map((designation: any) => ({
      value: designation.id.toString(),
      label: `${designation.name} - ${designation.department?.name || t('No Department')}`
    }))
  ];

  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
    // { value: 'probation', label: t('Probation') },
    { value: 'terminated', label: t('Terminated') }
  ];

  const skillOptions = [
    { value: 'all', label: t('All Skills') },
    ...(skills || []).map((skill: any) => ({
      value: skill.id.toString(),
      label: skill.name
    }))
  ];

  const categoryOptions = [
    { value: 'all', label: t('All Categories') },
    ...(categories || []).map((category: any) => ({
      value: category.id.toString(),
      label: category.name
    }))
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
      title={t("Employee Management")}
      url="/employees"
      actions={pageActions as any[]}
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
              name: 'department',
              label: t('Department'),
              type: 'combobox',
              value: selectedDepartment,
              onChange: setSelectedDepartment,
              options: departmentOptions
            },
            {
              name: 'category',
              label: t('Category'),
              type: 'combobox',
              value: selectedCategory,
              onChange: setSelectedCategory,
              options: categoryOptions
            },
            {
              name: 'designation',
              label: t('Designation'),
              type: 'combobox',
              value: selectedDesignation,
              onChange: setSelectedDesignation,
              options: designationOptions
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
              name: 'skill',
              label: t('Skill'),
              type: 'combobox',
              value: selectedSkill,
              onChange: setSelectedSkill,
              options: skillOptions
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
            router.get(route('hr.employees.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
              designation: selectedDesignation !== 'all' ? selectedDesignation : undefined,
              status: selectedStatus,
              category: selectedCategory !== 'all' ? selectedCategory : undefined,
              skill: selectedSkill !== 'all' ? selectedSkill : undefined,
            }, { preserveState: true, preserveScroll: true });
          }}
          showViewToggle={true}
          activeView={activeView}
          onViewChange={setActiveView}
        />
      </div>

      {/* Content section */}
      {activeView === 'list' ? (
        <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
          <CrudTable
            columns={columns}
            actions={actions}
            data={employees?.data || []}
            from={employees?.from || 1}
            onAction={handleAction}
            sortField={pageFilters.sort_field}
            sortDirection={pageFilters.sort_direction}
            onSort={handleSort}
            permissions={permissions}
            entityPermissions={{
              view: 'view-employees',
              create: 'create-employees',
              edit: 'edit-employees',
              delete: 'delete-employees'
            } as any}
          />

          {/* Pagination section */}
          <Pagination
            from={employees?.from || 0}
            to={employees?.to || 0}
            total={employees?.total || 0}
            links={employees?.links}
            entityName={t("employees")}
            onPageChange={(url) => router.get(url)}
          />
        </div>
      ) : (
        <>
          {/* Grid View */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {employees?.data?.map((employee: any) => (
              <Card key={employee.id} className="group overflow-hidden border-none shadow-md hover:shadow-xl transition-all duration-300 bg-white dark:bg-gray-800 rounded-xl">
                {/* Header Background */}
                <div className="h-24 bg-gradient-to-r from-primary/80 to-primary relative">
                  <div className="absolute top-3 right-3">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="secondary" size="icon" className="h-8 w-8 rounded-full bg-white/20 backdrop-blur-md border-none text-white hover:bg-white/40">
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-48 z-50">
                        {hasPermission(permissions, 'view-employees') && (
                          <DropdownMenuItem onClick={() => handleAction('view', employee)}>
                            <Eye className="h-4 w-4 mr-2" />
                            <span>{t("View Profile")}</span>
                          </DropdownMenuItem>
                        )}
                        {hasPermission(permissions, 'edit-employees') && (
                          <DropdownMenuItem onClick={() => handleAction('edit', employee)}>
                            <Edit className="h-4 w-4 mr-2" />
                            <span>{t("Edit Details")}</span>
                          </DropdownMenuItem>
                        )}
                        {hasPermission(permissions, 'view-employees') && (
                          <DropdownMenuItem onClick={() => handleAction('export', employee)} className="text-red-500">
                            <FileText className="h-4 w-4 mr-2" />
                            <span>{t("Export Profile (PDF)")}</span>
                          </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        {hasPermission(permissions, 'delete-employees') && (
                          <DropdownMenuItem onClick={() => handleAction('delete', employee)} className="text-rose-600">
                            <Trash2 className="h-4 w-4 mr-2" />
                            <span>{t("Delete")}</span>
                          </DropdownMenuItem>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                {/* Profile Info */}
                <div className="px-6 pb-6 pt-0 -mt-12 relative flex flex-col items-center text-center">
                  <div className="h-24 w-24 rounded-full bg-white dark:bg-gray-800 p-1 shadow-lg ring-4 ring-white/10 overflow-hidden mb-4">
                    <div className="h-full w-full rounded-full bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold overflow-hidden">
                      {employee.avatar ? (
                        <img src={getImagePath(employee.avatar)} alt={employee.name} className="h-full w-full object-cover" />
                      ) : (
                        getInitials(employee.name)
                      )}
                    </div>
                  </div>

                  <h3 className="text-lg font-bold text-gray-900 dark:text-white leading-tight mb-1 group-hover:text-primary transition-colors">
                    {employee.name}
                  </h3>
                  <div className="text-[10px] font-extrabold text-primary bg-primary/10 px-2.5 py-1 rounded-full mb-3 uppercase tracking-widest border border-primary/20 shadow-sm">
                    ID: {employee.employee?.employee_id || t('N/A')}
                  </div>

                  <div className="flex items-center justify-center gap-2 mb-4">
                    <span className="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold tracking-wider uppercase">
                      {employee.employee?.employee_id || '-'}
                    </span>
                    <button
                      onClick={() => handleAction('toggle-status', employee)}
                      title={employee.status === 'active' ? t('Click to Deactivate') : t('Click to Activate')}
                      className={`px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase cursor-pointer border-none bg-transparent hover:scale-105 transition-transform ${
                        employee.status === 'active' 
                          ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' 
                          : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                      }`}
                    >
                      {employee.status === 'active' ? t('Active') : t('Inactive')}
                    </button>
                  </div>

                  <div className="w-full bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 space-y-2 mb-4">
                    <div className="flex items-center text-left">
                      <div className="h-7 w-7 rounded-md bg-white dark:bg-gray-800 shadow-sm flex items-center justify-center mr-2">
                        <Building className="h-3.5 w-3.5 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-[10px] text-muted-foreground leading-none mb-0.5">{t("Department")}</p>
                        <p className="text-xs font-semibold truncate">{employee.employee?.department?.name || '-'}</p>
                      </div>
                    </div>
                    <div className="flex items-center text-left">
                      <div className="h-7 w-7 rounded-md bg-white dark:bg-gray-800 shadow-sm flex items-center justify-center mr-2">
                        <Briefcase className="h-3.5 w-3.5 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-[10px] text-muted-foreground leading-none mb-0.5">{t("Designation")}</p>
                        <p className="text-xs font-semibold truncate">{employee.employee?.designation?.name || '-'}</p>
                      </div>
                    </div>
                    <div className="flex items-center text-left">
                      <div className="h-7 w-7 rounded-md bg-white dark:bg-gray-800 shadow-sm flex items-center justify-center mr-2">
                        <Briefcase className="h-3.5 w-3.5 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-[10px] text-muted-foreground leading-none mb-0.5">{t("Category")}</p>
                        <p className="text-xs font-semibold truncate">{employee.employee?.category?.name || '-'}</p>
                      </div>
                    </div>
                  </div>

                  {/* Joined Date */}
                  <div className="flex flex-col items-center mb-4">
                    <p className="text-[10px] text-muted-foreground uppercase font-bold tracking-tight mb-0.5">{t("Joined Date")}</p>
                    <p className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                      {employee.employee?.date_of_joining ? (window.appSettings?.formatDateTime(employee.employee.date_of_joining, false) || new Date(employee.employee.date_of_joining).toLocaleDateString()) : '-'}
                    </p>
                  </div>

                  {/* Actions */}
                  <div className="grid grid-cols-2 gap-2 w-full">
                    {hasPermission(permissions, 'view-employees') && (
                      <Button
                        variant="default"
                        size="sm"
                        onClick={() => handleAction('view', employee)}
                        className="h-9 text-xs rounded-lg shadow-sm bg-primary hover:bg-primary/90"
                      >
                        <Eye className="h-3.5 w-3.5 mr-1.5" />
                        {t("View")}
                      </Button>
                    )}
                    {hasPermission(permissions, 'edit-employees') && (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => handleAction('edit', employee)}
                        className="h-9 text-xs rounded-lg border-gray-200 dark:border-gray-700 shadow-sm"
                      >
                        <Edit className="h-3.5 w-3.5 mr-1.5" />
                        {t("Edit")}
                      </Button>
                    )}
                  </div>
                </div>
              </Card>
            ))}
          </div>

          {/* Pagination for grid view */}
          <div className="mt-6 bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
            <Pagination
              from={employees?.from || 0}
              to={employees?.to || 0}
              total={employees?.total || 0}
              links={employees?.links}
              entityName={t("employees")}
              onPageChange={(url) => router.get(url)}
            />
          </div>
        </>
      )}

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="employee"
      />

      {/* Change Password Modal */}
      <CrudFormModal
        isOpen={isPasswordModalOpen}
        onClose={() => setIsPasswordModalOpen(false)}
        onSubmit={handlePasswordChange}
        formConfig={{
          fields: [
            {
              name: 'password',
              label: t('New Password'),
              type: 'password',
              required: true
            },
            {
              name: 'password_confirmation',
              label: t('Confirm Password'),
              type: 'password',
              required: true
            }
          ],
          modalSize: 'md'
        }}
        initialData={{}}
        title={t('Change Employee Password')}
        mode='edit'
      />
      <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>{t('Import Employees')}</DialogTitle>
          </DialogHeader>
          <div className="max-h-[60vh] overflow-y-auto pr-2 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-slate-100/50 [&::-webkit-scrollbar-thumb]:bg-slate-300 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-400">
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="file">{t('Select Excel File')}</Label>
                <Input
                  id="file"
                  type="file"
                  accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                  onChange={(e) => handleImportFileChange(e.target.files?.[0] || null)}
                />
                <p className="text-xs text-muted-foreground">
                  {t('Supported formats: .xlsx, .xls, .csv (use the sample file for correct columns)')}
                </p>
              </div>
              <div className="text-sm text-muted-foreground">
                <a
                  href="#"
                  onClick={(e) => {
                    e.preventDefault();
                    window.location.href = route('hr.employees.download-sample');
                  }}
                  className="text-primary hover:underline flex items-center gap-2"
                >
                  <FileUp className="h-4 w-4" />
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
            <Button onClick={handleImport} disabled={!importFile || !!validateImportFile(importFile)}>{t('Import')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}