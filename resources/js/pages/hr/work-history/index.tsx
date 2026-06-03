import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, ChevronDown, ChevronRight, Edit, Trash2 } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getImagePath } from '@/utils/helpers';
import { useInitials } from '@/hooks/use-initials';

export default function WorkHistory() {
    const { t } = useTranslation();
    const { auth, workHistories = { data: [], links: [], from: 0, to: 0, total: 0 }, employees, skills, branches, active_branch_id, filters: pageFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const getInitials = useInitials();

    // State
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(pageFilters.employee_id || '');
    const [skillFilter, setSkillFilter] = useState(pageFilters.skill || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
    const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});

    // Prepare options for Selects
    const employeeOptions = Array.isArray(employees) ? employees : [];
    const branchOptions = Array.isArray(branches) ? branches : [];
    const skillOptions = [
        { value: 'all', label: t('All Skills') },
        ...(Array.isArray(skills) ? skills : [])
    ];

    const toggleRow = (id: string) => {
        setExpandedRows(prev => ({
            ...prev,
            [id]: !prev[id]
        }));
    };

    // Check if any filters are active
    const hasActiveFilters = () => {
        return searchTerm !== '' || employeeFilter !== '' || (skillFilter !== '' && skillFilter !== 'all');
    };

    // Count active filters
    const activeFilterCount = () => {
        let count = 0;
        if (searchTerm) count++;
        if (employeeFilter) count++;
        if (skillFilter && skillFilter !== 'all') count++;
        return count;
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleSearchClear = () => {
        setSearchTerm('');
        router.get(route('hr.work-history.index'), {
            page: 1,
            search: undefined,
            employee_id: employeeFilter || undefined,
            skill: skillFilter !== 'all' ? skillFilter : undefined,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
    };

    const applyFilters = () => {
        router.get(route('hr.work-history.index'), {
            page: 1,
            search: searchTerm || undefined,
            employee_id: employeeFilter || undefined,
            skill: skillFilter !== 'all' ? skillFilter : undefined,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setEmployeeFilter('');
        setSkillFilter('all');
        setShowFilters(false);
        router.get(route('hr.work-history.index'), {
            page: 1,
            per_page: pageFilters.per_page
        }, { preserveState: true, preserveScroll: true });
    };

    // Filters configuration
    const filters = [
        {
            name: 'employee_id',
            label: t('Employee'),
            type: 'combobox',
            options: employeeOptions,
            value: employeeFilter,
            onChange: (value: string) => setEmployeeFilter(value)
        },
        {
            name: 'skill',
            label: t('Skill'),
            type: 'combobox',
            options: skillOptions,
            value: skillFilter,
            onChange: (value: string) => setSkillFilter(value)
        }
    ];

    // ... (breadcrumbs and actions remain same)



    // Breadcrumbs
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Work History') }
    ];

    // Page actions
    const pageActions = [
        {
            label: t('Add Work History'),
            icon: <Plus className="h-4 w-4" />,
            onClick: () => {
                setFormMode('create');
                setCurrentItem(null);
                setIsFormModalOpen(true);
            },
            variant: 'default' as const,
            permission: 'manage-employees'
        }
    ];

    const handleAction = (action: string, item: any) => {
        if (action === 'edit') {
            setCurrentItem({
                ...item,
                skills: item.skills ? item.skills.map((s: any) => s.id.toString()) : []
            });
            setFormMode('edit');
            setIsFormModalOpen(true);
        } else if (action === 'delete') {
            setCurrentItem(item);
            setIsDeleteModalOpen(true);
        }
    };

    const handleFormSubmit = (formData: any) => {
        if (formMode === 'create') {
            router.post(route('hr.work-history.store'), formData, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    toast.success(t('Work history created successfully'));
                }
            });
        } else {
            router.put(route('hr.work-history.update', currentItem.id), formData, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    toast.success(t('Work history updated successfully'));
                }
            });
        }
    };

    const handleDeleteConfirm = () => {
        if (!currentItem) return;
        router.delete(route('hr.work-history.destroy', currentItem.id), {
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                toast.success(t('Work history deleted successfully'));
            }
        });
    };

    return (
        <PageTemplate
            title={t("Worker History Management")}
            url="/work-history"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
                <SearchAndFilterBar
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    onSearch={handleSearch}
                    onSearchClear={handleSearchClear}
                    filters={filters as any}
                    showFilters={showFilters}
                    setShowFilters={setShowFilters}
                    hasActiveFilters={hasActiveFilters}
                    activeFilterCount={activeFilterCount}
                    onResetFilters={handleResetFilters}
                    onApplyFilters={applyFilters}
                    currentPerPage={pageFilters.per_page?.toString() || "10"}
                    onPerPageChange={(value) => {
                        router.get(route('hr.work-history.index'), {
                            page: 1,
                            per_page: parseInt(value),
                            search: searchTerm || undefined,
                            employee_id: employeeFilter || undefined,
                            skill: skillFilter !== 'all' ? skillFilter : undefined,
                        }, { preserveState: true, preserveScroll: true });
                    }}
                />
            </div>

            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <div className="border-collapse dark:bg-gray-900">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-gray-50 dark:bg-gray-800 border-b">
                                <TableHead className="w-12 py-2.5"></TableHead>
                                <TableHead className="py-2.5 font-semibold">{t('Employee')}</TableHead>
                                <TableHead className="py-2.5 font-semibold">{t('Skills')}</TableHead>
                                <TableHead className="py-2.5 font-semibold">{t('Latest Site')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {workHistories?.data?.length > 0 ? (
                                workHistories.data.map((group: any) => (
                                    <>
                                        <TableRow
                                            key={group.id}
                                            className={`border-b cursor-pointer transition-colors ${expandedRows[group.id] ? 'bg-gray-50/50 dark:bg-gray-800/50' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900'}`}
                                            onClick={() => toggleRow(group.id)}
                                        >
                                            <TableCell className="py-3 pl-4">
                                                <div className={`h-8 w-8 rounded-full flex items-center justify-center transition-all duration-200 ${expandedRows[group.id]
                                                    ? 'bg-primary text-primary-foreground shadow-sm transform'
                                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
                                                    }`}>
                                                    <ChevronRight className={`h-4 w-4 transition-transform duration-200 ${expandedRows[group.id] ? 'rotate-90' : ''}`} />
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-9 w-9 border-2 border-white dark:border-gray-800 shadow-sm">
                                                        <AvatarImage src={getImagePath(group.avatar)} />
                                                        <AvatarFallback className="bg-primary/5 text-primary text-xs">{getInitials(group.name)}</AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="font-semibold text-gray-900 dark:text-gray-100">{group.name}</div>
                                                        <div className="text-xs text-muted-foreground">{group.email}</div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {group.skills?.slice(0, 3).map((skill: any) => (
                                                        <span key={skill.id} className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800">
                                                            {skill.name}
                                                        </span>
                                                    ))}
                                                    {group.skills?.length > 3 && (
                                                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-50 text-gray-600 border border-gray-100 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700">
                                                            +{group.skills.length - 3}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300 font-medium">
                                                {group.latest_site}
                                            </TableCell>
                                        </TableRow>

                                        {expandedRows[group.id] && (
                                            <TableRow className="bg-gray-50/50 dark:bg-gray-800/50">
                                                <TableCell colSpan={4} className="p-4">
                                                    <div className="rounded-md border bg-white dark:bg-gray-900">
                                                        <Table>
                                                            <TableHeader>
                                                                <TableRow>
                                                                    <TableHead>{t('Site Name')}</TableHead>
                                                                    <TableHead>{t('Start Date')}</TableHead>
                                                                    <TableHead>{t('End Date')}</TableHead>
                                                                    <TableHead>{t('Skills')}</TableHead>
                                                                    <TableHead>{t('Created Date')}</TableHead>
                                                                    <TableHead className="text-right">{t('Actions')}</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody>
                                                                {group.work_histories?.map((history: any) => (
                                                                    <TableRow key={history.id}>
                                                                        <TableCell className="font-medium">
                                                                            <div>
                                                                                <div>{history.site_name}</div>
                                                                                <div className="text-xs text-muted-foreground">{history.branch?.name || '-'}</div>
                                                                            </div>
                                                                        </TableCell>
                                                                        <TableCell>{new Date(history.start_date).toLocaleDateString()}</TableCell>
                                                                        <TableCell>{new Date(history.end_date).toLocaleDateString()}</TableCell>
                                                                        <TableCell>
                                                                            <div className="flex flex-wrap gap-1">
                                                                                {history.skills?.map((skill: any) => (
                                                                                    <Badge key={skill.id} variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">
                                                                                        {skill.name}
                                                                                    </Badge>
                                                                                ))}
                                                                            </div>
                                                                        </TableCell>
                                                                        <TableCell>{new Date(history.created_at).toLocaleDateString()}</TableCell>
                                                                        <TableCell className="text-right">
                                                                            <div className="flex justify-end gap-2">
                                                                                {hasPermission(permissions, 'manage-employees') && (
                                                                                    <>
                                                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-amber-500" onClick={(e) => { e.stopPropagation(); handleAction('edit', history); }}>
                                                                                            <Edit className="h-4 w-4" />
                                                                                        </Button>
                                                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-red-500" onClick={(e) => { e.stopPropagation(); handleAction('delete', history); }}>
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
                                    </>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                                        {t('No results found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <Pagination
                    from={workHistories?.from || 0}
                    to={workHistories?.to || 0}
                    total={workHistories?.total || 0}
                    links={workHistories?.links}
                    entityName={t("employees")}
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
                            options: employeeOptions,
                            required: true,
                            placeholder: t('Select Employee')
                        },
                        {
                            name: 'branch_id',
                            label: t('Branch'),
                            type: 'combobox',
                            options: branchOptions,
                            required: true,
                            placeholder: t('Select Branch'),
                            defaultValue: active_branch_id
                        },
                        {
                            name: 'site_name',
                            label: t('Site Name'),
                            type: 'text',
                            required: true,
                        },
                        {
                            name: 'start_date',
                            label: t('Start Date'),
                            type: 'date',
                            required: true,
                            width: '100%',
                            defaultValue: new Date().toLocaleDateString('en-CA')
                        },
                        {
                            name: 'end_date',
                            label: t('End Date'),
                            type: 'date',
                            required: true,
                            width: '100%',
                            defaultValue: new Date().toLocaleDateString('en-CA')
                        },
                        {
                            name: 'skills',
                            label: t('Skills'),
                            type: 'multi-select',
                            options: skillOptions,
                            placeholder: t('Select skills...'),
                        }
                    ],
                    modalSize: 'xl',
                    layout: 'default',
                    columns: 1
                }}
                initialData={currentItem}
                title={
                    formMode === 'create'
                        ? t('Add Work History')
                        : formMode === 'edit'
                            ? t('Edit Work History')
                            : t('View Work History')
                }
                mode={formMode}
            />

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.site_name || ''}
                entityName="work history"
            />
        </PageTemplate>
    );
}
