export type PunchPair = {
    id: string;
    in_time: string;
    out_time: string;
};

export const createPunchPair = (in_time = '', out_time = ''): PunchPair => ({
    id: typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `p-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    in_time,
    out_time,
});

export const defaultPunchPairs = (in_time = '09:00', out_time = '18:00'): PunchPair[] => [
    createPunchPair(in_time, out_time),
];

export type ShiftBounds = {
    start: string;
    end: string;
};

/** Single pair pre-filled for shift window (e.g. 08:00–20:00) */
export const pairsFromShiftBounds = (bounds: ShiftBounds): PunchPair[] => [
    createPunchPair(bounds.start, bounds.end),
];

/** We no longer overwrite user punches with shift times automatically, so users can edit freely. */
export const applyShiftAnchors = (pairs: PunchPair[], bounds: ShiftBounds): PunchPair[] => {
    return pairs;
};

/**
 * Add a middle pair for break / split punch.
 */
export const addMiddlePunchPair = (pairs: PunchPair[], bounds?: ShiftBounds): PunchPair[] => {
    if (pairs.length === 0) {
        return bounds?.start && bounds?.end ? pairsFromShiftBounds(bounds) : [createPunchPair()];
    }

    // Split the last pair
    const targetIndex = pairs.length - 1;
    const target = pairs[targetIndex];
    const next = [...pairs];
    
    const newPair = createPunchPair('', target.out_time);
    next[targetIndex] = { ...target, out_time: '' };
    next.splice(targetIndex + 1, 0, newPair);

    if (bounds?.start && bounds?.end && next.length >= 2) {
        return applyShiftAnchors(next, bounds);
    }
    return next;
};

/** Remove pair without overwriting remaining punches with default shift times, but cleverly merge split times back if a row is removed */
export const removePunchPair = (
    pairs: PunchPair[],
    id: string,
    minPairs = 1,
    bounds?: ShiftBounds
): PunchPair[] => {
    if (pairs.length <= minPairs) return pairs;
    
    const index = pairs.findIndex((p) => p.id === id);
    if (index === -1) return pairs;

    const removed = pairs[index];
    const next = [...pairs];

    // If removing the second half of a split (where previous OUT is blank), give the previous row our OUT time
    if (index > 0 && !next[index - 1].out_time && removed.out_time) {
        next[index - 1] = { ...next[index - 1], out_time: removed.out_time };
    } 
    // If removing the first half of a split (where next IN is blank), give the next row our IN time
    else if (index < pairs.length - 1 && !next[index + 1].in_time && removed.in_time) {
        next[index + 1] = { ...next[index + 1], in_time: removed.in_time };
    }

    next.splice(index, 1);
    return next;
};

export const getPairRole = (
    index: number,
    total: number,
    bounds?: ShiftBounds
): 'start' | 'end' | 'middle' | 'single' => {
    if (!bounds?.start || !bounds?.end || total <= 1) return 'single';
    if (index === 0) return 'start';
    if (index === total - 1) return 'end';
    return 'middle';
};

/** Human-readable label for each IN/OUT block */
export const getSessionLabel = (index: number, total: number, bounds?: ShiftBounds): string => {
    const role = getPairRole(index, total, bounds);
    if (role === 'single') return 'Work session';
    if (role === 'start') return 'Shift start';
    if (role === 'end') return 'Shift end';
    if (total === 3) return 'Break';
    return `Work session ${index}`;
};

/** Insert a new pair after a given index by splitting the current pair's OUT time. */
export const insertPunchPairAfter = (
    pairs: PunchPair[],
    afterIndex: number,
    bounds?: ShiftBounds
): PunchPair[] => {
    if (!pairs.length) {
        return bounds?.start && bounds?.end
            ? pairsFromShiftBounds(bounds)
            : [createPunchPair()];
    }

    const target = pairs[afterIndex];
    const next = [...pairs];

    // Split the target pair: move its OUT time to the new pair
    const newPair = createPunchPair('', target.out_time);
    next[afterIndex] = { ...target, out_time: '' };
    next.splice(afterIndex + 1, 0, newPair);

    if (bounds?.start && bounds?.end && next.length >= 2) {
        return applyShiftAnchors(next, bounds);
    }
    return next;
};

/** Insert break pair after shift-start block (first OUT / middle IN). */
export const addBreakAfterStart = (pairs: PunchPair[], bounds?: ShiftBounds): PunchPair[] => {
    if (!bounds?.start || !bounds?.end) {
        return insertPunchPairAfter(pairs, 0, bounds);
    }
    if (pairs.length <= 1) {
        return addMiddlePunchPair(pairs, bounds);
    }
    return insertPunchPairAfter(pairs, 0, bounds);
};

/** Shift window for anchoring first IN / last OUT (single slot only, not double shift) */
export const resolveShiftBounds = (
    emp: {
        shift_start?: string | null;
        shift_end?: string | null;
        slots?: { id: number | string; start_time?: string; end_time?: string }[];
    } | null | undefined,
    shiftSlotId?: string | null
): ShiftBounds | undefined => {
    if (!emp) return undefined;
    if (shiftSlotId === 'both' || shiftSlotId === 'custom') return undefined;

    if (shiftSlotId && emp.slots?.length) {
        const slot = emp.slots.find((s) => String(s.id) === String(shiftSlotId));
        if (slot) {
            const start = formatCleanTime(slot.start_time);
            const end = formatCleanTime(slot.end_time);
            if (start && end && start !== end) {
                return { start, end };
            }
        }
    }

    if (emp.slots?.length === 1) {
        const start = formatCleanTime(emp.slots[0].start_time);
        const end = formatCleanTime(emp.slots[0].end_time);
        if (start && end) return { start, end };
    }

    const start = formatCleanTime(emp.shift_start);
    const end = formatCleanTime(emp.shift_end);
    if (start && end && start !== end) {
        return { start, end };
    }

    return undefined;
};

export const formatCleanTime = (timeStr: string | null | undefined): string => {
    if (!timeStr) return '';
    const s = String(timeStr);
    if (s.includes('T')) return s.split('T')[1].substring(0, 5);
    if (s.includes(' ')) return s.split(' ')[1].substring(0, 5);
    return s.substring(0, 5);
};

/** Parse log_details "08:00 IN, 12:00 OUT, 20:00 IN, 23:00 OUT" into IN/OUT pairs */
export const parseLogDetailsToPairs = (
    logDetails: string | null | undefined,
    fallbackIn?: string | null,
    fallbackOut?: string | null
): PunchPair[] => {
    const parsed = dedupeExactPunchEvents(parseLogDetailsToEvents(logDetails));

    const pairs: PunchPair[] = [];
    let currentIn = '';

    parsed.forEach((p) => {
        if (p.type === 'IN') {
            if (currentIn) {
                pairs.push(createPunchPair(currentIn, ''));
            }
            currentIn = p.time;
        } else if (currentIn) {
            pairs.push(createPunchPair(currentIn, p.time));
            currentIn = '';
        } else {
            pairs.push(createPunchPair('', p.time));
        }
    });

    if (currentIn) {
        pairs.push(createPunchPair(currentIn, ''));
    }

    const inT = formatCleanTime(fallbackIn);
    const outT = formatCleanTime(fallbackOut);
    const hasCompletePair = pairs.some((p) => p.in_time && p.out_time);

    if (inT && outT && !hasCompletePair) {
        return [createPunchPair(inT, outT)];
    }

    if (pairs.length === 0) {
        if (inT || outT) {
            return [createPunchPair(inT, outT)];
        }
        return defaultPunchPairs();
    }

    return pairs;
};

export const buildLogDetailsFromPairs = (pairs: PunchPair[]): string => {
    const parts: string[] = [];
    pairs.forEach((pair) => {
        if (pair.in_time) parts.push(`${pair.in_time} IN`);
        if (pair.out_time) parts.push(`${pair.out_time} OUT`);
    });
    return parts.join(', ');
};

export const calcTotalMinutesFromPairs = (pairs: PunchPair[]): number => {
    let total = 0;
    pairs.forEach((pair) => {
        if (!pair.in_time || !pair.out_time) return;
        const [ih, im] = pair.in_time.split(':').map(Number);
        const [oh, om] = pair.out_time.split(':').map(Number);
        let diff = (oh * 60 + om) - (ih * 60 + im);
        if (diff <= 0) diff += 24 * 60;
        total += diff;
    });
    return total;
};

export const formatTime12h = (time: string | null | undefined): string => {
    const t = formatCleanTime(time);
    if (!t) return '—';
    const [h, m] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
};

export type ShiftSlotDisplay = {
    id?: number | string;
    slot_name: string;
    start_time: string;
    end_time: string;
    half_day_mins?: number;
    full_day_mins?: number;
    duration_mins?: number;
};

export const addMinutesToTime = (time: string, minutes: number): string => {
    const t = formatCleanTime(time) || '00:00';
    const [h, m] = t.split(':').map(Number);
    let total = h * 60 + m + minutes;
    total = ((total % 1440) + 1440) % 1440;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
};

export const getHalfDayMinutesFromSlot = (slot: ShiftSlotDisplay): number => {
    if (slot.half_day_mins && slot.half_day_mins > 0) {
        return slot.half_day_mins;
    }
    if (slot.duration_mins && slot.duration_mins > 0) {
        return Math.round(slot.duration_mins * 0.5);
    }
    const start = formatCleanTime(slot.start_time);
    const end = formatCleanTime(slot.end_time);
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    let dur = (eh * 60 + em) - (sh * 60 + sm);
    if (dur <= 0) dur += 24 * 60;
    return Math.round(dur * 0.5);
};

export const resolveSlotForHalfDay = (
    emp: { slots?: ShiftSlotDisplay[] } | null | undefined,
    shiftSlotId?: string | null
): ShiftSlotDisplay | null => {
    if (!emp?.slots?.length) return null;
    if (shiftSlotId && shiftSlotId !== 'both') {
        const found = emp.slots.find((s) => String(s.id) === String(shiftSlotId));
        if (found) return found;
    }
    return emp.slots[0] ?? null;
};

/** Half day IN = shift start, OUT = start + half_day_mins from shift duty rules */
export const buildHalfDayPunchPairsFromSlot = (slot: ShiftSlotDisplay): PunchPair[] => {
    const start = formatCleanTime(slot.start_time) || '09:00';
    const halfMins = getHalfDayMinutesFromSlot(slot);
    const out = addMinutesToTime(start, halfMins);
    return [createPunchPair(start, out)];
};

export const resolveHalfDayShiftBounds = (
    emp: { slots?: ShiftSlotDisplay[]; shift_start?: string | null; shift_end?: string | null } | null | undefined,
    shiftSlotId?: string | null
): ShiftBounds | undefined => {
    const slot = resolveSlotForHalfDay(emp, shiftSlotId);
    if (!slot) {
        const bounds = resolveShiftBounds(emp, shiftSlotId);
        if (!bounds?.start) return bounds;
        const halfMins = Math.round(
            ((() => {
                const [sh, sm] = bounds.start.split(':').map(Number);
                const [eh, em] = (bounds.end || bounds.start).split(':').map(Number);
                let d = (eh * 60 + em) - (sh * 60 + sm);
                if (d <= 0) d += 24 * 60;
                return d;
            })()) * 0.5
        );
        return { start: bounds.start, end: addMinutesToTime(bounds.start, halfMins) };
    }
    const start = formatCleanTime(slot.start_time) || '09:00';
    return { start, end: addMinutesToTime(start, getHalfDayMinutesFromSlot(slot)) };
};

/** Employee's assigned shift slots for display */
export const getEmployeeShiftSchedule = (emp: {
    shift?: string;
    shift_start?: string | null;
    shift_end?: string | null;
    slots?: ShiftSlotDisplay[];
} | null | undefined): ShiftSlotDisplay[] => {
    if (!emp) return [];
    if (emp.slots?.length) {
        return emp.slots.map((s) => ({
            id: s.id,
            slot_name: s.slot_name || 'Shift',
            start_time: formatCleanTime(s.start_time) || '—',
            end_time: formatCleanTime(s.end_time) || '—',
        }));
    }
    const start = formatCleanTime(emp.shift_start);
    const end = formatCleanTime(emp.shift_end);
    if (start || end) {
        return [{ slot_name: emp.shift || 'Shift', start_time: start || '—', end_time: end || '—' }];
    }
    return [];
};

export const hasActualPunchData = (record: {
    log_details?: string | null;
    in_time?: string | null;
    out_time?: string | null;
}): boolean => {
    if (record.in_time || record.out_time) return true;
    const logs = (record.log_details || '').trim();
    if (!logs) return false;
    return logs.split(',').some((p) => p.trim().length > 0);
};

/** Punches for read-only view — no fake defaults when absent */
export const getRecordDisplayPairs = (
    record: {
        log_details?: string | null;
        in_time?: string | null;
        out_time?: string | null;
    },
    emp?: { shift_start?: string | null; shift_end?: string | null } | null
): PunchPair[] => {
    if (!hasActualPunchData(record)) return [];
    return parseLogDetailsToPairs(record.log_details, record.in_time, record.out_time);
};

export const getDaySpanFromPairs = (pairs: PunchPair[]): { in_time: string; out_time: string } => {
    const withIn = pairs.filter((p) => p.in_time);
    const withOut = pairs.filter((p) => p.out_time);
    return {
        in_time: withIn[0]?.in_time || '',
        out_time: withOut[withOut.length - 1]?.out_time || '',
    };
};

export const formatMinutesAsHours = (minutes: number): string => {
    if (minutes <= 0) return '0h';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
};

export type PunchEvent = { time: string; type: 'IN' | 'OUT' };

/** Flat IN/OUT sequence from log_details (e.g. "09:08 OUT, 09:47 OUT") */
export const parseLogDetailsToEvents = (
    logDetails: string | null | undefined
): PunchEvent[] => {
    return (logDetails || '')
        .split(',')
        .map((p) => p.trim())
        .filter(Boolean)
        .map((p) => {
            const parts = p.split(/\s+/);
            const time = formatCleanTime(parts[0]);
            const type = (parts[1] || 'IN').toUpperCase() === 'OUT' ? 'OUT' : 'IN';
            return { time, type } as PunchEvent;
        })
        .filter((e) => e.time);
};

/** Only remove exact duplicate (same time + same type) — keep consecutive OUT/IN */
export const dedupeExactPunchEvents = (events: PunchEvent[]): PunchEvent[] => {
    const result: PunchEvent[] = [];
    events.forEach((ev) => {
        const last = result[result.length - 1];
        if (last && last.time === ev.time && last.type === ev.type) {
            return;
        }
        result.push(ev);
    });
    return result;
};

/** Remove duplicate punches and collapse consecutive same-direction entries (legacy — avoid for pairing) */
export const normalizePunchEvents = (events: PunchEvent[]): PunchEvent[] => {
    const result: PunchEvent[] = [];
    events.forEach((ev) => {
        const last = result[result.length - 1];
        if (last && last.time === ev.time && last.type === ev.type) {
            return;
        }
        if (last && last.type === ev.type) {
            return;
        }
        result.push(ev);
    });
    return result;
};

export const buildLogDetailsFromEvents = (events: PunchEvent[]): string =>
    events.map((ev) => `${ev.time} ${ev.type}`).join(', ');

export const normalizeLogDetails = (logDetails: string | null | undefined): string =>
    buildLogDetailsFromEvents(normalizePunchEvents(parseLogDetailsToEvents(logDetails)));

/** Display-ready — all punches, no collapsing consecutive OUT/IN */
export const getDisplayPunchEvents = (logDetails: string | null | undefined): PunchEvent[] =>
    dedupeExactPunchEvents(parseLogDetailsToEvents(logDetails));

/** Show every punch from log_details in device order (no collapsing) */
export const getDisplayPunchEventsFromRecord = (record: {
    log_details?: string | null;
    in_time?: string | null;
    out_time?: string | null;
}): PunchEvent[] => {
    const fromLogs = parseLogDetailsToEvents(record.log_details);
    if (fromLogs.length > 0) {
        return fromLogs;
    }

    const inT = formatCleanTime(record.in_time);
    const outT = formatCleanTime(record.out_time);

    if (inT && outT) {
        return [
            { time: inT, type: 'IN' },
            { time: outT, type: 'OUT' },
        ];
    }

    if (inT || outT) {
        const events: PunchEvent[] = [];
        if (inT) events.push({ time: inT, type: 'IN' });
        if (outT) events.push({ time: outT, type: 'OUT' });
        return events;
    }

    return [];
};

export type MispunchIssue = { pairIndex: number; missing: 'IN' | 'OUT' };

export const getMispunchIssues = (pairs: PunchPair[]): MispunchIssue[] => {
    const issues: MispunchIssue[] = [];
    pairs.forEach((p, i) => {
        if (!p.in_time && p.out_time) issues.push({ pairIndex: i, missing: 'IN' });
        if (p.in_time && !p.out_time) issues.push({ pairIndex: i, missing: 'OUT' });
    });
    return issues;
};

export const hasMispunchIssues = (pairs: PunchPair[]): boolean => getMispunchIssues(pairs).length > 0;

export const mispunchSummaryText = (pairs: PunchPair[]): string => {
    const issues = getMispunchIssues(pairs);
    const missingIn = issues.filter((i) => i.missing === 'IN').length;
    const missingOut = issues.filter((i) => i.missing === 'OUT').length;
    const parts: string[] = [];
    if (missingIn) parts.push(`${missingIn} missing IN`);
    if (missingOut) parts.push(`${missingOut} missing OUT`);
    return parts.join(', ');
};

export type PunchValidationResult = { valid: boolean; message?: string; incomplete?: boolean };

export const validatePunchPairs = (
    pairs: PunchPair[],
    bounds?: ShiftBounds
): PunchValidationResult => {
    if (!pairs.length) {
        return { valid: false, message: 'Add at least one IN / OUT pair.' };
    }

    const anchored = !!(bounds?.start && bounds?.end && pairs.length > 1);

    if (anchored) {
        if (!pairs[0].in_time) {
            return { valid: false, message: 'Shift start (first IN) is required.' };
        }
        if (!pairs[pairs.length - 1].out_time) {
            return { valid: false, message: 'Shift end (last OUT) is required.' };
        }
        for (let i = 0; i < pairs.length; i++) {
            const p = pairs[i];
            const isFirst = i === 0;
            const isLast = i === pairs.length - 1;
            const isMiddle = !isFirst && !isLast;

            if (isMiddle && (!p.in_time || !p.out_time)) {
                return { valid: false, message: `Pair ${i + 1}: enter both IN and OUT, or remove the pair.` };
            }
            if (isFirst && !p.out_time) {
                return { valid: false, message: 'Pair 1: enter OUT time.' };
            }
            if (isLast && !p.in_time) {
                return { valid: false, message: `Pair ${pairs.length}: enter IN time.` };
            }
        }
        return { valid: true };
    }

    const complete = pairs.filter((p) => p.in_time && p.out_time);
    if (complete.length === 0) {
        return { valid: false, message: 'At least one pair must have both Clock IN and Clock OUT.' };
    }

    for (let i = 0; i < pairs.length; i++) {
        const p = pairs[i];
        const hasAny = p.in_time || p.out_time;
        const hasBoth = p.in_time && p.out_time;
        if (hasAny && !hasBoth) {
            return { valid: false, message: `Pair ${i + 1}: fill both IN and OUT, or remove the pair.` };
        }
    }

    return { valid: true };
};

export const buildAttendancePayloadFromPairs = (
    pairs: PunchPair[],
    status: string,
    extra: Record<string, unknown> = {}
) => {
    const sorted = [...pairs].sort((a, b) => (a.in_time || '').localeCompare(b.in_time || ''));
    const firstIn = sorted.find((p) => p.in_time)?.in_time || '';
    const lastOut = [...sorted].reverse().find((p) => p.out_time)?.out_time || '';
    const hasBoth = !!(firstIn && lastOut);
    const stillIncomplete = hasMispunchIssues(pairs);
    let finalStatus = status;
    if (!stillIncomplete && pairs.some((p) => p.in_time && p.out_time)) {
        if (status === 'MIS') finalStatus = 'P';
    } else if (stillIncomplete && status === 'P') {
        finalStatus = 'MIS';
    }

    const workedMinutes = calcTotalMinutesFromPairs(pairs);

    return {
        ...extra,
        in_time: firstIn || null,
        out_time: lastOut || null,
        status: finalStatus,
        log_details: buildLogDetailsFromPairs(pairs),
        actual_work_minutes: workedMinutes,
    };
};
