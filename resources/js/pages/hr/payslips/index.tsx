// pages/hr/payslips/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, Download, FileText, FileSpreadsheet, RefreshCw } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Button } from '@/components/ui/button';

import { PayslipEditModal } from '@/components/PayslipEditModal';
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/components/ui/tooltip';

export default function Payslips() {
  const { t } = useTranslation();
  const { auth, payslips, employees, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedEmployee, setSelectedEmployee] = useState(pageFilters.employee_id || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [selectedCalculationType, setSelectedCalculationType] = useState(pageFilters.salary_calculation_type || 'all');
  const [selectedSalaryStatus, setSelectedSalaryStatus] = useState(pageFilters.salary_status || 'all');
  const [dateFrom, setDateFrom] = useState(pageFilters.date_from || '');
  const [dateTo, setDateTo] = useState(pageFilters.date_to || '');
  const [showFilters, setShowFilters] = useState(false);
  const [isExporting, setIsExporting] = useState(false);

  // Edit Modal State
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [currentPayslip, setCurrentPayslip] = useState<any>(null);

  // Check if any filters are active
  const hasActiveFilters = () => {
    return searchTerm !== '' || selectedEmployee !== 'all' || selectedStatus !== 'all' || selectedCalculationType !== 'all' || selectedSalaryStatus !== 'all' || dateFrom !== '' || dateTo !== '';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (searchTerm ? 1 : 0) + (selectedEmployee !== 'all' ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedCalculationType !== 'all' ? 1 : 0) + (selectedSalaryStatus !== 'all' ? 1 : 0) + (dateFrom ? 1 : 0) + (dateTo ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const applyFilters = () => {
    router.get(route('hr.payslips.index'), {
      page: 1,
      search: searchTerm || undefined,
      employee_id: selectedEmployee !== 'all' ? selectedEmployee : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      salary_calculation_type: selectedCalculationType !== 'all' ? selectedCalculationType : undefined,
      salary_status: selectedSalaryStatus !== 'all' ? selectedSalaryStatus : undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.payslips.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      employee_id: selectedEmployee !== 'all' ? selectedEmployee : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      salary_calculation_type: selectedCalculationType !== 'all' ? selectedCalculationType : undefined,
      salary_status: selectedSalaryStatus !== 'all' ? selectedSalaryStatus : undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: any) => {
    switch (action) {
      case 'download':
        handleDownload(item);
        break;
      case 'edit':
        setCurrentPayslip(item);
        setIsEditModalOpen(true);
        break;
      case 'toggle-hold':
        router.put(route('hr.payslips.toggle-hold', item.id), {}, {
          onSuccess: (page) => {
            toast.dismiss();
            if ((page.props.flash as any)?.success) {
              toast.success(t((page.props.flash as any).success));
            } else if ((page.props.flash as any)?.error) {
              toast.error(t((page.props.flash as any).error));
            }
          },
          onError: () => {
            toast.dismiss();
            toast.error(t('Failed to update salary status'));
          }
        });
        break;
      case 'regenerate':
        toast.loading(t('Regenerating payslip...'));
        router.post(route('hr.payslips.regenerate', item.id), {}, {
          onSuccess: (page) => {
            toast.dismiss();
            if ((page.props.flash as any)?.success) {
              toast.success(t((page.props.flash as any).success));
            }
          },
          onError: () => {
            toast.dismiss();
            toast.error(t('Failed to regenerate payslip'));
          }
        });
        break;
    }
  };

  const handleDownload = (payslip: any) => {
    toast.loading(t('Downloading payslip...'));

    window.location.href = route('hr.payslips.download', payslip.id);

    // Clear loading toast after a delay
    setTimeout(() => {
      toast.dismiss();
      toast.success(t('Payslip downloaded successfully'));
    }, 1000);
  };

  const handleBulkExcelExport = () => {
    const from = dateFrom;
    const to = dateTo;

    if (from && to) {
      const startDate = new Date(from);
      const endDate = new Date(to);
      const diffTime = Math.abs(endDate.getTime() - startDate.getTime());
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays > 62) {
        toast.error("Maximum 62 days date range is allowed for bulk export.");
        return;
      }
    }

    setIsExporting(true);
    const toastId = toast.loading("Generating Bulk Excel Export...");

    const queryParams = new URLSearchParams({
      search: searchTerm || '',
      employee_id: selectedEmployee !== 'all' ? selectedEmployee : '',
      status: selectedStatus !== 'all' ? selectedStatus : '',
      salary_calculation_type: selectedCalculationType !== 'all' ? selectedCalculationType : '',
      salary_status: selectedSalaryStatus !== 'all' ? selectedSalaryStatus : 'released', // default released for bulk
      date_from: dateFrom ? new Date(dateFrom).toISOString().split('T')[0] : '',
      date_to: dateTo ? new Date(dateTo).toISOString().split('T')[0] : '',
    });

    window.location.href = route('hr.payslips.export-bulk-excel') + '?' + queryParams.toString();

    // Reset exporting state after some time
    setTimeout(() => {
      setIsExporting(false);
      toast.dismiss();
      toast.success(t('Excel download started.'));
    }, 2000);
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedEmployee('all');
    setSelectedStatus('all');
    setSelectedCalculationType('all');
    setSelectedSalaryStatus('all');
    setDateFrom('');
    setDateTo('');
    setShowFilters(false);

    router.get(route('hr.payslips.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  // Define page actions
  const pageActions: any[] = [];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Payroll Management'), href: route('hr.payslips.index') },
    { title: t('Payslips') }
  ];

  // Define table columns
  const columns = [
    {
      key: 'payslip_number',
      label: t('Payslip Number'),
      sortable: true,
      render: (value: string) => (
        <span className="font-mono text-blue-600">{value}</span>
      )
    },
    {
      key: 'employee',
      label: t('Employee'),
      render: (value: any, row: any) => (
        <div className="flex flex-col">
          <span className="font-medium text-gray-900">{row.employee?.name || '-'}</span>
          <div className="text-xs text-gray-500">
            <span>{row.employee?.employee?.employee_id || '-'}</span>
            <span className="mx-1">•</span>
            <span>{row.employee?.employee?.branch?.name || '-'}</span>
          </div>
        </div>
      )
    },
    {
      key: 'pay_period',
      label: t('Pay Period'),
      render: (value: any, row: any) => (
        <div className="text-sm">
          <div>{window.appSettings?.formatDateTime(row.pay_period_start, false) || new Date(row.pay_period_start).toLocaleDateString()}</div>
          <div className="text-gray-500">to {window.appSettings?.formatDateTime(row.pay_period_end, false) || new Date(row.pay_period_end).toLocaleDateString()}</div>
        </div>
      )
    },
    {
      key: 'pay_date',
      label: t('Pay Date'),
      sortable: true,
      render: (value: string, row: any) => {
        if (row.salary_status === 'hold') return '-';
        return window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString();
      }
    },
    {
      key: 'salary_calculation_type',
      label: t('Type'),
      render: (value: any, row: any) => {
        const type = row.payroll_entry?.payroll_run?.salary_calculation_type || 'basic_pay';
        return (
          <span className="capitalize">
            {type === 'basic_pay' ? t('Basic Pay') : t('Minimum Wages')}
          </span>
        );
      }
    },
    {
      key: 'net_pay',
      label: t('Net Pay'),
      render: (value: any, row: any) => (
        <span className="font-mono text-green-600">
          {window.appSettings?.formatCurrency(row.payroll_entry?.net_pay || 0)}
        </span>
      )
    },
    {
      key: 'status',
      label: t('Status'),
      render: (value: string) => {
        const statusColors = {
          generated: 'bg-blue-50 text-blue-700 ring-blue-600/20',
          sent: 'bg-green-50 text-green-700 ring-green-600/20',
          downloaded: 'bg-purple-50 text-purple-700 ring-purple-600/20'
        };
        return (
          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${statusColors[value as keyof typeof statusColors]}`}>
            {t(value)}
          </span>
        );
      }
    },
    {
      key: 'salary_status',
      label: t('Salary Status'),
      render: (value: string) => {
        const isHold = value === 'hold';
        return (
          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${isHold
            ? 'bg-red-50 text-red-700 ring-red-600/20'
            : 'bg-green-50 text-green-700 ring-green-600/20'
            }`}>
            {isHold ? t('HOLD') : t('RELEASED')}
          </span>
        );
      }
    },
    {
      key: 'created_at',
      label: t('Generated On'),
      sortable: true,
      render: (value: string) => new Date(value).toLocaleString('en-GB', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).replace(',', '')
    },
    {
      key: 'updated_at',
      label: t('Updated On'),
      sortable: true,
      render: (value: string) => new Date(value).toLocaleString('en-GB', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).replace(',', '')
    }

  ];

  // Define table actions
  const actions = [
    {
      label: t('Edit'),
      icon: 'Pencil',
      action: 'edit',
      className: 'text-amber-500',
      condition: (item: any) => item.payroll_entry?.payroll_run?.status === 'in_review'
    },
    {
      label: t('Download PDF'),
      icon: 'Download',
      action: 'download',
      className: 'text-blue-500',
      requiredPermission: 'download-payslips'
    },
    {
      label: t('Regenerate PDF'),
      icon: 'RefreshCw',
      action: 'regenerate',
      className: 'text-emerald-600',
      requiredPermission: 'create-payslips'
    },
    {
      // Shown when salary is RELEASED and payroll is not completed → allow marking as Hold
      label: t('Mark as Hold'),
      icon: 'Lock',
      action: 'toggle-hold',
      className: 'text-orange-500',
      condition: (item: any) => {
        const runStatus = item.payroll_entry?.payroll_run?.status;
        const salaryStatus = item.salary_status ?? 'released';
        // Only show if salary is released and payroll is not yet completed
        return salaryStatus !== 'hold' && runStatus !== 'completed';
      }
    },
    {
      // Shown when salary is on HOLD → allow releasing (even after payroll completed)
      label: t('Release'),
      icon: 'Unlock',
      action: 'toggle-hold',
      className: 'text-green-600',
      condition: (item: any) => (item.salary_status ?? 'released') === 'hold'
    }
  ];

  // Prepare options for filters
  const employeeOptions = [
    { value: 'all', label: t('All Employees') },
    ...(employees || []).map((emp: any) => ({
      value: emp.id.toString(),
      label: emp.name
    }))
  ];

  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'generated', label: t('Generated') },
    { value: 'sent', label: t('Sent') },
    { value: 'downloaded', label: t('Downloaded') }
  ];

  const calculationTypeOptions = [
    { value: 'all', label: t('All Types') },
    { value: 'basic_pay', label: t('Basic Pay') },
    { value: 'minimum_wages', label: t('Minimum Wages') }
  ];

  const salaryStatusOptions = [
    { value: 'all', label: t('All Salary Statuses') },
    { value: 'released', label: t('Released') },
    { value: 'hold', label: t('Hold') }
  ];

  return (
    <PageTemplate
      title={t("Payslip Management")}
      url="/payslips"
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
              name: 'salary_calculation_type',
              label: t('Type'),
              type: 'combobox',
              value: selectedCalculationType,
              onChange: setSelectedCalculationType,
              options: calculationTypeOptions
            },
            {
              name: 'salary_status',
              label: t('Salary Status'),
              type: 'combobox',
              value: selectedSalaryStatus,
              onChange: setSelectedSalaryStatus,
              options: salaryStatusOptions
            },
            {
              name: 'date_from',
              label: t('Period From'),
              type: 'date',
              value: dateFrom,
              onChange: setDateFrom
            },
            {
              name: 'date_to',
              label: t('Period To'),
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
            router.get(route('hr.payslips.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              employee_id: selectedEmployee !== 'all' ? selectedEmployee : undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              salary_calculation_type: selectedCalculationType !== 'all' ? selectedCalculationType : undefined,
              salary_status: selectedSalaryStatus !== 'all' ? selectedSalaryStatus : undefined,
              date_from: dateFrom || undefined,
              date_to: dateTo || undefined
            }, { preserveState: true, preserveScroll: true });
          }}
          extraActions={
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="sm"
                    className="h-8 px-2 py-1 border-green-600 text-green-700 hover:bg-green-50"
                    onClick={handleBulkExcelExport}
                    disabled={isExporting}
                  >
                    <FileSpreadsheet className="h-3.5 w-3.5 mr-1.5" />
                    {isExporting ? t('Downloading...') : "Download All Payslips"}
                  </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-[450px] !bg-black !text-black border-black">
                  <p className="text-[12px] leading-relaxed text-white">
                    <strong className="uppercase">Notice:</strong> If dates are not selected, the export defaults to the <strong>Current Month</strong>. Maximum range allowed is <strong>62 days</strong>. Always select "Period From/To" when using filters for accurate results.
                  </p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          }
        />
      </div>

      {/* Content section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={payslips?.data || []}
          from={payslips?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
        />

        {/* Pagination section */}
        <Pagination
          from={payslips?.from || 0}
          to={payslips?.to || 0}
          total={payslips?.total || 0}
          links={payslips?.links}
          entityName={t("payslips")}
          onPageChange={(url) => router.get(url)}
        />
      </div >

      <PayslipEditModal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        payslip={currentPayslip}
      />
    </PageTemplate >
  );
}