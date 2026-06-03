
import { useState } from 'react';
import React from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, Download, ChevronRight, Edit, Trash2, FileUp } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { getImagePath } from '@/utils/helpers';
import { useInitials } from '@/hooks/use-initials';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';

export default function EmployeeAdvances() {
    const { t } = useTranslation();
    const { auth, advances, employees, filters: pageFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const getInitials = useInitials();

    // State
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [fromDate, setFromDate] = useState(pageFilters.from_date || '');
    const [toDate, setToDate] = useState(pageFilters.to_date || '');
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importErrors, setImportErrors] = useState<string | null>(null);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
    const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});

    // Check if any filters are active
    const hasActiveFilters = () => {
        return searchTerm !== '' || fromDate !== '' || toDate !== '';
    };

    const toggleRow = (id: string) => {
        setExpandedRows(prev => ({
            ...prev,
            [id]: !prev[id]
        }));
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        router.get(route('hr.employee-advances.index'), {
            page: 1,
            search: searchTerm || undefined,
            from_date: fromDate || undefined,
            to_date: toDate || undefined,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams({
            search: searchTerm || '',
            from_date: fromDate || '',
            to_date: toDate || '',
        });
        window.location.href = route('hr.employee-advances.export') + '?' + params.toString();
    };

    const handleAction = (action: string, item: any) => {
        if (action === 'edit') {
            setCurrentItem(item);
            setFormMode('edit');
            setIsFormModalOpen(true);
        } else if (action === 'delete') {
            setCurrentItem(item);
            setIsDeleteModalOpen(true);
        }
    };

    const handleAddNew = () => {
        setCurrentItem(null);
        setFormMode('create');
        setIsFormModalOpen(true);
    };

    const handleFormSubmit = (formData: any) => {
        if (formMode === 'create') {
            toast.loading(t('Creating employee advance...'));

            router.post(route('hr.employee-advances.store'), formData, {
                onSuccess: (page) => {
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
                        toast.error(`Failed to create employee advance: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        } else if (formMode === 'edit') {
            toast.loading(t('Updating employee advance...'));

            router.put(route('hr.employee-advances.update', currentItem.id), formData, {
                onSuccess: (page) => {
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
                        toast.error(`Failed to update employee advance: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        }
    };

    const handleDeleteConfirm = () => {
        toast.loading(t('Deleting employee advance...'));

        router.delete(route('hr.employee-advances.destroy', currentItem.id), {
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
                    toast.error(`Failed to delete employee advance: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    const handleImportModalOpenChange = (open: boolean) => {
        setIsImportModalOpen(open);
        if (!open) {
            setImportFile(null);
            setImportErrors(null);
        }
    };

    const handleImport = () => {
        if (!importFile) return;

        toast.loading(t('Importing employee advances...'));
        const formData = new FormData();
        formData.append('file', importFile);

        router.post(route('hr.employee-advances.import'), formData, {
            onSuccess: (page) => {
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                    setIsImportModalOpen(false);
                    setImportFile(null);
                } else if (page.props.flash.error) {
                    if (Array.isArray(page.props.flash.error)) {
                        setImportErrors(page.props.flash.error.join('<br>'));
                    } else {
                        toast.error(t(page.props.flash.error));
                    }
                }
            },
            onError: (errors) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(errors);
                } else {
                    toast.error(`Import failed: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    const pageActions = [];

    if (hasPermission(permissions, 'manage-employee-salaries')) {
        pageActions.push({
            label: t('Import Advances'),
            icon: <FileUp className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => handleImportModalOpenChange(true)
        });
        pageActions.push({
            label: t('Add Advance'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: () => handleAddNew()
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Employee Advances') },
    ];

    return (
        <PageTemplate
            title={t("Employee Advance Pay")}
            url="/employee-advances"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
                <div className="flex flex-col md:flex-row gap-4 items-end">
                    <div className="flex-1">
                        <SearchAndFilterBar
                            searchTerm={searchTerm}
                            onSearchChange={setSearchTerm}
                            onSearch={handleSearch}
                            filters={[]}
                            showFilters={showFilters}
                            setShowFilters={setShowFilters}
                            hasActiveFilters={hasActiveFilters}
                            activeFilterCount={() => (searchTerm ? 1 : 0) + (fromDate ? 1 : 0) + (toDate ? 1 : 0)}
                            onResetFilters={() => {
                                setSearchTerm('');
                                setFromDate('');
                                setToDate('');
                                router.get(route('hr.employee-advances.index'), { page: 1 });
                            }}
                            onApplyFilters={applyFilters}
                            currentPerPage={pageFilters.per_page?.toString() || "10"}
                            onPerPageChange={(value) => {
                                router.get(route('hr.employee-advances.index'), {
                                    page: 1,
                                    per_page: parseInt(value),
                                    search: searchTerm || undefined,
                                    from_date: fromDate || undefined,
                                    to_date: toDate || undefined,
                                }, { preserveState: true, preserveScroll: true });
                            }}
                        />
                    </div>
                </div>
                {/* Date Range Inputs */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-2">
                    <div>
                        <label className="block text-sm font-medium mb-1">{t('From Date')}</label>
                        <input
                            type="date"
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            value={fromDate}
                            onChange={(e) => setFromDate(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">{t('To Date')}</label>
                        <input
                            type="date"
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            value={toDate}
                            onChange={(e) => setToDate(e.target.value)}
                        />
                    </div>
                    <div className="flex items-end space-x-2">
                        <Button
                            onClick={applyFilters}
                        >
                            {t('Filter')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setFromDate('');
                                setToDate('');
                                router.get(route('hr.employee-advances.index'));
                            }}
                            disabled={!fromDate && !toDate}
                        >
                            {t('Reset Filter')}
                        </Button>
                        {hasPermission(permissions, 'manage-employee-salaries') && (
                            <Button
                                onClick={handleExport}
                            >
                                <Download className="h-4 w-4 mr-2" />
                                {t('Download Export')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-gray-50 dark:bg-gray-800 border-b">
                            <TableHead className="w-12 py-3"></TableHead>
                            <TableHead className="py-3 font-semibold">{t('Employee')}</TableHead>
                            <TableHead className="py-3 font-semibold text-right">{t('Total Advance')}</TableHead>
                            <TableHead className="py-3 font-semibold text-right">{t('Total Paid')}</TableHead>
                            <TableHead className="py-3 font-semibold text-right">{t('Pending Amount')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {advances?.data?.length > 0 ? (
                            advances.data.map((user: any) => {
                                const totalAmount = user.employee_advances?.reduce((sum: number, adv: any) => sum + parseFloat(adv.amount), 0) || 0;
                                const totalPaid = user.employee_advances?.reduce((sum: number, adv: any) => sum + parseFloat(adv.paid_amount), 0) || 0;
                                const totalPending = totalAmount - totalPaid;

                                return (
                                    <React.Fragment key={user.id}>
                                        <TableRow
                                            className={`border-b cursor-pointer transition-colors ${expandedRows[user.id] ? 'bg-gray-50/50 dark:bg-gray-800/50' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900'}`}
                                            onClick={() => toggleRow(user.id)}
                                        >
                                            <TableCell className="py-3 pl-4">
                                                <div className={`h-6 w-6 rounded-full flex items-center justify-center transition-all duration-200 ${expandedRows[user.id]
                                                    ? 'bg-primary text-primary-foreground shadow-sm transform'
                                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
                                                    }`}>
                                                    <ChevronRight className={`h-4 w-4 transition-transform duration-200 ${expandedRows[user.id] ? 'rotate-90' : ''}`} />
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-9 w-9 border-2 border-white dark:border-gray-800 shadow-sm">
                                                        <AvatarImage src={getImagePath(user.avatar)} />
                                                        <AvatarFallback className="bg-primary/5 text-primary text-xs">{getInitials(user.name)}</AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="font-semibold text-gray-900 dark:text-gray-100">{user.name}</div>
                                                        <div className="text-xs text-muted-foreground flex gap-2">
                                                            <span>{user.employee?.employee_id || '-'}</span>
                                                            {user.employee?.branch && (
                                                                <>
                                                                    <span>•</span>
                                                                    <span>{user.employee.branch.name}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3 text-right font-medium">
                                                {window.appSettings?.formatCurrency(totalAmount)}
                                            </TableCell>
                                            <TableCell className="py-3 text-right font-medium text-green-600">
                                                {window.appSettings?.formatCurrency(totalPaid)}
                                            </TableCell>
                                            <TableCell className="py-3 text-right font-bold text-red-600">
                                                {window.appSettings?.formatCurrency(totalPending)}
                                            </TableCell>
                                        </TableRow>

                                        {expandedRows[user.id] && (
                                            <TableRow className="bg-gray-50/50 dark:bg-gray-800/50">
                                                <TableCell colSpan={5} className="p-4">
                                                    <div className="rounded-md border bg-white dark:bg-gray-900">
                                                        <Table>
                                                            <TableHeader>
                                                                <TableRow>
                                                                    <TableHead>{t('Pay Date')}</TableHead>
                                                                    <TableHead>{t('Amount')}</TableHead>
                                                                    <TableHead>{t('Status')}</TableHead>
                                                                    <TableHead>{t('Remarks')}</TableHead>
                                                                    <TableHead className="text-right">{t('Actions')}</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody>
                                                                {user.employee_advances?.map((adv: any) => (
                                                                    <TableRow key={adv.id}>
                                                                        <TableCell>
                                                                            {(window.appSettings && typeof window.appSettings.formatDate === 'function') ? window.appSettings.formatDate(adv.pay_date) : new Date(adv.pay_date).toLocaleDateString()}
                                                                        </TableCell>
                                                                        <TableCell className="font-medium text-green-600">
                                                                            {window.appSettings?.formatCurrency(adv.amount)}
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${adv.status === 'paid' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' :
                                                                                adv.status === 'recovered' ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20' :
                                                                                    'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20'
                                                                                }`}>
                                                                                {t(adv.status.charAt(0).toUpperCase() + adv.status.slice(1))}
                                                                            </span>
                                                                        </TableCell>
                                                                        <TableCell className="text-muted-foreground text-sm max-w-[200px] truncate">
                                                                            {adv.remarks || '-'}
                                                                        </TableCell>
                                                                        <TableCell className="text-right">
                                                                            <div className="flex justify-end gap-2">
                                                                                {hasPermission(permissions, 'manage-employee-salaries') && (
                                                                                    <>
                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            className="h-8 w-8 text-amber-500"
                                                                                            onClick={(e) => { e.stopPropagation(); handleAction('edit', adv); }}
                                                                                            disabled={adv.status === 'recovered'}
                                                                                            title={adv.status === 'recovered' ? t('Cannot edit recovered advance') : t('Edit')}
                                                                                        >
                                                                                            <Edit className="h-4 w-4" />
                                                                                        </Button>
                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            className="h-8 w-8 text-red-500"
                                                                                            onClick={(e) => { e.stopPropagation(); handleAction('delete', adv); }}
                                                                                            disabled={adv.status === 'recovered'}
                                                                                            title={adv.status === 'recovered' ? t('Cannot delete recovered advance') : t('Delete')}
                                                                                        >
                                                                                            <Trash2 className="h-4 w-4" />
                                                                                        </Button>
                                                                                    </>
                                                                                )}
                                                                            </div>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ))}
                                                            </TableBody>
                                                        </Table>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </React.Fragment>
                                );
                            })
                        ) : (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                    {t('No active advances found.')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <Pagination
                    from={advances?.from || 0}
                    to={advances?.to || 0}
                    total={advances?.total || 0}
                    links={advances?.links}
                    entityName={t("employee advances")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

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
                            required: true,
                            options: employees ? employees.map((emp: any) => ({
                                value: emp.id.toString(),
                                label: emp.name
                            })) : [],
                            disabled: formMode === 'edit',
                            placeholder: t('Select or search employee...')
                        },
                        { name: 'amount', label: t('Amount'), type: 'number', required: true, min: 1, step: 0.01 },
                        {
                            name: 'pay_date',
                            label: t('Pay Date'),
                            type: 'date',
                            required: true,
                            defaultValue: new Date().toISOString().split('T')[0]
                        },
                        { name: 'remarks', label: t('Remarks'), type: 'textarea' }
                    ],
                    modalSize: 'md'
                }}
                initialData={currentItem ? {
                    ...currentItem,
                    employee_id: currentItem.employee_id?.toString()
                } : {}}
                title={
                    formMode === 'create'
                        ? t('Give Advance Pay')
                        : formMode === 'edit'
                            ? t('Edit Advance Pay')
                            : t('View Advance Pay')
                }
                mode={formMode}
            />

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={`Advance of ${window.appSettings?.formatCurrency(currentItem?.amount || 0)}`}
                entityName="advance"
            />

            {/* Import Modal */}
            <Dialog open={isImportModalOpen} onOpenChange={handleImportModalOpenChange}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{t('Import Employee Advances')}</DialogTitle>
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
                                        window.location.href = route('hr.employee-advances.import.template');
                                    }}
                                    className="text-primary hover:underline font-medium flex items-center gap-1"
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
