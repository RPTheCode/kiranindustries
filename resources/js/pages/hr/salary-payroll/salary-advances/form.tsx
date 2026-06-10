import { useEffect, useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Combobox } from '@/components/ui/combobox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from '@/components/custom-toast';

function formatCurrency(value: number) {
  return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
}

export default function SalaryAdvanceForm() {
  const { t } = useTranslation();
  const { advance, employees = [] } = usePage().props as any;
  const isEdit = Boolean(advance?.id);

  const [eligibility, setEligibility] = useState<any>(null);
  const [loadingEligibility, setLoadingEligibility] = useState(false);

  const { data, setData, processing, errors } = useForm({
    employee_id: advance?.employee_id?.toString() || '',
    application_date: advance?.application_date || new Date().toISOString().split('T')[0],
    requested_amount: advance?.requested_amount?.toString() || '',
    purpose: advance?.purpose || '',
    remarks: advance?.remarks || '',
    submit: false,
  });

  const pageErrors = (usePage().props as { errors?: Record<string, string> }).errors ?? {};
  const fieldError = (field: string) => errors[field as keyof typeof errors] || pageErrors[field];

  const employeeOptions = useMemo(
    () => employees.map((e: any) => ({ value: e.id.toString(), label: e.name })),
    [employees]
  );

  const loadEligibility = async (employeeId: string, date: string) => {
    if (!employeeId || !date) return;
    setLoadingEligibility(true);
    try {
      const response = await axios.get(route('hr.salary-advances.eligibility', employeeId), { params: { date } });
      setEligibility(response.data);
    } catch {
      setEligibility(null);
    } finally {
      setLoadingEligibility(false);
    }
  };

  useEffect(() => {
    if (data.employee_id) {
      loadEligibility(data.employee_id, data.application_date);
    }
  }, [data.employee_id, data.application_date]);

  const submitForm = (submit: boolean) => {
    const amount = parseFloat(data.requested_amount);
    if (eligibility && amount > eligibility.allowed_amount) {
      const msg = t('Advance amount cannot exceed allowed advance of {{amount}}', {
        amount: formatCurrency(eligibility.allowed_amount),
      });
      toast.error(msg);
      return;
    }

    if (!data.employee_id) {
      toast.error(t('Please select an employee.'));
      return;
    }

    if (!data.purpose?.trim()) {
      toast.error(t('Please enter purpose.'));
      return;
    }

    const url = isEdit
      ? route('hr.salary-advances.update', advance.id)
      : route('hr.salary-advances.store');

    const options = {
      preserveScroll: true,
      onError: (errs: Record<string, string>) => {
        const first = errs.requested_amount
          || errs.employee_id
          || errs.purpose
          || Object.values(errs)[0];
        toast.error(typeof first === 'string' ? first : t('Failed to save advance request'));
      },
    };

    router[isEdit ? 'put' : 'post'](url, { ...data, submit }, options);
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Advance'), href: route('hr.salary-advances.index') },
    { title: isEdit ? t('Edit Advance') : t('New Advance') },
  ];

  return (
    <PageTemplate
      title={isEdit ? t('Edit Salary Advance') : t('New Salary Advance')}
      breadcrumbs={breadcrumbs}
      noPadding
    >
      <div className="max-w-5xl mx-auto p-4 space-y-4">
        <Card>
          <CardHeader><CardTitle>{t('Employee Information')}</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {!isEdit && (
              <div className="md:col-span-2">
                <Label>{t('Employee')}</Label>
                <Combobox
                  options={employeeOptions}
                  value={data.employee_id}
                  onChange={(value) => setData('employee_id', value)}
                  placeholder={t('Select employee...')}
                />
                {fieldError('employee_id') && <p className="text-sm text-red-500 mt-1">{fieldError('employee_id')}</p>}
              </div>
            )}
            <div>
              <Label>{t('Application Date')}</Label>
              <Input type="date" value={data.application_date} onChange={(e) => setData('application_date', e.target.value)} />
            </div>
            {loadingEligibility ? (
              <div className="flex items-center gap-2 text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> {t('Loading eligibility...')}</div>
            ) : eligibility ? (
              <div className="md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div><span className="text-muted-foreground">{t('Present Salary')}</span><div className="font-semibold">{formatCurrency(eligibility.present_salary)}</div></div>
                <div><span className="text-muted-foreground">{t('Earned Till Date')}</span><div className="font-semibold">{formatCurrency(eligibility.earned_salary)}</div></div>
                <div><span className="text-muted-foreground">{t('Taken This Month')}</span><div className="font-semibold">{formatCurrency(eligibility.taken_this_month)}</div></div>
                <div><span className="text-muted-foreground">{t('Allowed Advance')}</span><div className="font-semibold text-green-700">{formatCurrency(eligibility.allowed_amount)}</div></div>
              </div>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>{t('Advance Details')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label>{t('Advance Amount')}</Label>
              <Input
                type="number"
                min="1"
                step="0.01"
                max={eligibility?.allowed_amount > 0 ? eligibility.allowed_amount : undefined}
                value={data.requested_amount}
                onChange={(e) => setData('requested_amount', e.target.value)}
              />
              {eligibility ? (
                <p className="text-xs text-muted-foreground mt-1">
                  {t('Maximum allowed')}: {formatCurrency(eligibility.allowed_amount)}
                </p>
              ) : null}
              {fieldError('requested_amount') && (
                <p className="text-sm text-red-500 mt-1">{fieldError('requested_amount')}</p>
              )}
            </div>
            <div>
              <Label>{t('Purpose')}</Label>
              <Textarea value={data.purpose} onChange={(e) => setData('purpose', e.target.value)} rows={3} />
              {fieldError('purpose') && <p className="text-sm text-red-500 mt-1">{fieldError('purpose')}</p>}
            </div>
            <div>
              <Label>{t('Remarks')}</Label>
              <Textarea value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} rows={2} />
            </div>
          </CardContent>
        </Card>

        <div className="flex flex-wrap gap-2 justify-end pb-8">
          <Button variant="outline" onClick={() => router.visit(route('hr.salary-advances.index'))}>{t('Cancel')}</Button>
          <Button variant="secondary" disabled={processing} onClick={() => submitForm(false)}>{t('Save Draft')}</Button>
          <Button disabled={processing} onClick={() => submitForm(true)}>{t('Submit')}</Button>
        </div>
      </div>
    </PageTemplate>
  );
}
