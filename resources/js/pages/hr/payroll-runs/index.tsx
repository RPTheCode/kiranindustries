// pages/hr/payroll-runs/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { 
    Plus, Play, Eye, CheckCircle, RefreshCw, FileSpreadsheet,
    AlertCircle, AlertTriangle, Loader2, ArrowRight, Banknote, Edit, Trash2,
    Search, Filter, X, ChevronRight, Download, Info, FileCheck, CheckCircle2
} from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Modal } from '@/components/ui/modal';
import { ErrorBoundary } from '@/components/error-boundary';
import { PayrollRunWizard } from './components/PayrollRunWizard';
import { ExportSummaryModal } from './components/ExportSummaryModal';
import { periodDatesFromMonthYear } from '@/lib/payroll-scope';
import axios from 'axios';

interface Props {
    payrollRuns: any;
    filters: any;
    branches: any[];
    departments: any[];
}

export default function PayrollRuns() {
    const { t } = useTranslation();
    const {
        auth,
        payrollRuns,
        filters: pageFilters = {},
        branches: rawBranches = [],
        departments: rawDepartments = [],
        shifts: rawShifts = [],
        categories: rawCategories = [],
        designations: rawDesignations = [],
        skills: rawSkills = [],
        activeBranchId,
    } = usePage().props as any;
    const branches = rawBranches || [];
    const departments = rawDepartments || [];
    const shifts = rawShifts || [];
    const categories = rawCategories || [];
    const designations = rawDesignations || [];
    const skills = rawSkills || [];
    const permissions = auth?.permissions || [];

    // State
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [dateFrom, setDateFrom] = useState(pageFilters.date_from || '');
    const [dateTo, setDateTo] = useState(pageFilters.date_to || '');
    const [filterMonth, setFilterMonth] = useState(pageFilters.month_year || '');
    const [filterBranchId, setFilterBranchId] = useState(pageFilters.filter_branch_id || '');
    const [filterDepartmentId, setFilterDepartmentId] = useState(pageFilters.filter_department_id || '');
    const [filterSalaryType, setFilterSalaryType] = useState(pageFilters.salary_calculation_type || '');
    const [showFilters, setShowFilters] = useState(false);
    const [isWizardOpen, setIsWizardOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isReleaseModalOpen, setIsReleaseModalOpen] = useState(false);
    const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false);
    const [isRegenerateModalOpen, setIsRegenerateModalOpen] = useState(false);
    const [isExportModalOpen, setIsExportModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
    const [releaseMode, setReleaseMode] = useState<'all' | 'non_hold'>('all');

    // Processing States
    const [isProcessModalOpen, setIsProcessModalOpen] = useState(false);
    const [processStep, setProcessStep] = useState<'checking' | 'health_check' | 'mispunch_report' | 'processing' | 'finalizing' | 'completed' | 'error'>('checking');
    
    console.log('[PayrollRuns] State Update', { isProcessModalOpen, processStep });
    const [mispunchEmployees, setMispunchEmployees] = useState<any[]>([]);
    const [processProgress, setProcessProgress] = useState(0);
    const [totalEmployees, setTotalEmployees] = useState(0);
    const [validCount, setValidCount] = useState(0);
    const [mispunchCount, setMispunchCount] = useState(0);
    const [employeeIds, setEmployeeIds] = useState<number[]>([]);
    const [processingError, setProcessingError] = useState<string | null>(null);

    const [readySearch, setReadySearch] = useState('');
    const [mispunchSearch, setMispunchSearch] = useState('');
    const [readyEmployees, setReadyEmployees] = useState<any[]>([]);
    const [skippedEmployees, setSkippedEmployees] = useState<any[]>([]);
    const [processTab, setProcessTab] = useState<'ready' | 'exceptions' | 'skipped'>('ready');
    const [isMispunchReportLoading, setIsMispunchReportLoading] = useState(false);

    const filterQueryParams = (extra: Record<string, unknown> = {}) => ({
        page: 1,
        search: searchTerm || undefined,
        status: selectedStatus !== 'all' ? selectedStatus : undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        month_year: filterMonth || undefined,
        filter_branch_id: filterBranchId || undefined,
        filter_department_id: filterDepartmentId || undefined,
        salary_calculation_type: filterSalaryType || undefined,
        per_page: pageFilters.per_page,
        ...extra,
    });

    const hasActiveFilters = () =>
        searchTerm !== '' ||
        selectedStatus !== 'all' ||
        dateFrom !== '' ||
        dateTo !== '' ||
        filterMonth !== '' ||
        filterBranchId !== '' ||
        filterDepartmentId !== '' ||
        filterSalaryType !== '';

    const activeFilterCount = () =>
        (searchTerm ? 1 : 0) +
        (selectedStatus !== 'all' ? 1 : 0) +
        (dateFrom ? 1 : 0) +
        (dateTo ? 1 : 0) +
        (filterMonth ? 1 : 0) +
        (filterBranchId ? 1 : 0) +
        (filterDepartmentId ? 1 : 0) +
        (filterSalaryType ? 1 : 0);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        router.get(route('hr.payroll-runs.index'), filterQueryParams(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSort = (field: string) => {
        const direction =
            pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

        router.get(
            route('hr.payroll-runs.index'),
            filterQueryParams({ sort_field: field, sort_direction: direction }),
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleAction = (action: string, item: any) => {
        setCurrentItem({
            ...item,
            branch_id: item?.branch_id != null ? String(item.branch_id) : '',
            department_id: item?.department_id != null ? String(item.department_id) : '',
        });

        switch (action) {
            case 'view':
                router.get(route('hr.payroll-runs.show', item.id));
                break;
            case 'edit':
                setFormMode('edit');
                setIsWizardOpen(true);
                break;
            case 'delete':
                setIsDeleteModalOpen(true);
                break;
            case 'process':
                handleProcessPayroll(item);
                break;
            case 'confirm':
                setIsReleaseModalOpen(true);
                break;
            case 'regenerate':
                setIsRegenerateModalOpen(true);
                break;
            case 'export-advances':
                window.location.href = route('hr.payroll-runs.export-advances', item.id);
                break;
            case 'export-salary-register':
                window.location.href = route('hr.payroll-runs.export-salary-register', item.id);
                break;
        }
    };

    const handleAddNew = () => {
        const now = new Date();
        const defaultMonthYear = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        const defaults = periodDatesFromMonthYear(defaultMonthYear);

        setCurrentItem({
            month_year: defaultMonthYear,
            title: defaults.title,
            pay_period_start: defaults.pay_period_start,
            pay_period_end: defaults.pay_period_end,
            pay_date: defaults.pay_date,
            salary_calculation_type: 'basic_pay',
            branch_id: activeBranchId ? String(activeBranchId) : '',
            department_id: '',
        });
        setFormMode('create');
        setIsWizardOpen(true);
    };

    const handleFormSubmit = (formData: Record<string, unknown>) => {
        if (formMode === 'create') {
            toast.loading(t('Creating payroll run...'));

            router.post(route('hr.payroll-runs.store'), formData, {
                onSuccess: (page) => {
                    setIsWizardOpen(false);
                    toast.dismiss();
                    if (page.props.flash.success) {
                        toast.success(t(page.props.flash.success));
                    }
                    if (page.props.flash.warning) {
                        toast.warning(t(page.props.flash.warning));
                    }
                    if (page.props.flash.error) {
                        toast.error(t(page.props.flash.error));
                    }
                },
                onError: (errors) => {
                    toast.dismiss();
                    if (typeof errors === 'string') {
                        toast.error(errors);
                    } else {
                        toast.error(`Failed to create payroll run: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        } else if (formMode === 'edit') {
            toast.loading(t('Updating payroll run...'));

            router.put(route('hr.payroll-runs.update', currentItem.id), formData, {
                onSuccess: (page) => {
                    setIsWizardOpen(false);
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
                        toast.error(`Failed to update payroll run: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        }
    };

    const handleDeleteConfirm = () => {
        toast.loading(t('Deleting payroll run...'));

        router.delete(route('hr.payroll-runs.destroy', currentItem.id), {
            onSuccess: (page) => {
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
                    toast.error(`Failed to delete payroll run: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    const handleProcessPayroll = async (payrollRun: any) => {
        console.log('[PayrollRuns] handleProcessPayroll triggered', payrollRun?.id);
        setCurrentItem(payrollRun);
        setIsProcessModalOpen(true);
        setProcessStep('checking');
        setMispunchEmployees([]);
        setReadyEmployees([]);
        setSkippedEmployees([]);
        setProcessTab('ready');
        setProcessProgress(0);
        setProcessingError(null);

        try {
            const response = await axios.get(route('hr.payroll-runs.initiate-process', payrollRun.id));
            
            setEmployeeIds(response.data.valid_employee_ids || []);
            setMispunchEmployees(response.data.mispunch_employees || []);
            setReadyEmployees(response.data.ready_employees || []);
            setSkippedEmployees(response.data.skipped_employees || []);
            setTotalEmployees(response.data.total_count || 0);
            setValidCount(response.data.valid_count || 0);
            setMispunchCount(response.data.mispunch_count || 0);
            
            setProcessStep('health_check');
        } catch (error: any) {
            console.error('[PayrollRuns] Initiation Error', error);
            setProcessStep('error');
            setProcessingError(error.response?.data?.error || 'Failed to initialize processing');
        }
    };

    const handleReportMispunch = () => {
        if (!currentItem) return;
        
        // Use the dedicated route that generates the PDF directly
        const url = `/payroll-runs/${currentItem.id}/mispunch-report`;
        window.open(url, '_blank');
    };

    const startProcessing = async () => {
        if (!currentItem || (employeeIds || []).length === 0) return;

        setProcessStep('processing');
        const batchSize = 10;
        let processedCount = 0;

        try {
            for (let i = 0; i < employeeIds.length; i += batchSize) {
                const batch = employeeIds.slice(i, i + batchSize);
                await axios.post(route('hr.payroll-runs.process-batch', currentItem.id), {
                    employee_ids: batch
                });
                processedCount += batch.length;
                setProcessProgress(processedCount);
            }

            // Finalize
            setProcessStep('finalizing');
            await axios.post(route('hr.payroll-runs.finalize', currentItem.id));
            setProcessStep('completed');
            toast.success(t('Payroll processed successfully'));
            
            // Reload page to reflect changes
            setTimeout(() => {
                router.reload();
            }, 1500);
        } catch (error: any) {
            setProcessStep('error');
            setProcessingError(error.response?.data?.error || 'Error during processing batch');
            toast.error(t('Failed to complete payroll processing'));
        }
    };

    const handleConfirmPayroll = () => {
        toast.loading(t('Confirming payroll run...'));

        router.put(route('hr.payroll-runs.confirm', currentItem.id), { release_mode: releaseMode }, {
            onSuccess: (page) => {
                setIsConfirmModalOpen(false);
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
                    toast.error(`Failed to confirm payroll run: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    const handleRegeneratePayroll = () => {
        if (!currentItem) return;
        setIsRegenerateModalOpen(false);
        toast.loading(t('Resetting payroll run for regeneration...'));

        router.put(route('hr.payroll-runs.regenerate', currentItem.id), {}, {
            onSuccess: (page) => {
                toast.dismiss();
                if (page.props.flash?.error) {
                    toast.error(t(page.props.flash.error));
                    return;
                }
                if (page.props.flash?.success) {
                    toast.success(t(page.props.flash.success));
                }
                handleProcessPayroll({ ...currentItem, status: 'draft' });
            },
            onError: (errors) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(errors);
                } else {
                    toast.error(t('Failed to reset payroll run'));
                }
            }
        });
    };

    const handleGeneratePayslips = (payrollRun: any) => {
        toast.loading(t('Generating payslips...'));

        router.post(route('hr.payslips.bulk-generate'), {
            payroll_run_id: payrollRun.id
        }, {
            onSuccess: (page) => {
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                    // Redirect to payslips page to see generated payslips
                    setTimeout(() => {
                        router.get(route('hr.payslips.index'));
                    }, 1000);
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(errors);
                } else {
                    toast.error('Failed to generate payslips');
                }
            }
        });
    };

    const handleExportSummary = () => {
        setIsExportModalOpen(true);
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedStatus('all');
        setDateFrom('');
        setDateTo('');
        setFilterMonth('');
        setFilterBranchId('');
        setFilterDepartmentId('');
        setFilterSalaryType('');
        setShowFilters(false);

        router.get(
            route('hr.payroll-runs.index'),
            { page: 1, per_page: pageFilters.per_page },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Define page actions
    const pageActions = [];

    // Add the "Add New Payroll Run" button if user has permission
    if (hasPermission(permissions, 'create-payroll-runs')) {
        pageActions.push({
            label: t('Add Payroll Run'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => handleAddNew()
        });
    }

    pageActions.push({
        label: t('Monthly Summary'),
        icon: <FileSpreadsheet className="h-4 w-4 mr-2" />,
        variant: 'outline',
        onClick: () => handleExportSummary()
    });

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Payroll Management'), href: route('hr.payroll-runs.index') },
        { title: t('Payroll Runs') }
    ];

    // Define table columns
    const columns = [
        {
            key: 'title',
            label: t('Title'),
            sortable: true
        },
        {
            key: 'pay_period',
            label: t('Pay Period'),
            render: (_value: any, row: any) => (
                <div className="text-sm">
                    <div>
                        {window.appSettings?.formatDateTime(row.pay_period_start, false) ||
                            new Date(row.pay_period_start).toLocaleDateString()}
                    </div>
                    <div className="text-gray-500">
                        to{' '}
                        {window.appSettings?.formatDateTime(row.pay_period_end, false) ||
                            new Date(row.pay_period_end).toLocaleDateString()}
                    </div>
                    {row.period_days != null && (
                        <div className="text-[10px] text-slate-500 mt-0.5">
                            {row.period_days} {t('days')}
                            {row.period_days < 28 && (
                                <span className="ml-1 text-amber-600 font-medium">({t('partial')})</span>
                            )}
                        </div>
                    )}
                    {row.scope_summary && (
                        <div className="text-[10px] text-slate-400 truncate max-w-[180px]" title={row.scope_summary}>
                            {row.scope_summary}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'pay_date',
            label: t('Pay Date'),
            sortable: true,
            render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
        },
        {
            key: 'employee_count',
            label: t('Employees'),
            render: (_value: number, row: any) => {
                const total = row.eligible_count ?? row.employee_count ?? 0;
                const processed = row.processed_count ?? 0;
                const issues = row.mispunch_count ?? 0;
                
                return (
                    <div className="flex flex-col gap-0.5">
                        <div className="text-[13px] font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                            <span className="font-mono">{processed}</span>
                            <span className="text-gray-400 font-normal">/</span>
                            <span className="font-mono text-gray-500">{total}</span>
                        </div>
                        {issues > 0 && (
                            <div className="flex items-center gap-1 text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-tight">
                                <AlertTriangle className="h-2.5 w-2.5" />
                                {issues} {t('Issues')}
                            </div>
                        )}
                        {issues === 0 && processed === total && total > 0 && (
                            <div className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-tight">
                                {t('Completed')}
                            </div>
                        )}
                    </div>
                );
            }
        },
        {
            key: 'total_gross_pay',
            label: t('Gross Pay'),
            render: (value: number) => (
                <span className="font-mono text-green-600">{window.appSettings?.formatCurrency(value)}</span>
            )
        },
        {
            key: 'total_net_pay',
            label: t('Net Pay'),
            render: (value: number) => (
                <span className="font-mono text-blue-600">{window.appSettings?.formatCurrency(value)}</span>
            )
        },
        {
            key: 'held_count',
            label: t('Held Employees'),
            render: (_value: number, row: any) => {
                const count = row.held_count ?? 0;
                if (count === 0) return <span className="text-gray-400">—</span>;
                return (
                    <span className="inline-flex items-center gap-1 font-mono font-medium text-orange-700">
                        {count}
                    </span>
                );
            }
        },
        {
            key: 'held_amount',
            label: t('Held Amount'),
            render: (_value: number, row: any) => {
                const amount = row.held_amount ?? 0;
                const count = row.held_count ?? 0;
                if (count === 0) return <span className="text-gray-400">—</span>;
                return (
                    <span className="font-mono font-medium text-orange-600">
                        {window.appSettings?.formatCurrency(amount)}
                    </span>
                );
            }
        },
        {
            key: 'status',
            label: t('Status'),
            render: (value: string) => {
                const statusColors = {
                    draft: 'bg-gray-50 text-gray-700 ring-gray-600/20',
                    processing: 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
                    in_review: 'bg-blue-50 text-blue-700 ring-blue-600/20',
                    completed: 'bg-green-50 text-green-700 ring-green-600/20',
                    cancelled: 'bg-red-50 text-red-700 ring-red-600/20'
                };
                return (
                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${statusColors[value as keyof typeof statusColors] || statusColors.draft}`}>
                        {t(value === 'in_review' ? 'In Review' : value)}
                    </span>
                );
            }
        }
    ];

    // Define table actions
    const actions = [
        {
            label: t('View Details'),
            icon: 'Eye',
            action: 'view',
            className: 'text-blue-500',
            requiredPermission: 'view-payroll-runs'
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500',
            requiredPermission: 'edit-payroll-runs',
            condition: (item: any) => ['draft', 'processing'].includes(item.status)
        },
        {
            label: t('Process'),
            icon: 'Play',
            action: 'process',
            className: 'text-green-500',
            requiredPermission: 'process-payroll-runs',
            condition: (item: any) => ['draft', 'processing'].includes(item.status)
        },
        {
            label: t('Confirm'),
            icon: 'CheckCircle',
            action: 'confirm',
            className: 'text-green-600',
            requiredPermission: 'process-payroll-runs',
            condition: (item: any) => item.status === 'in_review'
        },
        {
            label: t('Regenerate'),
            icon: 'RefreshCw',
            action: 'regenerate',
            className: 'text-orange-600',
            requiredPermission: 'process-payroll-runs',
            condition: (item: any) => ['in_review', 'completed'].includes(item.status)
        },
        {
            label: t('Export Advances Paid'),
            icon: 'Banknote',
            action: 'export-advances',
            className: 'text-emerald-600',
            requiredPermission: 'view-payroll-runs'
        },
        {
            label: t('Export Salary Register'),
            icon: 'FileSpreadsheet',
            action: 'export-salary-register',
            className: 'text-blue-600',
            requiredPermission: 'view-payroll-runs'
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500',
            requiredPermission: 'delete-payroll-runs',
            condition: (item: any) => ['draft', 'processing'].includes(item.status)
        }
    ];

    // Prepare options for filters
    const statusOptions = [
        { value: 'all', label: t('All Statuses') },
        { value: 'draft', label: t('Draft') },
        { value: 'processing', label: t('Processing') },
        { value: 'in_review', label: t('In Review') },
        { value: 'completed', label: t('Completed') },
        { value: 'cancelled', label: t('Cancelled') },
    ];

    const monthFilterOptions = () => {
        const options = [{ value: '', label: t('All months') }];
        const now = new Date();
        for (let i = 0; i < 18; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
            options.push({ value, label });
        }
        return options;
    };

    const branchFilterOptions = [
        { value: '', label: t('All branches') },
        ...branches.map((b: { id: number; name: string }) => ({
            value: String(b.id),
            label: b.name,
        })),
    ];

    const departmentFilterOptions = [
        { value: '', label: t('All departments') },
        ...departments.map((d: { id: number; name: string }) => ({
            value: String(d.id),
            label: d.name,
        })),
    ];

    const salaryTypeOptions = [
        { value: '', label: t('All salary types') },
        { value: 'basic_pay', label: t('Basic Pay') },
        { value: 'minimum_wages', label: t('Minimum Wages') },
    ];

    return (
        <PageTemplate
            title={t("Payroll Run Management")}
            url="/payroll-runs"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
                <SearchAndFilterBar
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    onSearch={handleSearch}
                    filters={[
                        {
                            name: 'month_year',
                            label: t('Month'),
                            type: 'select',
                            value: filterMonth,
                            onChange: setFilterMonth,
                            options: monthFilterOptions(),
                        },
                        {
                            name: 'status',
                            label: t('Status'),
                            type: 'select',
                            value: selectedStatus,
                            onChange: setSelectedStatus,
                            options: statusOptions,
                        },
                        {
                            name: 'filter_branch_id',
                            label: t('Branch'),
                            type: 'select',
                            value: filterBranchId,
                            onChange: setFilterBranchId,
                            options: branchFilterOptions,
                        },
                        {
                            name: 'filter_department_id',
                            label: t('Department'),
                            type: 'select',
                            value: filterDepartmentId,
                            onChange: setFilterDepartmentId,
                            options: departmentFilterOptions,
                        },
                        {
                            name: 'salary_calculation_type',
                            label: t('Salary Basis'),
                            type: 'select',
                            value: filterSalaryType,
                            onChange: setFilterSalaryType,
                            options: salaryTypeOptions,
                        },
                        {
                            name: 'date_from',
                            label: t('Period From'),
                            type: 'date',
                            value: dateFrom,
                            onChange: setDateFrom,
                        },
                        {
                            name: 'date_to',
                            label: t('Period To'),
                            type: 'date',
                            value: dateTo,
                            onChange: setDateTo,
                        },
                    ]}
                    showFilters={showFilters}
                    setShowFilters={setShowFilters}
                    hasActiveFilters={hasActiveFilters}
                    activeFilterCount={activeFilterCount}
                    onResetFilters={handleResetFilters}
                    onApplyFilters={applyFilters}
                    currentPerPage={pageFilters.per_page?.toString() || "10"}
                    onPerPageChange={(value) => {
                        router.get(
                            route('hr.payroll-runs.index'),
                            filterQueryParams({ per_page: parseInt(value, 10) }),
                            { preserveState: true, preserveScroll: true }
                        );
                    }}
                />
            </div>

            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <CrudTable
                    columns={columns}
                    actions={actions}
                    data={payrollRuns?.data || []}
                    from={payrollRuns?.from || 1}
                    onAction={handleAction}
                    sortField={pageFilters.sort_field}
                    sortDirection={pageFilters.sort_direction}
                    onSort={handleSort}
                    permissions={permissions}
                    entityPermissions={{ view: 'view-payroll-runs', create: 'create-payroll-runs', edit: 'edit-payroll-runs', delete: 'delete-payroll-runs' }}
                />
                <Pagination
                    from={payrollRuns?.from || 0}
                    to={payrollRuns?.to || 0}
                    total={payrollRuns?.total || 0}
                    links={payrollRuns?.links}
                    entityName={t("payroll runs")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

            <PayrollRunWizard
                isOpen={isWizardOpen}
                onClose={() => setIsWizardOpen(false)}
                mode={formMode === 'edit' ? 'edit' : 'create'}
                initialRun={currentItem}
                activeBranchId={activeBranchId}
                branches={branches}
                departments={departments}
                shifts={shifts}
                categories={categories}
                designations={designations}
                skills={skills}
                onSubmit={handleFormSubmit}
            />

            <ExportSummaryModal 
                isOpen={isExportModalOpen}
                onClose={() => setIsExportModalOpen(false)}
            />

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.title || ''}
                entityName="payroll run"
            />

            <Modal isOpen={isReleaseModalOpen} onClose={() => setIsReleaseModalOpen(false)} size="md" showClose={false}>
                <div className="flex flex-col p-2">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 mb-4">
                        <CheckCircle className="h-8 w-8 text-blue-600" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1 text-center">{t('Release Salary Options')}</h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-5 text-center">{t('How do you want to release salaries?')}</p>
                    <div className="space-y-3 mb-6">
                        <label className={`flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors ${releaseMode === 'all' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300'}`}>
                            <input type="radio" name="release_mode" value="all" checked={releaseMode === 'all'} onChange={() => setReleaseMode('all')} className="mt-0.5 accent-blue-600" />
                            <div>
                                <p className="font-semibold text-sm text-gray-800 dark:text-gray-100">{t('Release Salary for All Employees')}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t('This will release salary for all employees including those on Hold')}</p>
                            </div>
                        </label>
                        <label className={`flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors ${releaseMode === 'non_hold' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300'}`}>
                            <input type="radio" name="release_mode" value="non_hold" checked={releaseMode === 'non_hold'} onChange={() => setReleaseMode('non_hold')} className="mt-0.5 accent-blue-600" />
                            <div>
                                <p className="font-semibold text-sm text-gray-800 dark:text-gray-100">{t('Release Only Non-Hold Employees')}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t('Employees who are on Hold will remain on Hold')}</p>
                            </div>
                        </label>
                    </div>
                    <div className="flex justify-center space-x-3 w-full">
                        <button type="button" onClick={() => setIsReleaseModalOpen(false)} className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors cursor-pointer">{t('Cancel')}</button>
                        <button type="button" onClick={() => { setIsReleaseModalOpen(false); setIsConfirmModalOpen(true); }} className="w-full px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors cursor-pointer">{t('Next')} →</button>
                    </div>
                </div>
            </Modal>

            <Modal isOpen={isConfirmModalOpen} onClose={() => setIsConfirmModalOpen(false)} size="md" showClose={false}>
                <div className="flex flex-col items-center text-center p-2">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 mb-6">
                        <CheckCircle className="h-8 w-8 text-green-600" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{t("Confirm Payroll Run")}</h3>
                    <div className="text-gray-500 dark:text-gray-400 mb-8 max-w-sm">
                        <p className="font-bold text-gray-800 dark:text-gray-200 text-base mb-3">{t("Are you sure you want to confirm this payroll run?")}</p>
                        <p className="text-sm">{t("This will finalize the payroll and lock all payslips from further editing.")}</p>
                    </div>
                    <div className="flex justify-center space-x-3 w-full">
                        <button type="button" onClick={() => setIsConfirmModalOpen(false)} className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors cursor-pointer">{t("Cancel")}</button>
                        <button type="button" onClick={handleConfirmPayroll} className="w-full px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors cursor-pointer">{t("Confirm & Finalize")}</button>
                    </div>
                </div>
            </Modal>

            <Modal isOpen={isRegenerateModalOpen} onClose={() => setIsRegenerateModalOpen(false)} size="md" showClose={false}>
                <div className="flex flex-col items-center text-center p-2">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 mb-6">
                        <RefreshCw className="h-8 w-8 text-orange-600" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{t("Regenerate Payroll Run")}</h3>
                    <div className="text-gray-500 dark:text-gray-400 mb-6 max-w-sm">
                        <p className="font-bold text-gray-800 dark:text-gray-200 text-base mb-3">{t("Are you sure you want to regenerate this payroll run?")}</p>
                        <p className="text-sm mb-4">{t("This will delete all existing payslips and manually applied edits for this run. The payroll will be recalculated from scratch.")}</p>
                    </div>
                    <div className="flex justify-center space-x-3 w-full">
                        <button type="button" onClick={() => setIsRegenerateModalOpen(false)} className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors cursor-pointer">{t("Cancel")}</button>
                        <button type="button" onClick={handleRegeneratePayroll} className="w-full px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors cursor-pointer">{t("Yes, Regenerate")}</button>
                    </div>
                </div>
            </Modal>

            <Modal 
                isOpen={isProcessModalOpen} 
                onClose={() => processStep !== 'processing' && setIsProcessModalOpen(false)} 
                size={processStep === 'health_check' ? 'xl' : 'md'}
                className="overflow-hidden rounded-3xl border-none shadow-[0_32px_64px_-15px_rgba(0,0,0,0.3)]"
            >
                <ErrorBoundary fallback={
                    <div className="p-12 text-center bg-white dark:bg-gray-900 rounded-lg">
                        <AlertTriangle className="h-12 w-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-bold text-gray-900 dark:text-white">{t('UI Render Error')}</h3>
                        <p className="text-xs text-gray-500 mt-2 max-w-xs mx-auto">{t('The processing dashboard encountered a rendering issue. This usually happens if the backend data format is unexpected.')}</p>
                        <button onClick={() => window.location.reload()} className="mt-6 px-6 py-2 bg-gray-900 dark:bg-gray-800 text-white rounded-lg text-xs font-bold uppercase tracking-widest">{t('Refresh Page')}</button>
                    </div>
                }>
                    <div className="p-1 min-h-[300px]">
                        {processStep === 'checking' && (
                        <div className="flex flex-col items-center py-12">
                            <div className="relative mb-6">
                                <Loader2 className="h-12 w-12 text-[#2d4a77] animate-spin" />
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div className="h-1.5 w-1.5 bg-[#2d4a77] rounded-full animate-ping" />
                                </div>
                            </div>
                            <h3 className="text-lg font-bold text-gray-900 dark:text-white tracking-tight">{t('Analyzing Payroll Health')}</h3>
                            <p className="text-[11px] text-gray-500 mt-1">{t('Scanning biometric logs and attendance records...')}</p>
                        </div>
                    )}

                    {processStep === 'health_check' && (
                        <div className="flex flex-col max-h-[85vh]">
                            {/* Header Section */}
                            <div className="flex-none flex items-center justify-between mb-4 px-2 py-1">
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                                        {t('Processing Dashboard')}
                                    </h3>
                                    <p className="text-[11px] text-gray-500 font-medium uppercase tracking-wider">{t('Verify attendance integrity')}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="hidden sm:flex items-center gap-2 bg-gray-50 dark:bg-gray-800/50 px-3 py-1.5 rounded-xl border border-gray-100 dark:border-gray-700">
                                        <span className="text-[9px] font-bold text-gray-400 uppercase tracking-widest">{currentItem?.title}</span>
                                    </div>
                                    <div className="flex gap-2">
                                        <div className="bg-gray-50/50 dark:bg-gray-800/10 px-3 py-1.5 rounded-xl border border-gray-100 dark:border-gray-700 text-center">
                                            <div className="text-sm font-black text-gray-600 dark:text-gray-400 leading-none">{validCount + mispunchCount}</div>
                                            <div className="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">{t('Total')}</div>
                                        </div>
                                        <div className="bg-emerald-50/50 dark:bg-emerald-900/10 px-3 py-1.5 rounded-xl border border-emerald-100/50 dark:border-emerald-800/30 text-center">
                                            <div className="text-sm font-black text-emerald-600 dark:text-emerald-400 leading-none">{validCount}</div>
                                            <div className="text-[8px] font-bold text-emerald-500 uppercase tracking-widest mt-0.5">{t('Ready')}</div>
                                        </div>
                                        <div className="bg-amber-50/50 dark:bg-amber-900/10 px-3 py-1.5 rounded-xl border border-amber-100/50 dark:border-amber-800/30 text-center">
                                            <div className="text-sm font-black text-amber-600 dark:text-amber-400 leading-none">{mispunchCount}</div>
                                            <div className="text-[8px] font-bold text-amber-500 uppercase tracking-widest mt-0.5">{t('Issues')}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex-none flex gap-2 px-2 border-b border-gray-100 pb-2">
                                <button
                                    type="button"
                                    onClick={() => setProcessTab('ready')}
                                    className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase ${processTab === 'ready' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-50 text-gray-500'}`}
                                >
                                    {t('Ready')} ({validCount})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setProcessTab('exceptions')}
                                    className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase ${processTab === 'exceptions' ? 'bg-amber-100 text-amber-800' : 'bg-gray-50 text-gray-500'}`}
                                >
                                    {t('Exceptions')} ({mispunchCount})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setProcessTab('skipped')}
                                    className={`px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase ${processTab === 'skipped' ? 'bg-red-100 text-red-800' : 'bg-gray-50 text-gray-500'}`}
                                >
                                    {t('Skipped')} ({skippedEmployees.length})
                                </button>
                            </div>

                            <div className="flex-1 min-h-0 overflow-y-auto overflow-x-hidden custom-scrollbar flex flex-col gap-4 px-2 pb-2">
                                {processTab === 'exceptions' && mispunchCount > 0 && (
                                    <div className="flex-none bg-[#2d4a77]/5 border border-[#2d4a77]/20 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm w-full">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div className="h-8 w-8 bg-[#2d4a77]/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <AlertTriangle className="h-4 w-4 text-[#2d4a77]" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-[13px] font-bold text-[#2d4a77] leading-tight">
                                                    {mispunchCount} {t('Exceptions Detected')}
                                                </p>
                                                <p className="text-[11px] font-medium text-[#2d4a77]/70 leading-tight mt-0.5 truncate">
                                                    {t('These employees will be skipped. View report for details.')}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex-shrink-0 self-start sm:self-auto">
                                            <button 
                                                onClick={handleReportMispunch}
                                                className="flex items-center justify-center gap-1.5 py-1.5 px-3 bg-[#2d4a77] hover:bg-[#1e3250] text-white text-[10px] font-bold uppercase tracking-wider rounded-lg shadow-sm transition-all active:scale-[0.98]"
                                            >
                                                <FileSpreadsheet className="h-3.5 w-3.5 opacity-80" />
                                                {t('View Report')}
                                            </button>
                                        </div>
                                    </div>
                                )}

                                {processTab === 'skipped' && (
                                    skippedEmployees.length === 0 ? (
                                        <p className="text-center text-xs text-slate-400 py-8">{t('No skipped employees')}</p>
                                    ) : (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            {skippedEmployees.map((e: any, idx: number) => (
                                                <div key={idx} className="p-3 rounded-xl border bg-red-50/30 text-xs">
                                                    <div className="font-bold">{e.name}</div>
                                                    <div className="text-[10px] text-slate-500">{e.code} · {e.status}</div>
                                                </div>
                                            ))}
                                        </div>
                                    )
                                )}

                                {processTab === 'exceptions' && mispunchCount === 0 && (
                                    <p className="text-center text-xs text-slate-400 py-8">{t('No mispunch exceptions')}</p>
                                )}

                                {processTab === 'exceptions' && mispunchCount > 0 && (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        {mispunchEmployees.map((m: any, idx: number) => (
                                            <div key={idx} className="p-3 rounded-xl border bg-amber-50/50 text-xs">
                                                <div className="font-bold">{m.name || m.employee_name}</div>
                                                <div className="text-[10px] text-slate-500">{m.code || m.employee_code}</div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Ready to Process Section */}
                                {processTab === 'ready' && (!Array.isArray(readyEmployees) || readyEmployees.length === 0) ? (
                                    <div className="bg-gray-50/50 dark:bg-gray-800/30 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 p-8 flex flex-col items-center justify-center text-center">
                                        <div className="h-12 w-12 bg-white dark:bg-gray-900 rounded-full flex items-center justify-center mb-3 shadow-sm border border-gray-100 dark:border-gray-800">
                                            <Info className="h-5 w-5 text-gray-300 dark:text-gray-600" />
                                        </div>
                                        <p className="text-[11px] font-bold text-gray-400 uppercase tracking-widest">{t('No clean records ready for processing')}</p>
                                    </div>
                                ) : processTab === 'ready' ? (
                                    <div className="flex-none flex flex-col bg-white dark:bg-gray-900 rounded-[1.5rem] border border-gray-100 dark:border-gray-800 shadow-sm transition-all overflow-hidden">
                                        <div className="p-3 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between sticky top-0 z-10">
                                            <div className="flex items-center gap-2 px-2">
                                                <div className="h-2 w-2 bg-emerald-500 rounded-full" />
                                                <span className="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{t('Ready to Process')}</span>
                                            </div>
                                            <div className="relative group">
                                                <div className="absolute inset-y-0 left-2.5 flex items-center pointer-events-none">
                                                    <Search className="h-3 w-3 text-gray-400" />
                                                </div>
                                                <input 
                                                    type="text" 
                                                    placeholder={t('Filter...')} 
                                                    className="text-[10px] font-medium py-1.5 pl-7 pr-3 bg-gray-50 dark:bg-gray-800 border-none rounded-xl w-48 focus:ring-1 focus:ring-[#2d4a77]/30 transition-all" 
                                                    value={readySearch} 
                                                    onChange={(e) => setReadySearch(e.target.value)} 
                                                />
                                            </div>
                                        </div>
                                        <div className="p-4">
                                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                {(Array.isArray(readyEmployees) ? readyEmployees : []).filter(e => e?.name?.toLowerCase().includes(readySearch?.toLowerCase() || '')).map((e, idx) => (
                                                    <div key={idx} className="flex items-center justify-between p-3 rounded-2xl bg-gray-50/50 dark:bg-gray-800/30 border border-transparent hover:border-emerald-200 dark:hover:border-emerald-900/50 hover:bg-white dark:hover:bg-gray-800 transition-all duration-300">
                                                        <div className="flex items-center gap-3 min-w-0">
                                                            <div className="h-8 w-8 bg-white dark:bg-gray-800 rounded-xl flex items-center justify-center text-emerald-600 font-black text-xs shadow-sm flex-shrink-0">{e?.name?.charAt(0) || '?'}</div>
                                                            <div className="min-w-0">
                                                                <div className="text-xs font-black text-gray-900 dark:text-white tracking-tight truncate">{e?.name || 'Unknown'}</div>
                                                                <div className="text-[9px] text-gray-400 font-bold uppercase tracking-widest truncate">{e?.code || '-'}</div>
                                                            </div>
                                                        </div>
                                                        <CheckCircle className="h-4 w-4 text-emerald-500 opacity-50 flex-shrink-0" />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                            </div>

                            <div className="flex-none flex items-center justify-between mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button 
                                    onClick={() => setIsProcessModalOpen(false)} 
                                    className="px-6 py-3 text-[10px] font-bold text-gray-400 hover:text-gray-900 dark:hover:text-white uppercase tracking-widest transition-colors"
                                >
                                    {t('Cancel')}
                                </button>
                                <div className="flex gap-4 items-center">
                                    {mispunchCount > 0 && (
                                        <div className="hidden sm:flex items-center gap-2 bg-amber-50/50 dark:bg-amber-900/10 px-3 py-1.5 rounded-lg border border-amber-100 dark:border-amber-800/30">
                                            <Info className="h-3 w-3 text-amber-500 flex-shrink-0" />
                                            <p className="text-[9px] text-amber-700 dark:text-amber-400 font-bold uppercase tracking-tight">
                                                {t('Exceptions will be skipped')}
                                            </p>
                                        </div>
                                    )}
                                    <button 
                                        onClick={startProcessing} 
                                        disabled={validCount === 0} 
                                        className="group relative px-8 py-3.5 bg-[#2d4a77] text-white rounded-xl hover:bg-[#1e3250] transition-all font-bold uppercase tracking-wider shadow-lg shadow-[#2d4a77]/20 dark:shadow-none disabled:opacity-50 overflow-hidden"
                                    >
                                        <span className="relative z-10 flex items-center gap-2 text-xs">
                                            {t('Generate Payroll')}
                                            <span className="bg-white/20 px-1.5 py-0.5 rounded text-[9px]">{validCount}</span>
                                        </span>
                                        <div className="absolute inset-0 bg-gradient-to-r from-[#2d4a77] via-[#3a5d96] to-[#2d4a77] opacity-0 group-hover:opacity-100 transition-opacity" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {processStep === 'processing' && (
                        <div className="flex flex-col items-center py-10 min-h-[350px] justify-center">
                            <div className="relative mb-8 scale-100">
                                {/* Outer Glow Effect */}
                                <div className="absolute inset-[-10px] rounded-full bg-[#2d4a77]/10 blur-2xl animate-pulse" />
                                
                                <div className="relative h-32 w-32">
                                    {/* Background Circle */}
                                    <svg className="h-32 w-32 -rotate-90">
                                        <circle cx="64" cy="64" r="56" stroke="currentColor" strokeWidth="6" fill="transparent" className="text-gray-100 dark:text-gray-800" />
                                        {/* Progress Circle */}
                                        <circle 
                                            cx="64" 
                                            cy="64" 
                                            r="56" 
                                            stroke="currentColor" 
                                            strokeWidth="6" 
                                            fill="transparent" 
                                            strokeDasharray={352} 
                                            strokeDashoffset={352 - (352 * (validCount > 0 ? (processProgress / validCount) : 0))} 
                                            strokeLinecap="round"
                                            className="text-[#2d4a77] transition-all duration-700 ease-out drop-shadow-[0_0_8px_rgba(45,74,119,0.4)]" 
                                        />
                                    </svg>
                                    
                                    {/* Percentage Center */}
                                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                                        <div className="text-3xl font-black text-[#2d4a77] tracking-tighter">
                                            {validCount > 0 ? Math.round((processProgress / validCount) * 100) : 0}%
                                        </div>
                                        <div className="text-[8px] font-black text-gray-400 uppercase tracking-widest leading-none mt-1">{t('Complete')}</div>
                                    </div>
                                </div>
                            </div>

                            <h3 className="text-xl font-black mb-1 text-gray-900 dark:text-white uppercase tracking-tighter">
                                {processStep === 'finalizing' ? t('Finalizing Payroll') : t('Executing Batch')}
                            </h3>
                            <p className="text-[11px] text-gray-500 mb-6 font-medium">
                                {processStep === 'finalizing' 
                                    ? t('Generating payslips and calculating grand totals...') 
                                    : t('Syncing calculations and generating official payslips...')}
                            </p>
                            
                            <div className="w-full space-y-4 px-10">
                                <div className="flex flex-col items-center bg-[#2d4a77]/5 dark:bg-[#2d4a77]/20 p-5 rounded-3xl border border-[#2d4a77]/10 dark:border-[#2d4a77]/30 relative overflow-hidden group">
                                    <div className="absolute top-0 right-0 p-2 opacity-10 group-hover:opacity-20 transition-opacity">
                                        <Loader2 className="h-12 w-12 text-[#2d4a77] animate-spin" />
                                    </div>
                                    
                                    <div className="flex items-baseline gap-2">
                                        <span className="text-4xl font-black text-[#2d4a77] tabular-nums">{processProgress}</span>
                                        <span className="text-lg font-bold text-gray-400">/ {validCount + mispunchCount}</span>
                                    </div>
                                    <div className="text-[10px] font-black text-[#2d4a77]/70 uppercase tracking-[0.2em] mt-1 text-center">
                                        {t('Processed')} ({validCount} {t('Eligible')})
                                        {mispunchCount > 0 && <span className="block text-amber-600 text-[8px] tracking-tight mt-0.5">{mispunchCount} {t('Exceptions Skipped')}</span>}
                                    </div>
                                </div>
                                
                                <div className="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-5 relative overflow-hidden p-1 shadow-inner border border-gray-200/50 dark:border-gray-700/50">
                                    <div 
                                        className="bg-gradient-to-r from-[#2d4a77] via-[#3a5d96] to-[#2d4a77] h-full transition-all duration-1000 ease-out rounded-full shadow-[0_0_15px_rgba(45,74,119,0.3)] relative" 
                                        style={{ width: `${validCount > 0 ? (processProgress / validCount) * 100 : 0}%` }}
                                    >
                                        <div className="absolute inset-0 bg-[linear-gradient(45deg,rgba(255,255,255,0.2)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.2)_50%,rgba(255,255,255,0.2)_75%,transparent_75%,transparent)] bg-[length:20px_20px] animate-[progress-stripe_1s_linear_infinite]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                     {processStep === 'finalizing' && (
                        <div className="flex flex-col items-center py-10 min-h-[350px] justify-center">
                            <div className="relative mb-8">
                                <div className="absolute inset-[-10px] rounded-full bg-[#2d4a77]/10 blur-2xl animate-pulse" />
                                <div className="h-32 w-32 flex items-center justify-center relative">
                                    <div className="absolute inset-0 rounded-full border-4 border-gray-100 dark:border-gray-800 border-t-[#2d4a77] animate-spin" />
                                    <FileCheck className="h-10 w-10 text-[#2d4a77] animate-bounce" />
                                </div>
                            </div>

                            <h3 className="text-xl font-black mb-1 text-gray-900 dark:text-white uppercase tracking-tighter">{t('Finalizing')}</h3>
                            <p className="text-[11px] text-gray-500 mb-6 font-medium text-center px-10">
                                {t('Generating payslip PDFs and finishing records for {{count}} employees...', { count: validCount })}
                            </p>

                            <div className="flex items-center gap-2 bg-emerald-50 text-emerald-700 px-4 py-2 rounded-xl border border-emerald-100 font-bold text-[10px] uppercase tracking-widest animate-pulse">
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                {t('Calculations Complete')}
                            </div>
                        </div>
                    )}

                    {processStep === 'completed' && (
                        <div className="flex flex-col items-center py-10 text-center">
                            <div className="h-20 w-20 bg-green-50 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-6 relative">
                                <div className="absolute inset-0 rounded-full border-4 border-green-500 animate-ping opacity-20" />
                                <CheckCircle className="h-10 w-10 text-green-600" />
                            </div>
                            <h3 className="text-2xl font-black text-gray-900 dark:text-white mb-2 tracking-tighter">{t('Payroll Complete')}</h3>
                            <p className="text-[11px] text-gray-500 mb-8 max-w-[280px] leading-relaxed">{t('Successfully processed')} <span className="font-black text-[#2d4a77]">{processProgress}</span> {t('employees. All records are finalized and locked for review.')}</p>
                            <button onClick={() => { setIsProcessModalOpen(false); router.reload(); }} className="w-full max-w-xs py-3.5 bg-gray-900 text-white rounded-xl hover:bg-black transition-all font-black uppercase tracking-widest shadow-xl cursor-pointer text-xs">{t('Close')}</button>
                        </div>
                    )}

                    {processStep === 'error' && (
                        <div className="flex flex-col items-center py-12 text-center px-4">
                            <div className="h-20 w-20 bg-red-50 dark:bg-red-900/20 rounded-full flex items-center justify-center mb-6"><AlertTriangle className="h-10 w-10 text-red-600" /></div>
                            <h3 className="text-2xl font-black text-red-600 mb-2">{t('Process Terminated')}</h3>
                            <p className="text-sm text-gray-500 mb-10 leading-relaxed">{processingError || t('A critical error occurred while communicating with the payroll engine. Please check your connection and try again.')}</p>
                            <div className="flex gap-4 w-full">
                                <button onClick={() => setIsProcessModalOpen(false)} className="flex-1 py-4 border-2 border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition-all font-black uppercase tracking-widest cursor-pointer">{t('Close')}</button>
                                <button onClick={handleProcessPayroll.bind(null, currentItem)} className="flex-1 py-4 bg-red-600 text-white rounded-xl hover:bg-red-700 transition-all font-black uppercase tracking-widest shadow-lg shadow-red-200 cursor-pointer">{t('Retry Now')}</button>
                            </div>
                        </div>
                    )}
                </div>
            </ErrorBoundary>
        </Modal>
        </PageTemplate>
    );
}