import { useCallback, useEffect, useRef, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { DragDropContext, Droppable, Draggable, DropResult } from '@hello-pangea/dnd';
import {
  Plus,
  CalendarDays,
  CalendarRange,
  GripVertical,
  Edit,
  Trash2,
  Search,
  X,
  Loader2,
  Check,
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
import axios from 'axios';
import { cn } from '@/lib/utils';
import { hasPermission } from '@/utils/authorization';

export default function DeductionTypes() {
  const { t } = useTranslation();
  const { auth, deductionTypes, categories = [], branches = [], activeBranchId, activeBranchName, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const canCreate = hasPermission(permissions, 'create-deduction-types');
  const canEdit = hasPermission(permissions, 'edit-deduction-types');
  const canDelete = hasPermission(permissions, 'delete-deduction-types');

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
  const [items, setItems] = useState<any[]>([]);
  const [isReordering, setIsReordering] = useState(false);
  const [orderSaved, setOrderSaved] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    amount_type: 'fixed' as 'fixed' | 'category_wise',
    default_amount: '0',
    calculation_mode: 'day' as 'day' | 'month',
    status: 'active' as 'active' | 'inactive',
  });
  const [categoryAmounts, setCategoryAmounts] = useState<Record<string, string>>({});
  const skipSearchDebounce = useRef(true);

  useEffect(() => {
    setItems(deductionTypes?.data || []);
    setIsSearching(false);
  }, [deductionTypes]);

  const currentBranchName =
    activeBranchName ||
    branches.find((b: any) => String(b.id) === String(activeBranchId))?.name ||
    t('Selected Branch');

  const canReorder = canEdit && !searchTerm && selectedStatus === 'all';

  const fetchList = useCallback((params: Record<string, string | undefined>) => {
    router.get(route('hr.deduction-types.index'), {
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
        status: selectedStatus !== 'all' ? selectedStatus : undefined,
      });
    }, 300);
    return () => clearTimeout(timer);
  }, [searchTerm, fetchList]);

  const handleStatusChange = (value: string) => {
    setSelectedStatus(value);
    fetchList({
      search: searchTerm || undefined,
      status: value !== 'all' ? value : undefined,
    });
  };

  const clearSearch = () => {
    setSearchTerm('');
    fetchList({
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
    });
  };

  const initCategoryAmounts = (item?: any) => {
    const map: Record<string, string> = {};
    (categories as any[]).forEach((cat) => {
      const match = item?.category_amounts_list?.find((row: any) => row.category_id === cat.id);
      map[String(cat.id)] = match ? String(match.amount) : '0';
    });
    setCategoryAmounts(map);
  };

  const openCreateForm = () => {
    setCurrentItem(null);
    setFormMode('create');
    setFormData({
      name: '',
      amount_type: 'fixed',
      default_amount: '0',
      calculation_mode: 'day',
      status: 'active',
    });
    initCategoryAmounts();
    setIsFormModalOpen(true);
  };

  const openEditForm = (item: any) => {
    setCurrentItem(item);
    setFormMode('edit');
    setFormData({
      name: item.name || '',
      amount_type: item.amount_type || 'fixed',
      default_amount: String(item.default_amount ?? 0),
      calculation_mode: item.calculation_mode || 'day',
      status: item.status || 'active',
    });
    initCategoryAmounts(item);
    setIsFormModalOpen(true);
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);
    switch (action) {
      case 'edit': openEditForm(item); break;
      case 'delete': setIsDeleteModalOpen(true); break;
      case 'toggle-status':
        router.put(route('hr.deduction-types.toggle-status', item.id), {}, {
          preserveScroll: true,
          onSuccess: () => toast.success(t('Status updated')),
        });
        break;
    }
  };

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const routeName = formMode === 'create' ? 'hr.deduction-types.store' : 'hr.deduction-types.update';
    const method = formMode === 'create' ? 'post' : 'put';
    const routeParams = formMode === 'create' ? [] : [currentItem.id];

    const payload: any = {
      name: formData.name,
      amount_type: formData.amount_type,
      default_amount: formData.amount_type === 'fixed' ? formData.default_amount : 0,
      calculation_mode: formData.calculation_mode,
      status: formData.status,
      branch_id: activeBranchId && activeBranchId !== 'all' ? activeBranchId : undefined,
      category_amounts: formData.amount_type === 'category_wise'
        ? (categories as any[]).map((cat) => ({
            category_id: cat.id,
            amount: categoryAmounts[String(cat.id)] ?? 0,
          }))
        : [],
    };

    setIsSaving(true);
    router[method](route(routeName, routeParams), payload, {
      onSuccess: () => {
        setIsFormModalOpen(false);
        setIsSaving(false);
        toast.success(t(formMode === 'create' ? 'Deduction type created' : 'Deduction type updated'));
      },
      onError: (errors) => {
        setIsSaving(false);
        toast.error(Object.values(errors).join(', '));
      },
    });
  };

  const handleDeleteConfirm = () => {
    router.delete(route('hr.deduction-types.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.success(t('Deduction type deleted'));
      },
      onError: () => toast.error(t('Cannot delete — deactivate instead if entries exist')),
    });
  };

  const handleDragEnd = async (result: DropResult) => {
    if (!result.destination || !canReorder) return;
    if (result.destination.index === result.source.index) return;

    const reordered = Array.from(items);
    const [moved] = reordered.splice(result.source.index, 1);
    reordered.splice(result.destination.index, 0, moved);
    setItems(reordered);

    setIsReordering(true);
    setOrderSaved(false);
    try {
      await axios.post(route('hr.deduction-types.reorder'), {
        ids: reordered.map((item) => item.id),
      });
      setOrderSaved(true);
      setTimeout(() => setOrderSaved(false), 2000);
    } catch {
      setItems(deductionTypes?.data || []);
      toast.error(t('Failed to update order'));
    } finally {
      setIsReordering(false);
    }
  };

  const formatMode = (mode: string) => (mode === 'day' ? t('Per Day') : t('Per Month'));

  const renderAmountCell = (item: any) => {
    if (item.amount_type === 'category_wise') {
      const list = item.category_amounts_list || [];
      if (list.length === 0) {
        return <span className="text-[11px] text-slate-400">{t('By Category')}</span>;
      }
      return (
        <div className="flex max-w-[220px] flex-wrap gap-1">
          {list.map((row: any) => (
            <span
              key={row.category_id}
              className="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600"
            >
              {row.category_name}: ₹{Number(row.amount).toLocaleString('en-IN', { minimumFractionDigits: 0 })}
            </span>
          ))}
        </div>
      );
    }
    return (
      <span className="font-medium tabular-nums text-slate-700">
        ₹{Number(item.default_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
      </span>
    );
  };

  const activeCount = items.filter((i) => i.status === 'active').length;

  return (
    <PageTemplate
      title={t('Deduction Master')}
      description={
        activeBranchId && activeBranchId !== 'all'
          ? `${currentBranchName} · ${t('Used in Earnings / Deductions Entry')}`
          : t('Deduction types for Earnings / Deductions Entry and payroll.')
      }
      url={route('hr.deduction-types.index')}
      noPadding
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Masters') },
        { title: t('Deduction Master') },
      ]}
      actions={canCreate ? [
        {
          label: t('Add Deduction Type'),
          icon: <Plus className="h-4 w-4" />,
          variant: 'default',
          onClick: () => openCreateForm(),
        },
      ] : []}
    >
      <div className="mx-auto max-w-6xl space-y-0 px-1 pb-6">
        {/* Compact toolbar + table card */}
        <div className="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-gray-900">
          {/* Toolbar */}
          <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2.5 sm:px-4">
            <div className="relative min-w-[180px] flex-1 sm:max-w-xs">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <Input
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder={t('Search deduction...')}
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
              {isReordering && (
                <span className="flex items-center gap-1 text-indigo-600">
                  <Loader2 className="h-3 w-3 animate-spin" />
                  {t('Saving...')}
                </span>
              )}
              {orderSaved && !isReordering && (
                <span className="flex items-center gap-1 text-emerald-600">
                  <Check className="h-3 w-3" />
                  {t('Order saved')}
                </span>
              )}
              <span className="hidden sm:inline">
                {items.length} {t('items')} · {activeCount} {t('active')}
              </span>
              {canReorder && items.length > 1 && (
                <span className="hidden items-center gap-1 rounded-full bg-white px-2 py-0.5 text-slate-400 ring-1 ring-slate-200 md:flex">
                  <GripVertical className="h-3 w-3" />
                  {t('Drag to reorder')}
                </span>
              )}
            </div>
          </div>

          {!canReorder && items.length > 0 && (
            <div className="border-b border-amber-100 bg-amber-50/80 px-4 py-1.5 text-[11px] text-amber-700">
              {t('Clear filters to enable drag & drop reordering')}
            </div>
          )}

          {/* Table */}
          <DragDropContext onDragEnd={handleDragEnd}>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-100 bg-white text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    {canReorder && <th className="w-9 px-2 py-2" />}
                    <th className="w-10 px-2 py-2">#</th>
                    <th className="px-3 py-2">{t('Name')}</th>
                    <th className="hidden px-3 py-2 sm:table-cell">{t('Amount')}</th>
                    <th className="px-3 py-2">{t('Mode')}</th>
                    <th className="w-16 px-3 py-2 text-center">{t('Status')}</th>
                    <th className="w-20 px-2 py-2 text-right">{t('Actions')}</th>
                  </tr>
                </thead>
                <Droppable droppableId="deduction-types-list" isDropDisabled={!canReorder}>
                  {(provided) => (
                    <tbody ref={provided.innerRef} {...provided.droppableProps}>
                      {items.length === 0 ? (
                        <tr>
                          <td colSpan={canReorder ? 7 : 6} className="px-4 py-14 text-center">
                            <p className="text-sm font-medium text-slate-500">{t('No deduction types found')}</p>
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              className="mt-3 h-8"
                              onClick={() => openCreateForm()}
                            >
                              <Plus className="mr-1.5 h-3.5 w-3.5" />
                              {t('Add first deduction type')}
                            </Button>
                          </td>
                        </tr>
                      ) : items.map((item, index) => (
                        <Draggable
                          key={item.id}
                          draggableId={String(item.id)}
                          index={index}
                          isDragDisabled={!canReorder}
                        >
                          {(dragProvided, snapshot) => (
                            <tr
                              ref={dragProvided.innerRef}
                              {...dragProvided.draggableProps}
                              className={cn(
                                'group border-b border-slate-50 transition-colors last:border-0',
                                snapshot.isDragging
                                  ? 'relative z-10 bg-indigo-50 shadow-lg ring-1 ring-indigo-200'
                                  : 'hover:bg-slate-50/80',
                              )}
                              style={dragProvided.draggableProps.style}
                            >
                              {canReorder && (
                                <td className="w-9 px-1 py-1.5">
                                  <button
                                    type="button"
                                    {...dragProvided.dragHandleProps}
                                    className="flex h-7 w-7 cursor-grab items-center justify-center rounded text-slate-300 opacity-60 transition-all hover:bg-slate-100 hover:text-slate-600 group-hover:opacity-100 active:cursor-grabbing"
                                    title={t('Drag to reorder')}
                                  >
                                    <GripVertical className="h-4 w-4" />
                                  </button>
                                </td>
                              )}
                              <td className="px-2 py-1.5 text-xs font-medium tabular-nums text-slate-400">
                                {index + 1}
                              </td>
                              <td className="px-3 py-1.5">
                                <div className="font-semibold text-slate-800">{item.name}</div>
                                <div className="mt-0.5 sm:hidden">{renderAmountCell(item)}</div>
                              </td>
                              <td className="hidden px-3 py-1.5 sm:table-cell">
                                {renderAmountCell(item)}
                              </td>
                              <td className="px-3 py-1.5">
                                <Badge
                                  variant="outline"
                                  className={cn(
                                    'h-6 gap-1 px-2 text-[10px] font-semibold',
                                    item.calculation_mode === 'day'
                                      ? 'border-sky-200 bg-sky-50 text-sky-700'
                                      : 'border-violet-200 bg-violet-50 text-violet-700',
                                  )}
                                >
                                  {item.calculation_mode === 'day'
                                    ? <CalendarDays className="h-3 w-3" />
                                    : <CalendarRange className="h-3 w-3" />}
                                  {formatMode(item.calculation_mode)}
                                </Badge>
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
                          )}
                        </Draggable>
                      ))}
                      {provided.placeholder}
                    </tbody>
                  )}
                </Droppable>
              </table>
            </div>
          </DragDropContext>
        </div>
      </div>

      <Dialog open={isFormModalOpen} onOpenChange={setIsFormModalOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {formMode === 'create' ? t('Add Deduction Type') : t('Edit Deduction Type')}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleFormSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <Label>{t('Name')}</Label>
              <Input
                value={formData.name}
                onChange={(e) => setFormData((p) => ({ ...p, name: e.target.value }))}
                placeholder={t('e.g. Canteen, Colony')}
                required
              />
            </div>

            {activeBranchId && activeBranchId !== 'all' && (
              <div className="space-y-1.5">
                <Label>{t('Branch')}</Label>
                <Input value={currentBranchName} disabled />
              </div>
            )}

            <div className="space-y-1.5">
              <Label>{t('Amount Type')}</Label>
              <Select
                value={formData.amount_type}
                onValueChange={(v: 'fixed' | 'category_wise') => setFormData((p) => ({ ...p, amount_type: v }))}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="fixed">{t('Fixed — same amount for all')}</SelectItem>
                  <SelectItem value="category_wise">{t('Category wise — different per category')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {formData.amount_type === 'fixed' ? (
              <div className="space-y-1.5">
                <Label>{t('Default Amount (₹)')}</Label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.default_amount}
                  onChange={(e) => setFormData((p) => ({ ...p, default_amount: e.target.value }))}
                  required
                />
              </div>
            ) : (
              <div className="space-y-2 rounded-lg border border-slate-200 bg-slate-50/80 p-3">
                <Label className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  {t('Amount by Category')}
                </Label>
                {(categories as any[]).length === 0 ? (
                  <p className="text-xs text-amber-700">
                    {t('No categories found for this branch. Add categories in Masters first.')}
                  </p>
                ) : (
                  <div className="grid gap-2 sm:grid-cols-2">
                    {(categories as any[]).map((cat) => (
                      <div key={cat.id} className="flex items-center gap-2">
                        <span className="min-w-0 flex-1 truncate text-xs font-medium text-slate-700">{cat.name}</span>
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          className="h-8 w-24 text-xs"
                          value={categoryAmounts[String(cat.id)] ?? '0'}
                          onChange={(e) => setCategoryAmounts((p) => ({ ...p, [String(cat.id)]: e.target.value }))}
                        />
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label>{t('Calculation Mode')}</Label>
                <Select
                  value={formData.calculation_mode}
                  onValueChange={(v: 'day' | 'month') => setFormData((p) => ({ ...p, calculation_mode: v }))}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="day">{t('Per Day')}</SelectItem>
                    <SelectItem value="month">{t('Per Month')}</SelectItem>
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
      />
    </PageTemplate>
  );
}
