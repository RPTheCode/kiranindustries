// pages/hr/employees/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Combobox } from '@/components/ui/combobox';
import { FileUp, Plus, Trash2, MoreHorizontal, Key, FileText, Calculator, Search, Filter, X } from 'lucide-react';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CrudFormModal } from '@/components/CrudFormModal';
import { getImagePath } from '@/utils/helpers';

export default function Employees() {
  const { t } = useTranslation();
  const { auth, employees, employeeStats, planLimits, departments, designations, categories, skills, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const getInitials = useInitials();
  const defaultStatus = 'active';

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
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

  const hasActiveFilters = () => {
    return selectedDepartment !== 'all' || selectedDesignation !== 'all' || selectedStatus !== defaultStatus || selectedCategory !== 'all' || selectedSkill !== 'all' || searchTerm !== '';
  };

  const activeFilterCount = () => {
    return (selectedDepartment !== 'all' ? 1 : 0) +
      (selectedDesignation !== 'all' ? 1 : 0) +
      (selectedStatus !== defaultStatus ? 1 : 0) +
      (selectedCategory !== 'all' ? 1 : 0) +
      (selectedSkill !== 'all' ? 1 : 0) +
      (searchTerm ? 1 : 0);
  };

  const getFilterParams = (overrides: Record<string, unknown> = {}) => {
    const department = (overrides.department as string | undefined) ?? selectedDepartment;
    const designation = (overrides.designation as string | undefined) ?? selectedDesignation;
    const status = (overrides.status as string | undefined) ?? selectedStatus;
    const category = (overrides.category as string | undefined) ?? selectedCategory;
    const skill = (overrides.skill as string | undefined) ?? selectedSkill;
    const search = (overrides.search as string | undefined) ?? searchTerm;

    return {
      page: overrides.page ?? 1,
      search: search || undefined,
      department: department !== 'all' ? department : undefined,
      designation: designation !== 'all' ? designation : undefined,
      status,
      category: category !== 'all' ? category : undefined,
      skill: skill !== 'all' ? skill : undefined,
      per_page: overrides.per_page ?? pageFilters.per_page ?? 25,
      ...(overrides.sort_field
        ? { sort_field: overrides.sort_field, sort_direction: overrides.sort_direction }
        : {}),
    };
  };

  const navigateFilters = (overrides: Record<string, unknown> = {}) => {
    router.get(route('hr.employees.index'), getFilterParams(overrides), { preserveState: true, preserveScroll: true });
  };

  const handleQuickFilter = (field: 'category' | 'department' | 'status', value: string) => {
    if (field === 'category') setSelectedCategory(value);
    if (field === 'department') setSelectedDepartment(value);
    if (field === 'status') setSelectedStatus(value);
    navigateFilters({ [field]: value, page: 1 });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSearchClear = () => {
    setSearchTerm('');
    navigateFilters({ search: '', page: 1 });
  };

  const applyFilters = () => {
    navigateFilters({ page: 1 });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    navigateFilters({ sort_field: field, sort_direction: direction, page: 1 });
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
        router.get(route('hr.earning-deduction.index', { employee_id: item.employee?.id || item.id }));
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
    setSelectedDepartment('all');
    setSelectedDesignation('all');
    setSelectedStatus(defaultStatus);
    setSelectedCategory('all');
    setSelectedSkill('all');
    setShowFilters(false);

    navigateFilters({
      page: 1,
      search: '',
      department: 'all',
      designation: 'all',
      status: defaultStatus,
      category: 'all',
      skill: 'all',
    });
  };

  const renderMoreActions = (row: any) => (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground hover:text-foreground">
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        {hasPermission(permissions, 'edit-employees') && (
          <DropdownMenuItem onClick={() => handleAction('change-password', row)}>
            <Key className="h-4 w-4 mr-2" />
            {t('Change Password')}
          </DropdownMenuItem>
        )}
        {hasPermission(permissions, 'view-employees') && (
          <DropdownMenuItem onClick={() => handleAction('monthly-incentive', row)}>
            <Calculator className="h-4 w-4 mr-2" />
            {t('Earning / Deduction')}
          </DropdownMenuItem>
        )}
        {hasPermission(permissions, 'view-employees') && (
          <DropdownMenuItem onClick={() => handleAction('export', row)}>
            <FileText className="h-4 w-4 mr-2" />
            {t('Export Profile (PDF)')}
          </DropdownMenuItem>
        )}
        {hasPermission(permissions, 'delete-employees') && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => handleAction('delete', row)} className="text-red-600">
              <Trash2 className="h-4 w-4 mr-2" />
              {t('Delete')}
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );

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
    onClick: () => window.open(route('hr.employees.report.pdf', { branch_id: pageFilters.branch }), '_blank'),
  });


  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Employees') }
  ];

  const copyEmpCode = (code: string) => {
    if (!code || code === '—') return;
    navigator.clipboard.writeText(code).then(() => {
      toast.success(t('Emp code copied: {{code}}', { code }));
    });
  };

  const copyPhone = (phone: string) => {
    if (!phone || phone === '—') return;
    navigator.clipboard.writeText(phone).then(() => {
      toast.success(t('Phone copied: {{phone}}', { phone }));
    });
  };

  const isPhoneMissing = (row: any) => !row.employee?.phone?.trim();

  // Define table columns — compact list, emp code easy to scan
  const columns = [
    {
      key: 'employee_id',
      label: t('Emp Code'),
      sortable: false,
      sticky: 'left',
      className: 'w-[5.5rem]',
      render: (_value: any, row: any) => {
        const code = row.employee?.employee_id;
        if (!code) {
          return <span className="text-xs text-muted-foreground">—</span>;
        }
        return (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              copyEmpCode(String(code));
            }}
            title={t('Click to copy emp code')}
            className="inline-flex min-w-[3.75rem] items-center justify-center rounded-md border border-primary/25 bg-primary/10 px-2 py-0.5 font-mono text-[13px] font-bold tabular-nums text-primary transition hover:bg-primary/15 active:scale-95"
          >
            {code}
          </button>
        );
      },
    },
    {
      key: 'name',
      label: t('Employee'),
      sortable: true,
      render: (_value: any, row: any) => (
        <div className="flex items-center gap-2 min-w-[160px]">
          <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary text-[10px] font-bold overflow-hidden">
            {row.avatar ? (
              <img src={getImagePath(row.avatar)} alt={row.name} className="h-full w-full object-cover" />
            ) : (
              getInitials(row.name)
            )}
          </div>
          <div className="min-w-0">
            <div className="font-medium text-[13px] text-slate-900 truncate leading-tight">{row.name}</div>
            <div className="text-[10px] text-slate-500 truncate" title={row.employee?.department?.name || ''}>
              {row.employee?.department?.name || '—'}
            </div>
          </div>
        </div>
      ),
    },
    {
      key: 'phone',
      label: t('Phone'),
      sortable: false,
      className: 'w-[6.5rem]',
      render: (_value: any, row: any) => {
        const phone = row.employee?.phone;
        if (!phone) {
          return (
            <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 ring-1 ring-amber-200/80">
              {t('Missing')}
            </span>
          );
        }
        return (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              copyPhone(String(phone));
            }}
            title={t('Click to copy phone')}
            className="text-xs font-medium text-slate-700 tabular-nums hover:text-primary transition"
          >
            {phone}
          </button>
        );
      },
    },
    {
      key: 'designation',
      label: t('Designation'),
      sortable: false,
      render: (_value: any, row: any) => (
        <span className="text-xs font-medium text-slate-700 truncate max-w-[6.5rem] block" title={row.employee?.designation?.name || ''}>
          {row.employee?.designation?.name || '—'}
        </span>
      ),
    },
    {
      key: 'shift',
      label: t('Shift'),
      sortable: false,
      className: 'w-[3.5rem]',
      render: (_value: any, row: any) => {
        const shift = row.employee?.shift;
        const shiftCode = shift?.short_code?.trim();
        if (!shiftCode) {
          return <span className="text-xs text-muted-foreground">—</span>;
        }
        return (
          <span
            className="inline-flex min-w-[2.25rem] items-center justify-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 uppercase"
            title={shift?.name || shiftCode}
          >
            {shiftCode}
          </span>
        );
      },
    },
    {
      key: 'status',
      label: t('Status'),
      sortable: false,
      render: (_value: any, row: any) => {
        const isActive = row.status === 'active';
        return (
          <button
            onClick={(e) => {
              e.stopPropagation();
              handleAction('toggle-status', row);
            }}
            title={isActive ? t('Click to Deactivate') : t('Click to Activate')}
            className="cursor-pointer select-none border-none bg-transparent p-0"
          >
            <span
              className={`relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors ${
                isActive ? 'bg-emerald-500' : 'bg-slate-300'
              }`}
              title={isActive ? t('Active') : t('Inactive')}
            >
              <span className={`inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform ${
                isActive ? 'translate-x-3.5' : 'translate-x-0.5'
              }`} />
            </span>
          </button>
        );
      },
    },
    {
      key: 'date_of_joining',
      label: t('Joined'),
      sortable: false,
      render: (_value: any, row: any) => {
        const joinDate = row.employee?.date_of_joining;
        return (
          <span className="text-xs text-slate-600 whitespace-nowrap">
            {joinDate ? (window.appSettings?.formatDateTime(joinDate, false) || new Date(joinDate).toLocaleDateString()) : '—'}
          </span>
        );
      },
    },
  ];

  // Primary row actions — rest live in the more menu
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-employees',
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-employees',
    },
  ];

  // Prepare filter options
  const departmentOptions = [
    { value: 'all', label: t('All') },
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
    { value: 'all', label: t('All') },
    ...(categories || []).map((category: any) => ({
      value: category.id.toString(),
      label: category.name
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
      {/* Compact toolbar — one row: search + filters + stats */}
      <div className="bg-white dark:bg-gray-900 rounded-lg border border-slate-200/80 dark:border-gray-800 mb-2 px-2.5 py-1.5">
        <div className="flex flex-wrap items-end gap-x-1.5 gap-y-2">
          <div className="flex flex-col flex-1 min-w-[200px] max-w-sm">
            <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Search')}</Label>
            <form onSubmit={handleSearch} className="flex items-center gap-1.5">
              <div className="relative flex-1 min-w-[140px]">
                <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                <Input
                  placeholder={t('Emp code, name, phone...')}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="h-8 pl-7 pr-7 text-xs"
                />
                {searchTerm && (
                  <button
                    type="button"
                    onClick={handleSearchClear}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>
              <Button type="submit" size="sm" className="h-8 px-2.5 text-xs shrink-0">
                <Search className="h-3.5 w-3.5 sm:mr-1" />
                <span className="hidden sm:inline">{t('Search')}</span>
              </Button>
            </form>
          </div>

          <div className="flex flex-col shrink-0">
            <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Category')}</Label>
            <Combobox
              options={categoryOptions}
              value={selectedCategory}
              onChange={(value) => handleQuickFilter('category', value)}
              placeholder={t('All')}
              className="h-8 text-xs w-[6.75rem]"
            />
          </div>
          <div className="flex flex-col shrink-0">
            <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Department')}</Label>
            <Combobox
              options={departmentOptions}
              value={selectedDepartment}
              onChange={(value) => handleQuickFilter('department', value)}
              placeholder={t('All')}
              className="h-8 text-xs w-[6.75rem]"
            />
          </div>
          <div className="flex flex-col shrink-0">
            <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Status')}</Label>
            <Combobox
              options={statusOptions}
              value={selectedStatus}
              onChange={(value) => handleQuickFilter('status', value)}
              placeholder={t('All')}
              className="h-8 text-xs w-[6.25rem]"
            />
          </div>

          <div className="flex flex-col shrink-0">
            <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1 invisible select-none" aria-hidden>
              {t('More')}
            </Label>
            <Button
              variant={hasActiveFilters() ? 'default' : 'outline'}
              size="sm"
              className="h-8 px-2 text-xs shrink-0"
              onClick={() => setShowFilters(!showFilters)}
            >
              <Filter className="h-3.5 w-3.5 mr-1" />
              {showFilters ? t('Hide') : t('More')}
              {hasActiveFilters() && (
                <span className="ml-1 bg-primary-foreground text-primary rounded-full w-4 h-4 flex items-center justify-center text-[10px]">
                  {activeFilterCount()}
                </span>
              )}
            </Button>
          </div>

          <div className="flex items-end gap-2 ml-auto shrink-0">
            {employeeStats && (
              <div className="hidden md:flex flex-col shrink-0">
                <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Summary')}</Label>
                <div className="flex h-8 items-center gap-2 text-[10px] text-slate-500 whitespace-nowrap">
                  <span><span className="font-semibold text-slate-700 tabular-nums">{employeeStats.total}</span> {t('total')}</span>
                  <span className="text-emerald-600"><span className="font-semibold tabular-nums">{employeeStats.active}</span> {t('active')}</span>
                  <span className="text-amber-600"><span className="font-semibold tabular-nums">{employeeStats.inactive}</span> {t('inactive')}</span>
                </div>
              </div>
            )}

            <div className="flex flex-col shrink-0">
              <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Per page')}</Label>
              <Select
                value={pageFilters.per_page?.toString() || '25'}
                onValueChange={(value) => navigateFilters({ page: 1, per_page: parseInt(value) })}
              >
                <SelectTrigger className="h-8 w-14 text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {[25, 10, 50, 100].map((n) => (
                    <SelectItem key={n} value={n.toString()}>{n}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>

        {showFilters && (
          <div className="flex flex-wrap items-end gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-gray-800">
            <div className="flex flex-col shrink-0">
              <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Designation')}</Label>
              <Combobox
                options={designationOptions}
                value={selectedDesignation}
                onChange={setSelectedDesignation}
                placeholder={t('All')}
                className="h-8 text-xs w-[8rem]"
              />
            </div>
            <div className="flex flex-col shrink-0">
              <Label className="text-[10px] font-medium text-slate-500 leading-none mb-1">{t('Skill')}</Label>
              <Combobox
                options={skillOptions}
                value={selectedSkill}
                onChange={setSelectedSkill}
                placeholder={t('All')}
                className="h-8 text-xs w-[8rem]"
              />
            </div>
            <Button size="sm" className="h-8 text-xs" onClick={applyFilters}>{t('Apply')}</Button>
            <Button size="sm" variant="outline" className="h-8 text-xs" onClick={handleResetFilters} disabled={!hasActiveFilters()}>
              {t('Reset')}
            </Button>
          </div>
        )}
      </div>

      {/* Employee list */}
      <div className="bg-white dark:bg-gray-900 rounded-xl border border-slate-200/80 dark:border-gray-800">
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
          dense
          compactActions
          stickyActions
          striped
          stickyHeader
          getRowClassName={(row) => (isPhoneMissing(row) ? 'bg-amber-50/70 hover:bg-amber-50' : undefined)}
          renderTrailingActions={renderMoreActions}
          onRowClick={(row) => {
            if (hasPermission(permissions, 'view-employees')) {
              handleAction('view', row);
            }
          }}
          entityPermissions={{
            view: 'view-employees',
            create: 'create-employees',
            edit: 'edit-employees',
            delete: 'delete-employees',
          } as any}
        />

        <Pagination
          from={employees?.from || 0}
          to={employees?.to || 0}
          total={employees?.total || 0}
          links={employees?.links}
          entityName={t('employees')}
          onPageChange={(url) => router.get(url)}
        />
      </div>

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
        <DialogContent className="sm:max-w-[520px]">
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
                <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200/80 rounded-md px-2 py-1.5 leading-snug">
                  {t('Section, Category, Department, Designation, Shift & Skill must already exist under Organization. Missing masters will skip that row with a clear reason.')}
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