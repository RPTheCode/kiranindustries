import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertCircle,
    CalendarIcon,
    CheckCircle2,
    Clock,
    Edit,
    LogIn,
    LogOut,
    Timer,
    User,
    XCircle,
} from 'lucide-react';
import { PunchPairsEditor } from '@/components/attendance/PunchPairsEditor';
import {
    PunchPair,
    ShiftBounds,
    calcTotalMinutesFromPairs,
    formatTime12h,
    getEmployeeShiftSchedule,
    getRecordDisplayPairs,
    hasActualPunchData,
    getMispunchIssues,
    mispunchSummaryText,
    parseLogDetailsToEvents,
    hasMispunchIssues,
} from '@/lib/attendance-punches';

// ─── Status meta ─────────────────────────────────────────────────────────────

const STATUS_OPTIONS = [
    {
        value: 'P',
        label: 'Present',
        shortDesc: 'Employee came to work',
        color: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        bannerBg: 'bg-emerald-50 border-emerald-200',
        bannerText: 'text-emerald-800',
        icon: <CheckCircle2 className="w-4 h-4" />,
    },
    {
        value: 'A',
        label: 'Absent',
        shortDesc: 'Did not come — no times needed',
        color: 'bg-rose-100 text-rose-800 border-rose-300',
        bannerBg: 'bg-rose-50 border-rose-200',
        bannerText: 'text-rose-800',
        icon: <XCircle className="w-4 h-4" />,
    },
    {
        value: 'HD',
        label: 'Half Day',
        shortDesc: 'Came for half the shift',
        color: 'bg-amber-100 text-amber-900 border-amber-300',
        bannerBg: 'bg-amber-50 border-amber-200',
        bannerText: 'text-amber-900',
        icon: <span className="text-sm font-black leading-none">½</span>,
    },
    {
        value: 'MIS',
        label: 'Mispunch',
        shortDesc: 'Machine missed a punch',
        color: 'bg-orange-100 text-orange-800 border-orange-300',
        bannerBg: 'bg-orange-50 border-orange-200',
        bannerText: 'text-orange-900',
        icon: <AlertCircle className="w-4 h-4" />,
    },
    {
        value: 'W',
        label: 'Week Off',
        shortDesc: 'Scheduled day off',
        color: 'bg-slate-100 text-slate-700 border-slate-300',
        bannerBg: 'bg-slate-50 border-slate-200',
        bannerText: 'text-slate-700',
        icon: <CalendarIcon className="w-4 h-4" />,
    },
] as const;

type StatusValue = (typeof STATUS_OPTIONS)[number]['value'];

function getStatusMeta(status: string) {
    return STATUS_OPTIONS.find((o) => o.value === status);
}

// ─── View-mode help text ──────────────────────────────────────────────────────

function viewHelpText(status: string, hasPunches: boolean, viewIssuesCount: number = 0, mispunchText: string = ''): string | null {
    if (status === 'P' && hasPunches)
        return 'Attendance looks correct. Tap "Edit" below only if you need to make a change.';
    if (status === 'P' && !hasPunches)
        return 'No machine punch found — attendance was set manually by HR.';
    if (status === 'A')
        return 'No punch recorded. Was the employee actually present? Tap "Edit" below to correct.';
    if (status === 'MIS') {
        if (viewIssuesCount > 0) {
            return `Mispunch: ${mispunchText}. Tap Edit to add missing times.`;
        }
        return 'A clock-in or clock-out is missing. Tap "Edit" to add the missing time.';
    }
    if (status === 'HD')
        return 'Half-day attendance recorded. Tap "Edit" to change if needed.';
    if (status === 'W')
        return 'This is a scheduled weekly off day. No attendance action needed.';
    if (status === 'H')
        return 'This is a public holiday. No attendance action needed.';
    return null;
}

// ─── Types ────────────────────────────────────────────────────────────────────

type Emp = {
    id: number;
    name: string;
    code: string;
    shift: string;
    is_multi_shift?: boolean;
    slots?: {
        id: number | string;
        slot_name?: string;
        start_time?: string;
        end_time?: string;
        half_day_mins?: number;
    }[];
};

type RecordRow = {
    status: string;
    in_time?: string | null;
    out_time?: string | null;
    log_details?: string | null;
    total_minutes?: number;
    ot_minutes?: number;
    is_manual?: boolean;
    shift_slot_id?: string | null;
};

type Props = {
    mode: 'view' | 'edit';
    dayLabel: string;
    monthName: string;
    emp: Emp;
    record: RecordRow;
    editForm: { status: string; remarks: string; shift_slot_id: string };
    onEditFormChange: (patch: Partial<{ status: string; remarks: string; shift_slot_id: string }>) => void;
    editPunchPairs: PunchPair[];
    onEditPunchPairsChange: (pairs: PunchPair[]) => void;
    editShiftBounds?: ShiftBounds;
    onStatusChange: (status: string) => void;
    onSelectShift: (slotId: string) => void;
    onMarkPresent: () => void;
    formatHours: (mins: number) => string;
};

// ─── Step badge ───────────────────────────────────────────────────────────────

function StepBadge({ n, label }: { n: number; label: string }) {
    return (
        <div className="flex items-center gap-2 mb-2">
            <div className="w-6 h-6 rounded-full bg-primary flex items-center justify-center text-white text-[11px] font-black shrink-0">
                {n}
            </div>
            <span className="text-sm font-semibold text-gray-700">{label}</span>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export function AttendanceDayModalBody({
    mode,
    dayLabel,
    monthName,
    emp,
    record,
    editForm,
    onEditFormChange,
    editPunchPairs,
    onEditPunchPairsChange,
    editShiftBounds,
    onStatusChange,
    onSelectShift,
    onMarkPresent,
    formatHours,
}: Props) {
    const shiftSchedule = getEmployeeShiftSchedule(emp);
    const hasPunches = hasActualPunchData(record);
    const viewPairs = getRecordDisplayPairs(record, emp);
    const punchEvents = parseLogDetailsToEvents(record.log_details);
    const viewIssues = getMispunchIssues(viewPairs);
    const isMispunchRecord =
        record.status === 'MIS' || viewIssues.length > 0 || hasMispunchIssues(editPunchPairs);
    const workedMins =
        (hasPunches ? calcTotalMinutesFromPairs(viewPairs) : 0) || (record.total_minutes ?? 0);

    const hasRecordedPunches = editPunchPairs.some((p) => p.in_time || p.out_time);
    const useCustomPunches =
        editForm.shift_slot_id === 'custom' ||
        (editForm.status === 'MIS' && hasRecordedPunches);

    const needsShiftPick =
        mode === 'edit' &&
        editForm.status !== 'A' &&
        emp.is_multi_shift &&
        (emp.slots?.length ?? 0) > 0 &&
        !editForm.shift_slot_id &&
        !useCustomPunches;

    const showPunchEditor =
        mode === 'edit' && editForm.status !== 'A' && editForm.status !== 'W' && editForm.status !== 'H';

    // Active status meta for edit mode
    const activeStatusMeta = getStatusMeta(mode === 'edit' ? editForm.status : record.status);
    const helpText = viewHelpText(record.status, hasPunches, viewIssues.length, mispunchSummaryText(viewPairs));

    return (
        <div className="space-y-3">

            {/* ── Employee strip ────────────────────────────────────────────── */}
            <div
                className={`flex items-center gap-4 p-4 rounded-xl border-2 ${
                    record.status === 'P'
                        ? 'border-emerald-200 bg-emerald-50/40'
                        : record.status === 'A'
                          ? 'border-rose-200 bg-rose-50/30'
                          : 'border-gray-200 bg-gray-50/80'
                }`}
            >
                <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                    <User className="w-6 h-6 text-primary" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="font-bold text-base truncate">{emp.name}</p>
                    <p className="text-sm text-muted-foreground">
                        {emp.code} · {emp.shift}
                    </p>
                    <p className="text-xs text-muted-foreground mt-0.5 flex items-center gap-1">
                        <CalendarIcon className="w-3 h-3" />
                        {dayLabel} {monthName}
                    </p>
                </div>
                <Badge
                    className={`${
                        activeStatusMeta?.color ?? 'bg-gray-100 text-gray-700'
                    } text-sm px-3 py-1`}
                >
                    {activeStatusMeta?.label ?? record.status}
                </Badge>
            </div>

            {/* ── Shift schedule pills ──────────────────────────────────────── */}
            {shiftSchedule.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {shiftSchedule.map((slot, i) => (
                        <div
                            key={slot.id ?? i}
                            className="inline-flex items-center gap-2 text-xs font-medium px-3 py-2 rounded-lg bg-indigo-50 text-indigo-900 border border-indigo-100"
                        >
                            <Timer className="w-3.5 h-3.5" />
                            <span className="font-bold">{slot.slot_name}</span>
                            <span className="font-mono">
                                {formatTime12h(slot.start_time)} – {formatTime12h(slot.end_time)}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/*  VIEW MODE                                                       */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            {mode === 'view' ? (
                <>
                    {/* Contextual help banner */}
                    {helpText && (
                        <div
                            className={`flex items-start gap-3 p-3 rounded-xl border text-sm ${
                                record.status === 'A'
                                    ? 'bg-rose-50 border-rose-200 text-rose-900'
                                    : record.status === 'MIS'
                                      ? 'bg-orange-50 border-orange-200 text-orange-900'
                                      : record.status === 'W' || record.status === 'H'
                                        ? 'bg-slate-50 border-slate-200 text-slate-700'
                                        : 'bg-blue-50 border-blue-200 text-blue-900'
                            }`}
                        >
                            <div className="shrink-0 mt-0.5">
                                {record.status === 'A' && <XCircle className="w-4 h-4 text-rose-500" />}
                                {record.status === 'MIS' && <AlertCircle className="w-4 h-4 text-orange-500" />}
                                {record.status === 'P' && <CheckCircle2 className="w-4 h-4 text-emerald-500" />}
                                {(record.status === 'W' || record.status === 'H') && (
                                    <CalendarIcon className="w-4 h-4 text-slate-500" />
                                )}
                                {record.status === 'HD' && <span className="text-sm font-black text-amber-600">½</span>}
                            </div>
                            <p className="text-sm font-medium">{helpText}</p>
                        </div>
                    )}

                    {/* Punch pairs / empty state */}
                    {hasPunches ? (
                        <div className="space-y-2">
                            <p className="text-sm font-semibold text-gray-800">Clock times</p>
                            {punchEvents.length > 0 && viewIssues.length > 0 ? (
                                <div className="space-y-1.5 rounded-xl border border-orange-100 bg-orange-50/30 p-3">
                                    <p className="text-[10px] font-bold uppercase text-orange-800 tracking-wide">
                                        Device punches (order)
                                    </p>
                                    {punchEvents.map((ev, idx) => (
                                        <div
                                            key={`${ev.type}-${ev.time}-${idx}`}
                                            className={`flex items-center gap-3 py-2 px-3 rounded-lg border ${
                                                ev.type === 'IN'
                                                    ? 'bg-emerald-50 border-emerald-100'
                                                    : 'bg-rose-50 border-rose-100'
                                            }`}
                                        >
                                            <span
                                                className={`text-[10px] font-black uppercase w-8 ${
                                                    ev.type === 'IN' ? 'text-emerald-700' : 'text-rose-700'
                                                }`}
                                            >
                                                {ev.type}
                                            </span>
                                            <span className="font-mono font-bold text-sm flex-1">
                                                {formatTime12h(ev.time)}
                                            </span>
                                            {ev.type === 'OUT' &&
                                                viewPairs.some(
                                                    (p, pi) =>
                                                        p.out_time === ev.time &&
                                                        !p.in_time &&
                                                        viewIssues.some(
                                                            (i) => i.pairIndex === pi && i.missing === 'IN'
                                                        )
                                                ) && (
                                                    <span className="text-[10px] font-bold text-orange-700 bg-orange-100 px-2 py-0.5 rounded">
                                                        Missing IN
                                                    </span>
                                                )}
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            {viewPairs.map((pair, idx) => {
                                const issue = viewIssues.find((i) => i.pairIndex === idx);
                                return (
                                    <div
                                        key={pair.id}
                                        className={`flex items-center gap-3 p-3 rounded-lg border bg-white dark:bg-gray-900 ${
                                            issue ? 'border-orange-300 ring-1 ring-orange-100' : ''
                                        }`}
                                    >
                                        {viewPairs.length > 1 && (
                                            <span className="text-xs font-bold text-muted-foreground w-5">
                                                {idx + 1}
                                            </span>
                                        )}
                                        <div className="flex-1 grid grid-cols-2 gap-2">
                                            <div
                                                className={`text-center py-2 rounded-md ${
                                                    !pair.in_time && issue?.missing === 'IN'
                                                        ? 'bg-orange-100 border border-orange-300 border-dashed'
                                                        : 'bg-emerald-50'
                                                }`}
                                            >
                                                <p className="text-[10px] text-emerald-600 font-medium">IN</p>
                                                <p
                                                    className={`font-mono font-bold text-sm ${
                                                        !pair.in_time ? 'text-orange-600' : ''
                                                    }`}
                                                >
                                                    {pair.in_time ? formatTime12h(pair.in_time) : 'Missing'}
                                                </p>
                                            </div>
                                            <div
                                                className={`text-center py-2 rounded-md ${
                                                    !pair.out_time && issue?.missing === 'OUT'
                                                        ? 'bg-orange-100 border border-orange-300 border-dashed'
                                                        : 'bg-rose-50'
                                                }`}
                                            >
                                                <p className="text-[10px] text-rose-600 font-medium">OUT</p>
                                                <p
                                                    className={`font-mono font-bold text-sm ${
                                                        !pair.out_time ? 'text-orange-600' : ''
                                                    }`}
                                                >
                                                    {pair.out_time ? formatTime12h(pair.out_time) : 'Missing'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        /* Empty punch state — friendly per status */
                        <div className="text-center py-8 px-4 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50/50">
                            <Clock className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                            {record.status === 'A' ? (
                                <>
                                    <p className="text-sm font-semibold text-gray-700">No punch needed for Absent days</p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Was the employee actually present? Tap "Edit attendance" below to correct.
                                    </p>
                                </>
                            ) : record.status === 'W' || record.status === 'H' ? (
                                <>
                                    <p className="text-sm font-semibold text-gray-700">
                                        {record.status === 'W' ? 'Week Off — no punch expected' : 'Holiday — no punch expected'}
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">No attendance action needed for this day.</p>
                                </>
                            ) : record.is_manual ? (
                                <>
                                    <p className="text-sm font-semibold text-gray-700">No machine punch found</p>
                                    <p className="text-xs text-muted-foreground mt-1">Attendance was set manually by HR.</p>
                                </>
                            ) : (
                                <>
                                    <p className="text-sm font-medium text-gray-700">No clock in / out recorded</p>
                                    <p className="text-xs text-muted-foreground mt-1">Use Edit to add times</p>
                                </>
                            )}
                        </div>
                    )}

                    {/* Hours / OT summary */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="p-4 rounded-xl border bg-white text-center">
                            <p className="text-xs text-muted-foreground mb-1">Hours worked</p>
                            <p className="text-2xl font-black text-emerald-600">
                                {workedMins > 0 ? formatHours(workedMins) : '0h'}
                            </p>
                        </div>
                        <div className="p-4 rounded-xl border bg-white text-center">
                            <p className="text-xs text-muted-foreground mb-1">Overtime</p>
                            <p className="text-2xl font-black text-blue-600">
                                {(record.ot_minutes ?? 0) > 0 ? formatHours(record.ot_minutes ?? 0) : '—'}
                            </p>
                        </div>
                    </div>

                    {/* Manual adjusted note */}
                    {record.is_manual && (
                        <div className="flex items-center gap-2 p-2.5 bg-amber-50 text-amber-800 text-xs rounded-lg border border-amber-200">
                            <Edit className="w-4 h-4 shrink-0" />
                            Manually adjusted by HR
                        </div>
                    )}
                </>
            ) : (
                /* ═══════════════════════════════════════════════════════════ */
                /*  EDIT MODE                                                   */
                /* ═══════════════════════════════════════════════════════════ */
                <div className="space-y-4">

                    {/* ── Step 1: Choose Status ────────────────────────────── */}
                    <div className="space-y-2">
                        <StepBadge n={1} label="What is the attendance status?" />
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                            {STATUS_OPTIONS.map((opt) => {
                                const isActive = editForm.status === opt.value;
                                return (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => onStatusChange(opt.value)}
                                        className={`flex items-start gap-2 px-3 py-2 rounded-xl border-2 text-left transition-all ${
                                            isActive
                                                ? opt.color + ' ring-2 ring-offset-1 ring-primary/30 shadow-sm'
                                                : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'
                                        }`}
                                    >
                                        <span className={`shrink-0 mt-0.5 ${isActive ? '' : 'opacity-40'}`}>
                                            {opt.icon}
                                        </span>
                                        <span className="flex flex-col min-w-0">
                                            <span className="font-bold text-xs leading-tight">{opt.label}</span>
                                            <span className={`text-[10px] leading-tight mt-0.5 ${isActive ? 'opacity-80' : 'text-gray-400'}`}>
                                                {opt.shortDesc}
                                            </span>
                                        </span>
                                        {isActive && (
                                            <CheckCircle2 className="w-3.5 h-3.5 ml-auto shrink-0 text-primary mt-0.5 hidden sm:block" />
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* ── Absent — simplified state ────────────────────────── */}
                    {editForm.status === 'A' && (
                        <div className="rounded-xl border-2 border-dashed border-rose-200 bg-rose-50/50 p-5 text-center space-y-3">
                            <div className="flex items-center justify-center gap-2 text-rose-700">
                                <XCircle className="w-5 h-5" />
                                <p className="text-sm font-bold">Absent — no IN/OUT times needed.</p>
                            </div>
                            <p className="text-xs text-rose-600">
                                If the employee was actually present, tap the button below to switch to Present and enter times.
                            </p>
                            <Button type="button" variant="default" size="sm" onClick={onMarkPresent} className="gap-2">
                                <LogIn className="w-4 h-4" />
                                Mark Present &amp; add times
                            </Button>
                        </div>
                    )}

                    {/* ── Week Off — no times needed ───────────────────────── */}
                    {editForm.status === 'W' && (
                        <div className="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-4 text-center">
                            <CalendarIcon className="w-6 h-6 text-slate-400 mx-auto mb-2" />
                            <p className="text-sm font-semibold text-slate-700">Week Off — no times needed</p>
                            <p className="text-xs text-slate-500 mt-1">This is a scheduled off day. No punch times are required.</p>
                        </div>
                    )}

                    {/* ── Step 2: Which shift? (multi-shift only) ──────────── */}
                    {showPunchEditor && emp.is_multi_shift && (emp.slots?.length ?? 0) > 0 && (
                        <div className="space-y-2">
                            <StepBadge n={2} label="Which shift did the employee work?" />
                            <p className="text-xs text-muted-foreground -mt-1 mb-1">
                                Pick the shift slot to auto-fill the IN/OUT times.
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {(editForm.status === 'MIS' || hasRecordedPunches) && (
                                    <button
                                        type="button"
                                        onClick={() => onSelectShift('custom')}
                                        className={`px-3 py-2 rounded-xl border-2 text-left text-xs transition-all min-w-[120px] ${
                                            editForm.shift_slot_id === 'custom'
                                                ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-200'
                                                : 'border-orange-200 bg-white hover:border-orange-300'
                                        }`}
                                    >
                                        <p className="font-bold text-orange-900">Recorded punches</p>
                                        <p className="text-muted-foreground mt-0.5 font-normal text-[10px]">
                                            Fix existing times
                                        </p>
                                    </button>
                                )}
                                {emp.slots!.map((slot) => {
                                    const start = slot.start_time?.substring(0, 5) || '';
                                    const end = slot.end_time?.substring(0, 5) || '';
                                    const id = String(slot.id);
                                    const selected = editForm.shift_slot_id === id;
                                    return (
                                        <button
                                            key={id}
                                            type="button"
                                            onClick={() => onSelectShift(id)}
                                            className={`px-3 py-2 rounded-xl border-2 text-left text-xs transition-all min-w-[100px] ${
                                                selected
                                                    ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                                    : 'border-gray-200 bg-white hover:border-gray-300'
                                            }`}
                                        >
                                            <p className="font-bold">{slot.slot_name}</p>
                                            <p className="font-mono text-muted-foreground mt-0.5 text-[10px]">
                                                {formatTime12h(start)} – {formatTime12h(end)}
                                            </p>
                                        </button>
                                    );
                                })}
                                {editForm.status !== 'HD' && (emp.slots?.length ?? 0) > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => onSelectShift('both')}
                                        className={`px-3 py-2 rounded-xl border-2 text-xs font-bold ${
                                            editForm.shift_slot_id === 'both'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-gray-200 bg-white'
                                        }`}
                                    >
                                        Both shifts
                                        <span className="block font-normal text-muted-foreground mt-0.5 text-[10px]">
                                            2 IN/OUT rows
                                        </span>
                                    </button>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Helper tip when shift still needs to be picked */}
                    {needsShiftPick && (
                        <div className="flex items-start gap-2 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs">
                            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-amber-500" />
                            <p>
                                <strong>Choose a shift above</strong> to fill in the IN/OUT times automatically, or choose{' '}
                                <strong>Recorded punches</strong> to fix this mispunch manually.
                            </p>
                        </div>
                    )}

                    {/* Mispunch hint */}
                    {editForm.status === 'MIS' && hasRecordedPunches && (
                        <div className="flex items-start gap-2 p-3 rounded-xl bg-orange-50 border border-orange-200 text-orange-900 text-xs">
                            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-orange-500" />
                            <p>
                                <strong>Mispunch fix:</strong> Add the missing IN time in the orange field below, or use{' '}
                                <strong>Recorded punches</strong> to edit all rows freely.
                            </p>
                        </div>
                    )}

                    {/* ── Step 3: IN/OUT times ─────────────────────────────── */}
                    {showPunchEditor && !needsShiftPick && (
                        <div className="space-y-2">
                            <StepBadge
                                n={emp.is_multi_shift && (emp.slots?.length ?? 0) > 0 ? 3 : 2}
                                label="Enter clock-in and clock-out times"
                            />
                            <PunchPairsEditor
                                pairs={editPunchPairs}
                                shiftBounds={editShiftBounds}
                                onChange={onEditPunchPairsChange}
                                allowPartial={editForm.status === 'MIS' || useCustomPunches}
                            />
                        </div>
                    )}

                    {/* ── Step 4: Note (optional) ──────────────────────────── */}
                    <div className="space-y-2">
                        <StepBadge
                            n={
                                !showPunchEditor ? 2
                                : emp.is_multi_shift && (emp.slots?.length ?? 0) > 0 ? 4
                                : 3
                            }
                            label="Add a note (optional)"
                        />
                        <Textarea
                            placeholder="e.g. device offline, corrected by HR..."
                            value={editForm.remarks}
                            onChange={(e) => onEditFormChange({ remarks: e.target.value })}
                            className="min-h-[48px] text-sm"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
