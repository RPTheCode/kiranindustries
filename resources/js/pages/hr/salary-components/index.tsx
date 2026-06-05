import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import {
  Plus,
  Edit,
  Trash2,
  Search,
  X,
  Loader2,
  TrendingUp,
  TrendingDown,
  Percent,
  Info,
} from 'lucide-react';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { hasPermission } from '@/utils/authorization';

const ROUNDING_OPTIONS = [
  {
    value: 'none' as const,
    label: 'Exact amount — keep paise',
    example: '₹21,000.75 stays ₹21,000.75',
  },
  {
    value: 'round' as const,
    label: 'Nearest rupee (recommended)',
    example: '₹21,000.50 → ₹21,001 · ₹21,000.49 → ₹21,000',
  },
  {
    value: 'ceil' as const,
    label: 'Always round up',
    example: '₹21,000.01 → ₹21,001 (extra paise added)',
  },
  {
    value: 'floor' as const,
    label: 'Always round down',
    example: '₹21,000.99 → ₹21,000 (paise removed)',
  },
];

export default function SalaryComponents() {
  const { t } = useTranslation();
  const {
    auth,
    salaryComponents,
    branches = [],
    activeBranchId,
    activeBranchName,
    filters: pageFilters = {},
  } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const canCreate = hasPermission(permissions, 'create-salary-components');
  const canEdit = hasPermission(permissions, 'edit-salary-components');
  const canDelete = hasPermission(permissions, 'delete-salary-components');

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedType, setSelectedType] = useState(pageFilters.type || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
  const [items, setItems] = useState<any[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    type: 'earning' as 'earning' | 'deduction',
    calculation_type: 'percentage_of_gross' as 'percentage' | 'percentage_of_gross',
    percentage_of_basic: '0',
    percentage_of_gross_pay: '0',
    rounding_method: 'round' as 'none' | 'round' | 'ceil' | 'floor',
    status: 'active' as 'active' | 'inactive',
  });
  const skipSearchDebounce = useRef(true);

  useEffect(() => {
    setItems(salaryComponents?.data || []);
    setIsSearching(false);
  }, [salaryComponents]);

  const currentBranchName =
    activeBranchName ||
    branches.find((b: any) => String(b.id) === String(activeBranchId))?.name ||
    t('Selected Branch');

  const fetchList = useCallback((params: Record<string, string | undefined>) => {
    router.get(route('hr.salary-components.index'), {
      page: 1,
      per_page: 100,
      ...params,
    }, {
      preserveState: true,
      preserveScroll: true,
      onStart: () => setIsSearching(true),
      onFinish: () => setIsSearching(false),
    });
  }, []);

  useEffect(() => {
    if (skipSearchDebounce.current) {
      skipSearchDebounce.current = false;
      return;
    }
    const timer = setTimeout(() => {
      fetchList({
        search: searchTerm || undefined,
        type: selectedType !== 'all' ? selectedType : undefined,
        status: selectedStatus !== 'all' ? selectedStatus : undefined,
      });
    }, 300);
    return () => clearTimeout(timer);
  }, [searchTerm, fetchList]);

  const handleTypeChange = (value: string) => {
    setSelectedType(value);
    fetchList({
      search: searchTerm || undefined,
      type: value !== 'all' ? value : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
    });
  };

  const handleStatusChange = (value: string) => {
    setSelectedStatus(value);
    fetchList({
      search: searchTerm || undefined,
      type: selectedType !== 'all' ? selectedType : undefined,
      status: value !== 'all' ? value : undefined,
    });
  };

  const clearSearch = () => {
    setSearchTerm('');
    fetchList({
      type: selectedType !== 'all' ? selectedType : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
    });
  };

  const openCreateForm = () => {
    setCurrentItem(null);
    setFormMode('create');
    setFormData({
      name: '',
      type: 'earning',
      calculation_type: 'percentage_of_gross',
      percentage_of_basic: '0',
      percentage_of_gross_pay: '0',
      rounding_method: 'round',
      status: 'active',
    });
    setIsFormModalOpen(true);
  };

  const openEditForm = (item: any) => {
    setCurrentItem(item);
    setFormMode('edit');
    setFormData({
      name: item.name || '',
      type: item.type || 'earning',
      calculation_type: item.calculation_type === 'percentage' ? 'percentage' : 'percentage_of_gross',
      percentage_of_basic: String(item.percentage_of_basic ?? 0),
      percentage_of_gross_pay: String(item.percentage_of_gross_pay ?? 0),
      rounding_method: item.rounding_method || 'round',
      status: item.status || 'active',
    });
    setIsFormModalOpen(true);
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);
    switch (action) {
      case 'edit':
        openEditForm(item);
        break;
      case 'delete':
        setIsDeleteModalOpen(true);
        break;
      case 'toggle-status':
        router.put(route('hr.salary-components.toggle-status', item.id), {}, {
          preserveScroll: true,
          onSuccess: () => toast.success(t('Status updated')),
        });
        break;
    }
  };

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const routeName = formMode === 'create' ? 'hr.salary-components.store' : 'hr.salary-components.update';
    const method = formMode === 'create' ? 'post' : 'put';
    const routeParams = formMode === 'create' ? [] : [currentItem.id];

    const payload: Record<string, unknown> = {
      name: formData.name,
      type: formData.type,
      calculation_type: formData.calculation_type,
      rounding_method: formData.rounding_method,
      status: formData.status,
    };

    if (formData.calculation_type === 'percentage') {
      payload.percentage_of_basic = formData.percentage_of_basic;
    } else {
      payload.percentage_of_gross_pay = formData.percentage_of_gross_pay;
    }

    setIsSaving(true);
    router[method](route(routeName, routeParams), payload, {
      onSuccess: () => {
        setIsFormModalOpen(false);
        setIsSaving(false);
        toast.success(t(formMode === 'create' ? 'Salary component created' : 'Salary component updated'));
      },
      onError: (errors) => {
        setIsSaving(false);
        toast.error(Object.values(errors).join(', '));
      },
    });
  };

  const handleDeleteConfirm = () => {
    router.delete(route('hr.salary-components.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.success(t('Salary component deleted'));
      },
      onError: () => toast.error(t('Failed to delete salary component')),
    });
  };

  const formatAmountCell = (item: any) => {
    const onGross = item.calculation_type === 'percentage_of_gross';
    const value = onGross ? item.percentage_of_gross_pay : item.percentage_of_basic;
    return (
      <span className={cn(
        'inline-flex items-center gap-1 font-semibold tabular-nums',
        onGross ? 'text-indigo-700' : 'text-violet-700',
      )}>
        <Percent className="h-3 w-3" />
        {Number(value).toLocaleString('en-IN', { maximumFractionDigits: 2 })}%
      </span>
    );
  };

  const formatCalcLabel = (item: any) => (
    item.calculation_type === 'percentage_of_gross'
      ? t('On Gross')
      : t('On Basic')
  );

  const formatPct = (value: number | string) =>
    Number(value).toLocaleString('en-IN', { maximumFractionDigits: 2 });

  const getComponentSummary = (item: any) => {
    const onGross = item.calculation_type === 'percentage_of_gross';
    const rate = onGross ? item.percentage_of_gross_pay : item.percentage_of_basic;
    const base = onGross ? t('gross') : t('basic');
    return `${formatCalcLabel(item)} · ${formatPct(rate)}% ${t('of')} ${base}`;
  };

  const getFormSummaryPreview = () => {
    const onGross = formData.calculation_type === 'percentage_of_gross';
    const rate = onGross ? formData.percentage_of_gross_pay : formData.percentage_of_basic;
    const base = onGross ? t('gross') : t('basic');
    const calc = onGross ? t('On Gross') : t('On Basic');
    return `${calc} · ${formatPct(rate)}% ${t('of')} ${base}`;
  };

  const activeCount = items.filter((i) => i.status === 'active').length;
  const earningCount = items.filter((i) => i.type === 'earning').length;
  const deductionCount = items.filter((i) => i.type === 'deduction').length;

  const structureSummary = useMemo(() => {
    const activeItems = items.filter((i) => i.status === 'active');
    const onGrossEarnings = activeItems.filter(
      (i) => i.type === 'earning' && i.calculation_type === 'percentage_of_gross',
    );
    const onBasicItems = activeItems.filter((i) => i.calculation_type === 'percentage');
    const totalGrossPct = onGrossEarnings.reduce(
      (sum, i) => sum + Number(i.percentage_of_gross_pay || 0),
      0,
    );
    const totalBasicPct = onBasicItems.reduce(
      (sum, i) => sum + Number(i.percentage_of_basic || 0),
      0,
    );

    return { activeItems, onGrossEarnings, onBasicItems, totalGrossPct, totalBasicPct };
  }, [items]);

  const getItemRate = (item: any) =>
    item.calculation_type === 'percentage_of_gross'
      ? Number(item.percentage_of_gross_pay || 0)
      : Number(item.percentage_of_basic || 0);

  return (
    <PageTemplate
      title={t('Salary Component Master')}
      description={
        activeBranchId && activeBranchId !== 'all'
          ? `${currentBranchName} · ${t('Earnings & deductions for salary structure')}`
          : t('Define branch-wise salary components for earnings and deductions.')
      }
      url={route('hr.salary-components.index')}
      noPadding
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Masters') },
        { title: t('Salary Component Master') },
      ]}
      actions={canCreate ? [
        {
          label: t('Add Component'),
          icon: <Plus className="h-4 w-4" />,
          variant: 'default',
          onClick: () => openCreateForm(),
        },
      ] : []}
    >
      <div className="mx-auto max-w-6xl space-y-3 px-1 pb-6">
        {/* Dynamic summary from table data */}
        <div className="overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50/90 to-white shadow-sm">
          <div className="flex flex-wrap items-start gap-3 px-4 py-3">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
              <Info className="h-4 w-4" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-slate-800">{t('Active salary structure')}</p>
              <p className="mt-0.5 text-xs text-slate-500">
                {t('Shows percentages from components added in the table below.')}
              </p>

              {structureSummary.onGrossEarnings.length === 0 && structureSummary.onBasicItems.length === 0 ? (
                <p className="mt-2 text-xs text-slate-400">{t('No active components yet — add from the table.')}</p>
              ) : (
                <div className="mt-2 flex flex-wrap gap-2">
                  {structureSummary.onGrossEarnings.map((item) => (
                    <span
                      key={item.id}
                      className="inline-flex items-center gap-1.5 rounded-md bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200"
                    >
                      {item.name}
                      <span className="tabular-nums text-indigo-700">{formatPct(getItemRate(item))}%</span>
                      <span className="font-normal text-slate-400">{t('on gross')}</span>
                    </span>
                  ))}
                  {structureSummary.onBasicItems.map((item) => (
                    <span
                      key={item.id}
                      className="inline-flex items-center gap-1.5 rounded-md bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200"
                    >
                      {item.name}
                      <span className="tabular-nums text-violet-700">{formatPct(getItemRate(item))}%</span>
                      <span className="font-normal text-slate-400">{t('on basic')}</span>
                    </span>
                  ))}
                  {structureSummary.onGrossEarnings.length > 0 && (
                    <span
                      className={cn(
                        'inline-flex items-center rounded-md px-2 py-1 text-[11px] font-semibold ring-1 ring-inset',
                        Math.abs(structureSummary.totalGrossPct - 100) < 0.01
                          ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                          : 'bg-amber-50 text-amber-800 ring-amber-200',
                      )}
                    >
                      {t('Total on gross')}: {formatPct(structureSummary.totalGrossPct)}%
                    </span>
                  )}
                  {structureSummary.onBasicItems.length > 0 && (
                    <span className="inline-flex items-center rounded-md bg-violet-50 px-2 py-1 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200">
                      {t('Total on basic')}: {formatPct(structureSummary.totalBasicPct)}%
                    </span>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Main table card */}
        <div className="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-gray-900">
          <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2.5 sm:px-4">
            <div className="relative min-w-[180px] flex-1 sm:max-w-xs">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <Input
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder={t('Search component...')}
                className="h-8 border-slate-200 bg-white pl-8 pr-8 text-sm shadow-none"
              />
              {searchTerm && (
                <button
                  type="button"
                  onClick={clearSearch}
                  className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:text-slate-600"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>

            <Select value={selectedType} onValueChange={handleTypeChange}>
              <SelectTrigger className="h-8 w-[130px] border-slate-200 bg-white text-xs shadow-none">
                <SelectValue placeholder={t('Type')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('All Types')}</SelectItem>
                <SelectItem value="earning">{t('Earnings')}</SelectItem>
                <SelectItem value="deduction">{t('Deductions')}</SelectItem>
              </SelectContent>
            </Select>

            <Select value={selectedStatus} onValueChange={handleStatusChange}>
              <SelectTrigger className="h-8 w-[130px] border-slate-200 bg-white text-xs shadow-none">
                <SelectValue placeholder={t('Status')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('All Status')}</SelectItem>
                <SelectItem value="active">{t('Active')}</SelectItem>
                <SelectItem value="inactive">{t('Inactive')}</SelectItem>
              </SelectContent>
            </Select>

            <div className="ml-auto flex items-center gap-2 text-[11px] text-slate-500">
              {isSearching && (
                <span className="flex items-center gap-1 text-indigo-600">
                  <Loader2 className="h-3 w-3 animate-spin" />
                  {t('Loading...')}
                </span>
              )}
              <span className="hidden sm:inline">
                {items.length} {t('items')} · {earningCount} {t('earn')} · {deductionCount} {t('ded')} · {activeCount} {t('active')}
              </span>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-white text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  <th className="w-10 px-2 py-2">#</th>
                  <th className="px-3 py-2">{t('Component')}</th>
                  <th className="px-3 py-2">{t('Type')}</th>
                  <th className="hidden px-3 py-2 sm:table-cell">{t('Calculation')}</th>
                  <th className="px-3 py-2">{t('Rate (%)')}</th>
                  <th className="w-16 px-3 py-2 text-center">{t('Status')}</th>
                  <th className="w-20 px-2 py-2 text-right">{t('Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-14 text-center">
                      <p className="text-sm font-medium text-slate-500">{t('No salary components found')}</p>
                      {canCreate && (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="mt-3 h-8"
                          onClick={() => openCreateForm()}
                        >
                          <Plus className="mr-1.5 h-3.5 w-3.5" />
                          {t('Add first component')}
                        </Button>
                      )}
                    </td>
                  </tr>
                ) : items.map((item, index) => (
                  <tr
                    key={item.id}
                    className="group border-b border-slate-50 transition-colors last:border-0 hover:bg-slate-50/80"
                  >
                    <td className="px-2 py-1.5 text-xs font-medium tabular-nums text-slate-400">
                      {index + 1}
                    </td>
                    <td className="px-3 py-1.5">
                      <div className="font-semibold text-slate-800">{item.name}</div>
                      <div className="mt-0.5 text-[11px] font-medium text-slate-500">
                        {getComponentSummary(item)}
                      </div>
                    </td>
                    <td className="px-3 py-1.5">
                      <Badge
                        variant="outline"
                        className={cn(
                          'h-6 gap-1 px-2 text-[10px] font-semibold',
                          item.type === 'earning'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-rose-200 bg-rose-50 text-rose-700',
                        )}
                      >
                        {item.type === 'earning'
                          ? <TrendingUp className="h-3 w-3" />
                          : <TrendingDown className="h-3 w-3" />}
                        {item.type === 'earning' ? t('Earning') : t('Deduction')}
                      </Badge>
                    </td>
                    <td className="hidden px-3 py-1.5 sm:table-cell">
                      <Badge
                        variant="outline"
                        className={cn(
                          'text-[10px] font-semibold',
                          item.calculation_type === 'percentage_of_gross'
                            ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                            : 'border-violet-200 bg-violet-50 text-violet-700',
                        )}
                      >
                        {formatCalcLabel(item)}
                      </Badge>
                    </td>
                    <td className="px-3 py-1.5">
                      {formatAmountCell(item)}
                      <div className="mt-0.5 text-[10px] text-slate-400 sm:hidden">
                        {formatCalcLabel(item)}
                      </div>
                    </td>
                    <td className="px-3 py-1.5 text-center">
                      {canEdit ? (
                        <button
                          type="button"
                          onClick={() => handleAction('toggle-status', item)}
                          title={item.status === 'active' ? t('Deactivate') : t('Activate')}
                          className="mx-auto flex cursor-pointer items-center justify-center border-none bg-transparent p-0"
                        >
                          <span className={cn(
                            'relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors duration-200',
                            item.status === 'active' ? 'bg-emerald-500' : 'bg-slate-300',
                          )}>
                            <span className={cn(
                              'inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform duration-200',
                              item.status === 'active' ? 'translate-x-3.5' : 'translate-x-0.5',
                            )} />
                          </span>
                        </button>
                      ) : (
                        <Badge variant="outline" className={cn(
                          'text-[10px] font-semibold',
                          item.status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500',
                        )}>
                          {item.status === 'active' ? t('Active') : t('Inactive')}
                        </Badge>
                      )}
                    </td>
                    <td className="px-2 py-1.5">
                      {(canEdit || canDelete) && (
                        <div className="flex items-center justify-end gap-0.5 opacity-70 transition-opacity group-hover:opacity-100">
                          {canEdit && (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="h-7 w-7 text-amber-600 hover:bg-amber-50 hover:text-amber-700"
                              onClick={() => handleAction('edit', item)}
                              title={t('Edit')}
                            >
                              <Edit className="h-3.5 w-3.5" />
                            </Button>
                          )}
                          {canDelete && (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="h-7 w-7 text-red-500 hover:bg-red-50 hover:text-red-600"
                              onClick={() => handleAction('delete', item)}
                              title={t('Delete')}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          )}
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <Dialog open={isFormModalOpen} onOpenChange={setIsFormModalOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {formMode === 'create' ? t('Add Salary Component') : t('Edit Salary Component')}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleFormSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <Label>{t('Component Name')}</Label>
              <Input
                value={formData.name}
                onChange={(e) => setFormData((p) => ({ ...p, name: e.target.value }))}
                placeholder={t('e.g. BASIC, LTA, HRA, PF')}
                required
              />
              <p className="rounded-md bg-indigo-50/80 px-2.5 py-1.5 text-[11px] text-indigo-800 ring-1 ring-indigo-100">
                {t('Preview')}: <span className="font-semibold">{formData.name || '—'}</span>
                {' · '}{getFormSummaryPreview()}
              </p>
            </div>

            {activeBranchId && activeBranchId !== 'all' && (
              <div className="space-y-1.5">
                <Label>{t('Branch')}</Label>
                <Input value={currentBranchName} disabled />
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label>{t('Type')}</Label>
                <Select
                  value={formData.type}
                  onValueChange={(v: 'earning' | 'deduction') => setFormData((p) => ({ ...p, type: v }))}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="earning">{t('Earning')}</SelectItem>
                    <SelectItem value="deduction">{t('Deduction')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>{t('Status')}</Label>
                <Select
                  value={formData.status}
                  onValueChange={(v: 'active' | 'inactive') => setFormData((p) => ({ ...p, status: v }))}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">{t('Active')}</SelectItem>
                    <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label>{t('Calculation Type')}</Label>
              <Select
                value={formData.calculation_type}
                onValueChange={(v: 'percentage' | 'percentage_of_gross') => setFormData((p) => ({ ...p, calculation_type: v }))}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="percentage_of_gross">{t('On Gross')}</SelectItem>
                  <SelectItem value="percentage">{t('On Basic')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {formData.calculation_type === 'percentage_of_gross' ? (
              <div className="space-y-1.5">
                <Label>{t('Percentage on Gross (%)')}</Label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={formData.percentage_of_gross_pay}
                  onChange={(e) => setFormData((p) => ({ ...p, percentage_of_gross_pay: e.target.value }))}
                  required
                />
              </div>
            ) : (
              <div className="space-y-1.5">
                <Label>{t('Percentage on Basic (%)')}</Label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={formData.percentage_of_basic}
                  onChange={(e) => setFormData((p) => ({ ...p, percentage_of_basic: e.target.value }))}
                  required
                />
              </div>
            )}

            <div className="space-y-1.5">
              <Label>{t('Amount rounding')}</Label>
              <p className="text-[11px] text-slate-500">
                {t('After % is calculated, how should the final ₹ amount be rounded?')}
              </p>
              <Select
                value={formData.rounding_method}
                onValueChange={(v: 'none' | 'round' | 'ceil' | 'floor') => setFormData((p) => ({ ...p, rounding_method: v }))}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {ROUNDING_OPTIONS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {t(opt.label)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="rounded-md bg-slate-50 px-2.5 py-2 text-[11px] leading-relaxed text-slate-600 ring-1 ring-slate-100">
                <span className="font-medium text-slate-700">{t('Example')}:</span>{' '}
                {t(ROUNDING_OPTIONS.find((o) => o.value === formData.rounding_method)?.example ?? '')}
              </p>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsFormModalOpen(false)}>
                {t('Cancel')}
              </Button>
              <Button type="submit" disabled={isSaving}>
                {isSaving ? t('Saving...') : t('Save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name}
        entityName="salary component"
      />
    </PageTemplate>
  );
}
