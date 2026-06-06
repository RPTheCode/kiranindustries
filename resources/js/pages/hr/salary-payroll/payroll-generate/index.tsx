import { PageTemplate } from '@/components/page-template';
import { usePage, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Plus, Eye, Trash2, Banknote, RefreshCw, Settings2, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Pagination } from '@/components/ui/pagination';
import { hasPermission } from '@/utils/authorization';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/custom-toast';
import { useEffect, useState } from 'react';
import { ConfirmActionDialog } from './components/ConfirmActionDialog';

function formatRupee(value: number) {
  return Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function statusBadge(status: string, t: (key: string) => string) {
  const map: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    calculated: 'bg-blue-100 text-blue-700',
    finalized: 'bg-green-100 text-green-700',
  };
  const label = status === 'finalized' ? t('Locked') : t(status.charAt(0).toUpperCase() + status.slice(1));
  return (
    <Badge className={`${map[status] || map.draft} border-0 text-[10px] uppercase`}>
      {label}
    </Badge>
  );
}

type PendingAction = {
  type: 'regenerate' | 'delete';
  id: number;
  title?: string;
  lockedCount?: number;
  unlockedCount?: number;
} | null;

export default function PayrollGenerateIndex() {
  const { t } = useTranslation();
  const { auth, runs, activeBranchName, flash } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const canCreate = hasPermission(permissions, 'create-salary-payroll-runs')
    || hasPermission(permissions, 'create-employee-salaries')
    || hasPermission(permissions, 'edit-employee-salaries')
    || hasPermission(permissions, 'manage-employee-salaries')
    || hasPermission(permissions, 'manage-any-employee-salaries');
  const canDelete = canCreate;
  const canView = hasPermission(permissions, 'view-salary-payroll-runs')
    || hasPermission(permissions, 'create-salary-payroll-runs')
    || hasPermission(permissions, 'finalize-salary-payroll-runs')
    || hasPermission(permissions, 'view-employee-salaries')
    || hasPermission(permissions, 'manage-employee-salaries');

  const [pendingAction, setPendingAction] = useState<PendingAction>(null);
  const [isProcessing, setIsProcessing] = useState(false);

  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Payroll'), href: '#' },
    { title: t('Generate Payroll') },
  ];

  const executePendingAction = () => {
    if (!pendingAction) return;
    setIsProcessing(true);

    if (pendingAction.type === 'delete') {
      router.delete(route('hr.salary-payroll.generate.destroy', pendingAction.id), {
        onFinish: () => {
          setIsProcessing(false);
          setPendingAction(null);
        },
      });
      return;
    }

    router.post(route('hr.salary-payroll.generate.regenerate', pendingAction.id), {}, {
      onFinish: () => {
        setIsProcessing(false);
        setPendingAction(null);
      },
    });
  };

  return (
    <PageTemplate title={t('Generate Payroll')} url={route('hr.salary-payroll.generate.index')} breadcrumbs={breadcrumbs}>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/80 p-3 shadow-sm">
        <div>
          <p className="text-sm font-semibold text-slate-800">{t('Salary Payroll Runs')}</p>
          <p className="text-[11px] text-slate-500">
            {t('Branch')}: {activeBranchName || t('All branches')}
          </p>
        </div>
        {canCreate && (
          <Button asChild size="sm" className="h-9">
            <Link href={route('hr.salary-payroll.generate.create')}>
              <Plus className="mr-1.5 h-4 w-4" />
              {t('Generate Payroll')}
            </Link>
          </Button>
        )}
      </div>

      <div className="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600">
        {t('Unlocked runs can be regenerated, customized, or deleted. Lock individual employees on the detail page, or use "Lock Payroll" to finalize. Locked employees are skipped during regenerate.')}
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <Table>
          <TableHeader className="bg-slate-50">
            <TableRow>
              <TableHead className="text-xs">{t('Month')}</TableHead>
              <TableHead className="text-xs">{t('Title')}</TableHead>
              <TableHead className="text-xs">{t('Scope')}</TableHead>
              <TableHead className="text-xs text-right">{t('Employees')}</TableHead>
              <TableHead className="text-xs text-right">{t('Total Gross')}</TableHead>
              <TableHead className="text-xs text-right">{t('Total Net')}</TableHead>
              <TableHead className="text-xs">{t('Status')}</TableHead>
              <TableHead className="text-xs text-right">{t('Action')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {!runs?.data?.length ? (
              <TableRow>
                <TableCell colSpan={8} className="py-12 text-center text-sm text-slate-400">
                  <Banknote className="mx-auto mb-2 h-8 w-8 opacity-40" />
                  {t('No payroll runs yet. Click Generate Payroll to start.')}
                </TableCell>
              </TableRow>
            ) : runs.data.map((run: any) => {
              const isLocked = run.status === 'finalized';
              const lockedCount = run.locked_entry_count ?? 0;
              const hasPartialLocks = !isLocked && lockedCount > 0;
              return (
              <TableRow key={run.id}>
                <TableCell className="text-xs font-medium">{run.month_year}</TableCell>
                <TableCell className="text-xs">{run.title}</TableCell>
                <TableCell className="text-xs capitalize">{run.scope_mode?.replace('_', ' ')}</TableCell>
                <TableCell className="text-right text-xs">{run.employee_count}</TableCell>
                <TableCell className="text-right text-xs">₹{formatRupee(Number(run.total_gross))}</TableCell>
                <TableCell className="text-right text-xs font-semibold text-primary">₹{formatRupee(Number(run.total_net))}</TableCell>
                <TableCell>
                  <div className="flex flex-wrap items-center gap-1">
                    {statusBadge(run.status, t)}
                    {hasPartialLocks && (
                      <Badge className="border-0 bg-amber-100 text-[9px] uppercase text-amber-800" title={t('Individual employees locked')}>
                        <Lock className="mr-0.5 inline h-2.5 w-2.5" />
                        {lockedCount} {t('locked')}
                      </Badge>
                    )}
                  </div>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-1">
                    {canView && (
                      <Button variant="ghost" size="icon" className="h-8 w-8" asChild title={t('View')}>
                        <Link href={route('hr.salary-payroll.generate.show', run.id)}>
                          <Eye className="h-4 w-4" />
                        </Link>
                      </Button>
                    )}
                    {canCreate && (
                      <>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8 text-primary hover:text-primary disabled:opacity-40"
                          title={isLocked ? t('Locked — cannot regenerate') : hasPartialLocks ? t('Regenerate unlocked employees only') : t('Regenerate')}
                          disabled={isLocked}
                          onClick={() => !isLocked && setPendingAction({
                            type: 'regenerate',
                            id: run.id,
                            title: run.title,
                            lockedCount,
                            unlockedCount: run.unlocked_entry_count ?? 0,
                          })}
                        >
                          <RefreshCw className="h-4 w-4" />
                        </Button>
                        {isLocked ? (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 disabled:opacity-40"
                            title={t('Locked — cannot customize')}
                            disabled
                          >
                            <Settings2 className="h-4 w-4" />
                          </Button>
                        ) : hasPartialLocks ? (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 disabled:opacity-40"
                            title={t('Cannot customize while employees are locked')}
                            disabled
                          >
                            <Settings2 className="h-4 w-4" />
                          </Button>
                        ) : (
                          <Button variant="ghost" size="icon" className="h-8 w-8" asChild title={t('Customize')}>
                            <Link href={route('hr.salary-payroll.generate.edit', run.id)}>
                              <Settings2 className="h-4 w-4" />
                            </Link>
                          </Button>
                        )}
                      </>
                    )}
                    {canDelete && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-red-500 hover:text-red-700 disabled:text-slate-300 disabled:opacity-40"
                        title={isLocked ? t('Locked — cannot delete') : t('Delete')}
                        disabled={isLocked}
                        onClick={() => !isLocked && setPendingAction({ type: 'delete', id: run.id, title: run.title })}
                      >
                        {isLocked ? <Lock className="h-4 w-4" /> : <Trash2 className="h-4 w-4" />}
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            );})}
          </TableBody>
        </Table>
        {runs?.data?.length > 0 && runs.last_page > 1 && (
          <div className="border-t p-3">
            <Pagination links={runs.links} />
          </div>
        )}
      </div>

      <ConfirmActionDialog
        open={pendingAction?.type === 'regenerate'}
        onOpenChange={(open) => !open && setPendingAction(null)}
        title={(pendingAction?.lockedCount ?? 0) > 0 ? t('Regenerate Unlocked Employees?') : t('Regenerate Payroll?')}
        description={
          (pendingAction?.lockedCount ?? 0) > 0
            ? t('{{unlocked}} unlocked employee(s) in "{{title}}" will be recalculated. {{locked}} locked employee(s) will stay unchanged.', {
              title: pendingAction?.title || '',
              unlocked: pendingAction?.unlockedCount ?? 0,
              locked: pendingAction?.lockedCount ?? 0,
            })
            : pendingAction?.title
              ? t('Recalculate "{{title}}" with the latest employee salaries and payroll settings. All entries will be replaced.', { title: pendingAction.title })
              : t('Recalculate this payroll with the latest employee salaries and payroll settings. Existing entries will be replaced.')
        }
        confirmLabel={(pendingAction?.lockedCount ?? 0) > 0
          ? t('Regenerate Unlocked ({{count}})', { count: pendingAction?.unlockedCount ?? 0 })
          : t('Regenerate')}
        cancelLabel={t('Cancel')}
        variant="primary"
        icon={<RefreshCw className="h-6 w-6" />}
        loading={isProcessing}
        onConfirm={executePendingAction}
      />

      <ConfirmActionDialog
        open={pendingAction?.type === 'delete'}
        onOpenChange={(open) => !open && setPendingAction(null)}
        title={t('Delete Payroll Run?')}
        description={
          pendingAction?.title
            ? t('Permanently delete "{{title}}"? Only unlocked payrolls can be deleted. This cannot be undone.', { title: pendingAction.title })
            : t('Permanently delete this payroll run? Only unlocked payrolls can be deleted.')
        }
        confirmLabel={t('Delete')}
        cancelLabel={t('Cancel')}
        variant="destructive"
        icon={<Trash2 className="h-6 w-6" />}
        loading={isProcessing}
        onConfirm={executePendingAction}
      />
    </PageTemplate>
  );
}
