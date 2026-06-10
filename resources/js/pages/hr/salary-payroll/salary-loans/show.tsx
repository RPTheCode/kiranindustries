import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Edit, Printer, CheckCircle, Banknote, Trash2 } from 'lucide-react';
import { canDeleteSalaryLoan, canEditSalaryLoan, canManageSalaryLoan } from '@/utils/authorization';
import { ConfirmActionDialog } from '../payroll-generate/components/ConfirmActionDialog';
import { toast } from '@/components/custom-toast';

const DELETABLE_STATUSES = ['draft', 'submitted', 'rejected', 'cancelled'];

function formatCurrency(value: number) {
  if (window.appSettings?.formatCurrency) return window.appSettings.formatCurrency(value);
  return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
}

export default function SalaryLoanShow() {
  const { t } = useTranslation();
  const { auth, loan } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Loan'), href: route('hr.salary-loans.index') },
    { title: `#${loan.id}` },
  ];

  const actions = [{
    label: t('Print'),
    icon: <Printer className="h-4 w-4 mr-2" />,
    variant: 'outline' as const,
    onClick: () => window.open(route('hr.salary-loans.print', loan.id), '_blank'),
  }];

  if (canEditSalaryLoan(permissions) && ['draft', 'submitted'].includes(loan.status)) {
    actions.unshift({
      label: t('Edit'),
      icon: <Edit className="h-4 w-4 mr-2" />,
      variant: 'outline' as const,
      onClick: () => router.visit(route('hr.salary-loans.edit', loan.id)),
    });
  }

  const postAction = (routeName: string) => {
    router.post(route(routeName, loan.id), {}, { preserveScroll: true });
  };

  const handleDelete = () => {
    setIsDeleting(true);
    router.delete(route('hr.salary-loans.destroy', loan.id), {
      onSuccess: (page: any) => {
        if (page.props.flash?.error) toast.error(page.props.flash.error);
      },
      onError: () => {
        toast.error(t('Failed to delete salary loan request.'));
        setIsDeleting(false);
      },
    });
  };

  if (canDeleteSalaryLoan(permissions) && DELETABLE_STATUSES.includes(loan.status)) {
    actions.push({
      label: t('Delete'),
      icon: <Trash2 className="h-4 w-4 mr-2" />,
      variant: 'destructive' as const,
      onClick: () => setDeleteOpen(true),
    });
  }

  return (
    <PageTemplate title={t('Salary Loan Request')} breadcrumbs={breadcrumbs} actions={actions} noPadding>
      <div className="max-w-5xl mx-auto p-4 space-y-4">
        <div className="flex items-center justify-between">
          <Badge className="text-sm capitalize">{t(loan.status)}</Badge>
          <div className="flex gap-2">
            {canManageSalaryLoan(permissions) && ['draft', 'submitted'].includes(loan.status) && (
              <Button onClick={() => postAction('hr.salary-loans.approve')}>
                <CheckCircle className="h-4 w-4 mr-2" />{t('Approve')}
              </Button>
            )}
            {canManageSalaryLoan(permissions) && loan.status === 'approved' && (
              <Button onClick={() => postAction('hr.salary-loans.disburse')}>
                <Banknote className="h-4 w-4 mr-2" />{t('Disburse')}
              </Button>
            )}
          </div>
        </div>

        <Card>
          <CardHeader><CardTitle>{t('Employee Details')}</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span className="text-muted-foreground">{t('Name')}</span><div className="font-medium">{loan.employee_name}</div></div>
            <div><span className="text-muted-foreground">{t('Employee Code')}</span><div className="font-medium">{loan.employee_code}</div></div>
            <div><span className="text-muted-foreground">{t('Department')}</span><div className="font-medium">{loan.department || '-'}</div></div>
            <div><span className="text-muted-foreground">{t('Designation')}</span><div className="font-medium">{loan.designation || '-'}</div></div>
            <div><span className="text-muted-foreground">{t('Application Date')}</span><div className="font-medium">{loan.application_date}</div></div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>{t('Loan Amount')}</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span className="text-muted-foreground">{t('Requested')}</span><div className="font-semibold text-lg">{formatCurrency(loan.requested_amount)}</div></div>
            <div><span className="text-muted-foreground">{t('Approved')}</span><div className="font-semibold text-lg">{formatCurrency(loan.approved_amount)}</div></div>
            <div><span className="text-muted-foreground">{t('Installments')}</span><div>{loan.installment_count} × {formatCurrency(loan.installment_amount)}</div></div>
            <div><span className="text-muted-foreground">{t('Deduction From')}</span><div>{loan.deduction_start_month || '-'}</div></div>
            <div className="md:col-span-2"><span className="text-muted-foreground">{t('In Words')}</span><div>{loan.amount_in_words}</div></div>
            <div className="md:col-span-2"><span className="text-muted-foreground">{t('Purpose')}</span><div>{loan.purpose}</div></div>
            <div><span className="text-muted-foreground">{t('Recovered')}</span><div>{formatCurrency(loan.paid_amount)}</div></div>
            <div><span className="text-muted-foreground">{t('Pending Recovery')}</span><div className="font-semibold text-orange-700">{formatCurrency(loan.pending_amount)}</div></div>
          </CardContent>
        </Card>

        {loan.guarantors?.length > 0 && (
          <Card>
            <CardHeader><CardTitle>{t('Guarantors')}</CardTitle></CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>#</TableHead>
                    <TableHead>{t('Name')}</TableHead>
                    <TableHead>{t('Code')}</TableHead>
                    <TableHead>{t('Department')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loan.guarantors.map((g: any) => (
                    <TableRow key={g.id || g.sort_order}>
                      <TableCell>{g.sort_order}</TableCell>
                      <TableCell>{g.name}</TableCell>
                      <TableCell>{g.employee_code || '-'}</TableCell>
                      <TableCell>{g.department || '-'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        {loan.installments?.length > 0 && (
          <Card>
            <CardHeader><CardTitle>{t('EMI Schedule')}</CardTitle></CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>#</TableHead>
                    <TableHead>{t('Due Month')}</TableHead>
                    <TableHead className="text-right">{t('Amount')}</TableHead>
                    <TableHead>{t('Status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loan.installments.map((i: any) => (
                    <TableRow key={i.id}>
                      <TableCell>{i.installment_no}</TableCell>
                      <TableCell>{i.due_month}</TableCell>
                      <TableCell className="text-right">{formatCurrency(i.amount)}</TableCell>
                      <TableCell className="capitalize">{t(i.status)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        <div className="flex justify-between text-sm text-muted-foreground pb-8">
          <div>{t('Created by')}: {loan.creator_name || '-'}</div>
          {loan.approver_name && <div>{t('Approved by')}: {loan.approver_name}</div>}
        </div>

        <Button variant="link" asChild className="px-0">
          <Link href={route('hr.salary-loans.index')}>{t('Back to list')}</Link>
        </Button>
      </div>

      <ConfirmActionDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title={t('Delete salary loan?')}
        description={t('Delete this loan request for {{name}}?', { name: loan.employee_name || `#${loan.id}` })}
        confirmLabel={t('Delete')}
        cancelLabel={t('Cancel')}
        variant="destructive"
        loading={isDeleting}
        onConfirm={handleDelete}
      />
    </PageTemplate>
  );
}
