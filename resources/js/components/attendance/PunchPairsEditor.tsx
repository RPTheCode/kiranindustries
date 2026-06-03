import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2, LogIn, LogOut, Clock, Lock, ArrowRight } from 'lucide-react';
import {
    PunchPair,
    ShiftBounds,
    calcTotalMinutesFromPairs,
    formatMinutesAsHours,
    formatTime12h,
    addMiddlePunchPair,
    removePunchPair,
    applyShiftAnchors,
    getPairRole,
    insertPunchPairAfter,
    getMispunchIssues,
    mispunchSummaryText,
} from '@/lib/attendance-punches';

type Props = {
    pairs: PunchPair[];
    onChange: (pairs: PunchPair[]) => void;
    disabled?: boolean;
    showSummary?: boolean;
    minPairs?: number;
    shiftBounds?: ShiftBounds;
    /** Mispunch fix: allow empty IN or OUT per row, no shift anchors */
    allowPartial?: boolean;
};

const pairDurationMinutes = (pair: PunchPair): number => {
    if (!pair.in_time || !pair.out_time) return 0;
    if (pair.in_time === pair.out_time) return 0;
    const [ih, im] = pair.in_time.split(':').map(Number);
    const [oh, om] = pair.out_time.split(':').map(Number);
    let d = oh * 60 + om - (ih * 60 + im);
    if (d <= 0) d += 24 * 60;
    return d;
};

export function PunchPairsEditor({
    pairs,
    onChange,
    disabled = false,
    showSummary = true,
    minPairs = 1,
    shiftBounds,
    allowPartial = false,
}: Props) {
    const hasAnchors = !allowPartial && !!(shiftBounds?.start && shiftBounds?.end);
    const multiWithAnchors = hasAnchors && pairs.length > 1;
    const mispunchIssues = getMispunchIssues(pairs);

    const updatePair = (id: string, field: 'in_time' | 'out_time', value: string) => {
        const idx = pairs.findIndex((p) => p.id === id);
        if (idx < 0) return;

        let next = pairs.map((p) => (p.id === id ? { ...p, [field]: value } : p));
        onChange(next);
    };

    const removePair = (id: string) => onChange(removePunchPair(pairs, id, minPairs, shiftBounds));
    const addInMiddle = () => onChange(addMiddlePunchPair(pairs, shiftBounds));

    const totalMins = calcTotalMinutesFromPairs(pairs);
    const canAddBetween = (index: number) => !multiWithAnchors || index < pairs.length - 1;

    return (
        <div className="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h4 className="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <Clock className="w-4 h-4 text-primary" />
                        IN / OUT times
                    </h4>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        {allowPartial
                            ? 'Fill missing IN or OUT. Add rows for break / middle punches.'
                            : hasAnchors
                              ? `Shift ${formatTime12h(shiftBounds!.start)} – ${formatTime12h(shiftBounds!.end)}.`
                              : 'Enter clock in and clock out. Use + to add more pairs.'}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="h-9 gap-1.5 shrink-0 font-semibold"
                    onClick={addInMiddle}
                    disabled={disabled}
                >
                    <Plus className="w-4 h-4" />
                    Add IN / OUT
                </Button>
            </div>

            {allowPartial && mispunchIssues.length > 0 && (
                <div className="text-xs font-medium text-orange-900 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
                    Fix: {mispunchSummaryText(pairs)} — enter the missing times below.
                </div>
            )}

            <div className="space-y-2">
                {pairs.map((pair, index) => {
                    const issue = mispunchIssues.find((i) => i.pairIndex === index);
                    const role = getPairRole(index, pairs.length, shiftBounds);
                    const lockIn = false;
                    const lockOut = false;
                    const duration = pairDurationMinutes(pair);
                    const rowLabel = pairs.length > 1 ? `${index + 1}` : null;

                    return (
                        <div key={pair.id}>
                            <div className="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-800/30 p-3">
                                <div className="flex items-center justify-between gap-2 mb-3">
                                    <span className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        {rowLabel ? (
                                            <>
                                                <span className="inline-flex w-6 h-6 items-center justify-center rounded-full bg-primary/10 text-primary text-[11px] mr-1.5">
                                                    {rowLabel}
                                                </span>
                                                IN → OUT
                                            </>
                                        ) : (
                                            'IN → OUT'
                                        )}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-8 px-2 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                                        onClick={() => removePair(pair.id)}
                                        disabled={disabled || pairs.length <= minPairs}
                                    >
                                        <Trash2 className="w-3.5 h-3.5 mr-1" />
                                        Remove
                                    </Button>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] gap-3 items-end">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                                            <LogIn className="w-3.5 h-3.5" />
                                            IN
                                            {lockIn && (
                                                <span className="text-[10px] text-muted-foreground font-normal inline-flex items-center gap-0.5">
                                                    <Lock className="w-2.5 h-2.5" /> fixed
                                                </span>
                                            )}
                                        </Label>
                                        <Input
                                            type="time"
                                            value={pair.in_time}
                                            onChange={(e) => updatePair(pair.id, 'in_time', e.target.value)}
                                            disabled={disabled || lockIn}
                                            placeholder={issue?.missing === 'IN' ? 'Add IN' : undefined}
                                            className={`h-10 font-mono text-base bg-white dark:bg-gray-900 ${
                                                issue?.missing === 'IN'
                                                    ? 'border-2 border-orange-400 ring-1 ring-orange-200'
                                                    : ''
                                            }`}
                                        />
                                    </div>
                                    <ArrowRight className="hidden sm:block w-5 h-5 text-muted-foreground mb-2" />
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium flex items-center gap-1 text-rose-700 dark:text-rose-400">
                                            <LogOut className="w-3.5 h-3.5" />
                                            OUT
                                            {lockOut && (
                                                <span className="text-[10px] text-muted-foreground font-normal inline-flex items-center gap-0.5">
                                                    <Lock className="w-2.5 h-2.5" /> fixed
                                                </span>
                                            )}
                                        </Label>
                                        <Input
                                            type="time"
                                            value={pair.out_time}
                                            onChange={(e) => updatePair(pair.id, 'out_time', e.target.value)}
                                            disabled={disabled || lockOut}
                                            placeholder={issue?.missing === 'OUT' ? 'Add OUT' : undefined}
                                            className={`h-10 font-mono text-base bg-white dark:bg-gray-900 ${
                                                issue?.missing === 'OUT'
                                                    ? 'border-2 border-orange-400 ring-1 ring-orange-200'
                                                    : ''
                                            }`}
                                        />
                                    </div>
                                </div>

                                {pair.in_time && pair.out_time && pair.in_time === pair.out_time && (
                                    <p className="text-[11px] text-amber-700 mt-2">
                                        Same IN/OUT time from device — enter correct OUT time if missing.
                                    </p>
                                )}
                                {duration > 0 && (
                                    <p className="text-[11px] text-muted-foreground mt-2">
                                        {formatMinutesAsHours(duration)} ({formatTime12h(pair.in_time)} –{' '}
                                        {formatTime12h(pair.out_time)})
                                    </p>
                                )}
                            </div>

                            {canAddBetween(index) && (
                                <div className="flex justify-center py-1.5">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 text-xs gap-1 border-dashed"
                                        onClick={() =>
                                            onChange(insertPunchPairAfter(pairs, index, shiftBounds))
                                        }
                                        disabled={disabled}
                                    >
                                        <Plus className="w-3 h-3" />
                                        Add here
                                    </Button>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {showSummary && (
                <div
                    className={`p-3 rounded-lg flex items-center justify-between gap-3 ${
                        totalMins > 0
                            ? 'bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800'
                            : 'bg-gray-50 dark:bg-gray-800/50 border border-dashed'
                    }`}
                >
                    <div>
                        <p className="text-xs font-bold">Total hours</p>
                        <p className="text-[10px] text-muted-foreground">
                            {totalMins > 0 && pairs.length > 1
                                ? 'Sum of each IN/OUT pair (gap time not counted)'
                                : 'From IN and OUT above'}
                        </p>
                    </div>
                    <span
                        className={`text-xl font-bold tabular-nums ${
                            totalMins > 0 ? 'text-emerald-700' : 'text-muted-foreground'
                        }`}
                    >
                        {totalMins > 0 ? formatMinutesAsHours(totalMins) : '—'}
                    </span>
                </div>
            )}
        </div>
    );
}
