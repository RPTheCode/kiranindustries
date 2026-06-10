import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus, Eye, Edit, Printer, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { canCreateSalaryAdvance, canDeleteSalaryAdvance, canEditSalaryAdvance } from '@/utils/authorization';
import { ConfirmActionDialog } from '../payroll-generate/components/ConfirmActionDialog';
import { toast } from '@/components/custom-toast';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const statusColors: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-blue-100 text-blue-800',
  disbursed: 'bg-green-100 text-green-800',
  recovering: 'bg-orange-100 text-orange-800',
  recovered: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-red-100 text-red-800',
  cancelled: 'bg-gray-100 text-gray-600',
};

const DELETABLE_STATUSES = ['draft', 'submitted', 'rejected', 'cancelled'];

function formatCurrency(value: number) {
  if (window.appSettings?.formatCurrency) {
    return window.appSettings.formatCurrency(value);
  }
  return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
}

function formatDate(value?: string | null) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function SalaryAdvancesIndex() {
  const { t } = useTranslation();
  const { auth, advances, filters = {}, statusOptions = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || 'all');
  const [monthYear, setMonthYear] = useState(filters.month_year || '');
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDeleteConfirm = () => {
    if (!deleteTarget) return;
    setIsDeleting(true);
    router.delete(route('hr.salary-advances.destroy', deleteTarget.id), {
      onSuccess: (page: any) => {
        setDeleteTarget(null);
        if (page.props.flash?.success) toast.success(page.props.flash.success);
        if (page.props.flash?.error) toast.error(page.props.flash.error);
      },
      onError: () => toast.error(t('Failed to delete salary advance request.')),
      onFinish: () => setIsDeleting(false),
    });
  };

  const applyFilters = () => {
    router.get(route('hr.salary-advances.index'), {
      search: search || undefined,
      status: status !== 'all' ? status : undefined,
      month_year: monthYear || undefined,
      per_page: filters.per_page,
    }, { preserveState: true });
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Payroll') },
    { title: t('Salary Advance') },
  ];

  const pageActions = canCreateSalaryAdvance(permissions)
    ? [{
        label: t('New Advance'),
        icon: <Plus className="h-4 w-4 mr-2" />,
        variant: 'default' as const,
        onClick: () => router.visit(route('hr.salary-advances.create')),
      }]
    : [];

  return (
    <PageTemplate title={t('Salary Advance')} breadcrumbs={breadcrumbs} actions={pageActions} noPadding>
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <div className="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <Input placeholder={t('Search employee...')} value={search} onChange={(e) => setSearch(e.target.value)} />
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger><SelectValue placeholder={t('Status')} /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('All Status')}</SelectItem>
              {Object.entries(statusOptions).map(([key, label]) => (
                <SelectItem key={key} value={key}>{t(String(label))}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Input type="month" value={monthYear} onChange={(e) => setMonthYear(e.target.value)} />
          <Button onClick={applyFilters}>{t('Filter')}</Button>
          <Button variant="outline" onClick={() => {
            setSearch('');
            setStatus('all');
            setMonthYear('');
            router.get(route('hr.salary-advances.index'));
          }}>{t('Reset')}</Button>
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <Table>
          <TableHeader className="bg-slate-50">
            <TableRow>
              <TableHead className="text-xs font-semibold">{t('Date')}</TableHead>
              <TableHead className="text-xs font-semibold">{t('Employee')}</TableHead>
              <TableHead className="text-right text-xs font-semibold">{t('Amount')}</TableHead>
              <TableHead className="text-xs font-semibold">{t('Status')}</TableHead>
              <TableHead className="text-right text-xs font-semibold">{t('Pending')}</TableHead>
              <TableHead className="text-right text-xs font-semibold">{t('Actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {advances?.data?.length ? advances.data.map((row: any) => (
              <TableRow key={row.id} className="hover:bg-slate-50/80">
                <TableCell className="text-sm tabular-nums text-slate-600">{formatDate(row.application_date)}</TableCell>
                <TableCell>
                  <div className="font-medium text-slate-900">{row.employee?.name}</div>
                  <div className="text-xs text-muted-foreground">{row.employee?.employee?.employee_id}</div>
                </TableCell>
                <TableCell className="text-right font-medium tabular-nums">{formatCurrency(row.approved_amount ?? row.requested_amount)}</TableCell>
                <TableCell>
                  <Badge className={`capitalize border-0 ${statusColors[row.status] || ''}`}>{t(row.status)}</Badge>
                </TableCell>
                <TableCell className="text-right tabular-nums text-orange-700 font-medium">{formatCurrency(row.pending_amount ?? 0)}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-0.5">
                    <Button variant="ghost" size="icon" className="h-8 w-8" asChild title={t('View')}>
                      <Link href={route('hr.salary-advances.show', row.id)}><Eye className="h-4 w-4" /></Link>
                    </Button>
                    {canEditSalaryAdvance(permissions) && ['draft', 'submitted'].includes(row.status) && (
                      <Button variant="ghost" size="icon" className="h-8 w-8" asChild title={t('Edit')}>
                        <Link href={route('hr.salary-advances.edit', row.id)}><Edit className="h-4 w-4" /></Link>
                      </Button>
                    )}
                    <Button variant="ghost" size="icon" className="h-8 w-8" asChild title={t('Print')}>
                      <a href={route('hr.salary-advances.print', row.id)} target="_blank" rel="noreferrer">
                        <Printer className="h-4 w-4" />
                      </a>
                    </Button>
                    {canDeleteSalaryAdvance(permissions) && DELETABLE_STATUSES.includes(row.status) && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-700"
                        title={t('Delete')}
                        onClick={() => setDeleteTarget({ id: row.id, name: row.employee?.name || `#${row.id}` })}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            )) : (
              <TableRow>
                <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                  {t('No salary advance requests found.')}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>

        <Pagination
          from={advances?.from || 0}
          to={advances?.to || 0}
          total={advances?.total || 0}
          links={advances?.links}
          entityName={t('salary advances')}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      <ConfirmActionDialog
        open={!!deleteTarget}
        onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}
        title={t('Delete salary advance?')}
        description={t('Delete advance request for {{name}}? This cannot be undone. Approved or disbursed advances cannot be deleted.', {
          name: deleteTarget?.name || '',
        })}
        confirmLabel={t('Delete')}
        cancelLabel={t('Cancel')}
        variant="destructive"
        loading={isDeleting}
        onConfirm={handleDeleteConfirm}
      />
    </PageTemplate>
  );
}
