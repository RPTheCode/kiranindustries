// pages/hr/shifts/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText, Copy, Building } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CopyToBranchesModal } from '@/components/CopyToBranchesModal';

const calcShiftDurationMinutes = (start: string, end: string): number => {
  if (!start || !end) return 480;
  const [sh, sm] = start.substring(0, 5).split(':').map(Number);
  const [eh, em] = end.substring(0, 5).split(':').map(Number);
  let mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins <= 0) mins += 24 * 60;
  return mins;
};

const buildDutyRules = (halfDayMins: number, fullDayMins: number) => [
  { rule_name: 'Absent', min_minutes: 0, max_minutes: Math.max(0, halfDayMins - 1), duty_value: 0.0, status: 'A', color: 'red', priority: 1 },
  { rule_name: 'Half Day', min_minutes: halfDayMins, max_minutes: Math.max(halfDayMins, fullDayMins - 1), duty_value: 0.5, status: 'HD', color: 'orange', priority: 2 },
  { rule_name: 'Present', min_minutes: fullDayMins, max_minutes: 1440, duty_value: 1.0, status: 'P', color: 'green', priority: 3 },
];

const buildDynamicDutyRules = (slot: { start_time?: string; end_time?: string }) => {
  const durationMins = calcShiftDurationMinutes(slot.start_time || '09:00', slot.end_time || '18:00');
  return buildDutyRules(Math.round(durationMins * 0.5), Math.round(durationMins * 0.75));
};

const getSlotDutyRules = (slot: any) => slot?.duty_rules || slot?.dutyRules || [];

const normalizeShiftSlot = (slot: any) => {
  const startTime = slot.start_time?.substring(0, 5) || '09:00';
  const endTime = slot.end_time?.substring(0, 5) || '18:00';
  const dutyRules = getSlotDutyRules(slot);

  const normalized = {
    slot_name: slot.slot_name || 'GENERAL',
    start_time: startTime,
    end_time: endTime,
    grace_before_in: Number.isFinite(Number(slot.grace_before_in)) ? Number(slot.grace_before_in) : 0,
    grace_after_out: Number.isFinite(Number(slot.grace_after_out)) ? Number(slot.grace_after_out) : 0,
    priority: slot.priority ?? 1,
    duty_rules: dutyRules.length > 0
      ? dutyRules.map(({ id, shift_slot_id, created_at, updated_at, ...rule }: any) => rule)
      : buildDynamicDutyRules({ start_time: startTime, end_time: endTime }),
  };

  return normalized;
};

const prepareShiftFormData = (formData: any) => {
  const slots = (formData.slots || []).map(normalizeShiftSlot);

  for (const slot of slots) {
    const halfDayRule = slot.duty_rules.find((r: any) => Number(r.duty_value) === 0.5);
    const fullDayRule = slot.duty_rules.find((r: any) => Number(r.duty_value) === 1.0);

    if (!halfDayRule || !fullDayRule) {
      throw new Error(`Duty rules are incomplete for slot "${slot.slot_name}".`);
    }

    if (halfDayRule.min_minutes >= fullDayRule.min_minutes) {
      throw new Error(`Half day min hours must be less than full day min hours for slot "${slot.slot_name}".`);
    }
  }

  return { ...formData, slots };
};

const createDefaultSlot = (overrides: Partial<any> = {}) => {
  const slot = {
    slot_name: 'GENERAL',
    start_time: '09:00',
    end_time: '18:00',
    grace_before_in: 0,
    grace_after_out: 0,
    ...overrides,
  };

  return {
    ...slot,
    duty_rules: buildDynamicDutyRules(slot),
  };
};

const formatSlotTime = (time?: string) => {
  if (!time) return '-';
  return window.appSettings?.formatTime?.(time) || time.substring(0, 5);
};

const formatDurationLabel = (start?: string, end?: string) => {
  if (!start || !end) return '-';
  const mins = calcShiftDurationMinutes(start, end);
  const hours = Math.floor(mins / 60);
  const minutes = mins % 60;
  if (minutes === 0) return `${hours}h`;
  return `${hours}h ${minutes}m`;
};

const getDutyThresholdHours = (slot: any, dutyValue: number) => {
  const rule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === dutyValue);
  if (rule) return Math.round((rule.min_minutes / 60) * 10) / 10;
  const factor = dutyValue === 0.5 ? 0.5 : 0.75;
  return Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * factor * 10) / 10;
};

const formatDutyHours = (hours: number) => {
  if (Number.isInteger(hours)) return `${hours}h`;
  return `${hours.toFixed(1)}h`;
};

export default function Shifts() {
  const { t } = useTranslation();
  const { auth, shifts, branches = [], filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [selectedShiftType, setSelectedShiftType] = useState(pageFilters.shift_type || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [selectedBranchIds, setSelectedBranchIds] = useState<number[]>([]);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isCopyModalOpen, setIsCopyModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
  const [selectedShifts, setSelectedShifts] = useState<number[]>([]);
  const [isBulkCopy, setIsBulkCopy] = useState(false);
  const [isCopying, setIsCopying] = useState(false);

  // Check if any filters are active
  const hasActiveFilters = () => {
    return searchTerm !== '' || selectedStatus !== 'all' || selectedShiftType !== 'all';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedShiftType !== 'all' ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const applyFilters = () => {
    router.get(route('hr.shifts.index'), {
      page: 1,
      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      shift_type: selectedShiftType !== 'all' ? selectedShiftType : undefined,

      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(route('hr.shifts.index'), {
      sort_field: field,
      sort_direction: direction,

      search: searchTerm || undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      shift_type: selectedShiftType !== 'all' ? selectedShiftType : undefined,

      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: any) => {
    const normalizedItem = item
      ? {
          ...item,
          slots: (item.slots?.length ? item.slots : [createDefaultSlot()]).map(normalizeShiftSlot),
        }
      : item;

    setCurrentItem(normalizedItem);

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
      case 'toggle-status':
        handleToggleStatus(item);
        break;
      case 'copy':
        setIsBulkCopy(false);
        setSelectedBranchIds([]);
        setIsCopyModalOpen(true);
        break;
    }
  };

  const handleAddNew = () => {
    setCurrentItem(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    let payload: any;

    try {
      payload = prepareShiftFormData(formData);
    } catch (error: any) {
      toast.error(error?.message || t('Please check shift duty rules before saving.'));
      return;
    }

    if (formMode === 'create') {
      toast.loading(t('Creating shift...'));

      router.post(route('hr.shifts.store'), payload, {
        onSuccess: (page) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          const pageProps = page.props as any;
          if ((page.props as any).flash.success) {
            toast.success(t((page.props as any).flash.success));
          } else if ((page.props as any).flash.error) {
            toast.error(t((page.props as any).flash.error));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(`Failed to create shift: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating shift...'));

      router.put(route('hr.shifts.update', currentItem.id), payload, {
        onSuccess: (page) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          const pageProps = page.props as any;
          if ((page.props as any).flash.success) {
            toast.success(t((page.props as any).flash.success));
          } else if ((page.props as any).flash.error) {
            toast.error(t((page.props as any).flash.error));
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(`Failed to update shift: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting shift...'));

    router.delete(route('hr.shifts.destroy', currentItem.id), {
      onSuccess: (page) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        const pageProps = page.props as any;
        if ((page.props as any).flash.success) {
          toast.success(t((page.props as any).flash.success));
        } else if ((page.props as any).flash.error) {
          toast.error(t((page.props as any).flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(`Failed to delete shift: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  const handleToggleStatus = (shift: any) => {
    const newStatus = shift.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} shift...`);

    router.put(route('hr.shifts.toggle-status', shift.id), {}, {
      onSuccess: (page) => {
        toast.dismiss();
        const pageProps = page.props as any;
        if ((page.props as any).flash.success) {
          toast.success(t((page.props as any).flash.success));
        } else if ((page.props as any).flash.error) {
          toast.error(t((page.props as any).flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(`Failed to update shift status: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  const handleCopySubmit = (branchIds: number[]) => {
    const isBulk = isBulkCopy;
    const url = isBulk ? route('hr.shifts.bulk_copy') : route('hr.shifts.copy', currentItem.id);
    const data = isBulk
      ? { shift_ids: selectedShifts, branch_ids: branchIds }
      : { branch_ids: branchIds };

    setIsCopying(true);
    toast.loading(isBulk ? t('Copying shifts to branches...') : t('Copying shift to branches...'));

    router.post(url, data, {
      onSuccess: (page) => {
        setIsCopying(false);
        setIsCopyModalOpen(false);
        setSelectedShifts([]);
        toast.dismiss();
        if ((page.props as any).flash.success) {
          toast.success(t((page.props as any).flash.success));
        } else if ((page.props as any).flash.error) {
          toast.error(t((page.props as any).flash.error));
        }
      },
      onError: (errors) => {
        setIsCopying(false);
        toast.dismiss();
        toast.error(typeof errors === 'string' ? errors : `Failed to copy: ${Object.values(errors).join(', ')}`);
      },
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedStatus('all');
    setSelectedShiftType('all');
    setShowFilters(false);

    router.get(route('hr.shifts.index'), {
      page: 1,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  // Define page actions
  const pageActions: any[] = [];

  // Add the "Add New Shift" button if user has permission
  if (hasPermission(permissions, 'create-shifts')) {
    pageActions.push({
      label: t('Add Shift'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: () => handleAddNew()
    });
  }

  if (selectedShifts.length > 0 && hasPermission(permissions, 'create-shifts')) {
    pageActions.push({
      label: `${t('Copy Selected')} (${selectedShifts.length})`,
      icon: <Copy className="h-4 w-4 mr-2" />,
      variant: 'secondary',
      className: 'bg-purple-600 hover:bg-purple-700 text-white border-none',
      onClick: () => {
        setIsBulkCopy(true);
        setSelectedBranchIds([]);
        setIsCopyModalOpen(true);
      }
    });
  }

  pageActions.push({
    label: t('Download Report'),
    icon: <FileText className="h-4 w-4 mr-2" />,
    variant: 'outline' as const,
    className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
    onClick: () => {
      window.open(route('hr.reports.master_listing', { type: 'SHT' }), '_blank');
    },
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Shift Management'), href: route('hr.shifts.index') },
    { title: t('Shifts') }
  ];

  const renderPerSlot = (record: any, renderSlot: (slot: any) => React.ReactNode, options?: { compact?: boolean; hideSlotLabel?: boolean }) => {
    const { compact = false, hideSlotLabel = false } = options || {};
    const slots = record.slots || [];
    if (!slots.length) {
      return <span className="text-[10px] font-bold uppercase text-amber-600">{t('Setup required')}</span>;
    }

    if (slots.length === 1 && !record.is_multi) {
      return <div className="whitespace-nowrap">{renderSlot(slots[0])}</div>;
    }

    return (
      <div className="space-y-1">
        {slots.map((slot: any, index: number) => (
          <div key={slot.id ?? index} className={cn('flex items-center leading-tight whitespace-nowrap', hideSlotLabel ? 'justify-center' : 'gap-1.5', compact ? 'text-[10px]' : 'text-xs')}>
            {!hideSlotLabel && (
              <span className="font-black uppercase text-slate-400 shrink-0 min-w-[1rem]">
                {slot.slot_name || `#${index + 1}`}
              </span>
            )}
            {renderSlot(slot)}
          </div>
        ))}
      </div>
    );
  };

  const renderGraceMinutes = (value: number) => (
    <span
      className="inline-flex h-6 min-w-[2.25rem] items-center justify-center rounded-full bg-blue-50 px-2 text-xs font-black text-blue-700 ring-1 ring-blue-200"
      title={`${value ?? 0} ${t('minutes')}`}
    >
      {value ?? 0}
    </span>
  );

  const renderGraceColumn = (record: any, field: 'grace_before_in' | 'grace_after_out') => {
    const slots = record.slots || [];
    if (!slots.length) {
      return <span className="text-[10px] font-bold uppercase text-amber-600">{t('Setup required')}</span>;
    }

    const content = slots.length === 1 && !record.is_multi ? (
      renderGraceMinutes(Number(slots[0][field] ?? 0))
    ) : (
      <div className="inline-grid grid-cols-[auto_auto] items-center gap-x-2 gap-y-1">
        {slots.map((slot: any, index: number) => (
          <div key={slot.id ?? index} className="contents">
            <span className="text-[10px] font-black uppercase text-slate-400 text-right">
              {slot.slot_name || `#${index + 1}`}
            </span>
            {renderGraceMinutes(Number(slot[field] ?? 0))}
          </div>
        ))}
      </div>
    );

    return <div className="flex justify-center">{content}</div>;
  };

  // Define table columns
  const columns = [
    {
      key: 'select',
      label: (
        <input 
          type="checkbox" 
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={shifts?.data?.length > 0 && selectedShifts.length === shifts.data.length}
          onChange={(e) => {
            if (e.target.checked) {
              setSelectedShifts(shifts.data.map((item: any) => item.id));
            } else {
              setSelectedShifts([]);
            }
          }}
        />
      ),
      render: (value: any, record: any) => (
        <input 
          type="checkbox" 
          className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer"
          checked={selectedShifts.includes(record.id)}
          onChange={(e) => {
            if (e.target.checked) {
              setSelectedShifts(prev => [...prev, record.id]);
            } else {
              setSelectedShifts(prev => prev.filter(id => id !== record.id));
            }
          }}
        />
      )
    },
    {
      key: 'short_code',
      label: t('Code'),
      sortable: true
    },
    {
      key: 'name',
      label: t('Full Name'),
      sortable: true
    },
    {
      key: 'is_multi',
      label: t('Type'),
      render: (value: boolean) => (
        <Badge variant="outline" className={cn(
          "text-[10px] font-black uppercase px-2 py-0.5 rounded-md",
          value ? "bg-purple-50 text-purple-700 border-purple-200" : "bg-blue-50 text-blue-700 border-blue-200"
        )}>
          {value ? t('Multi') : t('Fixed')}
        </Badge>
      )
    },
    {
      key: 'schedule',
      label: t('Schedule'),
      className: 'whitespace-nowrap min-w-[9rem]',
      render: (_: any, record: any) => renderPerSlot(record, (slot) => (
        <span className="font-mono text-xs text-slate-700">
          {formatSlotTime(slot.start_time)} → {formatSlotTime(slot.end_time)}
        </span>
      )),
    },
    {
      key: 'duration',
      label: t('Duration'),
      className: 'whitespace-nowrap min-w-[4rem]',
      render: (_: any, record: any) => renderPerSlot(record, (slot) => (
        <Badge variant="outline" className="text-[10px] font-bold px-1.5 py-0 bg-slate-50 text-slate-700 border-slate-200">
          {formatDurationLabel(slot.start_time, slot.end_time)}
        </Badge>
      )),
    },
    // {
    //   key: 'grace_before_in',
    //   label: t('Grace In'),
    //   className: 'whitespace-nowrap min-w-[6rem] text-center align-middle',
    //   render: (_: any, record: any) => renderGraceColumn(record, 'grace_before_in'),
    // },
    // {
    //   key: 'grace_after_out',
    //   label: t('Grace Out'),
    //   className: 'whitespace-nowrap min-w-[6rem] text-center align-middle',
    //   render: (_: any, record: any) => renderGraceColumn(record, 'grace_after_out'),
    // },
    {
      key: 'half_day_hours',
      label: t('Half Day'),
      className: 'whitespace-nowrap min-w-[4.5rem] text-center',
      render: (_: any, record: any) => renderPerSlot(record, (slot) => (
        <Badge variant="outline" className="text-[10px] font-black px-1.5 py-0 bg-orange-50 text-orange-700 border-orange-200">
          {formatDutyHours(getDutyThresholdHours(slot, 0.5))}
        </Badge>
      )),
    },
    {
      key: 'full_day_hours',
      label: t('Full Day'),
      className: 'whitespace-nowrap min-w-[4.5rem] text-center',
      render: (_: any, record: any) => renderPerSlot(record, (slot) => (
        <Badge variant="outline" className="text-[10px] font-black px-1.5 py-0 bg-emerald-50 text-emerald-700 border-emerald-200">
          {formatDutyHours(getDutyThresholdHours(slot, 1.0))}
        </Badge>
      )),
    },
    {
      key: 'branch',
      label: t('Branch'),
      render: (value: any, record: any) => (
        <Badge variant="outline" className="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-50 text-slate-600 border-slate-200">
          {record.branch?.name || t('All')}
        </Badge>
      )
    },
    {
      key: 'status',
      label: t('Status'),
      render: (value: string, record: any) => {
        const isActive = record.status === 'active';
        const canToggle = hasPermission(permissions, 'toggle-status-shifts') || hasPermission(permissions, 'edit-shifts');
        
        return (
          <button
            onClick={() => canToggle && handleAction('toggle-status', record)}
            title={canToggle ? (isActive ? t('Click to Deactivate') : t('Click to Activate')) : t('No permission to change status')}
            disabled={!canToggle}
            className={`flex items-center gap-1.5 select-none ${canToggle ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'}`}
          >
            {/* Toggle Track */}
            <span
              className={cn(
                "relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors duration-200 ease-in-out",
                isActive ? "bg-emerald-500" : "bg-slate-300"
              )}
            >
              {/* Toggle Knob */}
              <span
                className={cn(
                  "inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform duration-200 ease-in-out",
                  isActive ? "translate-x-3.5" : "translate-x-0.5"
                )}
              />
            </span>
            {/* Label */}
            <span
              className={cn(
                "text-[10px] font-semibold uppercase tracking-wide",
                isActive ? "text-emerald-600" : "text-slate-400"
              )}
            >
              {isActive ? t('Active') : t('Inactive')}
            </span>
          </button>
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
      requiredPermission: 'view-shifts'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-shifts'
    },
    {
      label: t('Copy to Branches'),
      icon: 'Copy',
      action: 'copy',
      className: 'text-purple-500',
      requiredPermission: 'create-shifts'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-shifts'
    }
  ];

  // Prepare options for filters
  const statusOptions = [
    { value: 'all', label: t('All Statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') }
  ];

  const shiftTypeOptions = [
    { value: 'all', label: t('All Types') },
    { value: 'day', label: t('Day Shift') },
    { value: 'night', label: t('Night Shift') }
  ];

  const workingDayOptions = [
    { value: 'monday', label: t('Monday') },
    { value: 'tuesday', label: t('Tuesday') },
    { value: 'wednesday', label: t('Wednesday') },
    { value: 'thursday', label: t('Thursday') },
    { value: 'friday', label: t('Friday') },
    { value: 'saturday', label: t('Saturday') },
    { value: 'sunday', label: t('Sunday') }
  ];

  return (
    <PageTemplate
      title={t("Shift Management")}
      url="/shifts"
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
          filters={[]}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          currentPerPage={pageFilters.per_page?.toString() || "10"}
          onPerPageChange={(value) => {
            router.get(route('hr.shifts.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              shift_type: selectedShiftType !== 'all' ? selectedShiftType : undefined
            }, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      {/* Content section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <div className="w-full overflow-x-auto">
          <div className="min-w-[900px]">
            <CrudTable
              columns={columns}
              actions={actions}
              data={shifts?.data || []}
              from={shifts?.from || 1}
              onAction={handleAction}
              sortField={pageFilters.sort_field}
              sortDirection={pageFilters.sort_direction}
              onSort={handleSort}
              permissions={permissions}
              entityPermissions={{
                view: 'view-shifts',
                edit: 'edit-shifts',
                delete: 'delete-shifts'
              }}
            />
          </div>
        </div>

        {/* Pagination section */}
        <Pagination
          from={shifts?.from || 0}
          to={shifts?.to || 0}
          total={shifts?.total || 0}
          links={shifts?.links}
          entityName={t("shifts")}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          fields: [
            { name: 'short_code', label: t('Code'), type: 'text', required: true, row: 1 },
            { name: 'name', label: t('Full Name'), type: 'text', required: true, row: 1 },
            { 
              name: 'is_multi', 
              label: t('Multi Shift Mode'), 
              type: 'switch',
              row: 2,
              onChange: (value, formData, setFormData) => {
                if (value) {
                  setFormData({
                    ...formData,
                    is_multi: true,
                    slots: [
                      createDefaultSlot({ slot_name: 'DAY', start_time: '08:00', end_time: '20:00' }),
                      createDefaultSlot({ slot_name: 'NIGHT', start_time: '20:00', end_time: '08:00' }),
                    ],
                  });
                } else {
                  setFormData({
                    ...formData,
                    is_multi: false,
                    slots: [createDefaultSlot()],
                  });
                }
              }
            },
            {
              name: 'slots',
              label: t('Shift Configuration'),
              row: 3,
              render: (field, formData, handleChange) => {
                const slots = formData.slots || [];
                if (slots.length === 0) {
                   setTimeout(() => {
                      handleChange('slots', [createDefaultSlot()]);
                   }, 0);
                   return null;
                }
                
                const updateSlot = (index: number, key: string, value: any) => {
                  const newSlots = [...slots];
                  newSlots[index] = { ...newSlots[index], [key]: value };
                  handleChange('slots', newSlots);
                };

                const updateRule = (slotIndex: number, ruleIndex: number, key: string, value: any) => {
                  const newSlots = [...slots];
                  const newRules = [...(newSlots[slotIndex].duty_rules || [])];
                  newRules[ruleIndex] = { ...newRules[ruleIndex], [key]: value };
                  newSlots[slotIndex].duty_rules = newRules;
                  handleChange('slots', newSlots);
                };

                const addRule = (slotIndex: number) => {
                  const newSlots = [...slots];
                  const newRules = [...(newSlots[slotIndex].duty_rules || [])];
                  newRules.push({ rule_name: 'New Rule', min_minutes: 0, max_minutes: 0, status: 'P', duty_value: 1.0, color: 'blue' });
                  newSlots[slotIndex].duty_rules = newRules;
                  handleChange('slots', newSlots);
                };

                const removeRule = (slotIndex: number, ruleIndex: number) => {
                  const newSlots = [...slots];
                  newSlots[slotIndex].duty_rules = (newSlots[slotIndex].duty_rules || []).filter((_: any, i: number) => i !== ruleIndex);
                  handleChange('slots', newSlots);
                };

                return (
                  <div className="space-y-6">
                    {formData.is_multi && (
                      <div className="flex justify-end mb-2">
                        <Button 
                          type="button" 
                          variant="outline" 
                          size="sm" 
                          className="h-7 text-[10px] font-bold uppercase"
                          style={{ color: 'var(--theme-color)', borderColor: 'var(--theme-color)' }}
                          onClick={() => handleChange('slots', [...slots, createDefaultSlot({ slot_name: 'NEW SLOT' })])}
                        >
                          + Add Shift Slot
                        </Button>
                      </div>
                    )}
                    
                    {slots.map((slot: any, idx: number) => (
                      <div key={idx} className="bg-slate-50 border border-slate-200 rounded-xl p-4 shadow-sm relative">
                        {formData.is_multi && slots.length > 1 && (
                          <Button 
                            type="button" 
                            variant="ghost" 
                            size="icon" 
                            className="absolute top-2 right-2 h-7 w-7 rounded-full bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-600 shadow-sm"
                            onClick={() => handleChange('slots', slots.filter((_: any, i: number) => i !== idx))}
                          >
                            ×
                          </Button>
                        )}
                        
                        <div className="grid grid-cols-12 gap-3 mb-4">
                          {formData.is_multi && (
                            <div className="col-span-12">
                              <label className="text-[10px] font-bold text-slate-500 uppercase">Slot Name</label>
                              <Input 
                                className="h-8 text-xs font-black uppercase border-slate-300 focus-visible:ring-primary"
                                value={slot.slot_name} 
                                onChange={(e) => updateSlot(idx, 'slot_name', e.target.value)} 
                                placeholder="e.g. DAY"
                              />
                            </div>
                          )}
                          <div className="col-span-6 md:col-span-3 space-y-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">Start Time</label>
                            <Input type="time" className="h-8 text-xs" value={slot.start_time || ''} onChange={(e) => updateSlot(idx, 'start_time', e.target.value)} />
                          </div>
                          <div className="col-span-6 md:col-span-3 space-y-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">End Time</label>
                            <Input type="time" className="h-8 text-xs" value={slot.end_time || ''} onChange={(e) => updateSlot(idx, 'end_time', e.target.value)} />
                          </div>
                          <div className="col-span-6 md:col-span-3 space-y-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">Grace In (Min)</label>
                            <Input type="number" min="0" className="h-8 text-xs" value={slot.grace_before_in ?? 0} onChange={(e) => updateSlot(idx, 'grace_before_in', Math.max(0, parseInt(e.target.value, 10) || 0))} />
                          </div>
                          <div className="col-span-6 md:col-span-3 space-y-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">Grace Out (Min)</label>
                            <Input type="number" min="0" className="h-8 text-xs" value={slot.grace_after_out ?? 0} onChange={(e) => updateSlot(idx, 'grace_after_out', Math.max(0, parseInt(e.target.value, 10) || 0))} />
                          </div>
                        </div>

                        {/* Simplified Duty Rules Engine */}
                        <div className="bg-white rounded-lg border border-slate-200 p-4 mt-2">
                          <h4 className="text-[11px] font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2 mb-4">
                            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: 'var(--theme-color)' }}></span>
                            Duty Calculation Rules
                          </h4>
                          
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-3">
                              <label className="text-[10px] font-bold text-slate-500 uppercase flex items-center justify-between">
                                <span>Min. Hours (Half Day)</span>
                                <span className="px-1.5 py-0.5 rounded bg-orange-100 text-orange-700 text-[9px] shrink-0">0.5 Duty</span>
                              </label>
                              <Input 
                                type="number" 
                                step="0.5"
                                min="0"
                                max={(() => {
                                  const fdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 1.0);
                                  if (fdRule) return fdRule.min_minutes / 60;
                                  return Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.75 * 10) / 10;
                                })()}
                                className="h-9 text-sm bg-slate-50" 
                                value={(() => {
                                  const hdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 0.5);
                                  if (hdRule) return hdRule.min_minutes / 60;
                                  return Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.5 * 10) / 10;
                                })()} 
                                onChange={(e) => {
                                  let halfDayHours = parseFloat(e.target.value) || 0;
                                  
                                  const fullDayRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 1.0);
                                  const fullDayMins = fullDayRule ? fullDayRule.min_minutes : Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.75);
                                  const fullDayHours = fullDayMins / 60;
                                  
                                  if (halfDayHours > fullDayHours) {
                                      halfDayHours = fullDayHours;
                                  }
                                  
                                  const halfDayMins = Math.round(halfDayHours * 60);
                                  const newRules = buildDutyRules(halfDayMins, fullDayMins);
                                  
                                  const newSlots = [...slots];
                                  newSlots[idx].duty_rules = newRules;
                                  handleChange('slots', newSlots);
                                }} 
                              />
                            </div>
                            <div className="space-y-3">
                              <label className="text-[10px] font-bold text-slate-500 uppercase flex items-center justify-between">
                                <span>Min. Hours (Full Day)</span>
                                <span className="px-1.5 py-0.5 rounded bg-green-100 text-green-700 text-[9px] shrink-0">1.0 Duty</span>
                              </label>
                              <Input 
                                type="number" 
                                step="0.5" 
                                min={(() => {
                                  const hdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 0.5);
                                  if (hdRule) return hdRule.min_minutes / 60;
                                  return Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.5 * 10) / 10;
                                })()}
                                className="h-9 text-sm bg-slate-50" 
                                value={(() => {
                                  const fdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 1.0);
                                  if (fdRule) return fdRule.min_minutes / 60;
                                  return Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.75 * 10) / 10;
                                })()} 
                                onChange={(e) => {
                                  let fullDayHours = parseFloat(e.target.value) || 0;
                                  
                                  const halfDayRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 0.5);
                                  const halfDayMins = halfDayRule ? halfDayRule.min_minutes : Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.5);
                                  const halfDayHours = halfDayMins / 60;
                                  
                                  if (fullDayHours < halfDayHours) {
                                      fullDayHours = halfDayHours;
                                  }
                                  
                                  const fullDayMins = Math.round(fullDayHours * 60);
                                  const newRules = buildDutyRules(halfDayMins, fullDayMins);
                                  
                                  const newSlots = [...slots];
                                  newSlots[idx].duty_rules = newRules;
                                  handleChange('slots', newSlots);
                                }} 
                              />
                            </div>
                          </div>
                          
                          <div className="mt-4 p-3 bg-slate-50 rounded-lg" style={{ border: '1px solid var(--theme-color)' }}>
                            <p className="text-[10px] font-medium leading-relaxed" style={{ color: 'var(--theme-color)' }}>
                              <strong>How this works:</strong> Less than {(() => {
                                  const hdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 0.5);
                                  return hdRule ? hdRule.min_minutes / 60 : Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.5 * 10) / 10;
                                })()} hours counts as <strong>Absent</strong>. Between {(() => {
                                  const hdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 0.5);
                                  return hdRule ? hdRule.min_minutes / 60 : Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.5 * 10) / 10;
                                })()} and {(() => {
                                  const fdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 1.0);
                                  return fdRule ? fdRule.min_minutes / 60 : Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.75 * 10) / 10;
                                })()} hours counts as <strong>Half Day</strong>. Over {(() => {
                                  const fdRule = getSlotDutyRules(slot).find((r: any) => Number(r.duty_value) === 1.0);
                                  return fdRule ? fdRule.min_minutes / 60 : Math.round(calcShiftDurationMinutes(slot.start_time, slot.end_time) * 0.75 * 10) / 10;
                                })()} hours counts as <strong>Full Day</strong>.
                            </p>
                          </div>
                        </div>

                      </div>
                    ))}
                  </div>
                );
              }
            }
          ],
          modalSize: 'lg',
          layout: 'default'
        }}
        initialData={currentItem}
        title={
          formMode === 'create'
            ? t('Add New Shift')
            : formMode === 'edit'
              ? t('Edit Shift')
              : t('View Shift')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="shift"
      />

      {/* Copy to Branches Modal — shared themed component */}
      <CopyToBranchesModal
        open={isCopyModalOpen}
        onClose={() => setIsCopyModalOpen(false)}
        onConfirm={handleCopySubmit}
        branches={branches}
        excludeBranchId={isBulkCopy ? null : currentItem?.branch_id}
        title={
          isBulkCopy
            ? t('Copy {{count}} Selected Shifts to Branches', { count: selectedShifts.length })
            : t('Copy "{{name}}" to Branches', { name: currentItem?.name })
        }
        description={
          isBulkCopy
            ? t('Duplicate {{count}} selected shifts to other branches. Existing codes will be skipped.', { count: selectedShifts.length })
            : undefined
        }
        isLoading={isCopying}
      />
    </PageTemplate>
  );
}