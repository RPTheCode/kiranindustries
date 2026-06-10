import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useState } from 'react';
import { Edit, Printer, CheckCircle, Banknote, Trash2 } from 'lucide-react';
import { canDeleteSalaryAdvance, canEditSalaryAdvance, canManageSalaryAdvance } from '@/utils/authorization';
import { ConfirmActionDialog } from '../payroll-generate/components/ConfirmActionDialog';
import { toast } from '@/components/custom-toast';

const DELETABLE_STATUSES = ['draft', 'submitted', 'rejected', 'cancelled'];

function formatCurrency(value: number) {
  if (window.appSettings?.formatCurrency) {
    return window.appSettings.formatCurrency(value);
  }
  return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
}

export default function SalaryAdvanceShow() {
  const { t } = useTranslation();
  const { auth, advance } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Advance'), href: route('hr.salary-advances.index') },
    { title: `#${advance.id}` },
  ];

  const actions = [
    {
      label: t('Print'),
      icon: <Printer className="h-4 w-4 mr-2" />,
      variant: 'outline' as const,
      onClick: () => window.open(route('hr.salary-advances.print', advance.id), '_blank'),
    },
  ];

  if (canEditSalaryAdvance(permissions) && ['draft', 'submitted'].includes(advance.status)) {
    actions.unshift({
      label: t('Edit'),
      icon: <Edit className="h-4 w-4 mr-2" />,
      variant: 'outline' as const,
      onClick: () => router.visit(route('hr.salary-advances.edit', advance.id)),
    });
  }

  const postAction = (routeName: string) => {
    router.post(route(routeName, advance.id), {}, { preserveScroll: true });
  };

  const handleDelete = () => {
    setIsDeleting(true);
    router.delete(route('hr.salary-advances.destroy', advance.id), {
      onSuccess: (page: any) => {
        if (page.props.flash?.error) toast.error(page.props.flash.error);
      },
      onError: () => {
        toast.error(t('Failed to delete salary advance request.'));
        setIsDeleting(false);
      },
    });
  };

  if (canDeleteSalaryAdvance(permissions) && DELETABLE_STATUSES.includes(advance.status)) {
    actions.push({
      label: t('Delete'),
      icon: <Trash2 className="h-4 w-4 mr-2" />,
      variant: 'destructive' as const,
      onClick: () => setDeleteOpen(true),
    });
  }

  return (
    <PageTemplate title={t('Salary Advance Request')} breadcrumbs={breadcrumbs} actions={actions} noPadding>
      <div className="max-w-5xl mx-auto p-4 space-y-4">
        <div className="flex items-center justify-between">
          <Badge className="text-sm capitalize">{t(advance.status)}</Badge>
          <div className="flex gap-2">
            {canManageSalaryAdvance(permissions) && ['draft', 'submitted'].includes(advance.status) && (
              <Button onClick={() => postAction('hr.salary-advances.approve')}>
                <CheckCircle className="h-4 w-4 mr-2" />{t('Approve')}
              </Button>
            )}
            {canManageSalaryAdvance(permissions) && advance.status === 'approved' && (
              <Button onClick={() => postAction('hr.salary-advances.disburse')}>
                <Banknote className="h-4 w-4 mr-2" />{t('Disburse')}
              </Button>
            )}
          </div>
        </div>

        <Card>
          <CardHeader><CardTitle>{t('Employee Details')}</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span className="text-muted-foreground">{t('Name')}</span><div className="font-medium">{advance.employee_name}</div></div>
            <div><span className="text-muted-foreground">{t('Employee Code')}</span><div className="font-medium">{advance.employee_code}</div></div>
            <div><span className="text-muted-foreground">{t('Department')}</span><div className="font-medium">{advance.department || '-'}</div></div>
            <div><span className="text-muted-foreground">{t('Designation')}</span><div className="font-medium">{advance.designation || '-'}</div></div>
            <div><span className="text-muted-foreground">{t('Application Date')}</span><div className="font-medium">{advance.application_date}</div></div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>{t('Advance Amount')}</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span className="text-muted-foreground">{t('Requested')}</span><div className="font-semibold text-lg">{formatCurrency(advance.requested_amount)}</div></div>
            <div><span className="text-muted-foreground">{t('Approved')}</span><div className="font-semibold text-lg">{formatCurrency(advance.approved_amount)}</div></div>
            <div className="md:col-span-2"><span className="text-muted-foreground">{t('In Words')}</span><div>{advance.amount_in_words}</div></div>
            <div className="md:col-span-2"><span className="text-muted-foreground">{t('Purpose')}</span><div>{advance.purpose}</div></div>
            <div><span className="text-muted-foreground">{t('Earned Snapshot')}</span><div>{formatCurrency(advance.earned_salary_snapshot)}</div></div>
            <div><span className="text-muted-foreground">{t('Allowed Snapshot')}</span><div>{formatCurrency(advance.allowed_amount_snapshot)}</div></div>
            <div><span className="text-muted-foreground">{t('Recovered')}</span><div>{formatCurrency(advance.paid_amount)}</div></div>
            <div><span className="text-muted-foreground">{t('Pending Recovery')}</span><div className="font-semibold text-orange-700">{formatCurrency(advance.pending_amount)}</div></div>
          </CardContent>
        </Card>

        <div className="flex justify-between text-sm text-muted-foreground pb-8">
          <div>{t('Created by')}: {advance.creator_name || '-'}</div>
          {advance.approver_name && <div>{t('Approved by')}: {advance.approver_name}</div>}
        </div>

        <Button variant="link" asChild className="px-0">
          <Link href={route('hr.salary-advances.index')}>{t('Back to list')}</Link>
        </Button>
      </div>

      <ConfirmActionDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title={t('Delete salary advance?')}
        description={t('Delete this advance request for {{name}}? Approved or disbursed advances cannot be deleted.', {
          name: advance.employee_name || `#${advance.id}`,
        })}
        confirmLabel={t('Delete')}
        cancelLabel={t('Cancel')}
        variant="destructive"
        loading={isDeleting}
        onConfirm={handleDelete}
      />
    </PageTemplate>
  );
}
