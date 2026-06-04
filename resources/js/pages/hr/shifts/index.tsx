// pages/hr/shifts/index.tsx
import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText, Copy, Users } from 'lucide-react';
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

const formatDutyHoursLabel = (hours: number) => {
  const value = Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
  return `${value} hrs`;
};

const renderAttendanceDutyCell = (
  record: any,
  labels: { title: string; halfDay: string; fullDay: string; slotLabel: string }
) => {
  const slots = record.slots || [];
  if (!slots.length) return null;

  const renderSlotBlock = (slot: any, showSlotName: boolean) => (
    <div
      className="inline-block rounded-md border border-slate-200 bg-slate-50/90 px-2 py-1 text-left"
      title={labels.title}
    >
      {showSlotName && (
        <p className="text-[9px] font-bold uppercase tracking-wide text-slate-500 mb-0.5 truncate max-w-[8.5rem]">
          {slot.slot_name || labels.slotLabel}
        </p>
      )}
      <div className="flex items-center justify-between gap-2 text-[11px] leading-tight">
        <span className="font-medium text-orange-700 shrink-0">{labels.halfDay}</span>
        <span className="font-bold tabular-nums text-slate-900">
          {formatDutyHoursLabel(getDutyThresholdHours(slot, 0.5))}
        </span>
      </div>
      <div className="mt-0.5 flex items-center justify-between gap-2 border-t border-slate-200/80 pt-0.5 text-[11px] leading-tight">
        <span className="font-medium text-emerald-700 shrink-0">{labels.fullDay}</span>
        <span className="font-bold tabular-nums text-slate-900">
          {formatDutyHoursLabel(getDutyThresholdHours(slot, 1.0))}
        </span>
      </div>
    </div>
  );

  if (!record.is_multi || slots.length === 1) {
    return <div className="flex justify-center">{renderSlotBlock(slots[0], false)}</div>;
  }

  return (
    <div className="flex flex-col items-center gap-1">
      {slots.map((slot: any, index: number) => (
        <div key={slot.id ?? index}>{renderSlotBlock(slot, true)}</div>
      ))}
    </div>
  );
};

const formatCompactSchedule = (record: any, formatTime: (time?: string) => string, formatDuration: (start?: string, end?: string) => string) => {
  const slots = record.slots || [];
  if (!slots.length) {
    return null;
  }

  if (!record.is_multi || slots.length === 1) {
    const slot = slots[0];
    return (
      <span className="text-[11px] font-mono text-slate-700 whitespace-nowrap">
        {formatTime(slot.start_time)}–{formatTime(slot.end_time)}
        <span className="text-slate-400 font-sans ml-1">({formatDuration(slot.start_time, slot.end_time)})</span>
      </span>
    );
  }

  return (
    <div className="space-y-0.5 text-[11px] font-mono text-slate-700">
      {slots.map((slot: any, index: number) => (
        <div key={slot.id ?? index} className="whitespace-nowrap leading-tight">
          <span className="font-bold text-slate-500">{slot.slot_name?.[0] || 'S'} </span>
          {formatTime(slot.start_time)}–{formatTime(slot.end_time)}
          <span className="text-slate-400 font-sans ml-1">({formatDuration(slot.start_time, slot.end_time)})</span>
        </div>
      ))}
    </div>
  );
};

export default function Shifts() {
  const { t } = useTranslation();
  const { auth, shifts, branches = [], stats = {}, activeBranchId, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const sessionActiveBranchId = activeBranchId ?? auth?.active_branch_id ?? null;
  const defaultStatus = 'active';

  const branchFilterFromUrl = pageFilters.branch_id
    ? String(pageFilters.branch_id)
    : sessionActiveBranchId
      ? String(sessionActiveBranchId)
      : 'all';

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedBranch, setSelectedBranch] = useState(branchFilterFromUrl);
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status ?? defaultStatus);
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

  useEffect(() => {
    setSelectedBranch(branchFilterFromUrl);
    if (pageFilters.status) {
      setSelectedStatus(pageFilters.status);
    }
    if (pageFilters.shift_type) {
      setSelectedShiftType(pageFilters.shift_type);
    }
  }, [pageFilters.branch_id, pageFilters.status, pageFilters.shift_type]);

  const listQueryParams = (extra: Record<string, unknown> = {}) => ({
    page: 1,
    search: searchTerm || undefined,
    branch_id: selectedBranch,
    status: selectedStatus,
    shift_type: selectedShiftType !== 'all' ? selectedShiftType : undefined,
    per_page: pageFilters.per_page,
    sort_field: pageFilters.sort_field,
    sort_direction: pageFilters.sort_direction,
    ...extra,
  });

  const filteredBranchName =
    selectedBranch !== 'all' ? branches.find((b: any) => String(b.id) === selectedBranch)?.name : null;

  const activeBranchName = sessionActiveBranchId
    ? branches.find((b: any) => String(b.id) === String(sessionActiveBranchId))?.name
    : null;

  const isViewingOtherBranch =
    selectedBranch !== 'all' &&
    sessionActiveBranchId &&
    String(selectedBranch) !== String(sessionActiveBranchId);

  const showBranchColumn =
    !sessionActiveBranchId || selectedBranch === 'all' || String(selectedBranch) !== String(sessionActiveBranchId);

  const hasActiveFilters = () =>
    searchTerm !== '' ||
    selectedStatus !== defaultStatus ||
    selectedShiftType !== 'all' ||
    selectedBranch !== (sessionActiveBranchId ? String(sessionActiveBranchId) : 'all');

  const activeFilterCount = () =>
    (searchTerm ? 1 : 0) +
    (selectedStatus !== defaultStatus ? 1 : 0) +
    (selectedShiftType !== 'all' ? 1 : 0) +
    (selectedBranch !== (sessionActiveBranchId ? String(sessionActiveBranchId) : 'all') ? 1 : 0);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSearchClear = () => {
    setSearchTerm('');
    router.get(route('hr.shifts.index'), listQueryParams({ search: undefined }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const applyFilters = () => {
    router.get(route('hr.shifts.index'), listQueryParams(), { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    router.get(
      route('hr.shifts.index'),
      listQueryParams({ sort_field: field, sort_direction: direction }),
      { preserveState: true, preserveScroll: true }
    );
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
        if (!item.can_delete) {
          toast.error(
            item.delete_block_reason ||
              t('This shift is assigned to employees and cannot be deleted.')
          );
          return;
        }
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
    const resetBranch = sessionActiveBranchId ? String(sessionActiveBranchId) : 'all';
    setSelectedBranch(resetBranch);
    setSelectedStatus(defaultStatus);
    setSelectedShiftType('all');
    setShowFilters(false);

    router.get(
      route('hr.shifts.index'),
      {
        page: 1,
        per_page: pageFilters.per_page,
        status: defaultStatus,
        branch_id: sessionActiveBranchId ? String(sessionActiveBranchId) : undefined,
      },
      { preserveState: true, preserveScroll: true }
    );
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
      className: 'theme-bg hover:opacity-90 text-white border-none',
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

  const checkboxClass =
    'rounded border-slate-300 h-4 w-4 cursor-pointer accent-[var(--theme-color)] focus:ring-[color-mix(in_srgb,var(--theme-color)_40%,transparent)]';

  const tableColumns = [
    {
      key: 'select',
      label: (
        <input
          type="checkbox"
          className={checkboxClass}
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
      render: (_: any, record: any) => (
        <input
          type="checkbox"
          className={checkboxClass}
          checked={selectedShifts.includes(record.id)}
          onChange={(e) => {
            if (e.target.checked) {
              setSelectedShifts((prev) => [...prev, record.id]);
            } else {
              setSelectedShifts((prev) => prev.filter((id) => id !== record.id));
            }
          }}
        />
      ),
    },
    {
      key: 'shift',
      label: t('Shift'),
      sortable: true,
      render: (_: any, record: any) => (
        <div className="min-w-[7.5rem] max-w-[10rem]">
          <div className="flex flex-wrap items-baseline gap-1">
            <span className="font-mono text-[11px] font-bold text-slate-500">{record.short_code}</span>
            <span className="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-tight">{record.name}</span>
          </div>
          {record.is_night_shift && (
            <span className="text-[9px] font-bold uppercase tracking-wide text-indigo-600">{t('Night')}</span>
          )}
        </div>
      ),
    },
    {
      key: 'is_multi',
      label: t('Type'),
      render: (value: boolean) => (
        <Badge
          variant="outline"
          className={cn(
            'text-[10px] font-black uppercase px-1.5 py-0 rounded-md',
            value ? 'bg-slate-100 text-slate-700 border-slate-200' : 'bg-blue-50 text-blue-700 border-blue-200'
          )}
        >
          {value ? t('Multi') : t('Fixed')}
        </Badge>
      ),
    },
    {
      key: 'schedule',
      label: t('Schedule'),
      className: 'whitespace-nowrap',
      render: (_: any, record: any) =>
        formatCompactSchedule(record, formatSlotTime, formatDurationLabel) || (
          <span className="text-[10px] font-bold uppercase text-amber-600">{t('Setup required')}</span>
        ),
    },
    {
      key: 'attendance_duty_hours',
      label: t('Min. attendance hours'),
      className: 'align-top',
      render: (_: any, record: any) =>
        renderAttendanceDutyCell(record, {
          title: t('Minimum working hours required for half day and full day attendance'),
          halfDay: t('Half day'),
          fullDay: t('Full day'),
          slotLabel: t('Slot'),
        }) || (
          <span className="text-[10px] font-bold uppercase text-amber-600">{t('Setup required')}</span>
        ),
    },
    {
      key: 'employees_count',
      label: t('Employees'),
      render: (_: number, record: any) => {
        const count = record.employees_count ?? 0;
        const branchId = record.branch_id ?? record.branch?.id;
        if (!hasPermission(permissions, 'view-employees') || !branchId) {
          return <span className="text-sm tabular-nums text-slate-600">{count}</span>;
        }
        return (
          <button
            type="button"
            title={t('View employees on this shift')}
            onClick={() =>
              router.get(route('hr.employees.index'), {
                branch: branchId,
                shift_id: record.id,
                status: 'active',
              })
            }
            className="flex items-center gap-1 text-sm font-medium tabular-nums theme-color hover:opacity-80 hover:underline border-none bg-transparent p-0 cursor-pointer"
          >
            <Users className="h-3.5 w-3.5 shrink-0" style={{ color: 'var(--theme-color)' }} />
            {count}
          </button>
        );
      },
    },
    ...(showBranchColumn
      ? [
          {
            key: 'branch',
            label: t('Branch'),
            render: (_: any, record: any) => (
              <Badge
                variant="outline"
                className="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-50 text-slate-600 border-slate-200"
              >
                {record.branch?.name || t('N/A')}
              </Badge>
            ),
          },
        ]
      : []),
    {
      key: 'status',
      label: t('Status'),
      render: (_: string, record: any) => {
        const isActive = record.status === 'active';
        const canToggle =
          hasPermission(permissions, 'toggle-status-shifts') || hasPermission(permissions, 'edit-shifts');

        return (
          <button
            type="button"
            onClick={() => canToggle && handleAction('toggle-status', record)}
            title={
              canToggle
                ? isActive
                  ? t('Click to Deactivate')
                  : t('Click to Activate')
                : t('No permission to change status')
            }
            disabled={!canToggle}
            className={cn(
              'flex items-center gap-1 select-none',
              canToggle ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'
            )}
          >
            <span
              className={cn(
                'relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors',
                isActive ? 'bg-emerald-500' : 'bg-slate-300'
              )}
            >
              <span
                className={cn(
                  'inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform',
                  isActive ? 'translate-x-3.5' : 'translate-x-0.5'
                )}
              />
            </span>
            <span
              className={cn(
                'text-[10px] font-semibold uppercase tracking-wide',
                isActive ? 'text-emerald-600' : 'text-slate-400'
              )}
            >
              {isActive ? t('Active') : t('Inactive')}
            </span>
          </button>
        );
      },
    },
  ];

  const columns = tableColumns;

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
      requiredPermission: 'delete-shifts',
      isDisabled: (row: any) => !row.can_delete,
      disabledTitle: (row: any) =>
        row.delete_block_reason ||
        t('This shift is assigned to employees in this branch and cannot be deleted.'),
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
    { value: 'fixed', label: t('Fixed') },
    { value: 'multi', label: t('Multi') },
    { value: 'day', label: t('Day Shift') },
    { value: 'night', label: t('Night Shift') },
  ];

  const branchOptions = [
    { value: 'all', label: t('All Branches') },
    ...(branches || []).map((b: any) => ({
      value: b.id.toString(),
      label: b.name,
    })),
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
      {filteredBranchName && isViewingOtherBranch && (
        <div
          className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm"
          style={{
            borderColor: 'color-mix(in srgb, var(--theme-color) 28%, transparent)',
            backgroundColor: 'color-mix(in srgb, var(--theme-color) 8%, transparent)',
            color: 'var(--theme-color)',
          }}
        >
          <span>
            {t('Showing shifts for')}: <strong>{filteredBranchName}</strong>
          </span>
          <button
            type="button"
            onClick={() => {
              const activeId = String(sessionActiveBranchId);
              setSelectedBranch(activeId);
              router.get(route('hr.shifts.index'), listQueryParams({ branch_id: activeId }), {
                preserveState: true,
                preserveScroll: true,
              });
            }}
            className="text-xs font-semibold underline border-none bg-transparent cursor-pointer hover:opacity-80"
            style={{ color: 'var(--theme-color)' }}
          >
            {t('Show active branch')}
            {activeBranchName ? ` (${activeBranchName})` : ''}
          </button>
        </div>
      )}

      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          {filteredBranchName && selectedBranch !== 'all' && (
            <>
              <span className="font-semibold text-slate-700 dark:text-slate-300">
                {filteredBranchName}
              </span>
              <span className="text-slate-300">·</span>
            </>
          )}
          <span>
            {t('Shifts')}{' '}
            <span className="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{stats.total ?? 0}</span>
          </span>
          <span className="text-slate-300">·</span>
          <span>
            {t('Active')}{' '}
            <span className="font-semibold text-emerald-600 tabular-nums">{stats.active ?? 0}</span>
          </span>
          <span className="text-slate-300">·</span>
          <span>
            {t('Inactive')}{' '}
            <span className="font-semibold text-slate-600 tabular-nums">{stats.inactive ?? 0}</span>
          </span>
          <span className="text-slate-300">·</span>
          <span>
            {t('Employees')}{' '}
            <span className="font-semibold tabular-nums theme-color">{stats.total_employees ?? 0}</span>
          </span>
        </div>
        <SearchAndFilterBar
          searchTerm={searchTerm}
          onSearchChange={setSearchTerm}
          onSearch={handleSearch}
          onSearchClear={handleSearchClear}
          filters={[
            {
              name: 'branch_id',
              label: t('Branch'),
              type: 'select',
              value: selectedBranch,
              onChange: setSelectedBranch,
              options: branchOptions,
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
              name: 'shift_type',
              label: t('Type'),
              type: 'select',
              value: selectedShiftType,
              onChange: setSelectedShiftType,
              options: shiftTypeOptions,
            },
          ]}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          currentPerPage={pageFilters.per_page?.toString() || '10'}
          onPerPageChange={(value) => {
            router.get(
              route('hr.shifts.index'),
              listQueryParams({ per_page: parseInt(value, 10) }),
              { preserveState: true, preserveScroll: true }
            );
          }}
        />
      </div>

      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <div className="w-full overflow-x-auto">
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
            dense
            stickyActions
            entityPermissions={{
              view: 'view-shifts',
              edit: 'edit-shifts',
              delete: 'delete-shifts',
            }}
          />
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