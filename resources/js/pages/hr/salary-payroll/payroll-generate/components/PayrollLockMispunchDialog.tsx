import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Loader2, Lock, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import {
  getMispunchIssues,
  mispunchSummaryText,
  parseLogDetailsToPairs,
} from '@/lib/attendance-punches';

type MispunchRecord = {
  id: number;
  attendance_date: string;
  display_date: string;
  shift_start: string | null;
  shift_end: string | null;
  can_quick_clear: boolean;
  in_time?: string | null;
  out_time?: string | null;
  missing_in?: boolean;
  missing_out?: boolean;
  missing_summary?: string | null;
  log_details?: string | null;
};

const reloadMispunchProps = {
  preserveScroll: true,
  only: ['entries', 'mispunch_count', 'mispunch_entries', 'run', 'ready_to_lock_count', 'flash'] as string[],
};

function analyzeMispunchRecord(record: MispunchRecord) {
  const pairs = parseLogDetailsToPairs(record.log_details, record.in_time, record.out_time);
  const issues = getMispunchIssues(pairs);
  const firstPair = pairs[0] || { in_time: '', out_time: '' };
  const missingIn = record.missing_in ?? issues.some((i) => i.missing === 'IN');
  const missingOut = record.missing_out ?? issues.some((i) => i.missing === 'OUT');
  const summary = record.missing_summary || mispunchSummaryText(pairs) || (missingIn && missingOut ? 'IN & OUT missing' : missingIn ? 'IN missing' : missingOut ? 'OUT missing' : '');

  return {
    inTime: firstPair.in_time || '',
    outTime: firstPair.out_time || '',
    missingIn,
    missingOut,
    summary,
  };
}

function defaultManualTimes(record: MispunchRecord) {
  const analysis = analyzeMispunchRecord(record);
  return {
    inTime: analysis.inTime,
    outTime: analysis.outTime,
    missingIn: analysis.missingIn,
    missingOut: analysis.missingOut,
    summary: analysis.summary,
  };
}

type MispunchEntry = {
  id: number;
  name: string;
  employee_code?: string | null;
  mispunch_count: number;
  mispunch_records: MispunchRecord[];
};

type EmployeeLockTarget = {
  id: number;
  name: string;
  has_mispunch?: boolean;
  mispunch_records?: MispunchRecord[];
};

interface PayrollLockMispunchDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: 'employee' | 'bulk';
  runId: number;
  employee?: EmployeeLockTarget | null;
  mispunchEntries?: MispunchEntry[];
  readyToLockCount?: number;
  mispunchCount?: number;
  locking?: boolean;
  finalizing?: boolean;
  onLockEmployee?: () => void;
  onSkipLockEmployee?: () => void;
  onLockAll?: () => void;
  onSkipAndLock?: () => void;
}

function MispunchDateRow({
  runId,
  entryId,
  record,
}: {
  runId: number;
  entryId: number;
  record: MispunchRecord;
}) {
  const [saving, setSaving] = useState(false);
  const analysis = useMemo(() => defaultManualTimes(record), [record]);
  const [inTime, setInTime] = useState(analysis.inTime);
  const [outTime, setOutTime] = useState(analysis.outTime);

  useEffect(() => {
    const next = defaultManualTimes(record);
    setInTime(next.inTime);
    setOutTime(next.outTime);
  }, [record.id, record.in_time, record.out_time, record.missing_in, record.missing_out, record.log_details, record.shift_start, record.shift_end]);

  const submitClear = () => {
    if (!inTime || !outTime) {
      toast.error('Please enter both IN and OUT time.');
      return;
    }

    setSaving(true);
    router.post(
      route('hr.salary-payroll.generate.clear-entry-mispunch', {
        salaryPayrollRun: runId,
        salaryPayrollEntry: entryId,
      }),
      {
        biometric_attendance_id: record.id,
        mode: 'manual',
        in_time: inTime,
        out_time: outTime,
      },
      {
        ...reloadMispunchProps,
        onFinish: () => setSaving(false),
      },
    );
  };

  return (
    <div className="space-y-3 rounded-lg border border-amber-100 bg-white p-3 text-sm">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <p className="font-semibold text-slate-900">{record.display_date}</p>
          {analysis.summary ? (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-800">
              {analysis.summary}
            </span>
          ) : null}
        </div>
        {record.shift_start && record.shift_end ? (
          <p className="text-xs text-slate-500">
            Shift: {record.shift_start} – {record.shift_end}
          </p>
        ) : null}
        {record.log_details ? (
          <p className="mt-0.5 text-xs text-slate-400">Punch: {record.log_details}</p>
        ) : null}
      </div>

      <div className="rounded-md border border-slate-100 bg-slate-50/80 p-3">
        <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-slate-600">
          <Clock className="h-3.5 w-3.5" />
          Enter time for this date
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <div className="space-y-1">
            <Label htmlFor={`in-${record.id}`} className={cn('text-xs', analysis.missingIn ? 'font-semibold text-amber-700' : 'text-slate-600')}>
              IN Time {analysis.missingIn ? '(missing)' : analysis.inTime ? '(present)' : ''}
            </Label>
            <Input
              id={`in-${record.id}`}
              type="time"
              value={inTime}
              onChange={(e) => setInTime(e.target.value)}
              className={cn('h-9 bg-white', analysis.missingIn && 'border-amber-300 ring-1 ring-amber-200')}
              placeholder={analysis.missingIn ? 'Missing' : undefined}
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor={`out-${record.id}`} className={cn('text-xs', analysis.missingOut ? 'font-semibold text-amber-700' : 'text-slate-600')}>
              OUT Time {analysis.missingOut ? '(missing)' : analysis.outTime ? '(present)' : ''}
            </Label>
            <Input
              id={`out-${record.id}`}
              type="time"
              value={outTime}
              onChange={(e) => setOutTime(e.target.value)}
              className={cn('h-9 bg-white', analysis.missingOut && 'border-amber-300 ring-1 ring-amber-200')}
              placeholder={analysis.missingOut ? 'Missing' : undefined}
            />
          </div>
          <div className="flex flex-wrap gap-2 sm:flex-col">
            <Button
              type="button"
              size="sm"
              className="h-9 bg-primary hover:bg-primary/90"
              disabled={saving}
              onClick={submitClear}
            >
              {saving ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : null}
              Save Time
            </Button>
          </div>
        </div>
        <p className="mt-2 text-[11px] text-slate-500">
          Enter the missing IN or OUT time, then Save. Night shift: if OUT is before IN, next day is assumed automatically.
        </p>
      </div>
    </div>
  );
}

function EmployeeMispunchBlock({
  runId,
  entry,
  defaultOpen = false,
}: {
  runId: number;
  entry: MispunchEntry;
  defaultOpen?: boolean;
}) {
  const [open, setOpen] = useState(defaultOpen);

  return (
    <div className="rounded-lg border border-amber-100 bg-amber-50/40">
      <button
        type="button"
        className="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left"
        onClick={() => setOpen((v) => !v)}
      >
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-slate-900">
            {entry.name}
            {entry.employee_code ? ` (${entry.employee_code})` : ''}
          </p>
          <p className="text-xs text-amber-800">
            {entry.mispunch_count} mispunch date(s)
          </p>
        </div>
        <span className="text-xs font-medium text-amber-700">{open ? 'Hide' : 'Show'}</span>
      </button>
      {open && (
        <div className="space-y-2 border-t border-amber-100 px-3 pb-3 pt-2">
          {entry.mispunch_records.map((record) => (
            <MispunchDateRow key={record.id} runId={runId} entryId={entry.id} record={record} />
          ))}
        </div>
      )}
    </div>
  );
}

export function PayrollLockMispunchDialog({
  open,
  onOpenChange,
  mode,
  runId,
  employee,
  mispunchEntries = [],
  readyToLockCount = 0,
  mispunchCount = 0,
  locking = false,
  finalizing = false,
  onLockEmployee,
  onSkipLockEmployee,
  onLockAll,
  onSkipAndLock,
}: PayrollLockMispunchDialogProps) {
  const employeeRecords = employee?.mispunch_records ?? [];
  const employeeStillHasMispunch = mode === 'employee' && !!employee?.has_mispunch;

  const summary = useMemo(() => {
    if (mode === 'bulk') {
      return {
        ready: readyToLockCount,
        mispunch: mispunchCount,
      };
    }
    return {
      ready: employeeStillHasMispunch ? 0 : 1,
      mispunch: employeeRecords.length,
    };
  }, [mode, readyToLockCount, mispunchCount, employeeStillHasMispunch, employeeRecords.length]);

  const handleSkipAndLock = () => {
    onSkipAndLock?.();
    onOpenChange(false);
  };

  const title = mode === 'employee'
    ? `Lock ${employee?.name ?? 'Employee'}?`
    : 'Lock Payroll';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-1 ring-amber-100">
            {mode === 'bulk' ? <Users className="h-6 w-6" /> : <Lock className="h-6 w-6" />}
          </div>
          <DialogTitle className="text-center">{title}</DialogTitle>
          <DialogDescription asChild>
            <div className="space-y-3 text-center text-sm text-muted-foreground">
              {mode === 'bulk' ? (
                <p>
                  <span className="font-semibold text-emerald-700">{summary.ready} employee(s)</span>
                  {' '}ready to lock.
                  {summary.mispunch > 0 && (
                    <>
                      {' '}
                      <span className="font-semibold text-amber-700">{summary.mispunch} still have mispunch</span>
                      {' '}and will be skipped unless you clear them below.
                    </>
                  )}
                </p>
              ) : (
                <p>
                  This employee has mispunch on the dates below.
                  Clear each date, or use <span className="font-medium">Skip &amp; Lock</span> to lock without clearing.
                </p>
              )}
            </div>
          </DialogDescription>
        </DialogHeader>

        {summary.mispunch > 0 && (
          <div className="space-y-3">
            <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
              <div>
                <p className="font-medium">Fix mispunch by date</p>
                <p className="mt-0.5 text-xs text-amber-800">
                  Shows what is missing (IN or OUT). Enter the time manually and click Save Time.
                </p>
              </div>
            </div>

            {mode === 'employee' ? (
              <div className="space-y-2">
                {employeeRecords.map((record) => (
                  <MispunchDateRow
                    key={record.id}
                    runId={runId}
                    entryId={employee!.id}
                    record={record}
                  />
                ))}
              </div>
            ) : (
              <div className="max-h-64 space-y-2 overflow-y-auto pr-1">
                {mispunchEntries.map((entry, index) => (
                  <EmployeeMispunchBlock
                    key={entry.id}
                    runId={runId}
                    entry={entry}
                    defaultOpen={index === 0}
                  />
                ))}
              </div>
            )}
          </div>
        )}

        {!employeeStillHasMispunch && mode === 'employee' && (
          <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            All mispunch cleared. You can lock this employee now.
          </div>
        )}

        <DialogFooter className="flex-col gap-2 sm:flex-col sm:space-x-0">
          <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>

            {mode === 'bulk' && summary.mispunch === 0 && summary.ready > 0 && (
              <Button
                type="button"
                className="bg-amber-600 hover:bg-amber-700"
                disabled={finalizing}
                onClick={onLockAll}
              >
                {finalizing ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Lock className="mr-1.5 h-4 w-4" />}
                Lock All {summary.ready}
              </Button>
            )}

            {mode === 'bulk' && summary.ready > 0 && summary.mispunch > 0 && (
              <Button
                type="button"
                className="bg-amber-600 hover:bg-amber-700"
                disabled={finalizing}
                onClick={handleSkipAndLock}
              >
                {finalizing ? (
                  <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                ) : (
                  <Lock className="mr-1.5 h-4 w-4" />
                )}
                Skip &amp; Lock {summary.ready} Ready
              </Button>
            )}

            {mode === 'employee' && employeeStillHasMispunch && (
              <Button
                type="button"
                className="bg-amber-600 hover:bg-amber-700"
                disabled={locking}
                onClick={onSkipLockEmployee}
              >
                {locking ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Lock className="mr-1.5 h-4 w-4" />}
                Skip &amp; Lock
              </Button>
            )}

            {mode === 'employee' && !employeeStillHasMispunch && (
              <Button
                type="button"
                className={cn('bg-amber-600 hover:bg-amber-700')}
                disabled={locking}
                onClick={onLockEmployee}
              >
                {locking ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Lock className="mr-1.5 h-4 w-4" />}
                Lock &amp; Generate Payslip
              </Button>
            )}
          </div>

          {mode === 'bulk' && summary.mispunch === 0 && summary.ready > 0 && (
            <p className="text-center text-xs text-muted-foreground">
              No mispunch pending — lock all unlocked employees and finalize payroll.
            </p>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export type { EmployeeLockTarget, MispunchEntry, MispunchRecord };
