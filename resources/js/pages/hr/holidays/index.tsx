// pages/hr/holidays/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Plus, Calendar, FileText, Download, FileUp, Building2, Layers, MousePointerClick } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { format, differenceInDays, addDays } from 'date-fns';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MultiSelect } from '@/components/ui/multi-select';
import { cn } from '@/lib/utils';

export default function Holidays() {
  const { t } = useTranslation();
  const { auth, holidays, categories, years, branches, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedCategory, setSelectedCategory] = useState(pageFilters.category || '');

  const [selectedYear, setSelectedYear] = useState(pageFilters.year || new Date().getFullYear().toString());
  const [dateFrom, setDateFrom] = useState(pageFilters.date_from || '');
  const [dateTo, setDateTo] = useState(pageFilters.date_to || '');
  const [showFilters, setShowFilters] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isImportModalOpen, setIsImportModalOpen] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importScope, setImportScope] = useState<'current' | 'all' | 'selected'>('current');
  const [importSelectedBranches, setImportSelectedBranches] = useState<string[]>([]);
  const [importErrors, setImportErrors] = useState<string | null>(null);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  // Check if any filters are active
  const hasActiveFilters = () => {
    return selectedCategory !== '' ||

      selectedYear !== new Date().getFullYear().toString() ||
      dateFrom !== '' ||
      dateTo !== '' ||
      searchTerm !== '';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (selectedCategory !== '' ? 1 : 0) +

      (selectedYear !== new Date().getFullYear().toString() ? 1 : 0) +
      (dateFrom !== '' ? 1 : 0) +
      (dateTo !== '' ? 1 : 0) +
      (searchTerm !== '' ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSearchClear = () => {
    setSearchTerm('');
    router.get(route('hr.holidays.index'), {
      page: 1,
      search: undefined,
      category: selectedCategory || undefined,
      year: selectedYear || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const applyFilters = () => {
    router.get(route('hr.holidays.index'), {
      page: 1,
      search: searchTerm || undefined,
      category: selectedCategory || undefined,

      year: selectedYear || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.holidays.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      category: selectedCategory || undefined,

      year: selectedYear || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: any) => {
    let mode = 'current';
    let selected: string[] = [];
    if (item && item.branches && item.branches.length > 0) {
      // If it's more than 1 branch, it's definitely 'selected' or 'all'.
      // To be safe, we just make it 'selected' and prefill checkboxes so the user sees exactly what is selected.
      mode = 'selected';
      selected = item.branches.map((b: any) => b.id.toString());
    }

    setCurrentItem({
      ...item,
      branch_scope: mode,
      selected_branches: selected
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
        setIsDeleteModalOpen(true);
        break;
    }
  };

  const handleAddNew = () => {
    const today = format(new Date(), 'yyyy-MM-dd');
    setCurrentItem({
      start_date: today,
      end_date: today,
      is_recurring: false,
      is_paid: true,
      is_half_day: false,
      category: 'national',
      branch_scope: 'current',
      selected_branches: []
    });
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating holiday...'));

      router.post(route('hr.holidays.store'), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash.error) {
            toast.error(t(page.props.flash.error));
          } else {
            toast.success(t('Holiday created successfully'));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(t(`Failed to create holiday: ${Object.values(errors).join(', ')}`));
          }
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating holiday...'));

      router.put(route('hr.holidays.update', currentItem.id), formData, {
        onSuccess: (page: any) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash.error) {
            toast.error(t(page.props.flash.error));
          } else {
            toast.success(t('Holiday updated successfully'));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(t(`Failed to update holiday: ${Object.values(errors).join(', ')}`));
          }
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting holiday...'));

    router.delete(route('hr.holidays.destroy', currentItem.id), {
      onSuccess: (page: any) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        } else {
          toast.success(t('Holiday deleted successfully'));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(t(`Failed to delete holiday: ${Object.values(errors).join(', ')}`));
        }
      }
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedCategory('');

    setSelectedYear(new Date().getFullYear().toString());
    setDateFrom('');
    setDateTo('');
    setShowFilters(false);

    router.get(route('hr.holidays.index'), {
      page: 1,
      year: new Date().getFullYear().toString(),
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleViewCalendar = () => {
    router.visit(route('hr.holidays.calendar'), {
      method: 'get',
      data: {
        year: selectedYear || new Date().getFullYear().toString(),
        category: selectedCategory || undefined,

      }
    });
  };

  const handleExportPdf = () => {
    const params = new URLSearchParams({
      year: selectedYear || new Date().getFullYear().toString(),
      ...(selectedCategory && { category: selectedCategory }),

    });

    window.open(`${route('hr.holidays.export.pdf')}?${params.toString()}`, '_blank');
  };

  const handleExportIcal = () => {
    const params = new URLSearchParams({
      year: selectedYear || new Date().getFullYear().toString(),
      ...(selectedCategory && { category: selectedCategory }),

    });

    window.open(`${route('hr.holidays.export.ical')}?${params.toString()}`, '_blank');
  };

  const handleImportModalOpenChange = (open: boolean) => {
    setIsImportModalOpen(open);
    if (!open) {
      setTimeout(() => {
        setImportErrors(null);
        setImportFile(null);
        setImportScope('current');
        setImportSelectedBranches([]);
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
    formData.append('branch_scope', importScope);

    if (importScope === 'selected' && importSelectedBranches.length > 0) {
      importSelectedBranches.forEach((branchId, index) => {
        formData.append(`selected_branches[${index}]`, branchId);
      });
    }

    router.post(route('hr.holidays.import'), formData, {
      onStart: () => {
        toast.loading(t('Importing holidays...'));
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
          toast.success(t('Holidays imported successfully'));
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
            errorMessage = t('Import failed. Please checks for errors.');
          }
        }
        setImportErrors(errorMessage);
      },
    });
  };

  // Define page actions
  const pageActions: any[] = [];

  // Add the "View Calendar" button
  pageActions.push({
    label: t('Calendar View'),
    icon: <Calendar className="h-4 w-4 mr-2" />,
    variant: 'outline',
    onClick: handleViewCalendar
  });

  // Add export buttons
  pageActions.push({
    label: t('Export PDF'),
    icon: <FileText className="h-4 w-4 mr-2" />,
    variant: 'outline',
    onClick: handleExportPdf
  });

  pageActions.push({
    label: t('Export iCal'),
    icon: <Download className="h-4 w-4 mr-2" />,
    variant: 'outline',
    onClick: handleExportIcal
  });

  // Add the "Add New Holiday" button if user has permission
  if (hasPermission(permissions, 'create-holidays')) {
    pageActions.push({
      label: t('Add Holiday'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: () => handleAddNew()
    });

    pageActions.push({
      label: t('Import Holidays'),
      icon: <FileUp className="h-4 w-4 mr-2" />,
      variant: 'outline',
      className: 'border-primary text-primary hover:bg-primary/5',
      onClick: () => setIsImportModalOpen(true),
    });
  }

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Holidays') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'name',
      label: t('Holiday Name'),
      sortable: true,
      render: (value) => value || '-'
    },
    {
      key: 'date',
      label: t('Date'),
      sortable: true,
      render: (_, row) => {
        if (row.end_date && row.start_date !== row.end_date) {
          return (
            <div>
              <div>{window.appSettings?.formatDateTime(row.start_date, false) || new Date(row.start_date).toLocaleDateString()}</div>
              <div className="text-xs text-gray-500">to</div>
              <div>{window.appSettings?.formatDateTime(row.end_date, false) || new Date(row.end_date).toLocaleDateString()}</div>
              <div className="text-xs text-gray-500">
                ({differenceInDays(new Date(row.end_date), new Date(row.start_date)) + 1} days)
              </div>
            </div>
          );
        }
        return window.appSettings?.formatDateTime(row.start_date, false) || new Date(row.start_date).toLocaleDateString();
      }
    },
    {
      key: 'category',
      label: t('Category'),
      render: (value) => {
        const categoryClasses = {
          'national': 'bg-blue-50 text-blue-700 ring-blue-600/20',
          'religious': 'bg-purple-50 text-purple-700 ring-purple-600/20',
          'company-specific': 'bg-green-50 text-green-700 ring-green-600/20',
          'regional': 'bg-amber-50 text-amber-700 ring-amber-600/20'
        };

        const categoryClass = categoryClasses[value] || 'bg-gray-50 text-gray-700 ring-gray-600/20';

        return (
          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${categoryClass}`}>
            {value.charAt(0).toUpperCase() + value.slice(1)}
          </span>
        );
      }
    },
    {
      key: 'branches',
      label: t('Branches'),
      render: (_, row) => {
        if (!row.branches || row.branches.length === 0) return '-';

        if (row.branches.length <= 2) {
          return (
            <div className="flex flex-wrap gap-1">
              {row.branches.map((branch: any) => (
                <Badge key={branch.id} variant="outline">{branch.name}</Badge>
              ))}
            </div>
          );
        }

        return (
          <div className="flex flex-wrap gap-1">
            <Badge variant="outline">{row.branches[0].name}</Badge>
            <Badge variant="outline">+{row.branches.length - 1} more</Badge>
          </div>
        );
      }
    },
    {
      key: 'type',
      label: t('Type'),
      render: (_, row) => {
        const badges = [];

        if (row.is_recurring) {
          badges.push(
            <Badge key="recurring" variant="secondary" className="bg-indigo-50 text-indigo-700 hover:bg-indigo-50">
              {t('Recurring')}
            </Badge>
          );
        }

        if (row.is_half_day) {
          badges.push(
            <Badge key="half-day" variant="secondary" className="bg-orange-50 text-orange-700 hover:bg-orange-50">
              {t('Half Day')}
            </Badge>
          );
        }

        if (row.is_paid) {
          badges.push(
            <Badge key="paid" variant="secondary" className="bg-green-50 text-green-700 hover:bg-green-50">
              {t('Paid')}
            </Badge>
          );
        } else {
          badges.push(
            <Badge key="unpaid" variant="secondary" className="bg-red-50 text-red-700 hover:bg-red-50">
              {t('Unpaid')}
            </Badge>
          );
        }

        return (
          <div className="flex flex-wrap gap-1">
            {badges}
          </div>
        );
      }
    },
    {
      key: 'description',
      label: t('Description'),
      render: (value) => value || '-'
    }
  ];

  // Define table actions
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-holidays'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-holidays'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-holidays'
    }
  ];

  // Prepare category options for filter
  const categoryOptions = [
    { value: '', label: t('All Categories') },
    ...(categories || []).map((category: string) => ({
      value: category,
      label: category.charAt(0).toUpperCase() + category.slice(1)
    }))
  ];



  // Prepare year options for filter
  const yearOptions = [
    ...(years || []).map((year: number) => ({
      value: year.toString(),
      label: year.toString()
    }))
  ];

  // Prepare category options for form
  const categoryFormOptions = [
    { value: 'national', label: t('National') },
    { value: 'religious', label: t('Religious') },
    { value: 'company-specific', label: t('Company Specific') },
    { value: 'regional', label: t('Regional') }
  ];

  return (
    <PageTemplate
      title={t("Holidays")}
      url="/holidays"
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
              name: 'category',
              label: t('Category'),
              type: 'select',
              value: selectedCategory,
              onChange: setSelectedCategory,
              options: categoryOptions
            },

            {
              name: 'year',
              label: t('Year'),
              type: 'select',
              value: selectedYear,
              onChange: setSelectedYear,
              options: yearOptions
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
            router.get(route('hr.holidays.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              category: selectedCategory || undefined,

              year: selectedYear || undefined,
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
          data={holidays?.data || []}
          from={holidays?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'view-holidays',
            create: 'create-holidays',
            edit: 'edit-holidays',
            delete: 'delete-holidays'
          }}
        />

        {/* Pagination section */}
        <Pagination
          from={holidays?.from || 0}
          to={holidays?.to || 0}
          total={holidays?.total || 0}
          links={holidays?.links}
          entityName={t("holidays")}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      {/* Form Modal */}
      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          layout: 'grid',
          columns: 12,
          fields: [
            {
              name: 'name',
              label: t('Holiday Name'),
              type: 'text',
              required: true,
              colSpan: 12
            },
            {
              name: 'category',
              label: t('Category'),
              type: 'select',
              required: true,
              options: categoryFormOptions,
              colSpan: 12
            },
            {
              name: 'start_date',
              label: t('Start Date'),
              type: 'date',
              required: true,
              colSpan: 6
            },
            {
              name: 'end_date',
              label: t('End Date'),
              type: 'date',
              helpText: t('Leave empty for single-day holiday'),
              colSpan: 6
            },
            {
              name: 'description',
              label: t('Description'),
              type: 'textarea',
              colSpan: 12
            },
            {
              name: 'is_recurring',
              label: t('Recurring Annual Holiday'),
              type: 'checkbox',
              helpText: t('Repeats every year'),
              colSpan: 4
            },
            {
              name: 'is_paid',
              label: t('Paid Holiday'),
              type: 'checkbox',
              defaultValue: true,
              colSpan: 4
            },
            {
              name: 'is_half_day',
              label: t('Half Day'),
              type: 'checkbox',
              colSpan: 4
            },
            {
              name: 'branch_scope_section',
              label: '',
              type: 'custom',
              colSpan: 12,
              render: (field, formData, handleChange) => (
                <div className="space-y-4">
                  <Label className="text-base font-medium mb-4 block">{t('Branch Scope')}</Label>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Current Branch */}
                    <Label
                      htmlFor="scope-current"
                      className={cn(
                        "flex flex-col items-center justify-center p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-accent/50 h-32",
                        formData.branch_scope === 'current' ? "border-primary bg-primary/5 shadow-sm" : "border-muted bg-transparent"
                      )}
                    >
                      <input
                        type="radio"
                        name="branch_scope"
                        id="scope-current"
                        value="current"
                        checked={formData.branch_scope === 'current'}
                        onChange={() => handleChange('branch_scope', 'current')}
                        className="sr-only"
                      />
                      <Building2 className={cn("h-8 w-8 mb-3", formData.branch_scope === 'current' ? "text-primary" : "text-muted-foreground")} />
                      <span className="font-semibold text-center">{t('Current Branch')}</span>
                      <span className="text-xs text-muted-foreground text-center mt-1">{t('Save only for this branch')}</span>
                    </Label>

                    {/* All Branches */}
                    <Label
                      htmlFor="scope-all"
                      className={cn(
                        "flex flex-col items-center justify-center p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-accent/50 h-32",
                        formData.branch_scope === 'all' ? "border-primary bg-primary/5 shadow-sm" : "border-muted bg-transparent"
                      )}
                    >
                      <input
                        type="radio"
                        name="branch_scope"
                        id="scope-all"
                        value="all"
                        checked={formData.branch_scope === 'all'}
                        onChange={() => handleChange('branch_scope', 'all')}
                        className="sr-only"
                      />
                      <Layers className={cn("h-8 w-8 mb-3", formData.branch_scope === 'all' ? "text-primary" : "text-muted-foreground")} />
                      <span className="font-semibold text-center">{t('All Branches')}</span>
                      <span className="text-xs text-muted-foreground text-center mt-1">{t('Apply to all your branches')}</span>
                    </Label>

                    {/* Selected Branches */}
                    <Label
                      htmlFor="scope-selected"
                      className={cn(
                        "flex flex-col items-center justify-center p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-accent/50 h-32",
                        formData.branch_scope === 'selected' ? "border-primary bg-primary/5 shadow-sm" : "border-muted bg-transparent"
                      )}
                    >
                      <input
                        type="radio"
                        name="branch_scope"
                        id="scope-selected"
                        value="selected"
                        checked={formData.branch_scope === 'selected'}
                        onChange={() => handleChange('branch_scope', 'selected')}
                        className="sr-only"
                      />
                      <MousePointerClick className={cn("h-8 w-8 mb-3", formData.branch_scope === 'selected' ? "text-primary" : "text-muted-foreground")} />
                      <span className="font-semibold text-center">{t('Selected Branches')}</span>
                      <span className="text-xs text-muted-foreground text-center mt-1">{t('Choose specific branches')}</span>
                    </Label>
                  </div>

                  {formData.branch_scope === 'selected' && (
                    <div className="mt-4 animate-in fade-in slide-in-from-top-2 p-5 border-2 border-primary/70 rounded-xl bg-primary/10 shadow-sm ring-1 ring-primary/5">
                      <Label className="mb-3 block font-bold text-xs uppercase tracking-widest">{t('Select Target Branches')}</Label>
                      <div className="max-w-xl">
                        <MultiSelect
                          options={(branches || []).map((b: any) => ({ label: b.name, value: b.id.toString() }))}
                          selected={formData.selected_branches || []}
                          onChange={(values) => handleChange('selected_branches', values)}
                          placeholder={t('Select branches...')}
                        />
                      </div>
                    </div>
                  )}
                </div>
              )
            },
          ],
          modalSize: '2xl'
        }}
        initialData={currentItem ? {
          ...currentItem,
          start_date: currentItem.start_date ? format(new Date(currentItem.start_date), 'yyyy-MM-dd') : '',
          end_date: currentItem.end_date ? format(new Date(currentItem.end_date), 'yyyy-MM-dd') : '',
        } : null}
        title={
          formMode === 'create'
            ? t('Add New Holiday')
            : formMode === 'edit'
              ? t('Edit Holiday')
              : t('View Holiday')
        }
        mode={formMode}
      />

      {/* Import Modal */}
      <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{t('Import Holidays')}</DialogTitle>
          </DialogHeader>
          <div className="max-h-[60vh] overflow-y-auto pr-2 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-slate-100/50 [&::-webkit-scrollbar-thumb]:bg-slate-300 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-400">
            <div className="grid gap-4 py-4">
              <div className="text-sm text-muted-foreground bg-blue-50 text-blue-800 p-3 rounded-md border border-blue-200">
                <p className="font-semibold mb-1">{t('Instructions')}:</p>
                <ul className="list-disc pl-4 space-y-1">
                  <li>{t('Recurring Annual Holiday, Paid, Half Day ::   Type yes or no')}</li>
                  <li>{t('Holiday Category must be one of')}: national, religious, company-specific, regional</li>
                </ul>
              </div>

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
                  href={route('hr.holidays.import.template')}
                  target="_blank"
                  className="text-primary hover:underline flex items-center gap-1"
                >
                  <Download className="h-3 w-3" />
                  {t('Download Sample File')}
                </a>
              </div>
              {importErrors && (
                <div className="bg-slate-50 border border-slate-200 px-4 py-3 rounded relative text-sm h-50 overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-slate-100/50 [&::-webkit-scrollbar-thumb]:bg-slate-300 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-400 font-medium" role="alert">
                  <div dangerouslySetInnerHTML={{ __html: importErrors }} />
                </div>
              )}
            </div>
            <div className="space-y-4 pt-4 border-t mt-2">
              <Label className="text-base font-medium mb-4 block">{t('Import Branch Scope')}</Label>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {/* Current Branch */}
                <Label
                  htmlFor="import-scope-current"
                  className={cn(
                    "flex flex-col items-center justify-center p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-accent/50",
                    importScope === 'current' ? "border-primary bg-primary/5 shadow-sm" : "border-muted bg-transparent"
                  )}
                >
                  <input
                    type="radio"
                    name="importCallbackScope"
                    id="import-scope-current"
                    value="current"
                    checked={importScope === 'current'}
                    onChange={() => setImportScope('current')}
                    className="sr-only"
                  />
                  <Building2 className={cn("h-8 w-8 mb-3", importScope === 'current' ? "text-primary" : "text-muted-foreground")} />
                  <span className="font-semibold text-center">{t('Current Branch')}</span>
                  <span className="text-xs text-muted-foreground text-center mt-1">{t('Save only for this branch')}</span>
                </Label>

                {/* All Branches */}
                <Label
                  htmlFor="import-scope-all"
                  className={cn(
                    "flex flex-col items-center justify-center p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-accent/50",
                    importScope === 'all' ? "border-primary bg-primary/5 shadow-sm" : "border-muted bg-transparent"
                  )}
                >
                  <input
                    type="radio"
                    name="importCallbackScope"
                    id="import-scope-all"
                    value="all"
                    checked={importScope === 'all'}
                    onChange={() => setImportScope('all')}
                    className="sr-only"
                  />
                  <Layers className={cn("h-8 w-8 mb-3", importScope === 'all' ? "text-primary" : "text-muted-foreground")} />
                  <span className="font-semibold text-center">{t('All Branches')}</span>
                  <span className="text-xs text-muted-foreground text-center mt-1">{t('Apply to all your branches')}</span>
                </Label>

                {/* Selected Branches */}
                <Label
                  htmlFor="import-scope-selected"
                  className={cn(
                    "flex flex-col items-center justify-center p-4 rounded-lg border-2 cursor-pointer transition-all hover:bg-accent/50",
                    importScope === 'selected' ? "border-primary bg-primary/5 shadow-sm" : "border-muted bg-transparent"
                  )}
                >
                  <input
                    type="radio"
                    name="importCallbackScope"
                    id="import-scope-selected"
                    value="selected"
                    checked={importScope === 'selected'}
                    onChange={() => setImportScope('selected')}
                    className="sr-only"
                  />
                  <MousePointerClick className={cn("h-8 w-8 mb-3", importScope === 'selected' ? "text-primary" : "text-muted-foreground")} />
                  <span className="font-semibold text-center">{t('Selected Branches')}</span>
                  <span className="text-xs text-muted-foreground text-center mt-1">{t('Choose specific branches')}</span>
                </Label>
              </div>

              {importScope === 'selected' && (
                <div className="mt-4 animate-in fade-in slide-in-from-top-2 p-5 border-2 border-primary/70 rounded-xl bg-primary/10 shadow-sm ring-1 ring-primary/5">
                  <Label className="mb-3 block font-bold text-xs uppercase tracking-widest">{t('Select Target Branches')}</Label>
                  <div className="max-w-xl">
                    <MultiSelect
                      options={(branches || []).map((b: any) => ({ label: b.name, value: b.id.toString() }))}
                      selected={importSelectedBranches || []}
                      onChange={(values) => setImportSelectedBranches(values)}
                      placeholder={t('Select branches...')}
                    />
                  </div>
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

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="holiday"
      />
    </PageTemplate>
  );
}